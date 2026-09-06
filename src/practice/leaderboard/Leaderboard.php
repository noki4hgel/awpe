<?php

declare(strict_types=1);

namespace practice\leaderboard;

use pocketmine\math\Vector3;
use pocketmine\world\particle\FloatingTextParticle;
use pocketmine\world\World;
use function is_numeric;
use function is_string;
use function max;
use function min;

/**
 * One floating board in the lobby: where it hangs, what it ranks and how it reads.
 *
 * The particle instance is kept because it holds the entity id the client knows
 * the board by — reusing it means a refresh edits the text in place instead of
 * spawning a second board next to the first.
 */
final class Leaderboard{

	public const MAX_ENTRIES = 15;

	private FloatingTextParticle $particle;

	public function __construct(
		private readonly string $id,
		private readonly string $worldName,
		private readonly Vector3 $position,
		private readonly string $field,
		private readonly int $limit,
		private readonly string $title,
		private readonly string $entryFormat,
		private readonly string $emptyText,
		private ?World $world = null
	){
		$this->particle = new FloatingTextParticle("");
	}

	/**
	 * @param array<string, mixed> $data
	 */
	public static function fromConfig(string $id, array $data, string $defaultWorld) : self{
		$number = static fn(string $key, float $default) : float => is_numeric($data[$key] ?? null) ? (float) $data[$key] : $default;
		$text = static fn(string $key, string $default) : string => is_string($data[$key] ?? null) ? $data[$key] : $default;

		return new self(
			$id,
			$text("world", $defaultWorld),
			new Vector3($number("x", 0.0), $number("y", 0.0), $number("z", 0.0)),
			$text("field", "kills"),
			(int) max(1, min(self::MAX_ENTRIES, $number("limit", 10.0))),
			$text("title", $id),
			$text("entry", "§7{position}. §f{player} §8- §b{value}"),
			$text("empty", "§8- nobody yet -")
		);
	}

	public function getId() : string{
		return $this->id;
	}

	public function getWorldName() : string{
		return $this->worldName;
	}

	public function getPosition() : Vector3{
		return $this->position;
	}

	/**
	 * The PlayerData field the board ranks by, e.g. "kills".
	 */
	public function getField() : string{
		return $this->field;
	}

	public function getLimit() : int{
		return $this->limit;
	}

	public function getTitle() : string{
		return $this->title;
	}

	public function getEntryFormat() : string{
		return $this->entryFormat;
	}

	public function getEmptyText() : string{
		return $this->emptyText;
	}

	public function getWorld() : ?World{
		if($this->world !== null && !$this->world->isLoaded()){
			$this->world = null;
		}

		return $this->world;
	}

	public function setWorld(?World $world) : void{
		$this->world = $world;
	}

	public function getParticle() : FloatingTextParticle{
		return $this->particle;
	}
}
