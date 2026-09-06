<?php

declare(strict_types=1);

namespace practice\rank;

use pocketmine\permission\PermissionAttachment;
use pocketmine\player\Player;
use pocketmine\plugin\Plugin;
use practice\session\Session;
use function count;
use function spl_object_id;

/**
 * Applies ranks to players: permissions now, chat and nametag through the
 * listener and the nametag manager.
 *
 * A player's rank lives in his PlayerData record, so it is written by whatever
 * storage backend is configured.
 */
final class RankManager{

	/** @var array<int, PermissionAttachment> player object id => the attachment we granted */
	private array $attachments = [];

	public function __construct(
		private readonly Plugin $plugin,
		private readonly RankRegistry $registry
	){
	}

	public function getRegistry() : RankRegistry{
		return $this->registry;
	}

	/**
	 * The player's rank, falling back to the default when he has none or his
	 * stored rank no longer exists in ranks.yml.
	 */
	public function get(Session $session) : ?Rank{
		return $this->registry->get($session->getData()->getRank()) ?? $this->registry->getDefault();
	}

	/**
	 * Assigns a rank and grants its permissions immediately.
	 */
	public function set(Session $session, Rank $rank) : void{
		$session->getData()->setRank($rank->getId());
		$session->save();

		$this->apply($session);
	}

	/**
	 * Grants the permissions of the player's current rank, replacing anything
	 * granted for a previous one.
	 */
	public function apply(Session $session) : void{
		$player = $session->getPlayer();
		$rank = $this->get($session);

		$this->revoke($player);
		if($rank === null){
			return;
		}

		$permissions = $rank->getPermissions();
		if(count($permissions) === 0){
			return;
		}

		$attachment = $player->addAttachment($this->plugin);
		foreach($permissions as $permission){
			$attachment->setPermission($permission, true);
		}

		$this->attachments[spl_object_id($player)] = $attachment;
	}

	public function revoke(Player $player) : void{
		$id = spl_object_id($player);
		$attachment = $this->attachments[$id] ?? null;
		if($attachment !== null){
			$player->removeAttachment($attachment);
			unset($this->attachments[$id]);
		}
	}
}
