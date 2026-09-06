<?php

declare(strict_types=1);

namespace practice\ffa;

use pocketmine\utils\Config;
use function is_array;

/**
 * Persists the knockback values changed at runtime by /kbdata.
 *
 * They are kept in their own file rather than written back into config.yml,
 * because saving that one would strip every comment out of it. On startup the
 * stored values are layered over whatever the config says, so a live tweak
 * survives a restart and "reset" simply drops the override.
 */
final class KnockbackStore{

	private const KEY_HORIZONTAL = "horizontal";
	private const KEY_VERTICAL = "vertical";
	private const KEY_ATTACK_DELAY = "attack-delay";

	private Config $config;

	public function __construct(string $file){
		$this->config = new Config($file, Config::YAML, []);
	}

	public function has(string $arenaId) : bool{
		return is_array($this->config->get($arenaId, null));
	}

	/**
	 * Returns the profile to actually use for an arena: the stored override if
	 * there is one, otherwise the profile built from config.yml.
	 */
	public function apply(string $arenaId, KnockbackProfile $fromConfig) : KnockbackProfile{
		$stored = $this->config->get($arenaId, null);

		return is_array($stored) ? KnockbackProfile::fromConfig($stored, $fromConfig) : $fromConfig;
	}

	public function set(string $arenaId, KnockbackProfile $profile) : void{
		$this->config->set($arenaId, [
			self::KEY_HORIZONTAL => $profile->getHorizontal(),
			self::KEY_VERTICAL => $profile->getVertical(),
			self::KEY_ATTACK_DELAY => $profile->getAttackDelay()
		]);
		$this->config->save();
	}

	public function remove(string $arenaId) : void{
		$this->config->remove($arenaId);
		$this->config->save();
	}
}
