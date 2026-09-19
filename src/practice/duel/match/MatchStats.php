<?php

declare(strict_types=1);

namespace practice\duel\match;

use function max;
use function min;

/**
 * What one player did in a match, for the summary at the end.
 *
 * Only numbers that mean exactly what they say: hits landed, damage dealt,
 * longest run of hits without being hit back, critical hits, and how the
 * player's own potting went.
 *
 * Potion accuracy is the average share of a potion the thrower gave himself,
 * over every pot he threw - a pot at his feet counts in full, one that lands
 * behind him counts for nothing. A count of hits and misses would hide exactly
 * the difference that matters between two players who never miss outright.
 */
final class MatchStats{

	private int $hits = 0;
	private float $damage = 0.0;
	private int $combo = 0;
	private int $longestCombo = 0;
	private int $potsThrown = 0;
	private int $criticals = 0;
	private float $potency = 0.0;

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

	public function recordCritical() : void{
		$this->criticals++;
	}

	public function recordPotThrown() : void{
		$this->potsThrown++;
	}

	/**
	 * @param float $potency 0.0 - 1.0, what the thrower got out of his own pot
	 */
	public function recordPotLanded(float $potency) : void{
		$this->potency += min(1.0, max(0.0, $potency));
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

	public function getCriticals() : int{
		return $this->criticals;
	}

	/**
	 * Percent, 0 when nothing was thrown.
	 */
	public function getPotionAccuracy() : float{
		return $this->potsThrown === 0 ? 0.0 : $this->potency / $this->potsThrown * 100;
	}
}
