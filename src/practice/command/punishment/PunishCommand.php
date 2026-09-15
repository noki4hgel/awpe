<?php

declare(strict_types=1);

namespace practice\command\punishment;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use practice\PracticeConfig;
use practice\punishment\PunishmentManager;
use practice\punishment\PunishmentType;
use practice\util\Placeholder;
use function strtolower;

/**
 * Shared body of /ban, /mute and /kick.
 *
 * Every refusal says why — a staff member should never be left wondering
 * whether his command did anything.
 */
abstract class PunishCommand extends Command{

	public function __construct(
		string $name,
		string $description,
		string $permission,
		private readonly PunishmentType $type,
		protected readonly PracticeConfig $config,
		protected readonly PunishmentManager $punishments
	){
		parent::__construct($name, $description);
		$this->setPermission($permission);
		$this->setUsage($config->getMessage("usage-" . $name));
	}

	final public function execute(CommandSender $sender, string $commandLabel, array $args) : void{
		if(!$this->testPermission($sender)){
			return;
		}

		$input = PunishmentInput::parse($args, $this->type->isLasting());
		if($input === null){
			$sender->sendMessage($this->getUsage());
			return;
		}

		if($input->getReason() === ""){
			$sender->sendMessage($this->config->getMessage("punish-no-reason"));
			$sender->sendMessage($this->getUsage());
			return;
		}

		$targetName = $this->punishments->resolveName($input->getTarget());
		$values = ["player" => $targetName];

		if($sender instanceof Player && strtolower($sender->getName()) === strtolower($targetName)){
			$this->send($sender, "punish-self", $values);
			return;
		}

		if(!$this->punishments->canPunish($sender, $targetName)){
			$this->send($sender, "punish-outranked", $values);
			return;
		}

		if($this->type === PunishmentType::KICK && $sender->getServer()->getPlayerExact($targetName) === null){
			$this->send($sender, "punish-not-online", $values);
			return;
		}

		if($this->type->isLasting()){
			$active = $this->punishments->getActive($targetName, $this->type);
			if($active !== null){
				$sender->sendMessage($this->punishments->format("punish-already", $active));
				return;
			}
		}

		$punishment = $this->punishments->punish(
			$this->type,
			$targetName,
			$sender->getName(),
			$input->getReason(),
			$input->getDuration(),
			$input->isSilent()
		);

		//Staff already got the full notice; everyone else gets a confirmation.
		if(!$sender->hasPermission(PunishmentManager::PERMISSION_NOTIFY)){
			$sender->sendMessage($this->punishments->format("punish-issued", $punishment));
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
