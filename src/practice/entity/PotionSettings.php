<?php

declare(strict_types=1);

namespace practice\entity;

use function is_numeric;
use function max;

/**
 * Tuning for splash potions.
 *
 * Vanilla measures the throw from the victim's eyes and starts losing potency
 * immediately, so a pot landed at your feet heals barely half of what the
 * potion is worth. Practice servers give a full heal inside a small radius and
 * only fall off past it — that is what fullEffectRange does.
 */
final class PotionSettings{

	public const VANILLA_RANGE = 4.0;

	public function __construct(
		private readonly float $range = self::VANILLA_RANGE,
		private readonly float $fullEffectRange = 1.75,
		private readonly float $gravity = 0.065,
		private readonly float $drag = 0.01,
		private readonly float $scale = 0.4
	){
	}

	/**
	 * @param array<string, mixed> $data
	 */
	public static function fromConfig(array $data) : self{
		$fallback = new self();
		$read = static fn(string $key, float $default) : float => is_numeric($data[$key] ?? null) ? (float) $data[$key] : $default;

		return new self(
			max(0.1, $read("range", $fallback->getRange())),
			max(0.0, $read("full-effect-range", $fallback->getFullEffectRange())),
			$read("gravity", $fallback->getGravity()),
			$read("drag", $fallback->getDrag()),
			max(0.1, $read("scale", $fallback->getScale()))
		);
	}

	public function getRange() : float{
		return $this->range;
	}

	public function getFullEffectRange() : float{
		return $this->fullEffectRange;
	}

	public function getGravity() : float{
		return $this->gravity;
	}

	public function getDrag() : float{
		return $this->drag;
	}

	public function getScale() : float{
		return $this->scale;
	}

	/**
	 * How much of the effect an entity at this distance receives.
	 */
	public function potencyAt(float $distance) : float{
		if($distance <= $this->fullEffectRange){
			return 1.0;
		}

		return max(0.0, 1 - (($distance - $this->fullEffectRange) / $this->range));
	}
}
