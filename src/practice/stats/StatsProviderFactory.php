<?php

declare(strict_types=1);

namespace practice\stats;

use Symfony\Component\Filesystem\Path;
use function strtolower;

/**
 * Picks the storage backend named in the config, falling back to JSON when the
 * preferred one cannot be opened so a broken database never blocks startup.
 */
final class StatsProviderFactory{

	public const TYPE_LEVELDB = "leveldb";
	public const TYPE_JSON = "json";

	private const LEVELDB_DIRECTORY = "stats.ldb";
	private const JSON_FILE = "stats.json";

	private function __construct(){
		//NOOP
	}

	/**
	 * @phpstan-param (\Closure(string) : void)|null $onFallback
	 */
	public static function create(string $type, string $dataFolder, ?\Closure $onFallback = null) : StatsProvider{
		if(strtolower($type) === self::TYPE_LEVELDB){
			try{
				return new LevelDbStatsProvider(Path::join($dataFolder, self::LEVELDB_DIRECTORY));
			}catch(\Throwable $e){
				if($onFallback !== null){
					$onFallback($e->getMessage());
				}
			}
		}

		return new JsonStatsProvider(Path::join($dataFolder, self::JSON_FILE));
	}
}
