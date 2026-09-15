<?php

declare(strict_types=1);

namespace practice\task;

use pocketmine\scheduler\Task;
use practice\duel\invite\InviteManager;
use practice\duel\match\MatchManager;
use practice\duel\queue\QueueManager;

/**
 * The duel clock: once a second it advances every match, pairs the queues and
 * drops expired invites.
 *
 * One task for all of it, rather than a task per match — a match that ends
 * cannot leave its own timer running behind it.
 */
final class DuelTask extends Task{

	public const INTERVAL_TICKS = 20;

	public function __construct(
		private readonly MatchManager $matches,
		private readonly QueueManager $queue,
		private readonly InviteManager $invites
	){
	}

	public function onRun() : void{
		$this->matches->tick();
		$this->queue->tick();
		$this->invites->expire();
	}
}
