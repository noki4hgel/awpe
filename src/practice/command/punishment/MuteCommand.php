<?php

declare(strict_types=1);

namespace practice\command\punishment;

use practice\PracticeConfig;
use practice\punishment\PunishmentManager;
use practice\punishment\PunishmentType;

/**
 * /mute — see PunishCommand for the syntax and the checks.
 */
final class MuteCommand extends PunishCommand{

	public const NAME = "mute";
	public const PERMISSION = "awpe.command.mute";

	public function __construct(PracticeConfig $config, PunishmentManager $punishments){
		parent::__construct(self::NAME, "Mute a player, for a while or for good", self::PERMISSION, PunishmentType::MUTE, $config, $punishments);
	}
}
