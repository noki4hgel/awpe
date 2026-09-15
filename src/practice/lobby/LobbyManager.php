<?php

declare(strict_types=1);

namespace practice\lobby;

use pocketmine\player\GameMode;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\world\Position;
use pocketmine\world\World;
use practice\Core;
use practice\item\LobbyItemRegistry;
use practice\PracticeConfig;
use practice\session\Session;
use practice\session\SessionManager;
use practice\session\SessionState;
use practice\util\PlayerUtil;
use practice\util\WorldUtil;
use function count;

/**
 * The hub every player starts and returns to.
 *
 * Nothing outside this class should decide whether a player "is in the lobby" —
 * ask isInLobby()/isLobbyWorld() instead of comparing world names by hand.
 */
final class LobbyManager{

	public const RULE_BLOCK_BREAK = "block-break";
	public const RULE_BLOCK_PLACE = "block-place";
	public const RULE_ITEM_DROP = "item-drop";
	public const RULE_ITEM_PICKUP = "item-pickup";
	public const RULE_INVENTORY_TRANSACTION = "inventory-transaction";
	public const RULE_DAMAGE = "damage";
	public const RULE_PVP = "pvp";
	public const RULE_HUNGER = "hunger";
	public const RULE_INTERACT = "interact";

	private const WORLD_DEFAULT = "default";

	private ?World $world = null;
	private readonly GameMode $gameMode;

	public function __construct(
		private readonly Core $plugin,
		private readonly PracticeConfig $config,
		private readonly SessionManager $sessions,
		private readonly LobbyItemRegistry $items
	){
		$this->gameMode = PlayerUtil::gameModeFromString($config->getString("lobby.gamemode", "adventure"));
		$this->loadWorld();
	}

	public function getItemRegistry() : LobbyItemRegistry{
		return $this->items;
	}

	public function getGameMode() : GameMode{
		return $this->gameMode;
	}

	public function getWorld() : ?World{
		if($this->world !== null && !$this->world->isLoaded()){
			$this->world = null;
			$this->loadWorld();
		}

		return $this->world;
	}

	public function getSpawn() : ?Position{
		$world = $this->getWorld();

		return $world === null ? null : WorldUtil::safeSpawn($world);
	}

	public function isLobbyWorld(?World $world) : bool{
		$lobby = $this->getWorld();

		return $world !== null && $lobby !== null && $world->getId() === $lobby->getId();
	}

	/**
	 * True while the player's session says he belongs to the lobby. Players that
	 * have no session yet (still logging in) are not in the lobby.
	 */
	public function isInLobby(Player $player) : bool{
		return $this->sessions->get($player)?->isInLobby() ?? false;
	}

	/**
	 * Whether a lobby protection rule is switched on in the config.
	 */
	public function isProtected(string $rule) : bool{
		return $this->config->getBool("lobby.protection." . $rule, true);
	}

	/**
	 * Shorthand for "this player is in the lobby and the rule is enabled".
	 */
	public function shouldProtect(Player $player, string $rule) : bool{
		return $this->isProtected($rule) && $this->isInLobby($player);
	}

	public function getVoidLevel() : int{
		return $this->config->getInt("lobby.void-level", 10);
	}

	/**
	 * Puts a player into the lobby: clean state, spawn position, hotbar items.
	 */
	public function join(Session $session) : void{
		$player = $session->getPlayer();
		if(!$player->isConnected()){
			return;
		}

		$session->setState(SessionState::LOBBY);
		$session->setArenaId(null);

		PlayerUtil::reset($player, $this->gameMode);
		$this->teleport($session);
		$this->sendItems($session);
	}

	public function teleport(Session $session) : void{
		$spawn = $this->getSpawn();
		if($spawn === null){
			return;
		}

		$session->getPlayer()->teleport($spawn);
	}

	/**
	 * Lays out the configured hotbar. Slots not covered by the config are left
	 * empty, and the first configured item is selected.
	 */
	public function sendItems(Session $session, string $layout = LobbyItemRegistry::LAYOUT_DEFAULT) : void{
		$player = $session->getPlayer();
		if(!$player->isConnected()){
			return;
		}

		$inventory = $player->getInventory();
		$inventory->clearAll();

		$first = null;
		foreach($this->items->getItems($layout) as $slot => $lobbyItem){
			if($slot >= $inventory->getHotbarSize()){
				//A slot outside the hotbar would be unreachable in adventure mode.
				continue;
			}
			$inventory->setItem($slot, $lobbyItem->getItem());
			$first ??= $slot;
		}

		if($first !== null){
			$inventory->setHeldItemIndex($first);
		}
	}

	/**
	 * @return Session[]
	 */
	public function getPlayers() : array{
		$result = [];
		foreach($this->sessions->all() as $id => $session){
			if($session->isInLobby()){
				$result[$id] = $session;
			}
		}

		return $result;
	}

	public function count() : int{
		return count($this->getPlayers());
	}

	private function loadWorld() : void{
		$worldManager = $this->plugin->getServer()->getWorldManager();

		$name = $this->config->getString("lobby.world", self::WORLD_DEFAULT);
		if($name === self::WORLD_DEFAULT || $name === ""){
			$world = $worldManager->getDefaultWorld();
		}else{
			if(!$worldManager->isWorldLoaded($name)){
				$worldManager->loadWorld($name);
			}
			$world = $worldManager->getWorldByName($name);
		}

		if($world === null){
			$this->plugin->getLogger()->error("Lobby world \"$name\" could not be loaded; players will not be teleported.");
			return;
		}

		WorldUtil::prepareSpawn($world, fn(string $name) => $this->plugin->getLogger()->warning("Could not generate the spawn area of lobby world \"$name\"."));

		if($this->config->getBool("lobby.lock-time", true)){
			$world->setTime(World::TIME_DAY);
			$world->stopTime();
		}

		$this->world = $world;
	}
}
