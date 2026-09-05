<?php

declare(strict_types=1);

namespace practice\scoreboard;

use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\RemoveObjectivePacket;
use pocketmine\network\mcpe\protocol\SetDisplayObjectivePacket;
use pocketmine\network\mcpe\protocol\SetScorePacket;
use pocketmine\network\mcpe\protocol\types\ScorePacketEntry;
use pocketmine\utils\TextFormat;
use function array_slice;
use function count;
use function str_repeat;

/**
 * Thin wrapper around the vanilla scoreboard packets.
 *
 * The display is diffed against what the client already knows, so a per-second
 * refresh only sends the lines that actually changed instead of tearing the
 * whole objective down and rebuilding it (which makes the sidebar flicker).
 */
final class Scoreboard{

	public const MAX_LINES = 15;

	public const SLOT_SIDEBAR = SetDisplayObjectivePacket::DISPLAY_SLOT_SIDEBAR;
	public const SLOT_LIST = SetDisplayObjectivePacket::DISPLAY_SLOT_LIST;
	public const SLOT_BELOW_NAME = SetDisplayObjectivePacket::DISPLAY_SLOT_BELOW_NAME;

	private const OBJECTIVE_NAME = "awpe";
	private const CRITERIA_NAME = "dummy";

	private bool $spawned = false;
	private string $title = "";
	/** @var array<int, string> line index (1 based) => text */
	private array $lines = [];

	public function __construct(
		private readonly NetworkSession $session,
		private readonly string $slot = self::SLOT_SIDEBAR,
		private readonly int $sortOrder = SetDisplayObjectivePacket::SORT_ORDER_ASCENDING
	){
	}

	public function getTitle() : string{
		return $this->title;
	}

	/**
	 * @return array<int, string>
	 */
	public function getLines() : array{
		return $this->lines;
	}

	public function isSpawned() : bool{
		return $this->spawned;
	}

	/**
	 * Displays the given title/lines, sending only what changed.
	 *
	 * @param string[] $lines any list; it is trimmed to MAX_LINES and de-duplicated
	 */
	public function display(string $title, array $lines) : void{
		$lines = self::normalise($lines);

		if(!$this->spawned || $this->title !== $title){
			//The objective has to be re-sent for the title to change.
			$this->sendObjective($title);
			$this->title = $title;
			$this->lines = [];
		}

		$changed = [];
		$removed = [];

		foreach($lines as $index => $text){
			if(($this->lines[$index] ?? null) !== $text){
				$changed[$index] = $text;
			}
		}
		foreach($this->lines as $index => $text){
			if(!isset($lines[$index])){
				$removed[$index] = $text;
			}
		}

		if(count($removed) > 0){
			$this->session->sendDataPacket(SetScorePacket::create(SetScorePacket::TYPE_REMOVE, self::buildEntries($removed)));
		}
		if(count($changed) > 0){
			//A changed line has to be removed first, otherwise the client keeps the old text.
			$stale = [];
			foreach($changed as $index => $text){
				if(isset($this->lines[$index])){
					$stale[$index] = $this->lines[$index];
				}
			}
			if(count($stale) > 0){
				$this->session->sendDataPacket(SetScorePacket::create(SetScorePacket::TYPE_REMOVE, self::buildEntries($stale)));
			}
			$this->session->sendDataPacket(SetScorePacket::create(SetScorePacket::TYPE_CHANGE, self::buildEntries($changed)));
		}

		$this->lines = $lines;
	}

	public function remove() : void{
		if(!$this->spawned){
			return;
		}

		$this->session->sendDataPacket(RemoveObjectivePacket::create(self::OBJECTIVE_NAME));
		$this->spawned = false;
		$this->title = "";
		$this->lines = [];
	}

	private function sendObjective(string $title) : void{
		if($this->spawned){
			$this->session->sendDataPacket(RemoveObjectivePacket::create(self::OBJECTIVE_NAME));
		}

		$this->session->sendDataPacket(SetDisplayObjectivePacket::create(
			$this->slot,
			self::OBJECTIVE_NAME,
			$title,
			self::CRITERIA_NAME,
			$this->sortOrder
		));
		$this->spawned = true;
	}

	/**
	 * The client identifies a sidebar row by its text, so two identical rows
	 * would collapse into one. Padding duplicates with invisible reset codes
	 * keeps blank spacer lines usable in the config.
	 *
	 * @param string[] $lines
	 *
	 * @return array<int, string>
	 */
	private static function normalise(array $lines) : array{
		$result = [];
		$used = [];
		$index = 1;

		foreach(array_slice($lines, 0, self::MAX_LINES) as $line){
			$text = $line;
			$padding = 0;
			while(isset($used[$text])){
				$text = $line . str_repeat(TextFormat::RESET, ++$padding);
			}
			$used[$text] = true;
			$result[$index++] = $text;
		}

		return $result;
	}

	/**
	 * @param array<int, string> $lines
	 *
	 * @return ScorePacketEntry[]
	 */
	private static function buildEntries(array $lines) : array{
		$entries = [];
		foreach($lines as $index => $text){
			$entry = new ScorePacketEntry();
			$entry->objectiveName = self::OBJECTIVE_NAME;
			$entry->type = ScorePacketEntry::TYPE_FAKE_PLAYER;
			$entry->customName = $text;
			$entry->score = $index;
			$entry->scoreboardId = $index;
			$entries[] = $entry;
		}

		return $entries;
	}
}
