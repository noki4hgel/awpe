<?php

declare(strict_types=1);

namespace practice\command;

use pocketmine\command\Command;
use practice\command\defaults\KbDataCommand;
use practice\command\defaults\RankCommand;
use practice\command\defaults\RekitCommand;
use practice\command\defaults\SpawnCommand;
use practice\Core;
use function array_merge;
use function array_unique;
use function str_starts_with;
use function strtolower;

/**
 * Builds and registers every command the core provides.
 *
 * Adding a command means one line in registerDefaults() — its dependencies are
 * still spelled out in its own constructor, this is just the single place they
 * get wired together.
 *
 * A command that clashes with a PocketMine built-in replaces it: the built-in
 * is unregistered first so ours gets the bare label (/ban, not /awpe:ban).
 * Commands from other plugins are never removed; they only get a warning.
 */
final class CommandManager{

	private const BUILT_IN_NAMESPACE = "pocketmine\\command\\defaults\\";

	/** @var array<string, Command> lowercase name => command */
	private array $commands = [];

	public function __construct(private readonly Core $plugin){
	}

	public function registerDefaults() : void{
		$config = $this->plugin->getPracticeConfig();
		$sessions = $this->plugin->getSessionManager();
		$ffa = $this->plugin->getFFAManager();

		foreach([
			//Players
			new SpawnCommand($config, $sessions, $ffa),
			new RekitCommand($config, $sessions, $ffa),

			//Staff
			new KbDataCommand($config, $ffa),
			new RankCommand($config, $sessions, $this->plugin->getRankManager(), $this->plugin->getServer())
		] as $command){
			$this->register($command);
		}
	}

	public function register(Command $command) : void{
		$this->replaceBuiltIns($command);

		$this->plugin->getServer()->getCommandMap()->register($this->plugin->getName(), $command);
		$this->commands[strtolower($command->getName())] = $command;
	}

	public function get(string $name) : ?Command{
		return $this->commands[strtolower($name)] ?? null;
	}

	/**
	 * @return array<string, Command>
	 */
	public function all() : array{
		return $this->commands;
	}

	/**
	 * Clears the way for a command whose name or alias is already taken.
	 *
	 * Aliases count too: the built-in /pardon answers to /unban, so a core /unban
	 * has to take /pardon down with it.
	 */
	private function replaceBuiltIns(Command $command) : void{
		$commandMap = $this->plugin->getServer()->getCommandMap();
		$logger = $this->plugin->getLogger();

		foreach(array_unique(array_merge([$command->getName()], $command->getAliases())) as $label){
			$existing = $commandMap->getCommand($label);
			if($existing === null || $existing === $command || isset($this->commands[strtolower($existing->getName())])){
				continue;
			}

			if(!str_starts_with($existing::class, self::BUILT_IN_NAMESPACE)){
				$logger->warning("/$label is already provided by another plugin; the core's version will only be reachable as /" . strtolower($this->plugin->getName()) . ":$label.");
				continue;
			}

			$commandMap->unregister($existing);
			$logger->debug("Replaced the built-in /" . $existing->getName() . " with the core's /" . $command->getName() . ".");
		}
	}
}
