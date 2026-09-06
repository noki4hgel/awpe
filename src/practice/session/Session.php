<?php

declare(strict_types=1);

namespace practice\session;

use pocketmine\player\Player;
use practice\bossbar\BossBar;
use practice\scoreboard\Scoreboard;
use practice\data\DataProvider;
use practice\data\PlayerData;
use practice\util\DeviceUtil;
use function is_int;
use function is_string;

/**
 * Everything the practice core knows about one connected player.
 *
 * Managers never store player state of their own — they read and mutate it
 * here, which keeps "where is this player / what is he holding" answerable from
 * a single object.
 */
final class Session{

	private SessionState $state = SessionState::LOBBY;
	private ?string $arenaId = null;
	private readonly string $deviceKey;
	private readonly Scoreboard $scoreboard;
	private readonly BossBar $bossBar;
	private readonly PlayerData $data;
	private ?string $lastNameTag = null;
	private int $pearlLandTick = -1;

	public function __construct(
		private readonly Player $player,
		private readonly DataProvider $dataProvider,
		string $scoreboardSlot = Scoreboard::SLOT_SIDEBAR
	){
		$extraData = $player->getPlayerInfo()->getExtraData();
		$deviceOs = is_int($extraData["DeviceOS"] ?? null) ? $extraData["DeviceOS"] : -1;
		$deviceModel = is_string($extraData["DeviceModel"] ?? null) ? $extraData["DeviceModel"] : "";

		$this->deviceKey = DeviceUtil::keyOf($deviceOs, $deviceModel);
		$this->scoreboard = new Scoreboard($player->getNetworkSession(), $scoreboardSlot);
		$this->bossBar = new BossBar($player);
		$this->data = $dataProvider->load($player->getName());
	}

	public function getPlayer() : Player{
		return $this->player;
	}

	public function getName() : string{
		return $this->player->getName();
	}

	public function getState() : SessionState{
		return $this->state;
	}

	public function setState(SessionState $state) : void{
		$this->state = $state;
	}

	public function isInLobby() : bool{
		return $this->state === SessionState::LOBBY;
	}

	public function isInFFA() : bool{
		return $this->state === SessionState::FFA;
	}

	public function getArenaId() : ?string{
		return $this->arenaId;
	}

	public function setArenaId(?string $arenaId) : void{
		$this->arenaId = $arenaId;
	}

	public function getDeviceKey() : string{
		return $this->deviceKey;
	}

	public function getScoreboard() : Scoreboard{
		return $this->scoreboard;
	}

	public function getBossBar() : BossBar{
		return $this->bossBar;
	}

	public function getPing() : int{
		return $this->player->getNetworkSession()->getPing() ?? 0;
	}

	/**
	 * The player's stored record: counters, rank and settings.
	 */
	public function getData() : PlayerData{
		return $this->data;
	}

	public function getKills() : int{
		return $this->data->getKills();
	}

	public function getDeaths() : int{
		return $this->data->getDeaths();
	}

	public function addKill() : void{
		$this->data->addKill();
	}

	public function addDeath() : void{
		$this->data->addDeath();
	}

	/**
	 * Remembers the last score tag pushed to the client so the heartbeat only
	 * resends it when the text actually changed.
	 */
	public function getLastNameTag() : ?string{
		return $this->lastNameTag;
	}

	public function setLastNameTag(?string $nameTag) : void{
		$this->lastNameTag = $nameTag;
	}

	/**
	 * Tick on which an ender pearl thrown by this player landed.
	 *
	 * The teleport damage vanilla deals afterwards is reported as fall damage,
	 * so this is what tells the two apart.
	 */
	public function getPearlLandTick() : int{
		return $this->pearlLandTick;
	}

	public function setPearlLandTick(int $tick) : void{
		$this->pearlLandTick = $tick;
	}

	public function save() : void{
		//Keep the stored casing in step with how the player spells his name.
		$this->data->setName($this->getName());
		$this->dataProvider->save($this->getName(), $this->data);
	}
}
