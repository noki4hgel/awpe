<?php

declare(strict_types=1);

namespace practice\duel\arena;

use Symfony\Component\Filesystem\Path;
use function array_rand;
use function array_values;
use function count;
use function is_array;
use function is_file;
use function is_string;
use function strtolower;

/**
 * The duel maps from config.yml, checked against what is actually on disk.
 */
final class ArenaRegistry{

	private const LEVEL_DAT = "level.dat";

	/** @var array<string, DuelArena> */
	private array $arenas = [];

	/**
	 * @param array<mixed> $config
	 *
	 * @return array<string, string> arena id => why it was skipped
	 */
	public function loadFromConfig(array $config, string $worldsPath) : array{
		$this->arenas = [];
		$problems = [];

		foreach($config as $id => $data){
			if(!is_string($id) || !is_array($data)){
				$problems[(string) $id] = "malformed entry";
				continue;
			}

			$template = is_string($data["template"] ?? null) ? $data["template"] : "";
			if($template === "" || !is_file(Path::join($worldsPath, $template, self::LEVEL_DAT))){
				$problems[$id] = "template world \"$template\" not found in " . $worldsPath;
				continue;
			}

			$spawns = [];
			foreach(is_array($data["spawns"] ?? null) ? $data["spawns"] : [] as $spawn){
				if(is_array($spawn) && ($point = SpawnPoint::fromConfig($spawn)) !== null){
					$spawns[] = $point;
				}
			}
			if(count($spawns) < 2){
				$problems[$id] = "needs two spawn points with x, y and z";
				continue;
			}

			$ladders = [];
			foreach(is_array($data["ladders"] ?? null) ? $data["ladders"] : [] as $ladder){
				if(is_string($ladder)){
					$ladders[] = strtolower($ladder);
				}
			}
			if(count($ladders) === 0){
				$problems[$id] = "lists no ladders";
				continue;
			}

			$this->arenas[$id] = new DuelArena(
				$id,
				is_string($data["display"] ?? null) ? $data["display"] : $id,
				$template,
				$ladders,
				$spawns[0],
				$spawns[1]
			);
		}

		return $problems;
	}

	/**
	 * @return array<string, DuelArena>
	 */
	public function all() : array{
		return $this->arenas;
	}

	public function supports(string $ladderId) : bool{
		foreach($this->arenas as $arena){
			if($arena->supports($ladderId)){
				return true;
			}
		}

		return false;
	}

	/**
	 * A random map for a mode, so the same two players do not always meet on
	 * the same one.
	 */
	public function pickFor(string $ladderId) : ?DuelArena{
		$candidates = [];
		foreach($this->arenas as $arena){
			if($arena->supports($ladderId)){
				$candidates[] = $arena;
			}
		}

		if(count($candidates) === 0){
			return null;
		}

		return array_values($candidates)[array_rand($candidates)];
	}
}
