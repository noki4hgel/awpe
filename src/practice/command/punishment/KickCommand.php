<?php

declare(strict_types=1);

namespace practice\command\punishment;

use practice\PracticeConfig;
use practice\punishment\PunishmentManager;
use practice\punishment\PunishmentType;

/**
 * /kick — see PunishCommand for the syntax and the checks.
 */
final class KickCommand extends PunishCommand{

	public const NAME = "kick";
	public const PERMISSION = "awpe.command.kick";

	public function __construct(PracticeConfig $config, PunishmentManager $punishments){
		parent::__construct(self::NAME, "Kick a player off the server", self::PERMISSION, PunishmentType::KICK, $config, $punishments);
	}
}
