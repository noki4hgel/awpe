<?php

declare(strict_types=1);

namespace practice\punishment\store;

use practice\data\DataProviderFactory;
use Symfony\Component\Filesystem\Path;
use function strtolower;

/**
 * Opens the punishment store with the same backend as player data, falling back
 * to JSON when LevelDB cannot be opened so a broken database never blocks
 * startup — and never silently stops bans from being enforced.
 */
final class PunishmentStoreFactory{

	private const LEVELDB_DIRECTORY = "punishments.ldb";
	private const JSON_FILE = "punishments.json";

	private function __construct(){
		//NOOP
	}

	/**
	 * @phpstan-param (\Closure(string) : void)|null $onFallback
	 */
	public static function create(string $type, string $dataFolder, ?\Closure $onFallback = null) : PunishmentStore{
		if(strtolower($type) === DataProviderFactory::TYPE_LEVELDB){
			try{
				return new LevelDbPunishmentStore(Path::join($dataFolder, self::LEVELDB_DIRECTORY));
			}catch(\Throwable $e){
				if($onFallback !== null){
					$onFallback($e->getMessage());
				}
			}
		}

		return new JsonPunishmentStore(Path::join($dataFolder, self::JSON_FILE));
	}
}
