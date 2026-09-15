<?php

declare(strict_types=1);

namespace practice\duel\world;

use Symfony\Component\Filesystem\Path;
use function clearstatcache;
use function copy;
use function is_dir;
use function is_file;
use function mkdir;
use function rmdir;
use function scandir;
use function unlink;

/**
 * Plain file operations on world folders, usable from an async worker.
 *
 * Deliberately free of any server state: these run on a worker thread, and
 * they are also what the tests exercise directly.
 */
final class WorldFiles{

	/**
	 * LevelDB's lock file. Copying it would make the copy look already opened,
	 * and the server would refuse to load it.
	 */
	private const LOCK_FILE = "LOCK";

	private function __construct(){
		//NOOP
	}

	/**
	 * Copies a world folder. Fails if the destination already exists, so a copy
	 * can never be mixed into another world.
	 */
	public static function copy(string $source, string $destination) : bool{
		if(!@is_dir($source) || @is_dir($destination)){
			return false;
		}

		return self::copyDirectory($source, $destination);
	}

	/**
	 * Deletes a world folder. Never throws.
	 *
	 * The stat cache is cleared first: PHP caches is_dir() per thread, so a
	 * folder another thread already removed can still look present here, and
	 * listing it then fails as if something went wrong.
	 */
	public static function delete(string $path) : bool{
		clearstatcache(true, $path);
		if(!@is_dir($path)){
			return !@is_file($path);
		}

		$entries = @scandir($path);
		if($entries === false){
			return false;
		}

		foreach($entries as $entry){
			if($entry === "." || $entry === ".."){
				continue;
			}
			$child = Path::join($path, $entry);
			if(@is_dir($child) ? !self::delete($child) : !@unlink($child)){
				return false;
			}
		}

		return @rmdir($path);
	}

	private static function copyDirectory(string $source, string $destination) : bool{
		if(!@mkdir($destination, 0777, true) && !is_dir($destination)){
			return false;
		}

		$entries = @scandir($source);
		if($entries === false){
			return false;
		}

		foreach($entries as $entry){
			if($entry === "." || $entry === ".." || $entry === self::LOCK_FILE){
				continue;
			}

			$from = Path::join($source, $entry);
			$to = Path::join($destination, $entry);
			if(is_dir($from) ? !self::copyDirectory($from, $to) : !@copy($from, $to)){
				return false;
			}
		}

		return true;
	}
}
