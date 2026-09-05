<?php

declare(strict_types=1);

namespace practice\combat;

use pocketmine\player\Player;
use pocketmine\Server;
use function ceil;
use function max;
use function spl_object_id;

/**
 * Tracks who is "in combat" and for how much longer.
 *
 * A player is tagged on every hit he lands or takes; the tag is what the
 * command block (combat log) and the {combat} placeholder read.
 */
final class CombatManager{

	private const TICKS_PER_SECOND = 20;

	/** @var array<int, int> player object id => expiry tick */
	private array $expiry = [];

	public function __construct(
		private readonly Server $server,
		private readonly int $durationTicks
	){
	}

	public function getDurationTicks() : int{
		return $this->durationTicks;
	}

	/**
	 * Tags both sides of an exchange. Passing null for the opponent covers
	 * damage that has no player behind it.
	 */
	public function tag(Player $player, ?Player $opponent = null) : void{
		if($this->durationTicks <= 0){
			return;
		}

		$until = $this->server->getTick() + $this->durationTicks;
		$this->expiry[spl_object_id($player)] = $until;
		if($opponent !== null && $opponent !== $player){
			$this->expiry[spl_object_id($opponent)] = $until;
		}
	}

	public function isTagged(Player $player) : bool{
		return $this->getRemainingTicks($player) > 0;
	}

	public function getRemainingTicks(Player $player) : int{
		return max(0, ($this->expiry[spl_object_id($player)] ?? 0) - $this->server->getTick());
	}

	public function getRemainingSeconds(Player $player) : int{
		return (int) ceil($this->getRemainingTicks($player) / self::TICKS_PER_SECOND);
	}

	public function untag(Player $player) : void{
		unset($this->expiry[spl_object_id($player)]);
	}
}
