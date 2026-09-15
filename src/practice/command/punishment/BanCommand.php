<?php

declare(strict_types=1);

namespace practice\command\punishment;

use practice\PracticeConfig;
use practice\punishment\PunishmentManager;
use practice\punishment\PunishmentType;

/**
 * /ban — see PunishCommand for the syntax and the checks.
 */
final class BanCommand extends PunishCommand{

	public const NAME = "ban";
	public const PERMISSION = "awpe.command.ban";

	public function __construct(PracticeConfig $config, PunishmentManager $punishments){
		parent::__construct(self::NAME, "Ban a player, for a while or for good", self::PERMISSION, PunishmentType::BAN, $config, $punishments);
	}
}
