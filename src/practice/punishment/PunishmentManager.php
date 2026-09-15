<?php

declare(strict_types=1);

namespace practice\punishment;

use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\Server;
use practice\data\DataProvider;
use practice\PracticeConfig;
use practice\punishment\store\PunishmentStore;
use practice\rank\Rank;
use practice\rank\RankManager;
use practice\session\SessionManager;
use practice\util\Placeholder;
use function date;
use function is_string;
use function time;

/**
 * Hands out, lifts and looks up punishments.
 *
 * Every kind goes through the same punish() and revoke(): the kind decides what
 * gets blocked, not how the record is made, announced or stored. Callers are
 * expected to have checked canPunish() and getActive() first; the commands do,
 * and say why when they refuse.
 */
final class PunishmentManager{

	public const PERMISSION_NOTIFY = "awpe.punish.notify";

	public function __construct(
		private readonly Server $server,
		private readonly PracticeConfig $config,
		private readonly PunishmentStore $store,
		private readonly SessionManager $sessions,
		private readonly DataProvider $data,
		private readonly RankManager $ranks
	){
	}

	public function getStore() : PunishmentStore{
		return $this->store;
	}

	/**
	 * Records a punishment and carries it out: a banned or kicked player is
	 * disconnected, a muted one is told.
	 */
	public function punish(PunishmentType $type, string $targetName, string $issuer, string $reason, ?Duration $duration, bool $silent) : Punishment{
		$now = time();
		$duration ??= $type->isLasting() ? $this->getDefaultDuration($type) : null;

		$punishment = new Punishment(
			$this->store->nextId(),
			$type,
			$targetName,
			$issuer,
			$reason,
			$now,
			$type->isLasting() ? $duration?->endsAt($now) : null,
			$silent
		);
		$this->store->save($punishment);

		$this->enforce($punishment);
		$this->announce($punishment);

		return $punishment;
	}

	/**
	 * Lifts the active punishment of a kind. Returns the updated record, or null
	 * when there was nothing to lift.
	 */
	public function revoke(PunishmentType $type, string $targetName, string $revokedBy, string $reason) : ?Punishment{
		$active = $this->getActive($targetName, $type);
		if($active === null){
			return null;
		}

		$revoked = $active->withRevocation(time(), $revokedBy, $reason);
		$this->store->save($revoked);

		$this->notifyStaff($this->format("punish-staff-revoke", $revoked));

		$online = $this->server->getPlayerExact($targetName);
		if($online !== null && $type === PunishmentType::MUTE){
			$this->send($online, $this->format("punish-unmuted-target", $revoked));
		}

		return $revoked;
	}

	public function getActive(string $playerName, PunishmentType $type) : ?Punishment{
		$now = time();
		foreach($this->store->getHistory($playerName) as $punishment){
			if($punishment->getType() === $type && $punishment->isActive($now)){
				return $punishment;
			}
		}

		return null;
	}

	/**
	 * @return Punishment[] newest first
	 */
	public function getHistory(string $playerName) : array{
		return $this->store->getHistory($playerName);
	}

	/**
	 * An active ban on another account that has used the same device, or null.
	 * Only consulted when punishments.device-bans is on.
	 */
	public function findDeviceBan(string $deviceId, string $joiningName) : ?Punishment{
		if(!$this->config->getBool("punishments.device-bans", true) || $deviceId === ""){
			return null;
		}

		$joining = Punishment::key($joiningName);
		foreach($this->store->getNamesOnDevice($deviceId) as $name){
			if($name === $joining){
				continue;
			}
			$ban = $this->getActive($name, PunishmentType::BAN);
			if($ban !== null){
				return $ban;
			}
		}

		return null;
	}

	public function linkDevice(string $playerName, string $deviceId) : void{
		$this->store->linkDevice($playerName, $deviceId);
	}

	/**
	 * The proper spelling of a name: the online player's when he is here, the
	 * stored one when he has played before, the one his punishments were
	 * recorded under otherwise, and the typed text as a last resort. Exact
	 * matches only — a prefix could land on somebody else.
	 */
	public function resolveName(string $input) : string{
		$online = $this->server->getPlayerExact($input);
		if($online !== null){
			return $online->getName();
		}

		//The data provider echoes the input back for a player it has never seen.
		$stored = $this->data->load($input)->getName();
		if($stored !== $input){
			return $stored;
		}

		return ($this->store->getHistory($input)[0] ?? null)?->getTargetName() ?? $input;
	}

	/**
	 * Staff may only punish players ranked strictly below them. The console and
	 * operators are not restricted.
	 */
	public function canPunish(CommandSender $issuer, string $targetName) : bool{
		if(!$issuer instanceof Player || $this->server->isOp($issuer->getName())){
			return true;
		}

		$issuerSession = $this->sessions->get($issuer);
		$issuerRank = $issuerSession === null ? null : $this->ranks->get($issuerSession);
		$targetRank = $this->getRankOf($targetName);

		if($issuerRank === null || $targetRank === null){
			//Without a rank ladder there is nothing to compare; permissions decide.
			return true;
		}

		return $issuerRank->outranks($targetRank);
	}

	public function getDefaultDuration(PunishmentType $type) : Duration{
		$text = $this->config->getString("punishments.default-duration." . $type->value, "perm");

		return Duration::parse($text) ?? Duration::permanent();
	}

	/**
	 * Renders a message or screen template with everything known about a
	 * punishment.
	 */
	public function format(string $messageKey, Punishment $punishment) : string{
		return Placeholder::apply($this->config->getMessage($messageKey), $this->describe($punishment));
	}

	/**
	 * The text shown on the disconnect screen of a banned or kicked player.
	 */
	public function renderScreen(string $screen, Punishment $punishment) : string{
		return Placeholder::apply($this->config->getString("punishments.screens." . $screen), $this->describe($punishment));
	}

	/**
	 * @return array<string, string|int>
	 */
	public function describe(Punishment $punishment) : array{
		$now = time();
		$never = $this->word("never");
		$remaining = $punishment->getRemaining($now);
		$expiresAt = $punishment->getExpiresAt();

		return [
			"id" => $punishment->getId(),
			"player" => $punishment->getTargetName(),
			"type" => $this->config->getString("punishments.types." . $punishment->getType()->value, $punishment->getType()->value),
			"action" => $this->config->getString("punishments.actions." . $punishment->getType()->value, $punishment->getType()->value),
			"reason" => $punishment->getReason(),
			"issuer" => $this->displayIssuer($punishment->getIssuer()),
			"duration" => $this->describeLength($punishment),
			"remaining" => $remaining === null ? $never : $this->formatSeconds($remaining),
			"expires" => $expiresAt === null ? $never : date($this->dateFormat(), $expiresAt),
			"created" => date($this->dateFormat(), $punishment->getCreatedAt()),
			"appeal" => $this->config->getString("punishments.appeal"),
			"revoked-by" => $this->displayIssuer($punishment->getRevokedBy() ?? ""),
			"revoke-reason" => $punishment->getRevokeReason() ?? ""
		];
	}

	public function formatSeconds(int $seconds) : string{
		return Duration::format($seconds, $this->unitLabels());
	}

	private function enforce(Punishment $punishment) : void{
		$online = $this->server->getPlayerExact($punishment->getTargetName());
		if($online === null){
			return;
		}

		match($punishment->getType()){
			PunishmentType::BAN => $online->disconnect(
				"Banned #" . $punishment->getId(),
				null,
				$this->renderScreen("ban", $punishment)
			),
			PunishmentType::KICK => $online->disconnect(
				"Kicked #" . $punishment->getId(),
				null,
				$this->renderScreen("kick", $punishment)
			),
			PunishmentType::MUTE => $this->send($online, $this->format("punish-muted", $punishment))
		};
	}

	/**
	 * Staff always hear about it; everyone else only when it was not silent.
	 */
	private function announce(Punishment $punishment) : void{
		$staffLine = $this->format("punish-staff", $punishment);
		if($punishment->isSilent()){
			$staffLine .= $this->config->getMessage("punish-staff-silent");
		}
		$this->notifyStaff($staffLine);

		if($punishment->isSilent()){
			return;
		}

		$broadcast = $this->format("punish-broadcast-" . $punishment->getType()->value, $punishment);
		if($broadcast === ""){
			return;
		}

		foreach($this->server->getOnlinePlayers() as $player){
			if(!$player->hasPermission(self::PERMISSION_NOTIFY)){
				$player->sendMessage($broadcast);
			}
		}
	}

	private function notifyStaff(string $message) : void{
		if($message === ""){
			return;
		}

		foreach($this->server->getOnlinePlayers() as $player){
			if($player->hasPermission(self::PERMISSION_NOTIFY)){
				$player->sendMessage($message);
			}
		}
		$this->server->getLogger()->info($message);
	}

	private function getRankOf(string $playerName) : ?Rank{
		$online = $this->server->getPlayerExact($playerName);
		$session = $online === null ? null : $this->sessions->get($online);
		if($session !== null){
			return $this->ranks->get($session);
		}

		$registry = $this->ranks->getRegistry();

		return $registry->get($this->data->load($playerName)->getRank()) ?? $registry->getDefault();
	}

	private function describeLength(Punishment $punishment) : string{
		if(!$punishment->getType()->isLasting()){
			return "";
		}
		if($punishment->isPermanent()){
			return $this->word("permanent");
		}

		return $this->formatSeconds((int) $punishment->getExpiresAt() - $punishment->getCreatedAt());
	}

	private function displayIssuer(string $issuer) : string{
		return $issuer === Punishment::CONSOLE ? $this->word("console") : $issuer;
	}

	private function word(string $key) : string{
		return $this->config->getString("punishments.words." . $key, $key);
	}

	private function dateFormat() : string{
		return $this->config->getString("punishments.date-format", "d.m.Y H:i");
	}

	/**
	 * @return array<string, string>
	 */
	private function unitLabels() : array{
		$labels = [];
		foreach($this->config->getArray("punishments.units") as $unit => $label){
			if(is_string($unit) && is_string($label)){
				$labels[$unit] = $label;
			}
		}

		return $labels;
	}

	private function send(Player $player, string $message) : void{
		if($message !== ""){
			$player->sendMessage($message);
		}
	}
}
