<?php

declare(strict_types=1);

namespace practice\duel\match;

use function max;

/**
 * What one player did in a match, for the summary at the end.
 *
 * Only numbers that mean exactly what they say: hits landed, damage dealt,
 * longest run of hits without being hit back, and splash potions thrown.
 */
final class MatchStats{

	private int $hits = 0;
	private float $damage = 0.0;
	private int $combo = 0;
	private int $longestCombo = 0;
	private int $potsThrown = 0;

	public function recordHit(float $damage) : void{
		$this->hits++;
		$this->damage += max(0.0, $damage);
		$this->combo++;
		$this->longestCombo = max($this->longestCombo, $this->combo);
	}

	/**
	 * Being hit ends the run.
	 */
	public function breakCombo() : void{
		$this->combo = 0;
	}

	public function recordPotThrown() : void{
		$this->potsThrown++;
	}

	public function getHits() : int{
		return $this->hits;
	}

	public function getDamage() : float{
		return $this->damage;
	}

	public function getLongestCombo() : int{
		return $this->longestCombo;
	}

	public function getPotsThrown() : int{
		return $this->potsThrown;
	}
}
