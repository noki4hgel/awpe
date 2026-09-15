<?php

declare(strict_types=1);

namespace practice\command\punishment;

use practice\PracticeConfig;
use practice\punishment\PunishmentManager;
use practice\punishment\PunishmentType;

/**
 * /unmute <player> [reason...] — see RevokeCommand.
 */
final class UnmuteCommand extends RevokeCommand{

	public const NAME = "unmute";
	public const PERMISSION = "awpe.command.unmute";

	public function __construct(PracticeConfig $config, PunishmentManager $punishments){
		parent::__construct(self::NAME, "Lift a player's mute", self::PERMISSION, PunishmentType::MUTE, $config, $punishments);
	}
}
