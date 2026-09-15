<?php

declare(strict_types=1);

namespace practice\punishment;

use function strtolower;

/**
 * The kinds of punishment the core hands out.
 *
 * One enum instead of a class per kind: a ban and a mute differ in what they
 * block, not in what they are, so they share one record and one code path.
 */
enum PunishmentType : string{

	case BAN = "ban";
	case MUTE = "mute";
	case KICK = "kick";

	/**
	 * Whether this kind stays in force for a while. A kick is over the moment it
	 * happens; it is recorded for the history and nothing else.
	 */
	public function isLasting() : bool{
		return $this !== self::KICK;
	}

	public static function fromName(string $name) : ?self{
		return self::tryFrom(strtolower($name));
	}
}
