<?php

declare(strict_types=1);

namespace practice\item;

use pocketmine\item\Item;
use pocketmine\nbt\tag\StringTag;
use practice\session\Session;
use practice\util\ItemParser;
use function is_array;
use function is_int;
use function ksort;

/**
 * Owns the lobby hotbar: which items exist (from config) and what each of them
 * does (from registered handlers).
 *
 * Items are recognised through an NBT tag instead of their type, so two actions
 * may share the same vanilla item and a player cannot fake one by crafting it.
 */
final class LobbyItemRegistry{

	public const TAG_ACTION = "awpe:action";

	/** The hotbar a player has in the lobby by default. */
	public const LAYOUT_DEFAULT = "default";

	/** @var array<string, LobbyItemHandler> */
	private array $handlers = [];

	/**
	 * Several hotbars can exist side by side — the normal lobby one, and the one
	 * a player gets while waiting in a queue.
	 *
	 * @var array<string, array<int, LobbyItem>> layout => slot => item
	 */
	private array $layouts = [];

	public function register(LobbyItemHandler $handler) : void{
		$this->handlers[$handler->getActionId()] = $handler;
	}

	public function getHandler(string $actionId) : ?LobbyItemHandler{
		return $this->handlers[$actionId] ?? null;
	}

	/**
	 * Builds one hotbar layout from a config section. Entries whose action has
	 * no handler, or whose item name is unknown, are skipped.
	 *
	 * @param array<string, mixed> $config
	 *
	 * @return string[] names of the entries that could not be loaded
	 */
	public function loadFromConfig(array $config, string $layout = self::LAYOUT_DEFAULT) : array{
		$items = [];
		$failed = [];

		foreach($config as $actionId => $data){
			if(!is_array($data) || !isset($this->handlers[$actionId])){
				$failed[] = (string) $actionId;
				continue;
			}

			$slot = $data["slot"] ?? null;
			$item = ItemParser::parse($data);
			if(!is_int($slot) || $slot < 0 || $item === null){
				$failed[] = (string) $actionId;
				continue;
			}

			$item->getNamedTag()->setTag(self::TAG_ACTION, new StringTag((string) $actionId));
			$items[$slot] = new LobbyItem((string) $actionId, $slot, $item);
		}

		ksort($items);
		$this->layouts[$layout] = $items;

		return $failed;
	}

	/**
	 * @return array<int, LobbyItem>
	 */
	public function getItems(string $layout = self::LAYOUT_DEFAULT) : array{
		return $this->layouts[$layout] ?? [];
	}

	/**
	 * Returns the action bound to an item stack, or null if it carries none.
	 */
	public function getActionId(Item $item) : ?string{
		$tag = $item->getNamedTag()->getTag(self::TAG_ACTION);

		return $tag instanceof StringTag ? $tag->getValue() : null;
	}

	/**
	 * @return bool true when the item was a lobby item and its handler ran
	 */
	public function use(Session $session, Item $item) : bool{
		$actionId = $this->getActionId($item);
		if($actionId === null){
			return false;
		}

		$handler = $this->handlers[$actionId] ?? null;
		if($handler === null){
			return false;
		}

		$handler->onUse($session);

		return true;
	}
}
