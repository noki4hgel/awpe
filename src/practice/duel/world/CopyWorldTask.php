<?php

declare(strict_types=1);

namespace practice\duel\world;

use pocketmine\scheduler\AsyncTask;

/**
 * Copies a template world off the main thread, so starting a match never
 * stalls the server for as long as the disk takes.
 */
final class CopyWorldTask extends AsyncTask{

	private const KEY_CALLBACK = "callback";

	/**
	 * @phpstan-param \Closure(bool) : void $onDone called on the main thread
	 */
	public function __construct(
		private string $source,
		private string $destination,
		\Closure $onDone
	){
		$this->storeLocal(self::KEY_CALLBACK, $onDone);
	}

	public function onRun() : void{
		$this->setResult(WorldFiles::copy($this->source, $this->destination));
	}

	public function onCompletion() : void{
		/** @var \Closure(bool) : void $onDone */
		$onDone = $this->fetchLocal(self::KEY_CALLBACK);
		$onDone($this->getResult() === true);
	}
}
