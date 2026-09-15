<?php

declare(strict_types=1);

namespace practice\command\punishment;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use practice\PracticeConfig;
use practice\punishment\PunishmentManager;
use practice\punishment\PunishmentType;
use practice\util\Placeholder;
use function array_slice;
use function count;
use function implode;
use function trim;

/**
 * Shared body of /unban and /unmute: lifts the active punishment of one kind.
 *
 *   <player> [reason...]
 *
 * The record stays in the history, marked with who lifted it and why. Works for
 * players who are offline — that is the usual case for a ban.
 */
abstract class RevokeCommand extends Command{

	public function __construct(
		string $name,
		string $description,
		string $permission,
		private readonly PunishmentType $type,
		private readonly PracticeConfig $config,
		private readonly PunishmentManager $punishments
	){
		parent::__construct($name, $description);
		$this->setPermission($permission);
		$this->setUsage($config->getMessage("usage-" . $name));
	}

	final public function execute(CommandSender $sender, string $commandLabel, array $args) : void{
		if(!$this->testPermission($sender)){
			return;
		}

		if(count($args) === 0){
			$sender->sendMessage($this->getUsage());
			return;
		}

		$targetName = $this->punishments->resolveName($args[0]);
		$reason = trim(implode(" ", array_slice($args, 1)));
		if($reason === ""){
			$reason = $this->config->getString("punishments.words.no-reason", "-");
		}

		if(!$this->punishments->canPunish($sender, $targetName)){
			$this->send($sender, "punish-outranked", ["player" => $targetName]);
			return;
		}

		$revoked = $this->punishments->revoke($this->type, $targetName, $sender->getName(), $reason);
		if($revoked === null){
			$this->send($sender, "punish-not-active", [
				"player" => $targetName,
				"type" => $this->config->getString("punishments.types." . $this->type->value, $this->type->value)
			]);
			return;
		}

		if(!$sender->hasPermission(PunishmentManager::PERMISSION_NOTIFY)){
			$sender->sendMessage($this->punishments->format("punish-revoked", $revoked));
		}
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
