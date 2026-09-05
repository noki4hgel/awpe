<?php

declare(strict_types=1);

namespace practice\stats;

/**
 * Storage backend for player counters.
 *
 * Names are treated case-insensitively by every implementation, so callers may
 * pass a player's display name directly.
 */
interface StatsProvider{

	public function load(string $playerName) : PlayerStats;

	public function save(string $playerName, PlayerStats $stats) : void;

	/**
	 * Flushes anything still buffered and releases the backend.
	 */
	public function close() : void;
}
