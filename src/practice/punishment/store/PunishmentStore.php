<?php

declare(strict_types=1);

namespace practice\punishment\store;

use practice\punishment\Punishment;

/**
 * Storage for punishments and the device links used to catch alternate
 * accounts.
 *
 * Player names are treated case-insensitively by every implementation.
 */
interface PunishmentStore{

	/**
	 * Reserves the next punishment id. Ids are small sequential numbers so a
	 * player can quote one in an appeal.
	 */
	public function nextId() : int;

	/**
	 * Inserts a new record or replaces an existing one with the same id.
	 */
	public function save(Punishment $punishment) : void;

	public function get(int $id) : ?Punishment;

	/**
	 * Every punishment a player has ever received, newest first.
	 *
	 * @return Punishment[]
	 */
	public function getHistory(string $playerName) : array;

	/**
	 * Remembers that a player has logged in from a device.
	 */
	public function linkDevice(string $playerName, string $deviceId) : void;

	/**
	 * Lowercase names of every player seen on a device.
	 *
	 * @return string[]
	 */
	public function getNamesOnDevice(string $deviceId) : array;

	public function close() : void;
}
