<?php

declare(strict_types=1);

namespace practice\command\defaults;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use practice\ffa\Arena;
use practice\ffa\FFAManager;
use practice\ffa\KnockbackProfile;
use practice\PracticeConfig;
use practice\util\Placeholder;
use function array_keys;
use function count;
use function implode;
use function is_numeric;
use function strtolower;

/**
 * /kbdata — reads and changes a mode's knockback while the server runs.
 *
 *   /kbdata                                          list every mode
 *   /kbdata <mode>                                   show one mode
 *   /kbdata <mode> <vertical> <horizontal> <delay>   change it
 *   /kbdata <mode> reset                             back to config.yml
 *
 * Changes take effect instantly for everybody already in the arena and are
 * remembered across restarts. Unlike the other commands this one does not act
 * on the sender, so it also works from the console.
 */
final class KbDataCommand extends Command{

	public const NAME = "kbdata";
	public const PERMISSION = "awpe.command.kbdata";

	/** Sanity bounds: wide enough for any real tuning, tight enough to catch a 40 typed instead of 0.4. */
	private const MAX_PUSH = 10.0;
	private const MAX_DELAY = 200;

	private const ARG_RESET = "reset";

	public function __construct(
		private readonly PracticeConfig $config,
		private readonly FFAManager $ffa
	){
		parent::__construct(self::NAME, "Read or change a mode's knockback");
		$this->setPermission(self::PERMISSION);
		$this->setUsage("/kbdata <mode> <vertical> <horizontal> <delay>");
		$this->setAliases(["kb"]);
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args) : void{
		if(!$this->testPermission($sender)){
			return;
		}

		if(count($args) === 0){
			$this->listModes($sender);
			return;
		}

		$arena = $this->findArena($args[0]);
		if($arena === null){
			$this->send($sender, "kb-unknown-mode", [
				"mode" => $args[0],
				"modes" => implode(", ", array_keys($this->ffa->getArenas()))
			]);
			return;
		}

		if(count($args) === 1){
			$this->show($sender, $arena);
			return;
		}

		if(count($args) === 2 && strtolower($args[1]) === self::ARG_RESET){
			$profile = $this->ffa->resetArenaKnockback($arena);
			$this->send($sender, "kb-reset", $this->describe($arena, $profile));
			return;
		}

		if(count($args) !== 4){
			$sender->sendMessage($this->config->getMessage("kb-usage"));
			return;
		}

		//Argument order follows the usage line: vertical, horizontal, delay.
		$vertical = $this->readPush($sender, $args[1]);
		$horizontal = $this->readPush($sender, $args[2]);
		$delay = $this->readDelay($sender, $args[3]);
		if($vertical === null || $horizontal === null || $delay === null){
			return;
		}

		$profile = new KnockbackProfile($horizontal, $vertical, $delay);
		$this->ffa->setArenaKnockback($arena, $profile);

		$this->send($sender, "kb-updated", $this->describe($arena, $profile));
	}

	private function listModes(CommandSender $sender) : void{
		$arenas = $this->ffa->getArenas();
		if(count($arenas) === 0){
			$this->send($sender, "kb-unknown-mode", ["mode" => "-", "modes" => "-"]);
			return;
		}

		$sender->sendMessage($this->config->getMessage("kb-list-header"));
		foreach($arenas as $arena){
			$this->show($sender, $arena);
		}
		$sender->sendMessage($this->config->getMessage("kb-usage"));
	}

	private function show(CommandSender $sender, Arena $arena) : void{
		$this->send($sender, "kb-show", $this->describe($arena, $arena->getKnockback()));
	}

	/**
	 * @return array<string, string|int|float>
	 */
	private function describe(Arena $arena, KnockbackProfile $profile) : array{
		return [
			"mode" => $arena->getId(),
			"arena_display" => $arena->getDisplayName(),
			"vertical" => $profile->getVertical(),
			"horizontal" => $profile->getHorizontal(),
			"delay" => $profile->getAttackDelay(),
			"players" => $arena->count(),
			"source" => $this->ffa->hasKnockbackOverride($arena) ? "runtime" : "config"
		];
	}

	/**
	 * Accepts the mode name in any casing, the way a player would type it.
	 */
	private function findArena(string $id) : ?Arena{
		$arena = $this->ffa->getArena($id);
		if($arena !== null){
			return $arena;
		}

		foreach($this->ffa->getArenas() as $candidate){
			if(strtolower($candidate->getId()) === strtolower($id)){
				return $candidate;
			}
		}

		return null;
	}

	private function readPush(CommandSender $sender, string $raw) : ?float{
		if(!is_numeric($raw)){
			$this->send($sender, "kb-invalid-number", ["value" => $raw]);
			return null;
		}

		$value = (float) $raw;
		if($value < 0 || $value > self::MAX_PUSH){
			$this->sendOutOfRange($sender);
			return null;
		}

		return $value;
	}

	private function readDelay(CommandSender $sender, string $raw) : ?int{
		if(!is_numeric($raw) || (string) (int) $raw !== $raw){
			$this->send($sender, "kb-invalid-number", ["value" => $raw]);
			return null;
		}

		$value = (int) $raw;
		if($value < 0 || $value > self::MAX_DELAY){
			$this->sendOutOfRange($sender);
			return null;
		}

		return $value;
	}

	private function sendOutOfRange(CommandSender $sender) : void{
		$this->send($sender, "kb-out-of-range", [
			"max_push" => self::MAX_PUSH,
			"max_delay" => self::MAX_DELAY
		]);
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
