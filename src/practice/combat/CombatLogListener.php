<?php

declare(strict_types=1);

namespace practice\combat;

use pocketmine\event\Listener;
use pocketmine\event\server\CommandEvent;
use pocketmine\player\Player;
use practice\PracticeConfig;
use practice\util\Placeholder;

/**
 * Combat log protection: no commands while tagged.
 *
 * Blocking every command (rather than a named list) is deliberate — command
 * aliases would make a blacklist trivial to walk around, and in a fight there
 * is no command a player legitimately needs.
 */
final class CombatLogListener implements Listener{

	public function __construct(
		private readonly PracticeConfig $config,
		private readonly CombatManager $combat
	){
	}

	/**
	 * @priority LOWEST
	 */
	public function onCommand(CommandEvent $event) : void{
		$sender = $event->getSender();
		if(!$sender instanceof Player){
			return;
		}

		if(!$this->config->getBool("combat.block-commands", true) || !$this->combat->isTagged($sender)){
			return;
		}

		$event->cancel();

		$message = $this->config->getMessage("combat-blocked");
		if($message !== ""){
			$sender->sendMessage(Placeholder::apply($message, [
				"seconds" => $this->combat->getRemainingSeconds($sender)
			]));
		}
	}
}
