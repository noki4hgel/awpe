<?php

/*
 *
 *  _____                    _   _       _
 * | ____|___ ___  ___ _ __ | |_(_) __ _| |
 * |  _| / __/ __|/ _ \ '_ \| __| |/ _` | |
 * | |___\__ \__ \  __/ | | | |_| | (_| | |
 * |_____|___/___/\___|_| |_|\__|_|\__,_|_|
 *
 * Essential — PocketMine-MP Fork
 * Supported MCPE/Bedrock versions: 1.12, 1.16 - 1.26.x
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author Essential Team
 * @link https://github.com/BakuTeam/Essential
 *
 *
 */

declare(strict_types=1);

namespace practice;

use pocketmine\plugin\PluginBase;
use pocketmine\world\generator\GeneratorManager;
use practice\bossbar\BossBarManager;
use practice\combat\CombatListener;
use practice\combat\CombatLogListener;
use practice\combat\CombatManager;
use practice\command\RekitCommand;
use practice\command\SpawnCommand;
use practice\cooldown\CooldownListener;
use practice\cooldown\CooldownManager;
use practice\cooldown\ItemCooldowns;
use practice\ffa\FFAListener;
use practice\ffa\FFAManager;
use practice\item\ItemListener;
use practice\item\LobbyItemRegistry;
use practice\item\types\FFASelectorHandler;
use practice\item\types\PrivateArenaHandler;
use practice\item\types\SettingsHandler;
use practice\lobby\LobbyListener;
use practice\lobby\LobbyManager;
use practice\nametag\NameTagManager;
use practice\player\PlayerFactoryListener;
use practice\scoreboard\Scoreboard;
use practice\scoreboard\ScoreboardManager;
use practice\session\SessionListener;
use practice\session\SessionManager;
use practice\stats\StatsProvider;
use practice\stats\StatsProviderFactory;
use practice\task\HeartbeatTask;
use practice\world\VoidGenerator;
use function count;
use function implode;
use function is_dir;
use function max;
use function mkdir;

/**
 * Entry point of the AWPE practice core.
 *
 * Every subsystem is a plain object created here and handed its dependencies,
 * so nothing reaches for a global singleton and each part can be swapped or
 * tested on its own.
 */
final class Core extends PluginBase{

	private const TICKS_PER_SECOND = 20;

	private static ?Core $instance = null;

	private PracticeConfig $practiceConfig;
	private StatsProvider $stats;
	private SessionManager $sessions;
	private CombatManager $combat;
	private CooldownManager $cooldowns;
	private ItemCooldowns $itemCooldowns;
	private LobbyItemRegistry $lobbyItems;
	private LobbyManager $lobby;
	private FFAManager $ffa;
	private PlaceholderProvider $placeholders;
	private ScoreboardManager $scoreboards;
	private BossBarManager $bossBars;
	private NameTagManager $nameTags;

	public static function getInstance() : Core{
		return self::$instance ?? throw new \LogicException("Awpe is not enabled");
	}

	/**
	 * Runs before any world is loaded, which is the only point at which a
	 * generator can still be registered for worlds the server loads itself.
	 */
	protected function onLoad() : void{
		$generatorManager = GeneratorManager::getInstance();
		if($generatorManager->getGenerator(VoidGenerator::NAME) === null){
			//Arena maps built on other servers commonly declare a "void"
			//generator in level.dat; without it the world refuses to load.
			$generatorManager->addGenerator(VoidGenerator::class, VoidGenerator::NAME, fn() => null);
		}
	}

	protected function onEnable() : void{
		self::$instance = $this;

		$this->saveDefaultConfig();
		$this->practiceConfig = new PracticeConfig($this->getConfig());

		$dataFolder = $this->getDataFolder();
		if(!is_dir($dataFolder)){
			mkdir($dataFolder, 0777, true);
		}

		$this->stats = StatsProviderFactory::create(
			$this->practiceConfig->getString("storage.provider", StatsProviderFactory::TYPE_LEVELDB),
			$dataFolder,
			fn(string $reason) => $this->getLogger()->warning("Could not open the LevelDB store; falling back to JSON. Reason: " . $reason)
		);
		$this->sessions = new SessionManager(
			$this->stats,
			$this->practiceConfig->getString("scoreboard.slot", Scoreboard::SLOT_SIDEBAR)
		);

		$this->combat = new CombatManager(
			$this->getServer(),
			max(0, $this->practiceConfig->getInt("combat.tag-duration", 10)) * self::TICKS_PER_SECOND
		);
		$this->cooldowns = new CooldownManager($this->getServer());
		$this->itemCooldowns = new ItemCooldowns();

		$this->lobbyItems = new LobbyItemRegistry();
		$this->lobby = new LobbyManager($this, $this->practiceConfig, $this->sessions, $this->lobbyItems);
		$this->ffa = new FFAManager($this, $this->practiceConfig, $this->sessions, $this->lobby);

		$this->registerLobbyItems();
		$this->registerItemCooldowns();

		$this->placeholders = new PlaceholderProvider($this->getServer(), $this->practiceConfig, $this->ffa, $this->combat);
		$this->scoreboards = new ScoreboardManager($this->practiceConfig, $this->placeholders);
		$this->bossBars = new BossBarManager($this->practiceConfig, $this->placeholders);
		$this->nameTags = new NameTagManager($this->practiceConfig, $this->placeholders);

		$this->registerListeners();
		$this->registerCommands();
		$this->scheduleTasks();
		$this->adoptOnlinePlayers();
	}

	protected function onDisable() : void{
		if(!isset($this->sessions)){
			//onEnable bailed out before the managers existed.
			return;
		}

		foreach($this->sessions->all() as $session){
			$this->scoreboards->remove($session);
			$this->bossBars->remove($session);
			$this->nameTags->remove($session);
		}

		$this->sessions->saveAll();
		$this->stats->close();
		self::$instance = null;
	}

	public function getPracticeConfig() : PracticeConfig{
		return $this->practiceConfig;
	}

	public function getSessionManager() : SessionManager{
		return $this->sessions;
	}

	public function getLobbyManager() : LobbyManager{
		return $this->lobby;
	}

	public function getFFAManager() : FFAManager{
		return $this->ffa;
	}

	public function getCombatManager() : CombatManager{
		return $this->combat;
	}

	public function getCooldownManager() : CooldownManager{
		return $this->cooldowns;
	}

	public function getLobbyItemRegistry() : LobbyItemRegistry{
		return $this->lobbyItems;
	}

	public function getScoreboardManager() : ScoreboardManager{
		return $this->scoreboards;
	}

	public function getBossBarManager() : BossBarManager{
		return $this->bossBars;
	}

	public function getNameTagManager() : NameTagManager{
		return $this->nameTags;
	}

	public function getPlaceholderProvider() : PlaceholderProvider{
		return $this->placeholders;
	}

	private function registerLobbyItems() : void{
		$this->lobbyItems->register(new FFASelectorHandler($this->ffa, $this->practiceConfig));
		$this->lobbyItems->register(new PrivateArenaHandler($this->practiceConfig));
		$this->lobbyItems->register(new SettingsHandler($this->practiceConfig));

		$failed = $this->lobbyItems->loadFromConfig($this->practiceConfig->getArray("lobby.items"));
		if(count($failed) > 0){
			$this->getLogger()->warning("Ignored lobby item(s) with an unknown action or item: " . implode(", ", $failed));
		}
	}

	private function registerItemCooldowns() : void{
		$failed = $this->itemCooldowns->loadFromConfig($this->practiceConfig->getArray("cooldowns.items"));
		if(count($failed) > 0){
			$this->getLogger()->warning("Ignored cooldown(s) for unknown item(s): " . implode(", ", $failed));
		}
	}

	private function registerListeners() : void{
		$pluginManager = $this->getServer()->getPluginManager();

		foreach([
			new PlayerFactoryListener(),
			new SessionListener($this->practiceConfig, $this->sessions, $this->lobby, $this->ffa, $this->combat, $this->cooldowns, $this->scoreboards, $this->bossBars, $this->nameTags),
			new ItemListener($this->lobbyItems, $this->sessions),
			new CooldownListener($this->practiceConfig, $this->cooldowns, $this->itemCooldowns, $this->getScheduler()),
			new LobbyListener($this->lobby, $this->sessions),
			new FFAListener($this->ffa),
			new CombatListener($this->getServer(), $this->practiceConfig, $this->sessions, $this->ffa, $this->combat),
			new CombatLogListener($this->practiceConfig, $this->combat)
		] as $listener){
			$pluginManager->registerEvents($listener, $this);
		}
	}

	private function registerCommands() : void{
		$this->getServer()->getCommandMap()->registerAll($this->getName(), [
			new SpawnCommand($this->practiceConfig, $this->sessions, $this->ffa),
			new RekitCommand($this->practiceConfig, $this->sessions, $this->ffa)
		]);
	}

	private function scheduleTasks() : void{
		$this->getScheduler()->scheduleRepeatingTask(
			new HeartbeatTask($this->sessions, $this->scoreboards, $this->bossBars, $this->nameTags),
			$this->practiceConfig->getUpdateInterval()
		);
	}

	/**
	 * Makes a reload behave like a fresh start for players who are already
	 * connected: PlayerJoinEvent will not fire again for them.
	 */
	private function adoptOnlinePlayers() : void{
		foreach($this->getServer()->getOnlinePlayers() as $player){
			$session = $this->sessions->open($player);
			$this->lobby->join($session);
		}
	}
}
