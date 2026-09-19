<?php

declare(strict_types=1);

namespace practice\duel\match;

use pocketmine\player\GameMode;
use pocketmine\world\sound\ClickSound;
use pocketmine\world\sound\XpLevelUpSound;
use pocketmine\world\World;
use practice\combat\CombatManager;
use practice\combat\KillEffects;
use practice\duel\arena\ArenaRegistry;
use practice\duel\elo\EloCalculator;
use practice\duel\Ladder;
use practice\duel\world\WorldCloner;
use practice\kit\KitIssuer;
use practice\lobby\LobbyManager;
use practice\player\PracticePlayer;
use practice\PracticeConfig;
use practice\session\Session;
use practice\session\SessionState;
use practice\util\Placeholder;
use practice\util\PlayerUtil;
use function count;
use function intdiv;
use function max;
use function round;
use function spl_object_id;
use function sprintf;
use function time;

/**
 * Runs every match from "found" to "cleaned up".
 *
 *   start()        pick an arena, copy its world in the background
 *   world ready    teleport, kit, freeze, countdown
 *   tick()         countdown, time limit, end pause — once a second, all matches
 *   handleDeath()  the fight is decided
 *   forfeit()      somebody left; what that means depends on how far it got
 *   close()        everyone out, the world copy thrown away
 *
 * Leaving early is judged by stage: before the fight starts an unranked match
 * is simply called off, while a ranked one counts as a loss for whoever left —
 * otherwise dodging a bad matchup would cost nothing.
 */
final class MatchManager{

	/** @var array<int, DuelMatch> */
	private array $matches = [];

	/** @var array<int, DuelMatch> player object id => match */
	private array $byPlayer = [];

	/** @var array<int, true> player object id => left before the match closed */
	private array $departed = [];

	private int $nextId = 1;

	public function __construct(
		private readonly PracticeConfig $config,
		private readonly LobbyManager $lobby,
		private readonly KitIssuer $kitIssuer,
		private readonly ArenaRegistry $arenas,
		private readonly WorldCloner $cloner,
		private readonly EloCalculator $elo,
		private readonly KillEffects $killEffects,
		private readonly CombatManager $combat
	){
	}

	public function getMatchOf(Session $session) : ?DuelMatch{
		return $this->byPlayer[spl_object_id($session->getPlayer())] ?? null;
	}

	public function isInMatch(Session $session) : bool{
		return $this->getMatchOf($session) !== null;
	}

	/**
	 * Players currently fighting on a ladder, for the menus.
	 */
	public function countPlaying(string $ladderId) : int{
		$players = 0;
		foreach($this->matches as $match){
			if($match->getLadder()->getId() === $ladderId){
				$players += 2;
			}
		}

		return $players;
	}

	/**
	 * @return array<int, DuelMatch>
	 */
	public function getMatches() : array{
		return $this->matches;
	}

	/**
	 * Starts a match. Returns false when the ladder has no arena or either
	 * player is already taken; both are told why.
	 */
	public function start(Ladder $ladder, bool $ranked, Session $first, Session $second) : bool{
		if($this->isInMatch($first) || $this->isInMatch($second)){
			return false;
		}

		$arena = $this->arenas->pickFor($ladder->getId());
		if($arena === null){
			foreach([$first, $second] as $session){
				$this->send($session, "duel-no-arena", ["ladder" => $ladder->getDisplay()]);
			}
			return false;
		}

		$match = new DuelMatch(
			$this->nextId++,
			$ladder,
			$arena,
			$ranked,
			$first,
			$second,
			max(0, $this->config->getInt("duels.countdown", 5))
		);
		$this->matches[$match->getId()] = $match;
		foreach($match->getParticipants() as $session){
			$this->byPlayer[spl_object_id($session->getPlayer())] = $match;
			$this->send($session, $ranked ? "duel-found-ranked" : "duel-found", $this->describe($match, $session));
		}

		$this->cloner->clone($arena->getTemplateWorld(), WorldCloner::nameFor($match->getId()), function(?World $world) use ($match) : void{
			$this->onWorldReady($match, $world);
		});

		return true;
	}

	/**
	 * Called once a second for every match.
	 */
	public function tick() : void{
		$now = time();
		foreach($this->matches as $match){
			match($match->getState()){
				MatchState::COUNTDOWN => $this->tickCountdown($match, $now),
				MatchState::FIGHTING => $this->tickFight($match, $now),
				MatchState::ENDING => $now >= $match->getEndsAt() ? $this->close($match) : null,
				default => null
			};
		}
	}

	/**
	 * A participant died. Only counts while the fight is on — the countdown and
	 * the pause after a win are invulnerable.
	 *
	 * @return bool whether the death decided the match
	 */
	public function handleDeath(Session $loser) : bool{
		$match = $this->getMatchOf($loser);
		if($match === null || !$match->isFighting()){
			return false;
		}

		$winner = $match->getOpponent($loser);
		$this->killEffects->play($winner, $loser);
		$this->finish($match, $winner);

		return true;
	}

	/**
	 * A participant is leaving: he quit ($quitting), or used /spawn.
	 */
	public function forfeit(Session $leaver, bool $quitting) : void{
		$match = $this->getMatchOf($leaver);
		if($match === null){
			return;
		}

		$opponent = $match->getOpponent($leaver);
		$this->departed[spl_object_id($leaver->getPlayer())] = true;

		switch($match->getState()){
			case MatchState::PREPARING:
				$this->cancel($match, "duel-cancelled-left", ["player" => $leaver->getName()]);
				break;

			case MatchState::COUNTDOWN:
				if(!$match->isRanked()){
					$this->cancel($match, "duel-cancelled-left", ["player" => $leaver->getName()]);
					break;
				}
				//Ranked: leaving on the countdown is dodging, and dodging is a loss.
				$this->send($opponent, "duel-forfeit", ["player" => $leaver->getName()]);
				$this->finish($match, $opponent);
				break;

			case MatchState::FIGHTING:
				$this->send($opponent, "duel-forfeit", ["player" => $leaver->getName()]);
				$this->finish($match, $opponent);
				break;

			default:
				//Already decided; he is just leaving the end screen early.
				break;
		}

		unset($this->byPlayer[spl_object_id($leaver->getPlayer())]);
		//Somebody who quit is on his way out already; only /spawn needs a lobby.
		if(!$quitting && $leaver->getPlayer()->isConnected()){
			$this->lobby->join($leaver);
		}

		if(!$match->isClosed() && $this->allDeparted($match)){
			$this->close($match);
		}
	}

	/**
	 * Server shutdown: no results, no pause, everything torn down now.
	 */
	public function shutdown() : void{
		foreach($this->matches as $match){
			foreach($match->getParticipants() as $session){
				if($session->getPlayer()->isConnected() && !$this->hasDeparted($session)){
					$this->lobby->join($session);
				}
			}

			$world = $match->getWorld();
			if($world !== null){
				$this->cloner->discardNow($world);
			}
			$match->setState(MatchState::CLOSED);
		}

		$this->matches = [];
		$this->byPlayer = [];
		$this->departed = [];
	}

	/**
	 * Placeholder values for one participant's view of a match.
	 *
	 * @return array<string, string|int>
	 */
	public function describe(DuelMatch $match, Session $viewer) : array{
		$opponent = $match->getOpponent($viewer);
		$elapsed = $match->getFightStartedAt() > 0 ? time() - $match->getFightStartedAt() : 0;

		return [
			"bar" => $this->config->getMessage("duel-bar"),
			"ladder" => $match->getLadder()->getDisplay(),
			"map" => $match->getArena()->getDisplay(),
			"opponent" => $opponent->getName(),
			"opponent_ping" => $opponent->getPing(),
			"duel_time" => self::clock($elapsed),
			"ranked_tag" => $match->isRanked() ? $this->config->getMessage("duel-ranked-tag") : "",
			"elo" => $viewer->getData()->getDuelElo($match->getLadder()->getId(), $this->startingElo()),
			"opponent_elo" => $opponent->getData()->getDuelElo($match->getLadder()->getId(), $this->startingElo())
		];
	}

	private function onWorldReady(DuelMatch $match, ?World $world) : void{
		if($match->isClosed()){
			//Called off while the copy was being made.
			if($world !== null){
				$this->cloner->discard($world);
			}
			return;
		}

		if($world === null){
			$this->cancel($match, "duel-arena-failed", []);
			return;
		}
		$match->setWorld($world);

		foreach($match->getParticipants() as $session){
			$player = $session->getPlayer();
			//Anyone who went somewhere else in the meantime - FFA, offline - calls it off.
			if(!$player->isConnected() || !$session->isInLobby()){
				$this->cancel($match, "duel-cancelled-left", ["player" => $session->getName()]);
				return;
			}
		}

		$gameMode = PlayerUtil::gameModeFromString($this->config->getString("duels.gamemode", "adventure"));
		$spawns = [$match->getArena()->getFirstSpawn(), $match->getArena()->getSecondSpawn()];
		foreach($match->getParticipants() as $index => $session){
			$player = $session->getPlayer();

			PlayerUtil::reset($player, $gameMode);
			$player->teleport($spawns[$index]->toLocation($world));
			$this->kitIssuer->issue($player, $match->getLadder()->getKit());
			if($player instanceof PracticePlayer){
				$player->setKnockbackProfile($match->getLadder()->getKnockback());
			}
			//Frozen for the countdown; the fight starts with both on their marks.
			$player->setNoClientPredictions();

			$session->setState(SessionState::DUEL);
			$session->setArenaId(null);
		}

		$match->setState(MatchState::COUNTDOWN);
	}

	private function tickCountdown(DuelMatch $match, int $now) : void{
		$seconds = $match->takeCountdownSecond();
		if($seconds > 0){
			foreach($match->getParticipants() as $session){
				$values = $this->describe($match, $session) + ["seconds" => $seconds];
				$this->title($session, "duel-countdown-title", "duel-countdown-subtitle", $values);
				$player = $session->getPlayer();
				$player->getWorld()->addSound($player->getPosition(), new ClickSound(), [$player]);
			}
			return;
		}

		foreach($match->getParticipants() as $session){
			$player = $session->getPlayer();
			$player->setNoClientPredictions(false);
			$this->title($session, "duel-fight-title", "duel-fight-subtitle", $this->describe($match, $session));
			$player->getWorld()->addSound($player->getPosition(), new XpLevelUpSound(1), [$player]);
		}

		$match->setFightStartedAt($now);
		$match->setState(MatchState::FIGHTING);
	}

	private function tickFight(DuelMatch $match, int $now) : void{
		$limit = $this->config->getInt("duels.time-limit", 600);
		if($limit > 0 && $now - $match->getFightStartedAt() >= $limit){
			$this->finish($match, null);
		}
	}

	/**
	 * Decides the match: records the result, shows it, and starts the pause
	 * before everyone is sent back. A null winner is a draw.
	 */
	private function finish(DuelMatch $match, ?Session $winner) : void{
		if($match->getState() === MatchState::ENDING || $match->isClosed()){
			return;
		}

		$loser = $winner === null ? null : $match->getOpponent($winner);
		$eloChange = $this->recordResult($match, $winner, $loser);

		//Counted before anybody's inventory is touched.
		$this->sendSummary($match, $winner, $loser, $eloChange);

		foreach($match->getParticipants() as $session){
			$this->combat->untag($session->getPlayer());
			$player = $session->getPlayer();
			if(!$player->isConnected() || $this->hasDeparted($session)){
				continue;
			}

			$player->setNoClientPredictions(false);
			if($winner === null){
				$this->title($session, "duel-draw-title", "duel-draw-subtitle", $this->describe($match, $session));
			}elseif($session === $winner){
				$this->title($session, "duel-win-title", "duel-win-subtitle", $this->describe($match, $session));
			}else{
				//The loser watches the last seconds rather than being bounced out.
				PlayerUtil::reset($player, GameMode::SPECTATOR);
				$this->title($session, "duel-lose-title", "duel-lose-subtitle", $this->describe($match, $session));
			}
		}

		$match->setState(MatchState::ENDING);
		$match->setEndsAt(time() + max(0, $this->config->getInt("duels.end-delay", 3)));
	}

	/**
	 * Stores wins, losses and (for ranked) Elo for both sides.
	 *
	 * @return int|null points moved, or null when no rating changed
	 */
	private function recordResult(DuelMatch $match, ?Session $winner, ?Session $loser) : ?int{
		if($winner === null || $loser === null){
			return null;
		}

		$ladderId = $match->getLadder()->getId();
		$starting = $this->startingElo();

		$change = null;
		$winnerElo = null;
		$loserElo = null;
		if($match->isRanked()){
			$winnerElo = $winner->getData()->getDuelElo($ladderId, $starting);
			$loserElo = $loser->getData()->getDuelElo($ladderId, $starting);
			$change = $this->elo->change($winnerElo, $loserElo);
			$winnerElo += $change;
			$loserElo -= $change;
		}

		$winner->getData()->recordDuel($ladderId, true, $winnerElo, $starting);
		$loser->getData()->recordDuel($ladderId, false, $loserElo, $starting);
		$winner->save();
		$loser->save();

		return $change;
	}

	/**
	 * Prints the result card. Both players get the same statistics, but the
	 * headline is written from the reader's own side - "you beat him" reads
	 * better than a third-person report of a fight one was just in.
	 */
	private function sendSummary(DuelMatch $match, ?Session $winner, ?Session $loser, ?int $eloChange) : void{
		$ladder = $match->getLadder();
		$elapsed = $match->getFightStartedAt() > 0 ? time() - $match->getFightStartedAt() : 0;
		$base = [
			"bar" => $this->config->getMessage("duel-bar"),
			"ladder" => $ladder->getDisplay(),
			"map" => $match->getArena()->getDisplay(),
			"ranked_tag" => $match->isRanked() ? $this->config->getMessage("duel-ranked-tag") : "",
			"duration" => self::clock($elapsed),
			"winner" => $winner?->getName() ?? "",
			"loser" => $loser?->getName() ?? "",
			"change" => $eloChange ?? 0,
			"winner_elo" => $winner?->getData()->getDuelElo($ladder->getId(), $this->startingElo()) ?? 0,
			"loser_elo" => $loser?->getData()->getDuelElo($ladder->getId(), $this->startingElo()) ?? 0
		];

		$statLines = [];
		foreach($this->summaryOrder($match, $winner) as $session){
			foreach($this->statLines($match, $session, $winner) as $line){
				$statLines[] = $line;
			}
		}

		foreach($match->getParticipants() as $session){
			$player = $session->getPlayer();
			if(!$player->isConnected()){
				continue;
			}

			$lines = [
				$this->config->getMessage("duel-summary-header"),
				$this->config->getMessage("duel-summary-title"),
				$this->config->getMessage($winner === null ? "duel-summary-draw" : ($session === $winner ? "duel-summary-you-won" : "duel-summary-you-lost"))
			];
			if($eloChange !== null){
				$lines[] = $this->config->getMessage("duel-summary-elo");
			}
			foreach($statLines as $statLine){
				$lines[] = $statLine;
			}
			$lines[] = $this->config->getMessage("duel-summary-footer");

			//Every line at once: the frame carries placeholders too, and the
			//statistics have had theirs filled in already.
			$lines = Placeholder::applyAll($lines, $base + ["opponent" => $match->getOpponent($session)->getName()]);

			foreach($lines as $line){
				if($line !== ""){
					$player->sendMessage($line);
				}
			}
		}
	}

	/**
	 * The winner's statistics go first; a draw keeps the match order.
	 *
	 * @return Session[]
	 */
	private function summaryOrder(DuelMatch $match, ?Session $winner) : array{
		$participants = $match->getParticipants();

		return $winner === null || $participants[0] === $winner ? $participants : [$winner, $match->getOpponent($winner)];
	}

	/**
	 * One player's block of the card. Which lines it holds is up to config:
	 * ladders without pots get a list of their own, so a Fist summary does not
	 * report potion accuracy nobody could have.
	 *
	 * @return string[]
	 */
	private function statLines(DuelMatch $match, Session $session, ?Session $winner) : array{
		$ladder = $match->getLadder();
		$stats = $match->getStats($session);
		$color = match(true){
			$winner === null => "duel-summary-draw-color",
			$session === $winner => "duel-summary-winner-color",
			default => "duel-summary-loser-color"
		};

		$lines = [];
		foreach($this->config->getArray($ladder->getPotItem() === null ? "messages.duel-summary-stats-nopots" : "messages.duel-summary-stats") as $line){
			$lines[] = (string) $line;
		}

		return Placeholder::applyAll($lines, [
			"color" => $this->config->getMessage($color),
			"player" => $session->getName(),
			"hits" => $stats->getHits(),
			"combo" => $stats->getLongestCombo(),
			"damage" => sprintf("%.1f", round($stats->getDamage() / 2, 1)),
			"criticals" => $stats->getCriticals(),
			"accuracy" => sprintf("%.2f", $stats->getPotionAccuracy()),
			"pots_thrown" => $stats->getPotsThrown(),
			"pots_left" => $this->countPots($session, $ladder)
		]);
	}

	private function close(DuelMatch $match) : void{
		if($match->isClosed()){
			return;
		}
		$match->setState(MatchState::CLOSED);

		foreach($match->getParticipants() as $session){
			$player = $session->getPlayer();
			if($player->isConnected() && !$this->hasDeparted($session)){
				$this->lobby->join($session);
			}
			unset($this->byPlayer[spl_object_id($player)], $this->departed[spl_object_id($player)]);
		}

		$world = $match->getWorld();
		if($world !== null){
			$this->cloner->discard($world);
		}
		unset($this->matches[$match->getId()]);
	}

	/**
	 * Calls a match off without a result.
	 *
	 * @param array<string, string|int> $values
	 */
	private function cancel(DuelMatch $match, string $messageKey, array $values) : void{
		foreach($match->getParticipants() as $session){
			$player = $session->getPlayer();
			if(!$player->isConnected() || $this->hasDeparted($session)){
				continue;
			}
			$this->send($session, $messageKey, $values);
			//Only players standing in the arena are moved. Anyone who went to FFA
			//while the copy was being made stays where he chose to be.
			if($session->isInDuel()){
				$this->lobby->join($session);
			}
		}

		//close() would send them to the lobby again; they are already handled.
		foreach($match->getParticipants() as $session){
			$this->departed[spl_object_id($session->getPlayer())] = true;
		}
		$this->close($match);
	}

	private function countPots(Session $session, Ladder $ladder) : int{
		$pot = $ladder->getPotItem();
		if($pot === null || !$session->getPlayer()->isConnected()){
			return 0;
		}

		$count = 0;
		foreach($session->getPlayer()->getInventory()->getContents() as $item){
			if($item->equals($pot, true, false)){
				$count += $item->getCount();
			}
		}

		return $count;
	}

	private function allDeparted(DuelMatch $match) : bool{
		foreach($match->getParticipants() as $session){
			if(!$this->hasDeparted($session) && $session->getPlayer()->isConnected()){
				return false;
			}
		}

		return true;
	}

	private function hasDeparted(Session $session) : bool{
		return isset($this->departed[spl_object_id($session->getPlayer())]);
	}

	private function startingElo() : int{
		return $this->config->getInt("duels.ranked.starting-elo", 1000);
	}

	/**
	 * @param array<string, string|int> $values
	 */
	private function title(Session $session, string $titleKey, string $subtitleKey, array $values) : void{
		$player = $session->getPlayer();
		if(!$player->isConnected()){
			return;
		}

		$player->sendTitle(
			Placeholder::apply($this->config->getMessage($titleKey), $values),
			Placeholder::apply($this->config->getMessage($subtitleKey), $values),
			$this->config->getInt("duels.titles.fade-in", 2),
			$this->config->getInt("duels.titles.stay", 18),
			$this->config->getInt("duels.titles.fade-out", 4)
		);
	}

	/**
	 * @param array<string, string|int> $values
	 */
	private function send(Session $session, string $key, array $values) : void{
		$message = $this->config->getMessage($key);
		if($message !== "" && $session->getPlayer()->isConnected()){
			$session->getPlayer()->sendMessage(Placeholder::apply($message, $values));
		}
	}

	private static function clock(int $seconds) : string{
		return sprintf("%d:%02d", intdiv($seconds, 60), $seconds % 60);
	}
}
