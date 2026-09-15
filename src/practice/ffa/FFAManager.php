<?php

declare(strict_types=1);

namespace practice\ffa;

use pocketmine\item\Item;
use pocketmine\player\GameMode;
use pocketmine\player\Player;
use pocketmine\world\generator\GeneratorManager;
use pocketmine\world\World;
use pocketmine\world\WorldCreationOptions;
use practice\Core;
use practice\form\SimpleForm;
use practice\kit\Kit;
use practice\kit\KitFactory;
use practice\kit\KitIssuer;
use practice\lobby\LobbyManager;
use practice\player\PracticePlayer;
use practice\PracticeConfig;
use practice\session\Session;
use practice\session\SessionManager;
use practice\session\SessionState;
use practice\util\ItemParser;
use practice\util\Placeholder;
use practice\util\PlayerUtil;
use practice\util\WorldUtil;
use function count;
use function is_array;
use function is_string;

/**
 * Registry and lifecycle for every free-for-all arena.
 *
 * Arenas, their worlds and their kits all come from config.yml; adding a mode is
 * a config edit plus (optionally) a world, never a code change.
 */
final class FFAManager{

	/** @var array<string, Arena> */
	private array $arenas = [];

	/** @var array<string, Kit> */
	private array $kits = [];

	private readonly GameMode $gameMode;
	private readonly KnockbackProfile $defaultKnockback;
	private readonly KnockbackStore $knockbackStore;

	public function __construct(
		private readonly Core $plugin,
		private readonly PracticeConfig $config,
		private readonly SessionManager $sessions,
		private readonly LobbyManager $lobby,
		private readonly KitIssuer $kitIssuer,
		KnockbackStore $knockbackStore
	){
		$this->gameMode = PlayerUtil::gameModeFromString($config->getString("ffa.gamemode", "adventure"));
		$this->defaultKnockback = KnockbackProfile::fromConfig($config->getArray("ffa.knockback"));
		$this->knockbackStore = $knockbackStore;
		$this->loadKits();
		$this->loadArenas();
	}

	/**
	 * @return array<string, Arena>
	 */
	public function getArenas() : array{
		return $this->arenas;
	}

	public function getGameMode() : GameMode{
		return $this->gameMode;
	}

	public function getArena(string $id) : ?Arena{
		return $this->arenas[$id] ?? null;
	}

	public function getKit(string $id) : ?Kit{
		return $this->kits[$id] ?? null;
	}

	public function isInFFA(Player $player) : bool{
		return $this->sessions->get($player)?->isInFFA() ?? false;
	}

	public function getArenaOf(Session $session) : ?Arena{
		$id = $session->getArenaId();

		return $id === null ? null : $this->getArena($id);
	}

	public function isArenaWorld(?World $world) : bool{
		if($world === null){
			return false;
		}
		foreach($this->arenas as $arena){
			if($arena->getWorld()?->getId() === $world->getId()){
				return true;
			}
		}

		return false;
	}

	public function countPlaying() : int{
		$total = 0;
		foreach($this->arenas as $arena){
			$total += $arena->count();
		}

		return $total;
	}

	/**
	 * Moves a player from wherever he is into the given arena.
	 *
	 * @return bool false when the arena world is unavailable
	 */
	public function join(Session $session, Arena $arena) : bool{
		$player = $session->getPlayer();
		if(!$player->isConnected()){
			return false;
		}

		$spawn = $arena->getSpawn();
		if($spawn === null || !$arena->isAvailable()){
			return false;
		}

		$this->leaveArenaOnly($session);

		PlayerUtil::reset($player, $this->gameMode);
		$player->teleport($spawn);
		$this->kitIssuer->issue($player, $arena->getKit());
		$this->applyKnockback($player, $arena->getKnockback());

		$session->setState(SessionState::FFA);
		$session->setArenaId($arena->getId());
		$arena->add($session);

		return true;
	}

	/**
	 * Sends a player back to the lobby, leaving whatever arena he was in.
	 */
	public function leave(Session $session) : void{
		$this->leaveArenaOnly($session);
		$this->lobby->join($session);
	}

	/**
	 * Same arena, fresh kit and spawn point — used after a death.
	 */
	public function respawn(Session $session, Arena $arena) : bool{
		return $this->join($session, $arena);
	}

	/**
	 * Removes the player from arena bookkeeping without touching his state; the
	 * caller decides where he goes next.
	 */
	public function leaveArenaOnly(Session $session) : void{
		$arena = $this->getArenaOf($session);
		$arena?->remove($session);

		foreach($this->arenas as $other){
			//Defensive: a session must never be listed in two arenas at once.
			$other->remove($session);
		}

		$session->setArenaId(null);
		$this->applyKnockback($session->getPlayer(), $this->defaultKnockback);
	}

	/**
	 * Hands out the arena kit again — /rekit, and the killer's reward after a
	 * kill. Refused outside an arena. The fresh kit comes without any leftover
	 * item cooldown, so re-issued pearls are usable straight away.
	 */
	public function rekit(Session $session) : bool{
		$arena = $this->getArenaOf($session);
		if($arena === null || !$session->isInFFA() || !$arena->contains($session)){
			return false;
		}

		$player = $session->getPlayer();
		if(!$player->isConnected()){
			return false;
		}

		$this->kitIssuer->issue($player, $arena->getKit());

		return true;
	}

	public function getDefaultKnockback() : KnockbackProfile{
		return $this->defaultKnockback;
	}

	/**
	 * Changes an arena's knockback while the server runs and remembers it.
	 *
	 * Players already fighting in the arena get the new feel immediately —
	 * their profile is per-player state, so it has to be pushed to each of them.
	 */
	public function setArenaKnockback(Arena $arena, KnockbackProfile $profile) : void{
		$arena->setKnockback($profile);
		$this->knockbackStore->set($arena->getId(), $profile);
		$this->refreshKnockback($arena);
	}

	/**
	 * Drops the runtime override and goes back to what config.yml says.
	 *
	 * @return KnockbackProfile the profile now in force
	 */
	public function resetArenaKnockback(Arena $arena) : KnockbackProfile{
		$this->knockbackStore->remove($arena->getId());

		$data = $this->config->getArray("ffa.arenas." . $arena->getId() . ".knockback");
		$profile = KnockbackProfile::fromConfig($data, $this->defaultKnockback);

		$arena->setKnockback($profile);
		$this->refreshKnockback($arena);

		return $profile;
	}

	public function hasKnockbackOverride(Arena $arena) : bool{
		return $this->knockbackStore->has($arena->getId());
	}

	private function refreshKnockback(Arena $arena) : void{
		foreach($arena->getPlayers() as $session){
			$this->applyKnockback($session->getPlayer(), $arena->getKnockback());
		}
	}

	/**
	 * Knockback is per-player state, so it only applies to the player class the
	 * core installs; a foreign Player implementation simply keeps vanilla feel.
	 */
	public function applyKnockback(Player $player, KnockbackProfile $profile) : void{
		if($player instanceof PracticePlayer){
			$player->setKnockbackProfile($profile);
		}
	}

	/**
	 * The arena picker shown by the lobby FFA item.
	 */
	public function sendSelectorForm(Session $session) : void{
		$player = $session->getPlayer();
		$arenas = [];

		$form = new SimpleForm(
			$this->config->getString("ffa.form.title", "§l§6-- Play --"),
			$this->config->getString("ffa.form.content")
		);

		$buttonTemplate = $this->config->getString("ffa.form.button", "{arena_display}\n§e{arena_players} playing");
		foreach($this->arenas as $arena){
			$arenas[] = $arena;
			$form->addButton(
				Placeholder::apply($buttonTemplate, [
					"arena" => $arena->getId(),
					"arena_display" => $arena->getDisplayName(),
					"arena_players" => $arena->count()
				]),
				$arena->getIcon()
			);
		}

		$form->setHandler(function(Player $player, ?int $selected) use ($arenas) : void{
			if($selected === null || !isset($arenas[$selected])){
				return;
			}

			$session = $this->sessions->get($player);
			if($session === null){
				return;
			}
			if(!$session->isInLobby()){
				$player->sendMessage($this->config->getMessage("not-in-lobby"));
				return;
			}

			$arena = $arenas[$selected];
			if(!$this->join($session, $arena)){
				$player->sendMessage($this->config->getMessage("ffa-arena-unavailable"));
				return;
			}

			$player->sendMessage(Placeholder::apply($this->config->getMessage("ffa-join"), [
				"arena" => $arena->getId(),
				"arena_display" => $arena->getDisplayName()
			]));
		});

		$player->sendForm($form);
	}

	private function loadKits() : void{
		foreach($this->config->getArray("kits") as $id => $data){
			if(!is_string($id) || !is_array($data)){
				continue;
			}
			$this->kits[$id] = KitFactory::fromConfig($id, $data);
		}
	}

	private function loadArenas() : void{
		$logger = $this->plugin->getLogger();

		foreach($this->config->getArray("ffa.arenas") as $id => $data){
			if(!is_string($id) || !is_array($data)){
				continue;
			}

			$kitId = is_string($data["kit"] ?? null) ? $data["kit"] : $id;
			$kit = $this->getKit($kitId);
			if($kit === null){
				$logger->warning("FFA arena \"$id\" refers to unknown kit \"$kitId\"; skipping.");
				continue;
			}

			$worldName = is_string($data["world"] ?? null) ? $data["world"] : $id;
			$arena = new Arena(
				$id,
				is_string($data["display"] ?? null) ? $data["display"] : $id,
				$worldName,
				is_string($data["icon"] ?? null) ? $data["icon"] : "",
				$kit,
				//Arenas inherit ffa.knockback and override only what they name;
				//anything changed live with /kbdata is layered on top of that.
				$this->knockbackStore->apply($id, KnockbackProfile::fromConfig(is_array($data["knockback"] ?? null) ? $data["knockback"] : [], $this->defaultKnockback)),
				$this->resolvePotItem($data, $id, $logger)
			);

			$world = $this->resolveWorld($worldName);
			if($world === null){
				$logger->warning("FFA arena \"$id\" has no world \"$worldName\"; it will stay unavailable until the world exists.");
			}else{
				$world->setTime(World::TIME_DAY);
				$world->stopTime();
				//A freshly generated arena world has no terrain around its spawn
				//yet; build it now so the first player to join does not fall.
				WorldUtil::prepareSpawn($world, fn(string $name) => $logger->warning("Could not generate the spawn area of arena world \"$name\"."));
				$arena->setWorld($world);
			}

			$this->arenas[$id] = $arena;
		}

		$logger->info("Loaded " . count($this->arenas) . " FFA arena(s).");
	}

	/**
	 * The item counted for the "[3] killed by [5]" kill feed. Arenas may name
	 * their own; ffa.pot-item is the default and an empty value disables it.
	 *
	 * @param array<string, mixed> $data
	 */
	private function resolvePotItem(array $data, string $arenaId, \Logger $logger) : ?Item{
		$name = is_string($data["pot-item"] ?? null) ? $data["pot-item"] : $this->config->getString("ffa.pot-item");
		if($name === ""){
			return null;
		}

		$item = ItemParser::parse(["item" => $name]);
		if($item === null){
			$logger->warning("FFA arena \"$arenaId\" names an unknown pot item \"$name\"; pot counts are disabled for it.");
		}

		return $item;
	}

	/**
	 * Loads an arena world, generating it first when the config allows it.
	 */
	private function resolveWorld(string $name) : ?World{
		$worldManager = $this->plugin->getServer()->getWorldManager();

		if(!$worldManager->isWorldLoaded($name)){
			if(!$worldManager->isWorldGenerated($name)){
				if(!$this->config->getBool("ffa.auto-generate-worlds", true)){
					return null;
				}

				$generatorName = $this->config->getString("ffa.generator", "flat");
				$generator = GeneratorManager::getInstance()->getGenerator($generatorName);
				if($generator === null){
					$this->plugin->getLogger()->warning("Unknown generator \"$generatorName\"; cannot create world \"$name\".");
					return null;
				}

				$worldManager->generateWorld($name, WorldCreationOptions::create()->setGeneratorClass($generator->getGeneratorClass()), false);
			}else{
				$worldManager->loadWorld($name);
			}
		}

		return $worldManager->getWorldByName($name);
	}
}
