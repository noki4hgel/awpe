<?php

declare(strict_types=1);

namespace practice\util;

use pocketmine\world\format\Chunk;
use pocketmine\world\Position;
use pocketmine\world\World;
use pocketmine\world\WorldException;

/**
 * World helpers that never throw at the caller.
 *
 * World::getSafeSpawn() raises a WorldException when the spawn chunk has not
 * been generated yet, which for a freshly created arena world would crash the
 * server the first time somebody joins it.
 */
final class WorldUtil{

	/** Chunk radius around spawn that is generated up front. */
	private const SPAWN_CHUNK_RADIUS = 1;

	private function __construct(){
		//NOOP
	}

	/**
	 * The safe spawn of a world, falling back to its raw spawn position while
	 * the terrain around it is still being generated.
	 */
	public static function safeSpawn(World $world) : Position{
		try{
			return $world->getSafeSpawn();
		}catch(WorldException){
			return $world->getSpawnLocation();
		}
	}

	/**
	 * Whether a real spawn point can be resolved right now.
	 *
	 * This deliberately asks getSafeSpawn() rather than checking the population
	 * flag: an imported arena map has its chunks on disk but does not always
	 * carry the flag, and what callers actually need to know is whether there is
	 * somewhere solid to put a player.
	 */
	public static function isSpawnReady(World $world) : bool{
		try{
			$world->getSafeSpawn();
			return true;
		}catch(WorldException){
			return false;
		}
	}

	/**
	 * Generates the chunks around a world's spawn so the first player teleported
	 * there does not land in unpopulated terrain.
	 *
	 * @phpstan-param (\Closure(string) : void)|null $onError
	 */
	public static function prepareSpawn(World $world, ?\Closure $onError = null) : void{
		$spawn = $world->getSpawnLocation();
		$chunkX = $spawn->getFloorX() >> Chunk::COORD_BIT_SIZE;
		$chunkZ = $spawn->getFloorZ() >> Chunk::COORD_BIT_SIZE;

		for($x = -self::SPAWN_CHUNK_RADIUS; $x <= self::SPAWN_CHUNK_RADIUS; $x++){
			for($z = -self::SPAWN_CHUNK_RADIUS; $z <= self::SPAWN_CHUNK_RADIUS; $z++){
				if($world->isChunkPopulated($chunkX + $x, $chunkZ + $z)){
					continue;
				}

				$world->orderChunkPopulation($chunkX + $x, $chunkZ + $z, null)->onCompletion(
					static function() : void{
						//NOOP - the chunk is ready.
					},
					static function() use ($world, $onError) : void{
						if($onError !== null){
							$onError($world->getDisplayName());
						}
					}
				);
			}
		}
	}
}
