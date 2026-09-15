<?php

declare(strict_types=1);

namespace practice\punishment;

use function array_slice;
use function count;
use function implode;
use function intdiv;
use function preg_match;
use function preg_match_all;
use function strlen;
use function strtolower;
use const PREG_SET_ORDER;

/**
 * How long a punishment lasts: a number of seconds, or forever.
 *
 * Parsing and formatting live together so the syntax staff type and the text
 * players read can never drift apart.
 *
 * Accepted input: one or more <number><unit> parts, e.g. "30m", "7d", "1d12h",
 * with units s, m, h, d, w, mo (30 days) and y (365 days); or "perm",
 * "permanent", "forever" for no end at all.
 */
final class Duration{

	public const SECOND = 1;
	public const MINUTE = 60;
	public const HOUR = 3600;
	public const DAY = 86400;
	public const WEEK = 604800;
	public const MONTH = 2592000;
	public const YEAR = 31536000;

	/** Input unit => seconds. "mo" is listed before "m" so it wins the match. */
	private const UNITS = [
		"y" => self::YEAR,
		"mo" => self::MONTH,
		"w" => self::WEEK,
		"d" => self::DAY,
		"h" => self::HOUR,
		"m" => self::MINUTE,
		"s" => self::SECOND
	];

	private const PERMANENT_WORDS = ["perm", "permanent", "forever"];

	/** How many units a formatted duration shows: "6d 23h", not "6d 23h 59m 59s". */
	private const FORMAT_PARTS = 2;

	private function __construct(private readonly ?int $seconds){
	}

	public static function permanent() : self{
		return new self(null);
	}

	public static function ofSeconds(int $seconds) : self{
		if($seconds <= 0){
			throw new \InvalidArgumentException("A duration must be longer than zero seconds");
		}

		return new self($seconds);
	}

	/**
	 * Reads a duration, or returns null when the text is not one — which is how
	 * a command tells "7d" apart from the first word of a reason.
	 */
	public static function parse(string $text) : ?self{
		$text = strtolower($text);
		foreach(self::PERMANENT_WORDS as $word){
			if($text === $word){
				return self::permanent();
			}
		}

		//The whole token has to be made of <number><unit> parts, nothing else.
		if($text === "" || preg_match('/^(?:\d+(?:y|mo|w|d|h|m|s))+$/', $text) !== 1){
			return null;
		}

		preg_match_all('/(\d+)(y|mo|w|d|h|m|s)/', $text, $parts, PREG_SET_ORDER);

		$seconds = 0;
		foreach($parts as [, $amount, $unit]){
			//A number long enough to overflow is not a duration anybody meant.
			if(strlen($amount) > 9){
				return null;
			}
			$seconds += (int) $amount * self::UNITS[$unit];
		}

		return $seconds > 0 ? self::ofSeconds($seconds) : null;
	}

	public function isPermanent() : bool{
		return $this->seconds === null;
	}

	/**
	 * @throws \LogicException for a permanent duration, which has no length
	 */
	public function getSeconds() : int{
		return $this->seconds ?? throw new \LogicException("A permanent duration has no length");
	}

	/**
	 * The moment this duration ends when it starts at $from, or null if never.
	 */
	public function endsAt(int $from) : ?int{
		return $this->seconds === null ? null : $from + $this->seconds;
	}

	/**
	 * Human text for a number of seconds, using the configured unit labels,
	 * e.g. "6d 23h". Anything under a second reads as "0s".
	 *
	 * @param array<string, string> $labels unit (year, week, day, hour, minute, second) => label
	 */
	public static function format(int $seconds, array $labels) : string{
		$units = [
			"year" => self::YEAR,
			"week" => self::WEEK,
			"day" => self::DAY,
			"hour" => self::HOUR,
			"minute" => self::MINUTE,
			"second" => self::SECOND
		];

		$parts = [];
		$left = $seconds;
		foreach($units as $unit => $size){
			if($left < $size){
				continue;
			}
			$parts[] = intdiv($left, $size) . ($labels[$unit] ?? $unit[0]);
			$left %= $size;
		}

		if(count($parts) === 0){
			return "0" . ($labels["second"] ?? "s");
		}

		return implode(" ", array_slice($parts, 0, self::FORMAT_PARTS));
	}
}
