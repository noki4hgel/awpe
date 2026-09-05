<?php

declare(strict_types=1);

namespace practice\bossbar;

use pocketmine\network\mcpe\protocol\BossEventPacket;
use pocketmine\network\mcpe\protocol\types\BossBarColor;
use pocketmine\player\Player;
use function max;
use function min;
use function strtolower;

/**
 * A boss bar bound to a single viewer.
 *
 * The bar is attached to the viewer's own actor id, which the client is always
 * aware of, so no dummy entity has to be spawned to keep it on screen.
 */
final class BossBar{

	private const COLORS = [
		"pink" => BossBarColor::PINK,
		"blue" => BossBarColor::BLUE,
		"red" => BossBarColor::RED,
		"green" => BossBarColor::GREEN,
		"yellow" => BossBarColor::YELLOW,
		"purple" => BossBarColor::PURPLE,
		"white" => BossBarColor::WHITE,
	];

	private bool $spawned = false;
	private string $title = "";
	private float $percentage = 1.0;

	public function __construct(
		private readonly Player $player,
		private int $color = BossBarColor::PURPLE
	){
	}

	public static function colorFromString(string $name, int $fallback = BossBarColor::PURPLE) : int{
		return self::COLORS[strtolower($name)] ?? $fallback;
	}

	public function isSpawned() : bool{
		return $this->spawned;
	}

	public function getTitle() : string{
		return $this->title;
	}

	public function getPercentage() : float{
		return $this->percentage;
	}

	public function getColor() : int{
		return $this->color;
	}

	public function setColor(int $color) : void{
		if($this->color === $color){
			return;
		}

		$this->color = $color;
		if($this->spawned){
			$this->send(BossEventPacket::properties($this->player->getId(), false, $this->color));
		}
	}

	/**
	 * Shows the bar, or updates only the parts that changed if it is already up.
	 */
	public function display(string $title, float $percentage) : void{
		$percentage = max(0.0, min(1.0, $percentage));

		if(!$this->spawned){
			$this->send(BossEventPacket::show($this->player->getId(), $title, $percentage, false, $this->color));
			$this->spawned = true;
			$this->title = $title;
			$this->percentage = $percentage;
			return;
		}

		if($this->title !== $title){
			$this->send(BossEventPacket::title($this->player->getId(), $title));
			$this->title = $title;
		}
		if($this->percentage !== $percentage){
			$this->send(BossEventPacket::healthPercent($this->player->getId(), $percentage));
			$this->percentage = $percentage;
		}
	}

	public function remove() : void{
		if(!$this->spawned){
			return;
		}

		$this->send(BossEventPacket::hide($this->player->getId()));
		$this->spawned = false;
		$this->title = "";
	}

	private function send(BossEventPacket $packet) : void{
		if(!$this->player->isConnected()){
			return;
		}

		$this->player->getNetworkSession()->sendDataPacket($packet);
	}
}
