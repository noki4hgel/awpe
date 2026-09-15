<?php

declare(strict_types=1);

namespace practice\duel;

use pocketmine\item\Item;
use practice\ffa\KnockbackProfile;
use practice\kit\Kit;
use practice\kit\KitRegistry;
use practice\util\ItemParser;
use function is_array;
use function is_numeric;
use function is_string;
use function max;

/**
 * A duel mode: which kit, how hits feel, and the few rules that set a mode
 * apart from plain "fight until one dies".
 *
 * Everything comes from duels.ladders in config.yml. A mode is a set of values,
 * not a subclass — a NoDebuff class with nothing in it is how the list of
 * modes and the list of classes drift out of step.
 */
final class Ladder{

	public function __construct(
		private readonly string $id,
		private readonly string $display,
		private readonly string $icon,
		private readonly Kit $kit,
		private readonly KnockbackProfile $knockback,
		private readonly bool $rankedAllowed,
		private readonly bool $noDamage,
		private readonly ?float $fallDepth,
		private readonly ?Item $potItem
	){
	}

	/**
	 * @param array<string, mixed> $data
	 *
	 * @return self|null null when the kit it names does not exist
	 */
	public static function fromConfig(string $id, array $data, KitRegistry $kits, KnockbackProfile $defaultKnockback, string $defaultPotItem) : ?self{
		$kit = $kits->get(is_string($data["kit"] ?? null) ? $data["kit"] : $id);
		if($kit === null){
			return null;
		}

		$rules = is_array($data["rules"] ?? null) ? $data["rules"] : [];
		$potName = is_string($data["pot-item"] ?? null) ? $data["pot-item"] : $defaultPotItem;

		return new self(
			$id,
			is_string($data["display"] ?? null) ? $data["display"] : $id,
			is_string($data["icon"] ?? null) ? $data["icon"] : "",
			$kit,
			KnockbackProfile::fromConfig(is_array($data["knockback"] ?? null) ? $data["knockback"] : [], $defaultKnockback),
			($data["ranked"] ?? true) === true,
			($rules["no-damage"] ?? false) === true,
			is_numeric($rules["fall-depth"] ?? null) ? max(0.0, (float) $rules["fall-depth"]) : null,
			$potName === "" ? null : ItemParser::parse(["item" => $potName])
		);
	}

	public function getId() : string{
		return $this->id;
	}

	public function getDisplay() : string{
		return $this->display;
	}

	public function getIcon() : string{
		return $this->icon;
	}

	public function getKit() : Kit{
		return $this->kit;
	}

	public function getKnockback() : KnockbackProfile{
		return $this->knockback;
	}

	public function isRankedAllowed() : bool{
		return $this->rankedAllowed;
	}

	/**
	 * Hits still push but never hurt — Sumo, where the only way to lose is to
	 * be knocked off the platform.
	 */
	public function hasNoDamage() : bool{
		return $this->noDamage;
	}

	/**
	 * How far below the lower spawn point a player may drop before it counts as
	 * falling off. Null for modes that end on death alone.
	 */
	public function getFallDepth() : ?float{
		return $this->fallDepth;
	}

	/**
	 * The item counted as "pots left" in the match summary; null when the mode
	 * has none.
	 */
	public function getPotItem() : ?Item{
		return $this->potItem;
	}
}
