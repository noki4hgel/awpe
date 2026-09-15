<?php

declare(strict_types=1);

namespace practice\item\types;

use practice\duel\queue\QueueManager;
use practice\item\LobbyItemHandler;
use practice\session\Session;

/**
 * The item a queued player holds — leaves the queue.
 */
final class LeaveQueueHandler implements LobbyItemHandler{

	public const ACTION_ID = "leave-queue";

	public function __construct(private readonly QueueManager $queue){
	}

	public function getActionId() : string{
		return self::ACTION_ID;
	}

	public function onUse(Session $session) : void{
		$this->queue->leave($session);
	}
}
