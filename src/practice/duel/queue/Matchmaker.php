<?php

declare(strict_types=1);

namespace practice\duel\queue;

use function abs;
use function count;
use function intdiv;
use function max;
use function min;
use function usort;

/**
 * Decides who plays whom.
 *
 * Unranked queues pair in arrival order. Ranked queues pair players whose
 * ratings are close, where "close" starts tight and widens the longer a player
 * waits — a fixed window never widening is how a ranked queue with a handful of
 * players ends up matching nobody at all.
 *
 * A pair needs both players to accept each other: the gap has to fit inside the
 * narrower of their two windows, so a fresh high-rated player is not dragged
 * into a mismatch just because his opponent has waited a long time.
 */
final class Matchmaker{

	public function __construct(
		private readonly int $windowStart = 50,
		private readonly int $windowGrowth = 25,
		private readonly int $growthEverySeconds = 5,
		private readonly int $windowMax = 400
	){
	}

	/**
	 * How far from his own rating a player accepts an opponent after waiting.
	 */
	public function windowFor(QueueEntry $entry, int $now) : int{
		$waited = max(0, $now - $entry->getJoinedAt());
		$steps = $this->growthEverySeconds > 0 ? intdiv($waited, $this->growthEverySeconds) : 0;

		return min($this->windowMax, $this->windowStart + $steps * $this->windowGrowth);
	}

	/**
	 * Every pair that can be made right now. Nobody appears in two pairs.
	 *
	 * @param QueueEntry[] $entries
	 *
	 * @return list<array{QueueEntry, QueueEntry}>
	 */
	public function pair(array $entries, int $now) : array{
		$buckets = [];
		foreach($entries as $entry){
			$buckets[$entry->getLadderId() . ($entry->isRanked() ? ":ranked" : ":unranked")][] = $entry;
		}

		$pairs = [];
		foreach($buckets as $bucket){
			usort($bucket, static fn(QueueEntry $a, QueueEntry $b) => $a->getJoinedAt() <=> $b->getJoinedAt());
			foreach($bucket[0]->isRanked() ? $this->pairRanked($bucket, $now) : $this->pairInOrder($bucket) as $pair){
				$pairs[] = $pair;
			}
		}

		return $pairs;
	}

	/**
	 * @param list<QueueEntry> $bucket oldest first
	 *
	 * @return list<array{QueueEntry, QueueEntry}>
	 */
	private function pairInOrder(array $bucket) : array{
		$pairs = [];
		for($i = 0; $i + 1 < count($bucket); $i += 2){
			$pairs[] = [$bucket[$i], $bucket[$i + 1]];
		}

		return $pairs;
	}

	/**
	 * Oldest player first picks the closest-rated opponent that accepts him.
	 *
	 * @param list<QueueEntry> $bucket oldest first
	 *
	 * @return list<array{QueueEntry, QueueEntry}>
	 */
	private function pairRanked(array $bucket, int $now) : array{
		$taken = [];
		$pairs = [];

		foreach($bucket as $i => $player){
			if(isset($taken[$i])){
				continue;
			}

			$best = null;
			$bestGap = null;
			foreach($bucket as $j => $candidate){
				if($j === $i || isset($taken[$j])){
					continue;
				}

				$gap = abs($player->getElo() - $candidate->getElo());
				if($gap > min($this->windowFor($player, $now), $this->windowFor($candidate, $now))){
					continue;
				}

				//Closest rating wins; on a tie the earlier arrival, which usort already put first.
				if($bestGap === null || $gap < $bestGap){
					$best = $j;
					$bestGap = $gap;
				}
			}

			if($best !== null){
				$taken[$i] = true;
				$taken[$best] = true;
				$pairs[] = [$player, $bucket[$best]];
			}
		}

		return $pairs;
	}
}
