<?php

declare(strict_types=1);

namespace practice\cooldown;

use pocketmine\player\Player;
use pocketmine\Server;
use function ceil;
use function max;
use function spl_object_id;

/**
 * Named per-player cooldowns, measured in server ticks.
 *
 * The core keeps its own timers rather than relying on the client-side item
 * cooldown, because that one is reset by the vanilla use handler and cannot be
 * queried for anything other than "is it running".
 */
final class CooldownManager{

	private const TICKS_PER_SECOND = 20;

	/** @var array<int, array<string, int>> player object id => tag => expiry tick */
	private array $expiry = [];

	public function __construct(private readonly Server $server){
	}

	public function set(Player $player, string $tag, int $ticks) : void{
		if($ticks <= 0){
			return;
		}

		$this->expiry[spl_object_id($player)][$tag] = $this->server->getTick() + $ticks;
	}

	public function has(Player $player, string $tag) : bool{
		return $this->getRemainingTicks($player, $tag) > 0;
	}

	public function getRemainingTicks(Player $player, string $tag) : int{
		$expiry = $this->expiry[spl_object_id($player)][$tag] ?? 0;

		return max(0, $expiry - $this->server->getTick());
	}

	public function getRemainingSeconds(Player $player, string $tag) : int{
		return (int) ceil($this->getRemainingTicks($player, $tag) / self::TICKS_PER_SECOND);
	}

	public function clear(Player $player, ?string $tag = null) : void{
		$id = spl_object_id($player);
		if($tag === null){
			unset($this->expiry[$id]);
			return;
		}

		unset($this->expiry[$id][$tag]);
	}
}
