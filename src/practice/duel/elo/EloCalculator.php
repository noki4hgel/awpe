<?php

declare(strict_types=1);

namespace practice\duel\elo;

use function max;
use function round;

/**
 * Standard Elo: a 400-point gap means the favourite is expected to win about
 * ten times out of eleven, and the rating change scales with how surprising
 * the result was.
 *
 * The change is zero-sum — what the winner gains the loser gives up — so the
 * ladder cannot inflate over time.
 */
final class EloCalculator{

	private const SCALE = 400;

	public function __construct(
		private readonly int $kFactor = 32,
		private readonly int $minimumChange = 1
	){
	}

	/**
	 * The probability, 0 to 1, that a player rated $elo beats one rated $opponent.
	 */
	public function expectedScore(int $elo, int $opponent) : float{
		return 1 / (1 + 10 ** (($opponent - $elo) / self::SCALE));
	}

	/**
	 * Points moved from the loser to the winner. Never below the minimum, so
	 * beating a much weaker player is still worth something.
	 */
	public function change(int $winnerElo, int $loserElo) : int{
		$surprise = 1 - $this->expectedScore($winnerElo, $loserElo);

		return max($this->minimumChange, (int) round($this->kFactor * $surprise));
	}
}
