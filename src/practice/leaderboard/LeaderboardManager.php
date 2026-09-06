<?php

declare(strict_types=1);

namespace practice\leaderboard;

use pocketmine\player\Player;
use pocketmine\Server;
use practice\data\DataProvider;
use practice\PracticeConfig;
use practice\util\Placeholder;
use function count;
use function implode;
use function is_array;
use function is_string;

/**
 * Builds and shows the lobby leaderboards.
 *
 * The ranking is read from storage rather than from online sessions, so a board
 * shows the whole server's history and not just who happens to be connected.
 */
final class LeaderboardManager{

	/** @var array<string, Leaderboard> */
	private array $boards = [];

	public function __construct(
		private readonly Server $server,
		private readonly PracticeConfig $config,
		private readonly DataProvider $data
	){
	}

	/**
	 * @return string[] ids that could not be loaded
	 */
	public function loadFromConfig(string $defaultWorld) : array{
		$this->boards = [];
		$failed = [];

		foreach($this->config->getArray("leaderboards.boards") as $id => $entry){
			if(!is_string($id) || !is_array($entry)){
				$failed[] = (string) $id;
				continue;
			}

			$board = Leaderboard::fromConfig($id, $entry, $defaultWorld);
			$world = $this->server->getWorldManager()->getWorldByName($board->getWorldName());
			if($world === null){
				$failed[] = $id;
				continue;
			}

			$board->setWorld($world);
			$this->boards[$id] = $board;
		}

		return $failed;
	}

	public function isEnabled() : bool{
		return $this->config->getBool("leaderboards.enabled", true) && count($this->boards) > 0;
	}

	/**
	 * @return array<string, Leaderboard>
	 */
	public function all() : array{
		return $this->boards;
	}

	/**
	 * Rebuilds every board's text and pushes it to the players who can see it.
	 */
	public function refresh() : void{
		if(!$this->isEnabled()){
			return;
		}

		foreach($this->boards as $board){
			$board->getParticle()->setText($this->render($board));
			$this->show($board);
		}
	}

	/**
	 * Sends the current boards to one player, for when he first arrives.
	 */
	public function sendTo(Player $player) : void{
		if(!$this->isEnabled()){
			return;
		}

		foreach($this->boards as $board){
			$world = $board->getWorld();
			if($world === null || $player->getWorld()->getId() !== $world->getId()){
				continue;
			}

			$board->getParticle()->setText($this->render($board));
			$world->addParticle($board->getPosition(), $board->getParticle(), [$player]);
		}
	}

	private function show(Leaderboard $board) : void{
		$world = $board->getWorld();
		if($world === null){
			return;
		}

		$viewers = $world->getPlayers();
		if(count($viewers) === 0){
			return;
		}

		$world->addParticle($board->getPosition(), $board->getParticle(), $viewers);
	}

	private function render(Leaderboard $board) : string{
		$lines = [$board->getTitle()];

		$top = $this->data->getTop($board->getField(), $board->getLimit());
		if(count($top) === 0){
			$lines[] = $board->getEmptyText();

			return implode("\n", $lines);
		}

		$position = 1;
		foreach($top as $name => $value){
			$lines[] = Placeholder::apply($board->getEntryFormat(), [
				"position" => $position++,
				"player" => $name,
				"value" => $value
			]);
		}

		return implode("\n", $lines);
	}
}
