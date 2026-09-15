<?php

declare(strict_types=1);

namespace practice\duel\world;

use pocketmine\scheduler\AsyncTask;

/**
 * Removes a finished match's world folder off the main thread.
 *
 * Reports back whether it worked: a folder that survives (a file locked by
 * another program, say) should at least be mentioned — the next startup sweeps
 * it up either way.
 */
final class DeleteWorldTask extends AsyncTask{

	private const KEY_CALLBACK = "callback";

	/**
	 * @phpstan-param (\Closure(bool) : void)|null $onDone called on the main thread
	 */
	public function __construct(
		private string $path,
		?\Closure $onDone = null
	){
		if($onDone !== null){
			$this->storeLocal(self::KEY_CALLBACK, $onDone);
		}
	}

	public function onRun() : void{
		$this->setResult(WorldFiles::delete($this->path));
	}

	public function onCompletion() : void{
		try{
			/** @var \Closure(bool) : void $onDone */
			$onDone = $this->fetchLocal(self::KEY_CALLBACK);
		}catch(\InvalidArgumentException){
			return;
		}

		$onDone($this->getResult() === true);
	}
}
