<?php

declare(strict_types=1);

namespace practice\cooldown;

use pocketmine\player\Player;
use pocketmine\Server;
use function ceil;
use function max;
use function min;
use function spl_object_id;

/**
 * Named per-player cooldowns, measured in server ticks.
 *
 * The core keeps its own timers rather than relying on the client-side item
 * cooldown, because that one is reset by the vanilla use handler and cannot be
 * queried for anything other than "is it running".
 *
 * The full duration is kept alongside the deadline so a progress display can
 * tell how much of the wait is left, not just whether it is over.
 */
final class CooldownManager{

	private const TICKS_PER_SECOND = 20;

	/**
	 * @var array<int, array<string, array{expiry: int, total: int}>>
	 * @phpstan-var array<int, array<string, array{expiry: int, total: int}>> player object id => tag => timer
	 */
	private array $timers = [];

	public function __construct(private readonly Server $server){
	}

	public function set(Player $player, string $tag, int $ticks) : void{
		if($ticks <= 0){
			return;
		}

		$this->timers[spl_object_id($player)][$tag] = [
			"expiry" => $this->server->getTick() + $ticks,
			"total" => $ticks
		];
	}

	public function has(Player $player, string $tag) : bool{
		return $this->getRemainingTicks($player, $tag) > 0;
	}

	public function getRemainingTicks(Player $player, string $tag) : int{
		$timer = $this->timers[spl_object_id($player)][$tag] ?? null;

		return $timer === null ? 0 : max(0, $timer["expiry"] - $this->server->getTick());
	}

	public function getRemainingSeconds(Player $player, string $tag) : int{
		return (int) ceil($this->getRemainingTicks($player, $tag) / self::TICKS_PER_SECOND);
	}

	public function getTotalTicks(Player $player, string $tag) : int{
		return $this->timers[spl_object_id($player)][$tag]["total"] ?? 0;
	}

	/**
	 * How much of the wait is still ahead, as 1.0 down to 0.0.
	 */
	public function getRemainingFraction(Player $player, string $tag) : float{
		$total = $this->getTotalTicks($player, $tag);
		if($total <= 0){
			return 0.0;
		}

		return min(1.0, max(0.0, $this->getRemainingTicks($player, $tag) / $total));
	}

	/**
	 * Every timer still running for a player, tag => ticks left.
	 *
	 * A player can be waiting on several things at once and not all of them are
	 * worth showing, so the caller decides which ones it cares about.
	 *
	 * @return array<string, int>
	 */
	public function getRunningTags(Player $player) : array{
		$running = [];

		foreach($this->timers[spl_object_id($player)] ?? [] as $tag => $timer){
			$left = max(0, $timer["expiry"] - $this->server->getTick());
			if($left > 0){
				$running[$tag] = $left;
			}
		}

		return $running;
	}

	public function clear(Player $player, ?string $tag = null) : void{
		$id = spl_object_id($player);
		if($tag === null){
			unset($this->timers[$id]);
			return;
		}

		unset($this->timers[$id][$tag]);
	}
}
