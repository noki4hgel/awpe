<?php

declare(strict_types=1);

namespace practice\command\duel;

use pocketmine\Server;
use practice\command\PracticeCommand;
use practice\duel\DuelMenu;
use practice\duel\invite\InviteManager;
use practice\duel\LadderRegistry;
use practice\duel\queue\QueueManager;
use practice\PracticeConfig;
use practice\session\Session;
use practice\session\SessionManager;
use practice\util\Placeholder;
use function count;
use function strtolower;

/**
 * /duel — challenges and the queue from chat.
 *
 *   /duel                     open the duel menu
 *   /duel <player> [ladder]   challenge a player (no ladder: pick one in a form)
 *   /duel accept [player]     accept a challenge (no name: the latest one)
 *   /duel deny [player]       decline one
 *   /duel leave               leave the queue
 */
final class DuelCommand extends PracticeCommand{

	public const NAME = "duel";
	public const PERMISSION = "awpe.command.duel";

	private const ARG_ACCEPT = "accept";
	private const ARG_DENY = "deny";
	private const ARG_LEAVE = "leave";

	public function __construct(
		PracticeConfig $config,
		SessionManager $sessions,
		private readonly Server $server,
		private readonly LadderRegistry $ladders,
		private readonly QueueManager $queue,
		private readonly InviteManager $invites,
		private readonly DuelMenu $menu
	){
		parent::__construct(self::NAME, "Challenge a player or queue for a duel", self::PERMISSION, $config, $sessions);
		$this->setUsage($config->getMessage("usage-duel"));
	}

	protected function run(Session $session, array $args) : void{
		if(count($args) === 0){
			$this->menu->openModes($session);
			return;
		}

		switch(strtolower($args[0])){
			case self::ARG_ACCEPT:
				$this->report($session, $this->invites->accept($session, $args[1] ?? null), ["player" => $args[1] ?? ""]);
				return;

			case self::ARG_DENY:
				$error = $this->invites->deny($session, $args[1] ?? null);
				$this->report($session, $error, ["player" => $args[1] ?? ""], "duel-invite-you-denied");
				return;

			case self::ARG_LEAVE:
				if(!$this->queue->leave($session)){
					$this->send($session, "duel-queue-not-queued", []);
				}
				return;
		}

		$this->challenge($session, $args[0], $args[1] ?? null);
	}

	private function challenge(Session $from, string $targetName, ?string $ladderId) : void{
		$target = $this->server->getPlayerExact($targetName);
		$to = $target === null ? null : $this->sessions->get($target);
		if($to === null){
			$this->send($from, "duel-invite-target-offline", ["player" => $targetName]);
			return;
		}

		if($ladderId === null){
			$this->menu->openChallenge($from, $to);
			return;
		}

		$ladder = $this->ladders->get($ladderId);
		if($ladder === null){
			$this->send($from, "duel-unknown-ladder", ["ladder" => $ladderId]);
			return;
		}

		$this->report($from, $this->invites->send($from, $to, $ladder), ["player" => $to->getName(), "ladder" => $ladder->getDisplay()]);
	}

	/**
	 * @param array<string, string|int> $values
	 */
	private function report(Session $session, ?string $error, array $values, ?string $successKey = null) : void{
		if($error !== null){
			$this->send($session, $error, $values);
		}elseif($successKey !== null){
			$this->send($session, $successKey, $values);
		}
	}

	/**
	 * @param array<string, string|int> $values
	 */
	private function send(Session $session, string $key, array $values) : void{
		$message = $this->config->getMessage($key);
		if($message !== ""){
			$session->getPlayer()->sendMessage(Placeholder::apply($message, $values));
		}
	}
}
