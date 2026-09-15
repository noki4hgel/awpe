<?php

declare(strict_types=1);

namespace practice\duel\world;

use pocketmine\scheduler\ClosureTask;
use pocketmine\scheduler\TaskScheduler;
use pocketmine\Server;
use pocketmine\world\World;
use Symfony\Component\Filesystem\Path;
use function bin2hex;
use function is_dir;
use function preg_match;
use function random_bytes;
use function scandir;

/**
 * Gives every match its own copy of an arena and throws it away afterwards.
 *
 * Copies are named "duel-<match id>-<random>". That name is the only thing that
 * lets a folder be deleted: nothing without it is ever removed, so a mistyped
 * template can never take a real world with it.
 */
final class WorldCloner{

	private const NAME_PATTERN = '/^duel-\d+-[0-9a-f]{8}$/';
	private const WORLDS_DIRECTORY = "worlds";

	public function __construct(
		private readonly Server $server,
		private readonly TaskScheduler $scheduler,
		private readonly \Logger $logger
	){
	}

	public static function nameFor(int $matchId) : string{
		return "duel-" . $matchId . "-" . bin2hex(random_bytes(4));
	}

	public static function isDisposable(string $worldName) : bool{
		return preg_match(self::NAME_PATTERN, $worldName) === 1;
	}

	/**
	 * Copies a template in the background, then loads the copy on the main
	 * thread. The callback receives the loaded world, or null if anything failed
	 * (the half-made copy is cleaned up in that case).
	 *
	 * @phpstan-param \Closure(World|null) : void $onReady
	 */
	public function clone(string $template, string $name, \Closure $onReady) : void{
		if(!self::isDisposable($name)){
			throw new \InvalidArgumentException("\"$name\" is not a disposable duel world name");
		}

		$source = Path::join($this->worldsPath(), $template);
		$destination = Path::join($this->worldsPath(), $name);

		$this->server->getAsyncPool()->submitTask(new CopyWorldTask($source, $destination, function(bool $copied) use ($template, $name, $destination, $onReady) : void{
			if(!$copied){
				$this->logger->warning("Could not copy the duel template \"$template\".");
				$this->deleteFolder($destination);
				$onReady(null);
				return;
			}

			$worldManager = $this->server->getWorldManager();
			if(!$worldManager->loadWorld($name)){
				$this->logger->warning("Copied the duel template \"$template\" but could not load the copy.");
				$this->deleteFolder($destination);
				$onReady(null);
				return;
			}

			$world = $worldManager->getWorldByName($name);
			if($world === null){
				$onReady(null);
				return;
			}

			//Nothing played on a throwaway copy is worth writing back to disk.
			$world->setAutoSave(false);
			$world->setTime(World::TIME_DAY);
			$world->stopTime();

			$onReady($world);
		}));
	}

	/**
	 * Unloads a match world and deletes its folder.
	 *
	 * Always deferred by a tick: a match can end from inside a world tick (void
	 * damage is dealt there), and unloading a world mid-tick throws.
	 */
	public function discard(World $world) : void{
		$name = $world->getFolderName();
		if(!self::isDisposable($name)){
			return;
		}

		$this->scheduler->scheduleDelayedTask(new ClosureTask(function() use ($world, $name) : void{
			if($world->isLoaded()){
				$this->server->getWorldManager()->unloadWorld($world, true);
			}
			$this->deleteFolder(Path::join($this->worldsPath(), $name));
		}), 1);
	}

	/**
	 * Unloads and deletes right away, for shutdown, when there is no next tick
	 * and no async pool to hand the deletion to.
	 */
	public function discardNow(World $world) : void{
		$name = $world->getFolderName();
		if(!self::isDisposable($name)){
			return;
		}

		if($world->isLoaded() && !$world->isDoingTick()){
			$this->server->getWorldManager()->unloadWorld($world, true);
		}
		WorldFiles::delete(Path::join($this->worldsPath(), $name));
	}

	/**
	 * Deletes match worlds left behind by a crash. Only called on startup,
	 * before anything could have loaded one.
	 *
	 * @return int how many were removed
	 */
	public function purgeLeftovers() : int{
		$entries = @scandir($this->worldsPath());
		if($entries === false){
			return 0;
		}

		$removed = 0;
		foreach($entries as $entry){
			$path = Path::join($this->worldsPath(), $entry);
			if(self::isDisposable($entry) && is_dir($path) && !$this->server->getWorldManager()->isWorldLoaded($entry) && WorldFiles::delete($path)){
				$removed++;
			}
		}

		return $removed;
	}

	private function deleteFolder(string $path) : void{
		$this->server->getAsyncPool()->submitTask(new DeleteWorldTask($path, function(bool $deleted) use ($path) : void{
			if(!$deleted){
				$this->logger->debug("Could not delete the duel world at $path yet; it will be removed on the next start.");
			}
		}));
	}

	/**
	 * Built the way the server builds it. WorldManager::getDataPath() would be
	 * shorter, but it only exists in newer builds than the one this runs on.
	 */
	public function worldsPath() : string{
		return Path::join($this->server->getDataPath(), self::WORLDS_DIRECTORY);
	}
}
