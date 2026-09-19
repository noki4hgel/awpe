# AWPE — Practice core

Practice server core for Essential / PocketMine-MP 5.

Everything the core shows or hands out is described in `plugin_data/Awpe/config.yml`;
the classes below only decide *when* something happens, never *what* it says.

## Layout

```
src/practice/
  Core.php                  plugin entry point — builds every manager and wires the listeners
  PracticeConfig.php        typed dot-path access to config.yml
  PlaceholderProvider.php   the {placeholders} every configurable text may use

  session/                  per-player state
    Session.php             state, arena, stats, scoreboard + boss bar, device, pearl timing
    SessionManager.php      one Session per online player
    SessionState.php        LOBBY | FFA | DUEL
    SessionListener.php     opens/closes sessions, join & quit broadcast

  player/
    PracticePlayer.php      Player subclass; exists only to make knockback tunable
    PlayerFactoryListener   installs it via PlayerCreationEvent
    PlayerRulesListener     server-wide rules (hunger off, click counting)

  lobby/
    LobbyManager.php        isInLobby(), join(), teleport(), sendItems(), shouldProtect()
    LobbyListener.php       enforces lobby.protection.*

  ffa/
    FFAManager.php          arena registry, join()/leave()/rekit(), arena picker form
    Arena.php               one arena: world, kit, knockback profile, pot counting
    KnockbackProfile.php    horizontal / vertical / attack-delay for one mode
    KnockbackStore.php      persists what /kbdata changes (plugin_data/Awpe/knockback.yml)
    FFAListener.php         keeps arena terrain and kit items intact

  duel/
    Ladder.php              one duel mode: kit, knockback, rules (no-damage, fall-depth), ranked or not
    LadderRegistry.php      ladders from config, only those some arena can host
    DuelMenu.php            unranked/ranked -> ladder picker, and the challenge picker
    DuelListener.php        damage, falls, pots and quits of the two players in a match
    arena/                  DuelArena (template world + two SpawnPoints), ArenaRegistry
    world/
      WorldCloner.php       copies a template per match, discards the copy, sweeps crash leftovers
      WorldFiles.php        the plain copy/delete, shared by the async tasks and the tests
      CopyWorldTask.php     the copy, off the main thread
      DeleteWorldTask.php   the delete, off the main thread
    match/
      DuelMatch.php         two players, a ladder, an arena copy, a state, per-player stats
      MatchState.php        PREPARING -> COUNTDOWN -> FIGHTING -> ENDING -> CLOSED
      MatchStats.php        hits, damage, longest combo, pots thrown
      MatchManager.php      the whole lifecycle: start, countdown, result, Elo, forfeit, cleanup
    queue/
      QueueEntry.php        who waits for what, since when, at which rating
      Matchmaker.php        pairs entries; the Elo window widens with waiting time
      QueueManager.php      join/leave, queue hotbar, pairs everyone once a second
    invite/
      Invite.php            one open challenge
      InviteManager.php     send / accept / deny, timeout, per-target cooldown, request setting
    elo/EloCalculator.php   standard Elo, K factor from config

  combat/
    CombatListener.php      hit distance, damage rules, kills, deaths
    KillEffects.php         kill lightning and the killer's banner, shared by FFA and duels
    CombatManager.php       combat tag (who is fighting, for how much longer)
    CombatLogListener.php   blocks commands while tagged
    ClickTracker.php        clicks over a one second window, for the CPS read-out

  cooldown/
    CooldownManager.php     named per-player timers, with progress
    ItemCooldowns.php       item name => cooldown, from config
    CooldownListener.php    blocks the throw and syncs the client-side overlay
    XpBarCooldown.php       draws the running item cooldown on the experience bar

  chat/
    ChatListener.php        chat rate limit and repeat blocking

  punishment/
    PunishmentType.php      BAN | MUTE | KICK
    Punishment.php          one record for every kind; never deleted, only revoked
    Duration.php            "30m", "7d", "1d12h", "perm" in; "6d 23h" out
    PunishmentManager.php   punish / revoke / lookups, rank hierarchy, announcements
    PunishmentListener.php  bans at the door (name and device), mutes in chat
    store/                  PunishmentStore interface, LevelDB and JSON backends

  kit/
    Kit.php                 an immutable loadout
    KitFactory.php          builds kits from config
    KitRegistry.php         every kit, built once at startup and shared by FFA and duels
    KitIssuer.php           the one way a kit reaches a player (FFA, /rekit, kills, duels)

  command/
    CommandManager.php      builds and registers every command, replaces clashing built-ins
    PracticeCommand.php     base: player-only + session lookup
    defaults/
      SpawnCommand.php      /spawn (aliases /lobby, /hub)
      RekitCommand.php      /rekit (alias /kit)
      KbDataCommand.php     /kbdata (alias /kb) - live knockback tuning, op only
      RankCommand.php       /rank - read and assign ranks, op only
    duel/
      DuelCommand.php       /duel [player] [ladder], /duel accept|deny [player], /duel leave
    punishment/
      PunishCommand.php     shared body of /ban, /mute, /kick
      RevokeCommand.php     shared body of /unban, /unmute
      PunishmentInput.php   parses <player> [duration] <reason...> [-s]
      HistoryCommand.php    /history <player> [page]

  item/
    PracticeSplashPotion    item that throws the practice-tuned projectile
    ItemSubstitutions.php   the one place that swaps a vanilla item for ours
    LobbyItemRegistry.php   builds the hotbar from config, resolves taps to actions
    LobbyItem.php           slot + tagged item stack
    LobbyItemHandler.php    interface implemented by item/types/*
    ItemListener.php        turns a tap into the bound action
    types/                  FFA selector (axe), duels (sword), leave queue (dye), settings (book)
    PracticeEnderPearl      pearl item that throws the silent projectile

  data/
    PlayerData.php          one record per player: counters, rank, settings
    DataProvider.php        storage interface, incl. the leaderboard top-N query
    LevelDbDataProvider     default backend (plugin_data/Awpe/players.ldb)
    JsonDataProvider        fallback backend
    DataProviderFactory     picks one, falls back instead of blocking startup

  rank/
    Rank.php                display, colour, chat/nametag format, permissions
    RankRegistry.php        ranks.yml, ordered by priority
    RankManager.php         reads/assigns ranks and grants their permissions
    RankChatFormatter       renders a chat line through the sender's rank
    RankListener.php        chat formatting, attachment cleanup

  settings/
    Settings.php            the ids the code reacts to
    SettingSection.php      a tab of the menu (from config)
    Setting.php             one toggle (from config)
    SettingRegistry.php     lookup + per-player values with proper defaults
    SettingsMenu.php        section picker, then the toggles

  leaderboard/
    Leaderboard.php         one floating board: where, what it ranks, how it reads
    LeaderboardManager.php  renders and pushes them to lobby players

  entity/
    PracticeSplashPotion    splash potion with practice potency
    PracticeEnderPearl      pearl that teleports without particles or sound
    PotionSettings.php      range / full-effect range / physics
    PracticeLightningBolt   cosmetic strike, shown per viewer
    EntityRegistry.php      registers both with the server

  scoreboard/               sidebar: Scoreboard (packets) + ScoreboardManager (content)
  bossbar/                  boss bar: BossBar (packets) + BossBarManager (content)
  nametag/NameTagManager    the ping / device / health line under a player's name
  world/VoidGenerator       lets imported arena maps that declare "void" load
  task/HeartbeatTask        one repeating task refreshing every live display
  task/XpBarCooldownTask    drives the experience-bar cooldown, once a tick
  task/DuelTask             matchmaking, invite expiry and match clocks, once a second
  util/                     placeholders, item parsing, device names, player/world helpers
  util/ConfigKeys           undoes YAML turning the keys y / n into booleans
  form/                     minimal SimpleForm implementation
```

## Knockback

Vanilla derives the vertical push from the horizontal force, so the two cannot be
tuned apart through the damage event alone. `PracticePlayer` replaces
`Living::knockBack()` and reads the active `KnockbackProfile` instead:

| key            | meaning                                            | vanilla |
|----------------|----------------------------------------------------|---------|
| `horizontal`   | sideways push                                      | `0.4`   |
| `vertical`     | upwards push                                       | `0.4`   |
| `attack-delay` | ticks of damage immunity after a hit ("tickrate")  | `10`    |

`ffa.knockback` is the default; each arena overrides only the keys it names.
The delay is applied through `EntityDamageEvent::setAttackCooldown()`.

### Tuning it live

```
/kbdata                                          list every mode
/kbdata <mode>                                   show one mode
/kbdata <mode> <vertical> <horizontal> <delay>   change it
/kbdata <mode> reset                             back to config.yml
```

Op only, and it also runs from the console. A change applies instantly to
everyone already in the arena and is written to `plugin_data/Awpe/knockback.yml`,
which is layered over `config.yml` on the next start — so config.yml keeps its
comments and `reset` simply drops the override.

## Potions

Vanilla measures a splash from the victim's **eyes** and loses potency from the
moment of impact, so a pot at your own feet heals about 60% of its value. The
core measures from the feet and gives the full effect inside
`potions.full-effect-range`, which is the behaviour practice players expect.
`ItemParser` swaps every vanilla splash potion for the practice one, so kits and
the pot counter never have to know.

## Noise

Two vanilla sounds are off by default, because at practice click speeds they
stop being feedback and turn into a rattle:

| switch | what it silences |
|---|---|
| `combat.disable-swing-sound` | the "whoosh" of a swing that hits nothing |
| `combat.disable-ender-pearl-effects` | the particles and the enderman sound of a landing pearl |

A silenced swing still **animates**: the miss event is what carries the sound,
and cancelling it would take the arm swing with it, so the animation is sent
again by hand — an opponent has to see the arm move. The pearl keeps its landing
damage, which has a switch of its own (`combat.disable-ender-pearl-damage`).

## Ranks

`ranks.yml` defines the ladder; the lowest `priority` is the default rank. A
rank carries its chat format, its colour and its permissions, which are granted
through a `PermissionAttachment` on join and released on quit. `/rank` will not
hand out anything at or above the sender's own rank (the console and operators
are exempt). The assignment lives in the player's stored record.

Above the head a player shows **only his name in the rank colour** — no prefix.
There is already a score tag on the line below, and a bracketed rank on top of
that is noise. The template is `nametag.name-format`, not a per-rank setting, so
the layout is decided once.

## Kills

The killer gets a banner across his screen naming who he took down — title and
subtitle from `messages.kill-title` / `kill-subtitle`, timings in ticks under
`combat.kill-title`. Only he sees it: the arena already gets the kill feed in
chat, so broadcasting it again would be noise. Like the kill lightning, it has
its own toggle in the Visual settings.

## Kits

Every kit hand-out goes through `KitIssuer`: arena entry, `/rekit`, the killer's
refill after a kill, and duels. A fresh kit always comes with full health and food
and **no item cooldown left over** — re-issued pearls are usable at once.

Clearing a cooldown takes three steps because it lives in three places: the core's
own timer, the vanilla `Player::$usedItemsCooldown` map the core fills so the client
can draw the timer (and which keeps refusing the item until it runs out), and the
drawing on the client itself. Internal timers like the chat limiter are left alone.

## Cooldowns

`cooldowns.items` maps a vanilla item name to a wait in seconds. The throw is
refused by the core's own timer rather than by the vanilla item cooldown, which
`Player::useHeldItem()` overwrites right after the event fires.

While an item cooldown runs it is drawn on the **experience bar**: the level is
the seconds left and the bar drains as it goes. Internal timers share the same
manager — the chat limiter is one — and are filtered out, since counting those
down in front of the player would mean nothing to him.

## Chat

`chat.cooldown` is the wait between messages and `chat.block-repeats` refuses a
message identical to the player's previous one, comparing case-insensitively
with runs of whitespace collapsed. Both are skipped by anyone holding
`chat.bypass-permission`, which staff ranks grant in `ranks.yml`. The check runs
at `LOWEST`, so a refused message never reaches the rank formatter.

## Punishments

| Command | Example |
|---|---|
| `/ban <player> [duration] <reason...> [-s]` | `/ban Steve 7d killaura` |
| `/mute <player> [duration] <reason...> [-s]` | `/mute Steve 30m spam` |
| `/kick <player> <reason...> [-s]` | `/kick Steve afk` |
| `/unban <player> [reason...]` | works for offline players |
| `/unmute <player> [reason...]` | |
| `/history <player> [page]` | every punishment, lifted and expired ones included |

Durations are `s m h d w mo y`, combinable (`1d12h`), or `perm`. Leave the duration
out and `punishments.default-duration` applies. `-s` skips the public announcement;
anyone with `awpe.punish.notify` still sees it, marked silent. `/ban`, `/kick` and
`/unban` replace the PocketMine built-ins (`/unban` takes `/pardon` with it).

Design points, most of them lessons from other practice cores:

- **One record type.** A ban and a mute differ in what they block, not in what
  they are, so both are a `Punishment` with a `PunishmentType`.
- **Nothing is deleted.** Lifting marks the record revoked with who and why, so
  `/history` is an audit trail.
- **Expiry is computed on read**, against the clock. No sweeping task, no window in
  which a lapsed ban still bites.
- **Names are keyed lowercase everywhere** — store, lookup and revoke. A mixed-case
  mismatch between those is how an unban silently fails to persist.
- **Exact names only.** A prefix match can announce one player and punish another.
- **Permanent really means permanent** (`expiresAt = null`), not a zero duration
  that expires instantly.
- **Rank hierarchy**: staff can only punish players ranked strictly below them.
- **Every refusal explains itself**: already punished, outranked, not online,
  missing reason.
- **Device bans**: the `DeviceId` of every login is linked to the name. A new account
  on a device that carries an active ban is refused (`punishments.device-bans`).
  IP addresses are deliberately not used — shared networks would ban innocent
  players.

Screens (ban, device, kick) and every message are templates in `config.yml`.

## Duels

The sword in the lobby opens the menu: **Unranked** or **Ranked**, then a ladder.
`/duel <player> [ladder]` challenges someone directly (without a ladder a picker
opens), and the target answers with `/duel accept` or `/duel deny`. The shipped
ladders are NoDebuff, Gapple, Fist and Sumo.

### Every match gets its own world

An arena's `template` is a world folder under `worlds/` that is **copied** for every
match and never played in itself. The copy is made on an async worker (under a
second for a small map), loaded with autosave off, and deleted once the match is
over. Blocks, dropped items and thrown potions can never leak into the next fight,
and any number of matches can run on the same map at once.

Two rules keep this safe:

- Copies are named `duel-<match id>-<8 hex>`, and **only folders with exactly that
  name are ever deleted**. A typo in `template` cannot take a real world with it.
- A crash leaves copies behind; startup sweeps them away before anything loads.

Keep the template out of `level-name` and out of `ffa.arenas`, and stop the server
before copying a new one in. The shipped `flat` arena (`duel_template_flat`, a copy
of the Fist FFA world) is a placeholder so duels work out of the box: replace it
with a real map and adjust `spawns`.

### A match

`PREPARING` (the copy is being made) -> `COUNTDOWN` (both players frozen and
invulnerable) -> `FIGHTING` -> `ENDING` (the result stays on screen for `end-delay`
seconds) -> both back to the lobby, the copy discarded.

- A fight ends on a lethal hit, in the void, or, for ladders with
  `rules.fall-depth`, on dropping that many blocks below the lower spawn. Nobody
  actually dies, so there is no respawn screen and no dropped kit.
- `time-limit` runs out as a draw.
- `rules.no-damage` (Sumo) cancels the damage but keeps the hit, so knockback and
  the attack cooldown behave exactly as usual.
- Both players get the same result card — ladder, map, time, both sets of
  statistics (hits, longest combo, damage, pots left and thrown), and on ranked
  the Elo change — but the headline is written from the reader's own side:
  "you beat him", not a third-person report of a fight one was just in.

**Leaving early** (quitting or `/spawn`) is judged by how far the match got. While
the world is still being prepared the match is called off. During the countdown an
unranked match is called off too, but a **ranked one counts as a loss**, otherwise
dodging a bad matchup would cost nothing. Once the fight has started, leaving is
always a loss.

### Ranked and the queue

Ratings are per ladder, stored in the player record under `duels.<ladder>.elo`
together with wins and losses. `ranked.k-factor` caps how far one match moves a
rating.

The ranked queue pairs players whose ratings lie within a window that starts at
`window.start` and widens by `growth` every `every` seconds, up to `max`. **Both**
players' windows must accept the gap: someone who has waited a minute reaches
further, but cannot pull in a player who has only just joined. The unranked queue
pairs in order of arrival. While queued, the hotbar holds a single item that leaves
the queue.

### Challenges

A challenge expires after `invites.timeout`, and the same player cannot be
challenged again within `invites.cooldown`. Players who do not want challenges turn
off **Duel requests** in the settings. Challenging someone who is busy (fighting,
queued, in a match) is refused with a reason.

## Settings

The lobby book opens a section picker, then a toggle list per section. Sections
and entries are both declared in `config.yml`; `settings/Settings.php` holds only
the ids the code reacts to, so presentation and behaviour stay apart. Values are
stored per player and fall back to the setting's configured default.

## Leaderboards

Floating boards in the lobby, ranked from storage rather than from who happens to
be online. Set the coordinates under `leaderboards.boards.<id>` — the shipped
board sits at 0.5/70/0.5, which is almost certainly not where you want it.
`field` is any numeric field of the stored record, as a dotted path: `kills`,
`deaths`, or `duels.nodebuff.elo` for a ranked board.

A bare `y:` key is the boolean **true** in YAML 1.1, which PHP then stores under the
key `1`, so a board's height used to be read as 0. Coordinates go through
`ConfigKeys`, which undoes that, so `x`, `y` and `z` can be written plainly.

## Adding things

* **A duel mode** — a block under `duels.ladders` naming a kit, then add its id to the
  `ladders` list of every arena that should host it. A ladder no arena lists stays
  hidden, with a warning on startup.
* **A duel map** — build it in its own world, stop the server, copy the folder into
  `worlds/`, and add a block under `duels.arenas` with `template`, `ladders` and the
  two `spawns`.
* **A lobby item** — implement `LobbyItemHandler`, register it in `Core::registerLobbyItems()`,
  then add a block under `lobby.items` using the same action id.
* **An FFA mode** — add a kit under `kits` and an arena under `ffa.arenas` pointing at it.
  A missing arena world is generated with `ffa.generator` when `ffa.auto-generate-worlds`
  is on; otherwise drop the world into `worlds/` yourself.
* **A placeholder** — add it to `PlaceholderProvider::forSession()`; it becomes available
  in the scoreboard, boss bar, nametag and every message at once.
* **A setting** — add a constant to `settings/Settings.php`, an entry under
  `settings.entries`, and read it with `SettingRegistry::isEnabled()`.
* **A rank** — one block in `ranks.yml`; no code involved.
* **An item cooldown** — one line under `cooldowns.items`: `<item name>: <seconds>`.
  It shows up on the experience bar on its own.
* **A leaderboard** — one block under `leaderboards.boards` naming any numeric
  field of `PlayerData`.
* **A command** — extend `PracticeCommand` (player-only, gets a `Session`) or plain
  `Command` for a server-level one like `/kbdata`, declare its permission in
  `plugin.yml` and add one line to `CommandManager::registerDefaults()`. If its name
  or an alias is taken by a PocketMine built-in, the built-in is replaced
  automatically; another plugin's command is left alone with a warning.
