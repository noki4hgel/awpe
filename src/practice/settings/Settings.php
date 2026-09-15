<?php

declare(strict_types=1);

namespace practice\settings;

/**
 * Ids of the settings the code reacts to.
 *
 * Their names, descriptions, defaults and which section they appear in all come
 * from config.yml — these constants only tie a stored value to the behaviour it
 * controls, so a typo cannot silently disable a feature.
 */
final class Settings{

	/** Re-enter the arena straight after dying instead of returning to the lobby. */
	public const AUTO_RESPAWN = "auto-respawn";
	/** Action bar showing the reach of every hit. */
	public const HIT_DISTANCE = "hit-distance";
	/** Sidebar. */
	public const SCOREBOARD = "scoreboard";
	/** Boss bar at the top of the screen. */
	public const BOSS_BAR = "bossbar";
	/** Ping / device / health line under your own name. */
	public const NAMETAG = "nametag";
	/** Clicks-per-second read-out. */
	public const CPS_COUNTER = "cps-counter";
	/** Lightning strike where an opponent dies. */
	public const LIGHTNING_ON_KILL = "lightning-on-kill";
	/** Big "KILL!" banner after taking somebody down. */
	public const KILL_TITLE = "kill-title";
	/** Accept duel challenges from other players. */
	public const DUEL_REQUESTS = "duel-requests";

	private function __construct(){
		//NOOP
	}
}
