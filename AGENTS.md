# MyPills API — Agent Instructions

Symfony 8.0 backend (PHP 8.5+, PostgreSQL 16, Doctrine ORM 3) for a Flutter medication-management app. Architecture: DDD + CQRS + Vertical Slices.

## Architecture — Strict Rules

```
UI → Application → Domain
Infrastructure → Domain (implements interfaces)
```

1. **Domain** imports nothing from Application, Infrastructure, or UI. Pure PHP — no Symfony, no Doctrine, no framework coupling.
2. **Application** contains Command/Query objects and Handlers (`MessageHandlerInterface`). Handlers depend on Domain interfaces only.
3. **Infrastructure** implements Domain interfaces. Doctrine entities live here and **must not leak** outside — map to domain entities at the repository boundary.
4. **UI** (Controllers) dispatches via the bus (`$commandBus->dispatch(...)` / `$queryBus->dispatch(...)`), never calls repositories or services directly.
5. Use cases return `Result<T, Failure>` — no thrown exceptions across layers.

## Bus Routing

| Bus           | Transport    | Notes                                        |
|---------------|--------------|----------------------------------------------|
| `command.bus` | `sync://`    | Synchronous                                  |
| `query.bus`   | `sync://`    | Synchronous                                  |
| `event.bus`   | `event.async`| Doctrine transport. All `Shared\Domain\DomainEvent` subclasses route here automatically |

## Bounded Contexts

```
src/
  Shared/              UserId, ProfileId, Email, Result<T>, Failure, bus interfaces
  Identity/            Account + OAuth links
  Profile/             PatientProfile (N per Account)
  Medication/          Medication (per Profile)
  Schedule/            Daily | DailyInterval | SpecificDays (STI)
  DoseEvent/           taken/skipped/pending occurrences
  Notification/        NotificationPreferences + ScheduledPush + DeviceToken
  CalendarIntegration/ CalendarLink (google|microsoft) + token vault
```

### Folder Shape (every context)

```
<Context>/
  Domain/           Entities, value objects, repo interfaces, domain events
  Application/
    Command/        <UseCase>Command.php + <UseCase>Handler.php
    Query/          <UseCase>Query.php + <UseCase>Handler.php
  Infrastructure/
    Persistence/    Doctrine entity mappings (PHP attributes only)
  UI/Http/          Controllers
```

### Namespaces

Each context has its own PSR-4 root (`Identity\`, `Profile\`, etc.). `App\` is reserved for `Kernel.php` only. Do NOT place business classes under `App\`.

### Doctrine

- PHP attribute mappings in `<Context>/Infrastructure/Persistence/`.
- Each context is a separate mapping in `config/packages/doctrine.yaml`.
- No XML or YAML mappings.

### Services

Each context is autowired in `config/services.yaml`. Domain directories are excluded (pure VOs and interfaces).

## Domain Decisions

- **Schedule**: Doctrine STI on `schedules` table, discriminator `type ∈ { daily, daily_interval, specific_days }`.
- **DoseEvent**: Server expands schedules into concrete occurrences (rolling 14-day window).
- **Sync**: Last-write-wins by `updatedAt`. Entities carry `clientId` (device-minted UUID) for idempotent creates. `GET /sync?since=` returns adds/updates/tombstones.
- **OAuth tokens**: Encrypted at rest (libsodium). Never returned via API.
- **Calendar**: `CalendarGateway` interface with `GoogleCalendarGateway` and `MicrosoftGraphCalendarGateway` implementations (strategy pattern).

## API Conventions

- All endpoints under `/api/v1/`.
- JWT in `Authorization: Bearer …`.
- `/profiles/{id}/…` endpoints enforce ownership (profile belongs to authenticated account).
- `camelCase` JSON keys.

## Coding Conventions

- `declare(strict_types=1);` in every file.
- `readonly` classes/properties, enums, `match`, named arguments, first-class callables.
- Value objects: immutable, validate in constructor, no setters.
- Entity IDs: UUID value objects (`UserId`, `ProfileId`), not raw strings/ints.
- `final` by default. No `mixed` type.
- Behavior on entities, not anemic models.

## Quality Gates

| Command        | Tool                          |
|----------------|-------------------------------|
| `make stan`    | PHPStan level 9               |
| `make cs`      | php-cs-fixer (@PSR12 + short_array_syntax + no_unused_imports) |
| `make test`    | PHPUnit (unit + WebTestCase with real Postgres) |

All three must pass before any PR.

## Dev Commands

`make up` (start), `make down` (stop), `make sh` (PHP shell), `make logs`, `make db` (psql), `make reset-db`, `make migrate`.

## Security

- Never log/expose/commit secrets, keys, or tokens.
- Validate inputs at controller boundary (`symfony/validator`).
- Profile-scoped endpoints enforce ownership.

## Environment

- `.env` — committed safe defaults.
- `.env.local` — gitignored secrets.
- `.env.dev` — dev overrides.

## Language

Code, comments, and commits in English.

## Communication Style

- Be extremely concise. Remove filler, pleasantries, repetition, and narration.
- Preserve all technical details, warnings, commands, file paths, and identifiers.
- Fragments are acceptable. Prefer short direct sentences.
- Do not shorten or alter code, logs, error messages, or technical terms.
- For actions, use: `[target] [action] [reason]. [next step].`
- Do not omit relevant risks, assumptions, or unresolved issues.

<!-- headroom:rtk-instructions -->
# RTK (Rust Token Killer) - Token-Optimized Commands

When running shell commands, **always prefix with `rtk`**. This reduces context
usage by 60-90% with zero behavior change. If rtk has no filter for a command,
it passes through unchanged — so it is always safe to use.

## Key Commands
```bash
# Git (59-80% savings)
rtk git status          rtk git diff            rtk git log

# Files & Search (60-75% savings)
rtk ls <path>           rtk read <file>         rtk grep <pattern>
rtk find <pattern>      rtk diff <file>

# Test (90-99% savings) — shows failures only
rtk pytest tests/       rtk cargo test          rtk test <cmd>

# Build & Lint (80-90% savings) — shows errors only
rtk tsc                 rtk lint                rtk cargo build
rtk prettier --check    rtk mypy                rtk ruff check

# Analysis (70-90% savings)
rtk err <cmd>           rtk log <file>          rtk json <file>
rtk summary <cmd>       rtk deps                rtk env

# GitHub (26-87% savings)
rtk gh pr view <n>      rtk gh run list         rtk gh issue list

# Package managers (70-90% savings)
rtk pip list            rtk pnpm install        rtk npm run <script>
```
<!-- /headroom:rtk-instructions -->

<!-- headroom:memory-instructions -->
## Memory Guidance
Use the `headroom_memory` MCP server for persistent cross-session knowledge.
- **Before** answering questions about prior decisions or architecture — call `memory_search` first.
- **After** making durable decisions or discovering conventions — call `memory_save` to persist them.
