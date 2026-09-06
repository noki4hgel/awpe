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
    CombatListener.php      hit distance, fall & pearl damage rules, kills, deaths
    CombatManager.php       combat tag (who is fighting, for how much longer)
    CombatLogListener.php   blocks commands while tagged

  cooldown/
    CooldownManager.php     named per-player timers
    ItemCooldowns.php       item name => cooldown, from config
    CooldownListener.php    blocks the throw and syncs the client-side overlay

  command/
    PracticeCommand.php     base: player-only + session lookup
    SpawnCommand.php        /spawn (aliases /lobby, /hub)
    RekitCommand.php        /rekit (alias /kit)
    KbDataCommand.php       /kbdata (alias /kb) - live knockback tuning, op only

  item/
    LobbyItemRegistry.php   builds the hotbar from config, resolves taps to actions
    LobbyItem.php           slot + tagged item stack
    LobbyItemHandler.php    interface implemented by item/types/*
    ItemListener.php        turns a tap into the bound action
    types/                  FFA selector (axe), private arenas (clock), settings (book)

  stats/
    StatsProvider.php       storage interface
    LevelDbStatsProvider    default backend (plugin_data/Awpe/stats.ldb)
    JsonStatsProvider       fallback backend
    StatsProviderFactory    picks one, falls back instead of blocking startup
    PlayerStats.php         the counters themselves

  scoreboard/               sidebar: Scoreboard (packets) + ScoreboardManager (content)
  bossbar/                  boss bar: BossBar (packets) + BossBarManager (content)
  nametag/NameTagManager    the ping / device / health line under a player's name
  world/VoidGenerator       lets imported arena maps that declare "void" load
  task/HeartbeatTask        one repeating task refreshing every live display
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

## Adding things

* **A lobby item** — implement `LobbyItemHandler`, register it in `Core::registerLobbyItems()`,
  then add a block under `lobby.items` using the same action id.
* **An FFA mode** — add a kit under `kits` and an arena under `ffa.arenas` pointing at it.
  A missing arena world is generated with `ffa.generator` when `ffa.auto-generate-worlds`
  is on; otherwise drop the world into `worlds/` yourself.
* **An item cooldown** — one line under `cooldowns.items`: `<item name>: <seconds>`.
* **A placeholder** — add it to `PlaceholderProvider::forSession()`; it becomes available
  in the scoreboard, boss bar, nametag and every message at once.
* **A command** — extend `PracticeCommand` (player-only, gets a `Session`) or plain
  `Command` for a server-level one like `/kbdata`, declare its permission in
  `plugin.yml` and register it in `Core::registerCommands()`.
