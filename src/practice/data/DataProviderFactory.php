<?php

declare(strict_types=1);

namespace practice\data;

use Symfony\Component\Filesystem\Path;
use function strtolower;

/**
 * Picks the storage backend named in the config, falling back to JSON when the
 * preferred one cannot be opened so a broken database never blocks startup.
 */
final class DataProviderFactory{

	public const TYPE_LEVELDB = "leveldb";
	public const TYPE_JSON = "json";

	private const LEVELDB_DIRECTORY = "players.ldb";
	private const JSON_FILE = "players.json";

	private function __construct(){
		//NOOP
	}

	/**
	 * @phpstan-param (\Closure(string) : void)|null $onFallback
	 */
	public static function create(string $type, string $dataFolder, ?\Closure $onFallback = null) : DataProvider{
		if(strtolower($type) === self::TYPE_LEVELDB){
			try{
				return new LevelDbDataProvider(Path::join($dataFolder, self::LEVELDB_DIRECTORY));
			}catch(\Throwable $e){
				if($onFallback !== null){
					$onFallback($e->getMessage());
				}
			}
		}

		return new JsonDataProvider(Path::join($dataFolder, self::JSON_FILE));
	}
}
