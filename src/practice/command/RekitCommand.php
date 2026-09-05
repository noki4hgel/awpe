<?php

declare(strict_types=1);

namespace practice\command;

use practice\ffa\FFAManager;
use practice\PracticeConfig;
use practice\session\Session;
use practice\session\SessionManager;

/**
 * /rekit — hands out the arena's kit again.
 *
 * Refused while the player is in combat, otherwise it would be a free reset in
 * the middle of a fight.
 */
final class RekitCommand extends PracticeCommand{

	public const NAME = "rekit";
	public const PERMISSION = "awpe.command.rekit";

	public function __construct(
		PracticeConfig $config,
		SessionManager $sessions,
		private readonly FFAManager $ffa
	){
		parent::__construct(self::NAME, "Get the arena kit again", self::PERMISSION, $config, $sessions);
		$this->setAliases(["kit"]);
	}

	protected function run(Session $session, array $args) : void{
		$player = $session->getPlayer();

		if(!$this->ffa->rekit($session)){
			$player->sendMessage($this->config->getMessage("rekit-unavailable"));
			return;
		}

		$player->sendMessage($this->config->getMessage("rekit-done"));
	}
}
