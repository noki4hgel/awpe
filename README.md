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
    SessionState.php        LOBBY | FFA
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
    kit/Kit.php             an immutable loadout
    kit/KitFactory.php      builds kits from config

  combat/
    CombatListener.php      hit distance, damage rules, kills, deaths, kill lightning
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

  command/
    PracticeCommand.php     base: player-only + session lookup
    SpawnCommand.php        /spawn (aliases /lobby, /hub)
    RekitCommand.php        /rekit (alias /kit)
    KbDataCommand.php       /kbdata (alias /kb) - live knockback tuning, op only
    RankCommand.php         /rank - read and assign ranks, op only

  item/
    PracticeSplashPotion    item that throws the practice-tuned projectile
    ItemSubstitutions.php   the one place that swaps a vanilla item for ours
    LobbyItemRegistry.php   builds the hotbar from config, resolves taps to actions
    LobbyItem.php           slot + tagged item stack
    LobbyItemHandler.php    interface implemented by item/types/*
    ItemListener.php        turns a tap into the bound action
    types/                  FFA selector (axe), private arenas (clock), settings (book)

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
    PotionSettings.php      range / full-effect range / physics
    PracticeLightningBolt   cosmetic strike, shown per viewer
    EntityRegistry.php      registers both with the server

  scoreboard/               sidebar: Scoreboard (packets) + ScoreboardManager (content)
  bossbar/                  boss bar: BossBar (packets) + BossBarManager (content)
  nametag/NameTagManager    the ping / device / health line under a player's name
  world/VoidGenerator       lets imported arena maps that declare "void" load
  task/HeartbeatTask        one repeating task refreshing every live display
  task/XpBarCooldownTask    drives the experience-bar cooldown, once a tick
  util/                     placeholders, item parsing, device names, player/world helpers
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

## Settings

The lobby book opens a section picker, then a toggle list per section. Sections
and entries are both declared in `config.yml`; `settings/Settings.php` holds only
the ids the code reacts to, so presentation and behaviour stay apart. Values are
stored per player and fall back to the setting's configured default.

## Leaderboards

Floating boards in the lobby, ranked from storage rather than from who happens to
be online. Set the coordinates under `leaderboards.boards.<id>` — the shipped
board sits at 0.5/70/0.5, which is almost certainly not where you want it.
`field` is any numeric field of the stored record (`kills`, `deaths`).

## Adding things

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
  `plugin.yml` and register it in `Core::registerCommands()`.
