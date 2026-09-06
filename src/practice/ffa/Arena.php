<?php

declare(strict_types=1);

namespace practice\ffa;

use pocketmine\item\Item;
use pocketmine\player\Player;
use pocketmine\world\Position;
use pocketmine\world\World;
use practice\ffa\kit\Kit;
use practice\session\Session;
use practice\util\WorldUtil;
use function count;
use function spl_object_id;

/**
 * One free-for-all arena: a world, the kit handed out on entry, the knockback
 * profile it fights with and the set of players currently in it.
 */
final class Arena{

	/** @var array<int, Session> */
	private array $players = [];

	public function __construct(
		private readonly string $id,
		private readonly string $displayName,
		private readonly string $worldName,
		private readonly string $icon,
		private readonly Kit $kit,
		private KnockbackProfile $knockback,
		private readonly ?Item $potItem = null,
		private ?World $world = null
	){
	}

	public function getId() : string{
		return $this->id;
	}

	public function getDisplayName() : string{
		return $this->displayName;
	}

	public function getWorldName() : string{
		return $this->worldName;
	}

	public function getIcon() : string{
		return $this->icon;
	}

	public function getKit() : Kit{
		return $this->kit;
	}

	public function getKnockback() : KnockbackProfile{
		return $this->knockback;
	}

	/**
	 * Swapped at runtime by /kbdata. FFAManager is responsible for pushing the
	 * new profile onto the players already fighting here.
	 */
	public function setKnockback(KnockbackProfile $knockback) : void{
		$this->knockback = $knockback;
	}

	public function getWorld() : ?World{
		if($this->world !== null && !$this->world->isLoaded()){
			$this->world = null;
		}

		return $this->world;
	}

	public function setWorld(?World $world) : void{
		$this->world = $world;
	}

	/**
	 * An arena is only joinable once its world exists and the terrain around
	 * the spawn has finished generating.
	 */
	public function isAvailable() : bool{
		$world = $this->getWorld();

		return $world !== null && WorldUtil::isSpawnReady($world);
	}

	/**
	 * The point players are teleported to. Falls back to the raw world spawn
	 * while the terrain around it is still generating.
	 */
	public function getSpawn() : ?Position{
		$world = $this->getWorld();

		return $world === null ? null : WorldUtil::safeSpawn($world);
	}

	/**
	 * Counts the healing splash potions a player has left, for the kill feed.
	 * Arenas without a pot item (fist, for example) report none.
	 */
	public function countPots(Player $player) : int{
		if($this->potItem === null){
			return 0;
		}

		$count = 0;
		foreach($player->getInventory()->getContents() as $item){
			//Compare the item state but not its NBT, so a renamed pot still counts.
			if($item->equals($this->potItem, true, false)){
				$count += $item->getCount();
			}
		}

		return $count;
	}

	public function hasPots() : bool{
		return $this->potItem !== null;
	}

	public function add(Session $session) : void{
		$this->players[spl_object_id($session->getPlayer())] = $session;
	}

	public function remove(Session $session) : void{
		unset($this->players[spl_object_id($session->getPlayer())]);
	}

	public function contains(Session $session) : bool{
		return isset($this->players[spl_object_id($session->getPlayer())]);
	}

	/**
	 * @return Session[]
	 */
	public function getPlayers() : array{
		return $this->players;
	}

	public function count() : int{
		return count($this->players);
	}
}
