# MyPills API — Agent Instructions

Symfony 8.0 backend (PHP 8.5+, PostgreSQL 16, Doctrine ORM 3) for a Flutter medication-management app. Architecture: DDD + CQRS + Vertical Slices.

## Context Management (Headroom MCP) — MANDATORY

**Every agent, subagent, and LLM operating in this project MUST use Headroom compression. No exceptions.**

The `headroom` MCP server exposes three tools: `headroom_compress`, `headroom_retrieve`, `headroom_stats`. All three are mandatory parts of the workflow.

### 1. Compress EVERYTHING — Default Behavior

**Before reasoning over ANY content**, pass it through `headroom_compress`. This applies to ALL of the following without exception:

| Content type | When to compress |
|---|---|
| **Source code files** | Every `read_file` / `view_file` result, regardless of file size |
| **Command output** | Every `run_command` result (test runs, migrations, make targets, composer, bin/console) |
| **Search results** | Every `grep_search` result set |
| **Directory listings** | Every `list_dir` result with more than 10 entries |
| **JSON / YAML configs** | `composer.json`, `doctrine.yaml`, `services.yaml`, `messenger.yaml`, etc. |
| **Logs** | Symfony logs, Docker logs, PHPUnit output, PHPStan output, php-cs-fixer output |
| **Database output** | SQL query results, migration diffs, schema dumps |
| **Diffs & patches** | Git diffs, git log output, git show output |
| **API responses** | Any JSON response body from curl, httpie, or test assertions |
| **Doctrine mappings** | Entity metadata, DQL output, mapping validation results |
| **Error traces** | Stack traces, exception output, debug dumps |
| **Vendor / generated code** | Anything under `vendor/`, `var/`, or auto-generated files |
| **Multi-file reads** | When reading 2+ files in sequence, compress each one individually |

**The ONLY exceptions** where you may skip compression:
- Content shorter than **50 characters** (e.g., a single status line like "OK" or a one-liner command result).
- Content you have **already compressed** in this same conversation turn.

### 2. Retrieval Protocol — `headroom_retrieve`

Compressed output contains **breadcrumb hashes** (markers like `[N items compressed… hash=abc123]`). These are retrieval keys.

- **Always track hashes.** When `headroom_compress` returns compressed content with hashes, note them mentally — you will need them.
- **Retrieve before acting on omitted details.** If you need the exact implementation of a function, the full body of a config block, the precise error message in a stack trace, or any detail that was omitted during compression, call `headroom_retrieve` with the hash **before** writing code or making decisions.
- **Never guess at compressed-away content.** If a compressed block says `[3 methods compressed… hash=xyz789]` and you need one of those methods to fix a bug, retrieve first. Do not assume, infer, or hallucinate the omitted content.
- **Retrieve is cheap.** It returns the original uncompressed content from local cache. Use it liberally whenever there is any ambiguity.

### 3. Stats Tracking — `headroom_stats`

- **Call `headroom_stats` at the end of every multi-step task** (after implementing a feature, fixing a bug, completing a refactor, or finishing an exploration session).
- This provides visibility into total compressions, tokens saved, and cost savings for the session.
- If stats show zero compressions during a task that involved reading files or running commands, something went wrong — review your process.

### 4. Subagent & Delegation Rules

- **Subagents inherit this policy.** When spawning subagents (research, self, or custom), include in their prompt: "You MUST use `headroom_compress` on all file reads, command outputs, and search results before reasoning."
- **Never pass uncompressed bulk content between agents.** If sharing context between agents, compress first.
- **Research subagents** are especially required to compress, since they perform many sequential reads that accumulate context rapidly.

### 5. Workflow Integration

```
read_file / run_command / grep_search / etc.
        │
        ▼
  headroom_compress(content)     ← ALWAYS, before reasoning
        │
        ▼
  Reason on compressed output
        │
        ├─ Need omitted detail? → headroom_retrieve(hash)
        │
        ▼
  Produce response / write code
        │
        ▼
  headroom_stats()               ← End of multi-step task
```

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
