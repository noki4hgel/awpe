<?php

declare(strict_types=1);

namespace practice\world;

use pocketmine\world\ChunkManager;
use pocketmine\world\format\Chunk;
use pocketmine\world\generator\Generator;

/**
 * Empty-world generator.
 *
 * Arena maps are usually exported from servers that generate void worlds, so
 * their level.dat names a "void" generator. Without one registered the world
 * refuses to load, even though every chunk it needs is already saved on disk.
 *
 * Runs on async generation workers, so it must stay free of server-side state.
 */
final class VoidGenerator extends Generator{

	public const NAME = "void";

	private Chunk $chunk;

	public function __construct(int $seed, string $preset){
		parent::__construct($seed, $preset);

		$this->chunk = new Chunk([], false);
	}

	public function generateChunk(ChunkManager $world, int $chunkX, int $chunkZ) : void{
		$world->setChunk($chunkX, $chunkZ, clone $this->chunk);
	}

	public function populateChunk(ChunkManager $world, int $chunkX, int $chunkZ) : void{
		//NOOP - nothing to decorate in an empty world.
	}
}
