<?php

declare(strict_types=1);

namespace practice\command\punishment;

use practice\punishment\Duration;
use function array_shift;
use function array_values;
use function count;
use function implode;
use function strtolower;
use function trim;

/**
 * What staff typed after /ban, /mute or /kick.
 *
 *   <player> [duration] <reason...> [-s]
 *
 * The duration is optional and recognised by shape: "7d" is a duration, "spam"
 * starts the reason. The silent flag may sit anywhere, since staff type it
 * wherever they remember it.
 */
final class PunishmentInput{

	public const FLAG_SILENT = "-s";

	private function __construct(
		private readonly string $target,
		private readonly ?Duration $duration,
		private readonly string $reason,
		private readonly bool $silent
	){
	}

	/**
	 * @param string[] $args
	 *
	 * @return self|null null when not even a target was given
	 */
	public static function parse(array $args, bool $acceptsDuration) : ?self{
		$silent = false;
		$words = [];
		foreach($args as $arg){
			if(strtolower($arg) === self::FLAG_SILENT){
				$silent = true;
				continue;
			}
			$words[] = $arg;
		}

		if(count($words) === 0){
			return null;
		}

		$target = (string) array_shift($words);
		$words = array_values($words);

		$duration = null;
		if($acceptsDuration && isset($words[0])){
			$duration = Duration::parse($words[0]);
			if($duration !== null){
				array_shift($words);
			}
		}

		return new self($target, $duration, trim(implode(" ", $words)), $silent);
	}

	public function getTarget() : string{
		return $this->target;
	}

	/**
	 * Null when staff left it out and the configured default applies.
	 */
	public function getDuration() : ?Duration{
		return $this->duration;
	}

	public function getReason() : string{
		return $this->reason;
	}

	public function isSilent() : bool{
		return $this->silent;
	}
}
