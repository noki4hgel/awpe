<?php

declare(strict_types=1);

namespace practice\combat;

use pocketmine\player\Player;
use pocketmine\Server;
use function array_filter;
use function array_values;
use function count;
use function spl_object_id;

/**
 * Counts clicks per player for the CPS read-out.
 *
 * A click is any swing — a hit that landed or one that missed — timestamped in
 * ticks and counted over a one second window, which is what "CPS" means to a
 * player watching the number.
 */
final class ClickTracker{

	private const WINDOW_TICKS = 20;

	/** @var array<int, list<int>> player object id => ticks on which he swung */
	private array $clicks = [];

	public function __construct(private readonly Server $server){
	}

	public function record(Player $player) : void{
		$id = spl_object_id($player);
		$this->clicks[$id][] = $this->server->getTick();
		$this->prune($id);
	}

	public function getClicksPerSecond(Player $player) : int{
		$id = spl_object_id($player);
		$this->prune($id);

		return count($this->clicks[$id] ?? []);
	}

	public function clear(Player $player) : void{
		unset($this->clicks[spl_object_id($player)]);
	}

	private function prune(int $id) : void{
		$oldest = $this->server->getTick() - self::WINDOW_TICKS;
		$recorded = $this->clicks[$id] ?? null;
		if($recorded === null){
			return;
		}

		$this->clicks[$id] = array_values(array_filter($recorded, static fn(int $tick) => $tick > $oldest));
	}
}
