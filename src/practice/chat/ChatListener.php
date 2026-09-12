<?php

declare(strict_types=1);

namespace practice\chat;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\player\Player;
use practice\cooldown\CooldownManager;
use practice\PracticeConfig;
use practice\util\Placeholder;
use function max;
use function mb_strtolower;
use function preg_replace;
use function spl_object_id;
use function trim;

/**
 * Chat rate limiting.
 *
 * Runs at LOWEST so a refused message never reaches the rank formatter or any
 * other handler. Staff skip both checks through a permission, which is granted
 * by their rank rather than by anything hard-coded here.
 */
final class ChatListener implements Listener{

	private const COOLDOWN_TAG = "chat";
	private const TICKS_PER_SECOND = 20;

	/** @var array<int, string> player object id => his previous message, normalised */
	private array $lastMessage = [];

	public function __construct(
		private readonly PracticeConfig $config,
		private readonly CooldownManager $cooldowns
	){
	}

	/**
	 * @priority LOWEST
	 */
	public function onChat(PlayerChatEvent $event) : void{
		$player = $event->getPlayer();
		if($this->mayBypass($player)){
			return;
		}

		if($this->cooldowns->has($player, self::COOLDOWN_TAG)){
			$event->cancel();
			$this->send($player, "chat-cooldown", [
				"seconds" => $this->cooldowns->getRemainingSeconds($player, self::COOLDOWN_TAG)
			]);
			return;
		}

		$normalised = self::normalise($event->getMessage());
		if($this->config->getBool("chat.block-repeats", true) && $normalised !== "" && ($this->lastMessage[spl_object_id($player)] ?? null) === $normalised){
			$event->cancel();
			$this->send($player, "chat-repeat", []);
			return;
		}

		$this->lastMessage[spl_object_id($player)] = $normalised;

		$seconds = max(0, $this->config->getInt("chat.cooldown", 3));
		$this->cooldowns->set($player, self::COOLDOWN_TAG, $seconds * self::TICKS_PER_SECOND);
	}

	public function onQuit(PlayerQuitEvent $event) : void{
		unset($this->lastMessage[spl_object_id($event->getPlayer())]);
	}

	private function mayBypass(Player $player) : bool{
		$permission = $this->config->getString("chat.bypass-permission");

		return $permission !== "" && $player->hasPermission($permission);
	}

	/**
	 * Collapses case and runs of whitespace, so "hi", "HI" and "h i" split by a
	 * double space all count as the same message.
	 */
	private static function normalise(string $message) : string{
		return trim(mb_strtolower(preg_replace('/\s+/u', " ", $message) ?? $message));
	}

	/**
	 * @param array<string, string|int|float> $values
	 */
	private function send(Player $player, string $key, array $values) : void{
		$message = $this->config->getMessage($key);
		if($message !== ""){
			$player->sendMessage(Placeholder::apply($message, $values));
		}
	}
}
