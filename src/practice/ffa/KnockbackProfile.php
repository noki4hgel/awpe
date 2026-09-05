<?php

declare(strict_types=1);

namespace practice\ffa;

use pocketmine\entity\Living;
use function is_int;
use function is_numeric;
use function max;

/**
 * Knockback tuning for one mode.
 *
 * Vanilla derives the vertical push from the horizontal force and only lets the
 * vertical limit clamp it, which makes the two impossible to tune apart. This
 * profile keeps them independent; PracticePlayer applies them.
 */
final class KnockbackProfile{

	/** Vanilla attack cooldown, in ticks. */
	public const DEFAULT_ATTACK_DELAY = 10;

	public function __construct(
		private readonly float $horizontal = Living::DEFAULT_KNOCKBACK_FORCE,
		private readonly float $vertical = Living::DEFAULT_KNOCKBACK_VERTICAL_LIMIT,
		private readonly int $attackDelay = self::DEFAULT_ATTACK_DELAY
	){
	}

	/**
	 * @param array<string, mixed> $data
	 */
	public static function fromConfig(array $data, ?self $fallback = null) : self{
		$fallback ??= new self();

		return new self(
			self::readFloat($data["horizontal"] ?? null, $fallback->getHorizontal()),
			self::readFloat($data["vertical"] ?? null, $fallback->getVertical()),
			max(0, is_int($data["attack-delay"] ?? null) ? $data["attack-delay"] : $fallback->getAttackDelay())
		);
	}

	public function getHorizontal() : float{
		return $this->horizontal;
	}

	public function getVertical() : float{
		return $this->vertical;
	}

	/**
	 * Ticks a player is immune to full damage after being hit — the "tickrate"
	 * practice servers advertise. Lower means faster trades.
	 */
	public function getAttackDelay() : int{
		return $this->attackDelay;
	}

	private static function readFloat(mixed $value, float $fallback) : float{
		return is_numeric($value) ? (float) $value : $fallback;
	}
}
