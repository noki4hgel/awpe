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
use pocketmine\utils\Config;
use pocketmine\world\generator\GeneratorManager;
use practice\bossbar\BossBarManager;
use practice\combat\ClickTracker;
use practice\combat\CombatListener;
use practice\combat\CombatLogListener;
use practice\combat\CombatManager;
use practice\command\KbDataCommand;
use practice\command\RankCommand;
use practice\command\RekitCommand;
use practice\command\SpawnCommand;
use practice\cooldown\CooldownListener;
use practice\cooldown\CooldownManager;
use practice\cooldown\ItemCooldowns;
use practice\data\DataProvider;
use practice\data\DataProviderFactory;
use practice\entity\EntityRegistry;
use practice\entity\PotionSettings;
use practice\entity\PracticeSplashPotion;
use practice\ffa\FFAListener;
use practice\ffa\FFAManager;
use practice\ffa\KnockbackStore;
use practice\item\ItemListener;
use practice\item\LobbyItemRegistry;
use practice\item\types\FFASelectorHandler;
use practice\item\types\PrivateArenaHandler;
use practice\item\types\SettingsHandler;
use practice\leaderboard\LeaderboardManager;
use practice\lobby\LobbyListener;
use practice\lobby\LobbyManager;
use practice\nametag\NameTagManager;
use practice\player\PlayerFactoryListener;
use practice\player\PlayerRulesListener;
use practice\rank\RankListener;
use practice\rank\RankManager;
use practice\rank\RankRegistry;
use practice\scoreboard\Scoreboard;
use practice\scoreboard\ScoreboardManager;
use practice\session\SessionListener;
use practice\session\SessionManager;
use practice\settings\SettingRegistry;
use practice\settings\SettingsMenu;
use practice\task\HeartbeatTask;
use practice\world\VoidGenerator;
use Symfony\Component\Filesystem\Path;
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

	/** Knockback values changed live with /kbdata, kept out of the commented config.yml. */
	private const KNOCKBACK_FILE = "knockback.yml";
	private const RANKS_FILE = "ranks.yml";

	private static ?Core $instance = null;

	private PracticeConfig $practiceConfig;
	private DataProvider $data;
	private SessionManager $sessions;
	private CombatManager $combat;
	private CooldownManager $cooldowns;
	private ItemCooldowns $itemCooldowns;
	private ClickTracker $clicks;
	private RankRegistry $rankRegistry;
	private RankManager $ranks;
	private SettingRegistry $settingRegistry;
	private SettingsMenu $settingsMenu;
	private LobbyItemRegistry $lobbyItems;
	private LobbyManager $lobby;
	private FFAManager $ffa;
	private LeaderboardManager $leaderboards;
	private PlaceholderProvider $placeholders;
	private ScoreboardManager $scoreboards;
	private BossBarManager $bossBars;
	private NameTagManager $nameTags;

	public static function getInstance() : Core{
		return self::$instance ?? throw new \LogicException("Awpe is not enabled");
	}

	/**
	 * Runs before any world is loaded, which is the only point at which a
	 * generator or an entity can still be registered for worlds and chunks the
	 * server loads itself.
	 */
	protected function onLoad() : void{
		$generatorManager = GeneratorManager::getInstance();
		if($generatorManager->getGenerator(VoidGenerator::NAME) === null){
			//Arena maps built on other servers commonly declare a "void"
			//generator in level.dat; without it the world refuses to load.
			$generatorManager->addGenerator(VoidGenerator::class, VoidGenerator::NAME, fn() => null);
		}

		EntityRegistry::register();
	}

	protected function onEnable() : void{
		self::$instance = $this;

		$this->saveDefaultConfig();
		$this->practiceConfig = new PracticeConfig($this->getConfig());

		$dataFolder = $this->getDataFolder();
		if(!is_dir($dataFolder)){
			mkdir($dataFolder, 0777, true);
		}

		PracticeSplashPotion::setSettings(PotionSettings::fromConfig($this->practiceConfig->getArray("potions")));

		$this->data = DataProviderFactory::create(
			$this->practiceConfig->getString("storage.provider", DataProviderFactory::TYPE_LEVELDB),
			$dataFolder,
			fn(string $reason) => $this->getLogger()->warning("Could not open the LevelDB store; falling back to JSON. Reason: " . $reason)
		);
		$this->sessions = new SessionManager(
			$this->data,
			$this->practiceConfig->getString("scoreboard.slot", Scoreboard::SLOT_SIDEBAR)
		);

		$this->combat = new CombatManager(
			$this->getServer(),
			max(0, $this->practiceConfig->getInt("combat.tag-duration", 10)) * self::TICKS_PER_SECOND
		);
		$this->cooldowns = new CooldownManager($this->getServer());
		$this->itemCooldowns = new ItemCooldowns();
		$this->clicks = new ClickTracker($this->getServer());

		$this->registerRanks();
		$this->registerSettings();

		$this->lobbyItems = new LobbyItemRegistry();
		$this->lobby = new LobbyManager($this, $this->practiceConfig, $this->sessions, $this->lobbyItems);
		$this->ffa = new FFAManager(
			$this,
			$this->practiceConfig,
			$this->sessions,
			$this->lobby,
			new KnockbackStore(Path::join($dataFolder, self::KNOCKBACK_FILE))
		);

		$this->registerLobbyItems();
		$this->registerItemCooldowns();
		$this->registerLeaderboards();

		$this->placeholders = new PlaceholderProvider($this->getServer(), $this->practiceConfig, $this->ffa, $this->combat, $this->clicks, $this->ranks);
		$this->scoreboards = new ScoreboardManager($this->practiceConfig, $this->placeholders, $this->settingRegistry);
		$this->bossBars = new BossBarManager($this->practiceConfig, $this->placeholders, $this->settingRegistry);
		$this->nameTags = new NameTagManager($this->practiceConfig, $this->placeholders, $this->settingRegistry, $this->ranks);

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
		$this->data->close();
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

	public function getClickTracker() : ClickTracker{
		return $this->clicks;
	}

	public function getRankManager() : RankManager{
		return $this->ranks;
	}

	public function getSettingRegistry() : SettingRegistry{
		return $this->settingRegistry;
	}

	public function getSettingsMenu() : SettingsMenu{
		return $this->settingsMenu;
	}

	public function getLeaderboardManager() : LeaderboardManager{
		return $this->leaderboards;
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

	private function registerRanks() : void{
		$this->saveResource(self::RANKS_FILE);

		$this->rankRegistry = new RankRegistry();
		$failed = $this->rankRegistry->loadFromConfig(new Config(Path::join($this->getDataFolder(), self::RANKS_FILE), Config::YAML, []));
		if(count($failed) > 0){
			$this->getLogger()->warning("Ignored malformed rank(s): " . implode(", ", $failed));
		}
		if($this->rankRegistry->getDefault() === null){
			$this->getLogger()->warning("No ranks are defined; chat formatting and /rank will do nothing.");
		}

		$this->ranks = new RankManager($this, $this->rankRegistry);
	}

	private function registerSettings() : void{
		$this->settingRegistry = new SettingRegistry();
		$failed = $this->settingRegistry->loadFromConfig(
			$this->practiceConfig->getArray("settings.sections"),
			$this->practiceConfig->getArray("settings.entries")
		);
		if(count($failed) > 0){
			$this->getLogger()->warning("Ignored setting(s) with an unknown section or shape: " . implode(", ", $failed));
		}

		$this->settingsMenu = new SettingsMenu($this->practiceConfig, $this->settingRegistry, $this->sessions);
	}

	private function registerLobbyItems() : void{
		$this->lobbyItems->register(new FFASelectorHandler($this->ffa, $this->practiceConfig));
		$this->lobbyItems->register(new PrivateArenaHandler($this->practiceConfig));
		$this->lobbyItems->register(new SettingsHandler($this->settingsMenu));

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

	private function registerLeaderboards() : void{
		$this->leaderboards = new LeaderboardManager($this->getServer(), $this->practiceConfig, $this->data);

		$lobbyWorld = $this->lobby->getWorld();
		$failed = $this->leaderboards->loadFromConfig($lobbyWorld?->getFolderName() ?? "");
		if(count($failed) > 0){
			$this->getLogger()->warning("Ignored leaderboard(s) with an unknown world or shape: " . implode(", ", $failed));
		}
	}

	private function registerListeners() : void{
		$pluginManager = $this->getServer()->getPluginManager();

		foreach([
			new PlayerFactoryListener(),
			new PlayerRulesListener($this->practiceConfig, $this->clicks),
			new SessionListener($this->practiceConfig, $this->sessions, $this->lobby, $this->ffa, $this->combat, $this->cooldowns, $this->clicks, $this->ranks, $this->scoreboards, $this->bossBars, $this->nameTags, $this->leaderboards),
			new RankListener($this->ranks, $this->sessions),
			new ItemListener($this->lobbyItems, $this->sessions),
			new CooldownListener($this->practiceConfig, $this->cooldowns, $this->itemCooldowns, $this->getScheduler()),
			new LobbyListener($this->lobby, $this->sessions),
			new FFAListener($this->ffa),
			new CombatListener($this->getServer(), $this->practiceConfig, $this->sessions, $this->ffa, $this->combat, $this->settingRegistry, $this->clicks),
			new CombatLogListener($this->practiceConfig, $this->combat)
		] as $listener){
			$pluginManager->registerEvents($listener, $this);
		}
	}

	private function registerCommands() : void{
		$this->getServer()->getCommandMap()->registerAll($this->getName(), [
			new SpawnCommand($this->practiceConfig, $this->sessions, $this->ffa),
			new RekitCommand($this->practiceConfig, $this->sessions, $this->ffa),
			new KbDataCommand($this->practiceConfig, $this->ffa),
			new RankCommand($this->practiceConfig, $this->sessions, $this->ranks, $this->getServer())
		]);
	}

	private function scheduleTasks() : void{
		$this->getScheduler()->scheduleRepeatingTask(
			new HeartbeatTask(
				$this->practiceConfig,
				$this->sessions,
				$this->scoreboards,
				$this->bossBars,
				$this->nameTags,
				$this->leaderboards,
				$this->settingRegistry,
				$this->combat,
				$this->clicks
			),
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
			$this->ranks->apply($session);
			$this->lobby->join($session);
			$this->leaderboards->sendTo($player);
		}
	}
}
