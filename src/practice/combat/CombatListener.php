<?php

declare(strict_types=1);

namespace practice\combat;

use pocketmine\entity\projectile\EnderPearl;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\ProjectileHitEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\player\Player;
use pocketmine\Server;
use practice\entity\PracticeLightningBolt;
use practice\ffa\Arena;
use practice\ffa\FFAManager;
use practice\PracticeConfig;
use practice\session\Session;
use practice\session\SessionManager;
use practice\settings\SettingRegistry;
use practice\settings\Settings;
use practice\util\Placeholder;
use function count;
use function sprintf;

/**
 * Hit feedback, damage rules and deaths.
 *
 * Deaths are resolved from the damage event itself: the killing blow is
 * cancelled and the victim is moved on, so no vanilla death screen or respawn
 * cycle is involved.
 */
final class CombatListener implements Listener{

	private const DISTANCE_PRECISION = 1;

	public function __construct(
		private readonly Server $server,
		private readonly PracticeConfig $config,
		private readonly SessionManager $sessions,
		private readonly FFAManager $ffa,
		private readonly CombatManager $combat,
		private readonly SettingRegistry $settings,
		private readonly ClickTracker $clicks
	){
	}

	/**
	 * @priority HIGH
	 *
	 * Runs after the lobby protection listener, so a hit that was cancelled
	 * there never produces an action bar message or a death.
	 */
	public function onEntityDamage(EntityDamageEvent $event) : void{
		$victim = $event->getEntity();
		if(!$victim instanceof Player){
			return;
		}

		$victimSession = $this->sessions->get($victim);
		if($victimSession === null){
			return;
		}

		if($event->getCause() === EntityDamageEvent::CAUSE_FALL && $this->shouldIgnoreFallDamage($victimSession)){
			$event->cancel();
			return;
		}

		if($victimSession->isInFFA() && $event->getCause() === EntityDamageEvent::CAUSE_VOID){
			//Falling out of an arena counts as a death, not as a slow drain.
			$event->cancel();
			$this->handleDeath($victimSession, null);
			return;
		}

		$this->applyAttackDelay($event, $victimSession);

		$killerSession = null;
		if($event instanceof EntityDamageByEntityEvent){
			$damager = $event->getDamager();
			if($damager instanceof Player){
				$this->clicks->record($damager);

				$killerSession = $this->sessions->get($damager);
				$this->sendHitFeedback($victimSession, $damager, $killerSession);

				if($victimSession->isInFFA()){
					$this->combat->tag($victim, $damager);
				}
			}
		}

		if(!$victimSession->isInFFA()){
			return;
		}

		if($victim->getHealth() - $event->getFinalDamage() > 0){
			return;
		}

		//Take over the kill: no vanilla death, straight into the next round.
		$event->cancel();
		$this->handleDeath($victimSession, $killerSession);
	}

	/**
	 * Remembers when a pearl thrown by a player lands.
	 *
	 * Vanilla charges the thrower 5 fall damage right after this event, which
	 * is the only way to tell that hit apart from a real fall.
	 */
	public function onProjectileHit(ProjectileHitEvent $event) : void{
		$projectile = $event->getEntity();
		if(!$projectile instanceof EnderPearl){
			return;
		}

		$owner = $projectile->getOwningEntity();
		if(!$owner instanceof Player){
			return;
		}

		$this->sessions->get($owner)?->setPearlLandTick($this->server->getTick());
	}

	/**
	 * A player should never actually die here; if one slips through (plugin
	 * damage, /kill), keep the drops away and route him through the lobby.
	 */
	public function onPlayerDeath(PlayerDeathEvent $event) : void{
		$event->setDrops([]);
		$event->setXpDropAmount(0);
		$event->setDeathMessage("");

		$session = $this->sessions->get($event->getPlayer());
		if($session !== null && $session->isInFFA()){
			$this->handleDeath($session, null);
		}
	}

	/**
	 * Fall damage is off by default; the pearl's landing charge is governed by
	 * its own switch so a server can keep real falls lethal without punishing
	 * pearls.
	 */
	private function shouldIgnoreFallDamage(Session $session) : bool{
		if($session->getPearlLandTick() === $this->server->getTick()){
			return $this->config->getBool("combat.disable-ender-pearl-damage", true);
		}

		return $this->config->getBool("combat.disable-fall-damage", true);
	}

	/**
	 * The "tickrate" of the arena the victim is in: how long he is immune to
	 * full damage after being hit.
	 */
	private function applyAttackDelay(EntityDamageEvent $event, Session $victimSession) : void{
		$arena = $this->ffa->getArenaOf($victimSession);
		$profile = $arena?->getKnockback() ?? $this->ffa->getDefaultKnockback();

		$event->setAttackCooldown($profile->getAttackDelay());
	}

	/**
	 * Shows the reach of the hit above the hotbar: the attacker sees the raw
	 * distance, the victim sees who hit him from how far. Either side can turn
	 * the read-out off for himself.
	 */
	private function sendHitFeedback(Session $victimSession, Player $damager, ?Session $damagerSession) : void{
		$victim = $victimSession->getPlayer();
		$distance = sprintf("%." . self::DISTANCE_PRECISION . "f", $damager->getPosition()->distance($victim->getPosition()));

		if($this->settings->isEnabled($victimSession, Settings::HIT_DISTANCE)){
			$victim->sendActionBarMessage(Placeholder::apply($this->config->getMessage("hit-taken"), [
				"player" => $damager->getName(),
				"distance" => $distance
			]));
		}

		if($damagerSession !== null && $this->settings->isEnabled($damagerSession, Settings::HIT_DISTANCE)){
			$damager->sendActionBarMessage(Placeholder::apply($this->config->getMessage("hit-dealt"), [
				"player" => $victim->getName(),
				"distance" => $distance
			]));
		}
	}

	private function handleDeath(Session $victim, ?Session $killer) : void{
		$arena = $this->ffa->getArenaOf($victim);
		$hasKiller = $killer !== null && $killer !== $victim;

		$victim->addDeath();
		$victim->save();
		if($hasKiller){
			$killer->addKill();
			//Written straight away so a crash cannot cost anyone their kills.
			$killer->save();
		}

		//Counted before the killer is re-kitted, otherwise everybody shows full.
		$this->broadcastDeath($victim, $hasKiller ? $killer : null, $arena);
		$this->strikeLightning($victim);

		$this->combat->untag($victim->getPlayer());
		if($hasKiller){
			$this->combat->untag($killer->getPlayer());
			$this->refill($killer, $arena);
		}

		$this->respawn($victim, $arena);
	}

	/**
	 * Back into the same arena when the player asked for that, otherwise to the
	 * lobby. A missing or unavailable arena always means the lobby.
	 */
	private function respawn(Session $victim, ?Arena $arena) : void{
		if($arena !== null && $this->settings->isEnabled($victim, Settings::AUTO_RESPAWN) && $this->ffa->join($victim, $arena)){
			return;
		}

		$this->ffa->leave($victim);
	}

	/**
	 * Cosmetic strike where the loser fell, sent only to the viewers who want
	 * to see it. Somebody whose chunks happen to be re-sent within the bolt's
	 * short lifetime may still catch it, which is harmless.
	 */
	private function strikeLightning(Session $victim) : void{
		if(!$this->config->getBool("combat.lightning-on-kill", true)){
			return;
		}

		$location = $victim->getPlayer()->getLocation();
		$viewers = [];
		foreach($location->getWorld()->getViewersForPosition($location) as $viewer){
			$viewerSession = $this->sessions->get($viewer);
			if($viewerSession !== null && $this->settings->isEnabled($viewerSession, Settings::LIGHTNING_ON_KILL)){
				$viewers[] = $viewer;
			}
		}

		$bolt = new PracticeLightningBolt($location);
		if(count($viewers) === 0){
			$bolt->close();
			return;
		}

		$bolt->showTo($viewers);
	}

	private function refill(Session $killer, ?Arena $arena) : void{
		if($arena === null || !$arena->contains($killer)){
			return;
		}

		$player = $killer->getPlayer();
		$arena->getKit()->apply($player);
		$player->setHealth((float) $player->getMaxHealth());
		$player->getHungerManager()->setFood((float) $player->getHungerManager()->getMaxFood());
	}

	private function broadcastDeath(Session $victim, ?Session $killer, ?Arena $arena) : void{
		$showPots = $arena !== null && $arena->hasPots();

		if($killer === null){
			$key = "ffa-death-generic";
		}else{
			$key = $showPots ? "ffa-death" : "ffa-death-nopots";
		}

		$message = Placeholder::apply($this->config->getMessage($key), [
			"victim" => $victim->getName(),
			"killer" => $killer?->getName() ?? "",
			"victim_pots" => $arena?->countPots($victim->getPlayer()) ?? 0,
			"killer_pots" => $killer !== null ? ($arena?->countPots($killer->getPlayer()) ?? 0) : 0
		]);

		if($message === ""){
			return;
		}

		$sent = [];
		foreach($arena?->getPlayers() ?? [] as $session){
			$sent[$session->getName()] = true;
			$session->getPlayer()->sendMessage($message);
		}

		//The victim has usually already been removed from the arena roster.
		if(!isset($sent[$victim->getName()])){
			$victim->getPlayer()->sendMessage($message);
		}
	}
}
