<?php

declare(strict_types=1);

namespace practice\kit;

use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\StringToEffectParser;
use pocketmine\utils\Limits;
use practice\util\ItemParser;
use function is_array;
use function is_int;
use function is_string;

/**
 * Turns the "kits" section of config.yml into Kit objects.
 *
 * Unknown item/enchantment/effect names are skipped rather than fatal, so a
 * typo in the config costs one item instead of the whole server boot.
 */
final class KitFactory{

	private const TICKS_PER_SECOND = 20;
	private const INFINITE = -1;

	private function __construct(){
		//NOOP
	}

	/**
	 * @param array<string, mixed> $data
	 */
	public static function fromConfig(string $id, array $data) : Kit{
		[$hotbar, $extra, $filler] = self::parseItems($data["items"] ?? []);

		return new Kit(
			$id,
			self::parseArmor($data["armor"] ?? []),
			$hotbar,
			$extra,
			self::parseEffects($data["effects"] ?? []),
			$filler
		);
	}

	/**
	 * @param mixed $raw
	 *
	 * @return array<string, \pocketmine\item\Item>
	 */
	private static function parseArmor($raw) : array{
		if(!is_array($raw)){
			return [];
		}

		$armor = [];
		foreach([Kit::SLOT_HELMET, Kit::SLOT_CHESTPLATE, Kit::SLOT_LEGGINGS, Kit::SLOT_BOOTS] as $slot){
			if(!is_array($raw[$slot] ?? null)){
				continue;
			}
			$item = ItemParser::parse($raw[$slot]);
			if($item !== null){
				$armor[$slot] = $item;
			}
		}

		return $armor;
	}

	/**
	 * @param mixed $raw
	 *
	 * @return array{array<int, \pocketmine\item\Item>, \pocketmine\item\Item[], \pocketmine\item\Item|null}
	 */
	private static function parseItems($raw) : array{
		$hotbar = [];
		$extra = [];
		$filler = null;

		if(!is_array($raw)){
			return [$hotbar, $extra, $filler];
		}

		foreach($raw as $entry){
			if(!is_array($entry)){
				continue;
			}
			$item = ItemParser::parse($entry);
			if($item === null){
				continue;
			}

			if(($entry["fill"] ?? false) === true){
				$filler = $item;
				continue;
			}

			$slot = $entry["slot"] ?? null;
			if(is_int($slot) && $slot >= 0){
				$hotbar[$slot] = $item;
			}else{
				$extra[] = $item;
			}
		}

		return [$hotbar, $extra, $filler];
	}

	/**
	 * @param mixed $raw
	 *
	 * @return EffectInstance[]
	 */
	private static function parseEffects($raw) : array{
		if(!is_array($raw)){
			return [];
		}

		$effects = [];
		foreach($raw as $name => $options){
			if(!is_string($name)){
				continue;
			}
			$effect = StringToEffectParser::getInstance()->parse($name);
			if($effect === null){
				continue;
			}

			$options = is_array($options) ? $options : [];
			$amplifier = is_int($options["amplifier"] ?? null) ? $options["amplifier"] : 0;
			$seconds = is_int($options["duration"] ?? null) ? $options["duration"] : self::INFINITE;
			$duration = $seconds === self::INFINITE ? Limits::INT32_MAX : $seconds * self::TICKS_PER_SECOND;

			$effects[] = new EffectInstance($effect, $duration, $amplifier, false);
		}

		return $effects;
	}
}
