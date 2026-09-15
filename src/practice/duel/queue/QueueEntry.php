<?php

declare(strict_types=1);

namespace practice\duel\queue;

/**
 * One player waiting for a match.
 *
 * Holds only plain values — the key that identifies the player, the ladder,
 * his rating and when he joined — so the matchmaker can be reasoned about and
 * tested without a server or a single connected player.
 */
final class QueueEntry{

	public function __construct(
		private readonly int $key,
		private readonly string $ladderId,
		private readonly bool $ranked,
		private readonly int $elo,
		private readonly int $joinedAt
	){
	}

	public function getKey() : int{
		return $this->key;
	}

	public function getLadderId() : string{
		return $this->ladderId;
	}

	public function isRanked() : bool{
		return $this->ranked;
	}

	public function getElo() : int{
		return $this->elo;
	}

	/**
	 * Unix seconds.
	 */
	public function getJoinedAt() : int{
		return $this->joinedAt;
	}
}
