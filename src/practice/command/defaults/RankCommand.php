<?php

declare(strict_types=1);

namespace practice\command\defaults;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\Server;
use practice\PracticeConfig;
use practice\rank\Rank;
use practice\rank\RankManager;
use practice\session\SessionManager;
use practice\util\Placeholder;
use function count;
use function implode;

/**
 * /rank — reads and assigns ranks.
 *
 *   /rank <player>          show a player's rank
 *   /rank <player> <rank>   assign one
 *
 * A player may not hand out a rank above his own; the console always can.
 */
final class RankCommand extends Command{

	public const NAME = "rank";
	public const PERMISSION = "awpe.command.rank";

	public function __construct(
		private readonly PracticeConfig $config,
		private readonly SessionManager $sessions,
		private readonly RankManager $ranks,
		private readonly Server $server
	){
		parent::__construct(self::NAME, "Read or assign a player's rank");
		$this->setPermission(self::PERMISSION);
		$this->setUsage("/rank <player> [rank]");
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args) : void{
		if(!$this->testPermission($sender)){
			return;
		}

		if(count($args) === 0){
			$sender->sendMessage($this->config->getMessage("rank-usage"));
			$this->send($sender, "rank-available", ["ranks" => implode(", ", $this->ranks->getRegistry()->ids())]);
			return;
		}

		$target = $this->server->getPlayerByPrefix($args[0]);
		if($target === null){
			$this->send($sender, "rank-player-offline", ["player" => $args[0]]);
			return;
		}

		$targetSession = $this->sessions->get($target);
		if($targetSession === null){
			return;
		}

		if(count($args) === 1){
			$current = $this->ranks->get($targetSession);
			$this->send($sender, "rank-show", [
				"player" => $target->getName(),
				"rank" => $current?->getDisplay() ?? "-"
			]);
			return;
		}

		$rank = $this->ranks->getRegistry()->get($args[1]);
		if($rank === null){
			$this->send($sender, "rank-unknown", [
				"rank" => $args[1],
				"ranks" => implode(", ", $this->ranks->getRegistry()->ids())
			]);
			return;
		}

		if(!$this->mayGrant($sender, $rank)){
			$this->send($sender, "rank-too-high", ["rank" => $rank->getDisplay()]);
			return;
		}

		$this->ranks->set($targetSession, $rank);

		$this->send($sender, "rank-updated", ["player" => $target->getName(), "rank" => $rank->getDisplay()]);
		if($target !== $sender){
			$this->send($target, "rank-received", ["rank" => $rank->getDisplay()]);
		}
	}

	/**
	 * Staff can only hand out ranks strictly below their own; the console and
	 * operators are not restricted.
	 */
	private function mayGrant(CommandSender $sender, Rank $rank) : bool{
		if(!$sender instanceof Player || $this->server->isOp($sender->getName())){
			return true;
		}

		$senderSession = $this->sessions->get($sender);
		$senderRank = $senderSession === null ? null : $this->ranks->get($senderSession);

		return $senderRank !== null && $senderRank->outranks($rank);
	}

	/**
	 * @param array<string, string|int|float> $values
	 */
	private function send(CommandSender $sender, string $key, array $values) : void{
		$message = $this->config->getMessage($key);
		if($message !== ""){
			$sender->sendMessage(Placeholder::apply($message, $values));
		}
	}
}
