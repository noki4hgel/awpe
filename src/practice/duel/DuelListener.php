<?php

declare(strict_types=1);

namespace practice\duel;

use pocketmine\entity\projectile\SplashPotion;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\ProjectileHitEntityEvent;
use pocketmine\event\entity\ProjectileHitEvent;
use pocketmine\event\entity\ProjectileLaunchEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\player\Player;
use practice\duel\invite\InviteManager;
use practice\duel\match\DuelMatch;
use practice\duel\match\MatchManager;
use practice\duel\queue\QueueManager;
use practice\entity\PracticeSplashPotion;
use practice\session\SessionManager;

/**
 * Everything that happens to a player while he is in a match.
 *
 * Only players in a match are touched here — FFA and the lobby have their own
 * listeners — and nothing outside a match's two players can affect it.
 */
final class DuelListener implements Listener{

	/**
	 * Modifier slot used to cancel a hit's damage while keeping the hit itself.
	 * A modifier rather than a zero base damage: vanilla compares base damage to
	 * decide whether a hit inside the attack cooldown counts, and zeroing it
	 * would let spam-clicks through that cooldown.
	 */
	private const MODIFIER_NO_DAMAGE = 0x6e6f; // "no"

	public function __construct(
		private readonly SessionManager $sessions,
		private readonly MatchManager $matches,
		private readonly QueueManager $queue,
		private readonly InviteManager $invites
	){
	}

	/**
	 * @priority HIGH
	 */
	public function onEntityDamage(EntityDamageEvent $event) : void{
		$victim = $event->getEntity();
		if(!$victim instanceof Player){
			return;
		}

		$session = $this->sessions->get($victim);
		$match = $session === null ? null : $this->matches->getMatchOf($session);
		if($session === null || $match === null){
			return;
		}

		//The countdown and the pause after a win are invulnerable.
		if(!$match->isFighting()){
			$event->cancel();
			return;
		}

		if($event->getCause() === EntityDamageEvent::CAUSE_VOID){
			$event->cancel();
			$this->matches->handleDeath($session);
			return;
		}

		if($event instanceof EntityDamageByEntityEvent){
			$damager = $event->getDamager();
			$damagerSession = $damager instanceof Player ? $this->sessions->get($damager) : null;
			if($damagerSession === null || $damagerSession === $session || !$match->contains($damagerSession)){
				$event->cancel();
				return;
			}

			if($match->getLadder()->hasNoDamage()){
				$event->setModifier(-$event->getFinalDamage(), self::MODIFIER_NO_DAMAGE);
			}

			$stats = $match->getStats($damagerSession);
			$stats->recordHit($event->getFinalDamage());
			if($event->getModifier(EntityDamageEvent::MODIFIER_CRITICAL) > 0){
				$stats->recordCritical();
			}
			$match->getStats($session)->breakCombo();
		}

		if($victim->getHealth() - $event->getFinalDamage() <= 0){
			$event->cancel();
			$this->matches->handleDeath($session);
		}
	}

	/**
	 * Modes that end by being knocked off (Sumo) check the fall here: the moment
	 * a player drops far enough below the lower spawn, he has lost.
	 */
	public function onMove(PlayerMoveEvent $event) : void{
		$session = $this->sessions->get($event->getPlayer());
		$match = $session === null ? null : $this->matches->getMatchOf($session);
		if($session === null || $match === null || !$match->isFighting()){
			return;
		}

		$depth = $match->getLadder()->getFallDepth();
		if($depth !== null && $event->getTo()->getY() < $match->getArena()->getFloorY() - $depth){
			$this->matches->handleDeath($session);
		}
	}

	public function onProjectileLaunch(ProjectileLaunchEvent $event) : void{
		$projectile = $event->getEntity();
		$thrower = $projectile->getOwningEntity();
		if(!$projectile instanceof SplashPotion || !$thrower instanceof Player){
			return;
		}

		$match = $this->matchOf($thrower);
		$session = $this->sessions->get($thrower);
		if($match !== null && $session !== null && $match->isFighting()){
			$match->getStats($session)->recordPotThrown();
		}
	}

	/**
	 * Rates the throw the moment the potion lands: how much of it the thrower
	 * gave himself. Asking the potion keeps the summary honest - it is the
	 * same number that was applied to him.
	 */
	public function onProjectileHit(ProjectileHitEvent $event) : void{
		$potion = $event->getEntity();
		$thrower = $potion->getOwningEntity();
		if(!$potion instanceof PracticeSplashPotion || !$thrower instanceof Player){
			return;
		}

		$match = $this->matchOf($thrower);
		$session = $this->sessions->get($thrower);
		if($match === null || $session === null || !$match->isFighting()){
			return;
		}

		$directHit = $event instanceof ProjectileHitEntityEvent && $event->getEntityHit() === $thrower;
		$match->getStats($session)->recordPotLanded($potion->potencyFor($thrower, $directHit));
	}

	/**
	 * @priority LOW
	 *
	 * Before the session listener closes the session: a player who quits a
	 * match forfeits it, and has to still be known to do that.
	 */
	public function onQuit(PlayerQuitEvent $event) : void{
		$session = $this->sessions->get($event->getPlayer());
		if($session === null){
			return;
		}

		$this->matches->forfeit($session, true);
		$this->queue->forget($session);
		$this->invites->clear($session);
	}

	public function onBlockBreak(BlockBreakEvent $event) : void{
		if($this->matchOf($event->getPlayer()) !== null){
			$event->cancel();
		}
	}

	public function onBlockPlace(BlockPlaceEvent $event) : void{
		if($this->matchOf($event->getPlayer()) !== null){
			$event->cancel();
		}
	}

	public function onDropItem(PlayerDropItemEvent $event) : void{
		if($this->matchOf($event->getPlayer()) !== null){
			$event->cancel();
		}
	}

	private function matchOf(Player $player) : ?DuelMatch{
		$session = $this->sessions->get($player);

		return $session === null ? null : $this->matches->getMatchOf($session);
	}
}
