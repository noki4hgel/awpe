<?php

declare(strict_types=1);

namespace practice\duel\arena;

use pocketmine\entity\Location;
use pocketmine\world\World;
use practice\util\ConfigKeys;
use function is_numeric;

/**
 * A position and facing inside an arena template.
 *
 * Stored without a world, because every match plays in a fresh copy of the
 * template and the coordinates are the same in each.
 */
final class SpawnPoint{

	public function __construct(
		private readonly float $x,
		private readonly float $y,
		private readonly float $z,
		private readonly float $yaw = 0.0,
		private readonly float $pitch = 0.0
	){
	}

	/**
	 * @param array<string, mixed> $data
	 */
	public static function fromConfig(array $data) : ?self{
		$axes = [];
		foreach(["x", "y", "z"] as $axis){
			//ConfigKeys: YAML reads a bare "y:" as a boolean key.
			$value = ConfigKeys::get($data, $axis);
			if(!is_numeric($value)){
				return null;
			}
			$axes[$axis] = (float) $value;
		}

		return new self(
			$axes["x"],
			$axes["y"],
			$axes["z"],
			is_numeric($data["yaw"] ?? null) ? (float) $data["yaw"] : 0.0,
			is_numeric($data["pitch"] ?? null) ? (float) $data["pitch"] : 0.0
		);
	}

	public function getY() : float{
		return $this->y;
	}

	public function toLocation(World $world) : Location{
		return new Location($this->x, $this->y, $this->z, $world, $this->yaw, $this->pitch);
	}
}
