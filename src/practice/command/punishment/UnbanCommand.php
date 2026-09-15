<?php

declare(strict_types=1);

namespace practice\command\punishment;

use practice\PracticeConfig;
use practice\punishment\PunishmentManager;
use practice\punishment\PunishmentType;

/**
 * /unban <player> [reason...] — see RevokeCommand.
 */
final class UnbanCommand extends RevokeCommand{

	public const NAME = "unban";
	public const PERMISSION = "awpe.command.unban";

	public function __construct(PracticeConfig $config, PunishmentManager $punishments){
		parent::__construct(self::NAME, "Lift a player's ban", self::PERMISSION, PunishmentType::BAN, $config, $punishments);
	}
}
