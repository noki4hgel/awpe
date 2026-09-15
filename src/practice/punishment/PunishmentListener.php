<?php

declare(strict_types=1);

namespace practice\punishment;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerPreLoginEvent;
use function is_string;

/**
 * Enforces bans at the door and mutes in chat.
 *
 * Must be registered before the chat rate limiter: both listen at LOWEST, and
 * a muted player's message should be refused as muted rather than first
 * starting his chat cooldown.
 */
final class PunishmentListener implements Listener{

	public function __construct(private readonly PunishmentManager $punishments){
	}

	/**
	 * @priority LOWEST
	 *
	 * The device is linked before the check on purpose: a banned player trying
	 * to get in is exactly the moment worth remembering his device.
	 */
	public function onPreLogin(PlayerPreLoginEvent $event) : void{
		$info = $event->getPlayerInfo();
		$name = $info->getUsername();
		$deviceId = $info->getExtraData()["DeviceId"] ?? "";
		$deviceId = is_string($deviceId) ? $deviceId : "";

		$this->punishments->linkDevice($name, $deviceId);

		$ban = $this->punishments->getActive($name, PunishmentType::BAN);
		if($ban !== null){
			$event->setKickFlag(
				PlayerPreLoginEvent::KICK_FLAG_BANNED,
				"Banned #" . $ban->getId(),
				$this->punishments->renderScreen("ban", $ban)
			);
			return;
		}

		$deviceBan = $this->punishments->findDeviceBan($deviceId, $name);
		if($deviceBan !== null){
			$event->setKickFlag(
				PlayerPreLoginEvent::KICK_FLAG_BANNED,
				"Banned device (#" . $deviceBan->getId() . " on " . $deviceBan->getTargetName() . ")",
				$this->punishments->renderScreen("device", $deviceBan)
			);
		}
	}

	/**
	 * @priority LOWEST
	 */
	public function onChat(PlayerChatEvent $event) : void{
		$player = $event->getPlayer();
		$mute = $this->punishments->getActive($player->getName(), PunishmentType::MUTE);
		if($mute === null){
			return;
		}

		$event->cancel();
		$message = $this->punishments->format("punish-muted", $mute);
		if($message !== ""){
			$player->sendMessage($message);
		}
	}
}
