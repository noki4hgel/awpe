<?php

declare(strict_types=1);

namespace practice\command;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use practice\PracticeConfig;
use practice\session\Session;
use practice\session\SessionManager;

/**
 * Base for the core's commands.
 *
 * Handles the two checks every one of them needs — the sender is a player and
 * has a session — so subclasses only describe what the command does.
 */
abstract class PracticeCommand extends Command{

	public function __construct(
		string $name,
		string $description,
		string $permission,
		protected readonly PracticeConfig $config,
		protected readonly SessionManager $sessions
	){
		parent::__construct($name, $description);
		$this->setPermission($permission);
	}

	final public function execute(CommandSender $sender, string $commandLabel, array $args) : void{
		if(!$this->testPermission($sender)){
			return;
		}

		if(!$sender instanceof Player){
			$sender->sendMessage($this->config->getMessage("players-only"));
			return;
		}

		$session = $this->sessions->get($sender);
		if($session === null){
			//Still logging in; nothing sensible to do yet.
			return;
		}

		$this->run($session, $args);
	}

	/**
	 * @param string[] $args
	 */
	abstract protected function run(Session $session, array $args) : void;
}
