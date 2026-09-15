<?php

declare(strict_types=1);

namespace practice\duel\invite;

use practice\session\Session;

/**
 * A challenge from one player to another on a ladder.
 */
final class Invite{

	public function __construct(
		private readonly Session $from,
		private readonly Session $to,
		private readonly string $ladderId,
		private readonly int $sentAt
	){
	}

	public function getFrom() : Session{
		return $this->from;
	}

	public function getTo() : Session{
		return $this->to;
	}

	public function getLadderId() : string{
		return $this->ladderId;
	}

	public function getSentAt() : int{
		return $this->sentAt;
	}

	public function isExpired(int $now, int $timeout) : bool{
		return $now - $this->sentAt >= $timeout;
	}
}
