<?php

declare(strict_types=1);

namespace practice\command\punishment;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use practice\PracticeConfig;
use practice\punishment\Punishment;
use practice\punishment\PunishmentManager;
use practice\util\Placeholder;
use function array_slice;
use function ceil;
use function count;
use function is_numeric;
use function max;
use function min;
use function time;

/**
 * /history <player> [page] — every punishment a player has received, newest
 * first, including the ones that ran out or were lifted.
 */
final class HistoryCommand extends Command{

	public const NAME = "history";
	public const PERMISSION = "awpe.command.history";

	public function __construct(
		private readonly PracticeConfig $config,
		private readonly PunishmentManager $punishments
	){
		parent::__construct(self::NAME, "Show a player's punishment history");
		$this->setPermission(self::PERMISSION);
		$this->setUsage($config->getMessage("usage-" . self::NAME));
		$this->setAliases(["punishments"]);
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args) : void{
		if(!$this->testPermission($sender)){
			return;
		}

		if(count($args) === 0){
			$sender->sendMessage($this->getUsage());
			return;
		}

		$targetName = $this->punishments->resolveName($args[0]);
		$history = $this->punishments->getHistory($targetName);
		if(count($history) === 0){
			$this->send($sender, "punish-history-empty", ["player" => $targetName]);
			return;
		}

		$pageSize = max(1, $this->config->getInt("punishments.history-page-size", 8));
		$pages = (int) ceil(count($history) / $pageSize);
		$page = isset($args[1]) && is_numeric($args[1]) ? (int) $args[1] : 1;
		$page = min(max(1, $page), $pages);

		$this->send($sender, "punish-history-header", [
			"player" => $targetName,
			"count" => count($history),
			"page" => $page,
			"pages" => $pages
		]);

		$now = time();
		foreach(array_slice($history, ($page - 1) * $pageSize, $pageSize) as $punishment){
			$line = $this->punishments->format("punish-history-entry", $punishment);
			$sender->sendMessage($line . $this->status($punishment, $now));
		}
	}

	/**
	 * Where a record stands today: still biting, run out, or lifted.
	 */
	private function status(Punishment $punishment, int $now) : string{
		$key = match(true){
			!$punishment->getType()->isLasting() => null,
			$punishment->isRevoked() => "punish-status-revoked",
			$punishment->isActive($now) => "punish-status-active",
			default => "punish-status-expired"
		};

		return $key === null ? "" : $this->punishments->format($key, $punishment);
	}

	/**
	 * @param array<string, string|int> $values
	 */
	private function send(CommandSender $sender, string $key, array $values) : void{
		$message = $this->config->getMessage($key);
		if($message !== ""){
			$sender->sendMessage(Placeholder::apply($message, $values));
		}
	}
}
