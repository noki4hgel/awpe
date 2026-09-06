<?php

declare(strict_types=1);

namespace practice\data;

/**
 * Storage backend for player records.
 *
 * Names are treated case-insensitively by every implementation, so callers may
 * pass a player's display name directly.
 */
interface DataProvider{

	public function load(string $playerName) : PlayerData;

	public function save(string $playerName, PlayerData $data) : void;

	/**
	 * Players with the highest value of a numeric field, best first.
	 *
	 * Used by the lobby leaderboards; entries with a value of zero are skipped
	 * so an empty database produces an empty board rather than a list of nobodies.
	 *
	 * @return array<string, int> display name => value
	 */
	public function getTop(string $field, int $limit) : array;

	/**
	 * Flushes anything still buffered and releases the backend.
	 */
	public function close() : void;
}
