# Grant

Grant is a TypeScript Discord bot built for NFSF officer management. It uses slash commands, role-based access control, and a SQLite database (via Knex + `better-sqlite3`) to track officer records and marks.

## Features

- Dynamic command + event loading from the filesystem at startup.
- Guild slash-command deployment through Discord REST.
- Role-gated HR/MR/LR workflows.
- Officer registration, lookup, mark adjustments, and removal.
- Developer-only command lifecycle tools (deploy/reload/delete).
- Structured embeds and timestamped console logging.

## Tech Stack

- Node.js + TypeScript (ESM)
- [discord.js v14](https://discord.js.org)
- Knex + SQLite (`better-sqlite3`)
- dotenv
- Docker

## Project Structure

```text
src/
├── index.ts                     # Bootstrap: env parsing, logger, bot initialization
├── Bot/
│   ├── Bot.ts                   # Discord client, command/event loading, command deployment APIs
│   ├── Commands/
│   │   ├── LR/                  # Low-rank utility commands
│   │   ├── MR/                  # Mid-rank workflows (marks)
│   │   ├── HR/                  # High-rank workflows (officer management)
│   │   └── Developer/           # Developer-only command management
│   └── Events/
│       └── Essentials/          # ready, interactionCreate, guildCreate handlers
├── Types/
│   ├── Globals.d.ts             # ICommand, IEvent, Environment types
│   └── Tables.d.ts              # Officer / Events table interfaces
└── Util/
    ├── EmbedTemplates.ts        # Shared embed builders
    ├── Log.ts                   # Colored logger
    └── Ranks.ts                 # Role ID groups/stacks for permission checks
```

## Prerequisites

- Node.js 20+ (or current LTS)
- npm
- A Discord bot application + token
- A Discord server (guild) where the bot can register slash commands

## Environment Variables

Create a `.env` file in the repository root:

```env
TOKEN=your_discord_bot_token
CLIENT=your_discord_application_client_id
GUILD=your_discord_guild_id
ENVIRONMENT=development
DATABASE_URL=./grant.sqlite
```

### Variable meanings

- `TOKEN`: bot token used for gateway login and REST command registration.
- `CLIENT`: Discord application (client) ID.
- `GUILD`: target guild ID for guild-scoped slash command deployment.
- `ENVIRONMENT`: free-form environment label (e.g., `development`, `production`).
- `DATABASE_URL`: SQLite file path used by Knex.

## Database Setup

This repo does not currently include migrations. Ensure the SQLite database configured by `DATABASE_URL` contains at least the `Officers` table expected by commands:

```sql
CREATE TABLE IF NOT EXISTS Officers (
  OfficerID INTEGER PRIMARY KEY AUTOINCREMENT,
  Discord_Username TEXT NOT NULL,
  Discord_ID TEXT NOT NULL UNIQUE,
  Marks INTEGER NOT NULL DEFAULT 0
);
```

An additional `Events` table type exists in `src/Types/Tables.d.ts`, but command handlers currently rely on `Officers`.

## Install & Run

```bash
npm install
npm run dev
```

### Build for production

```bash
npm run build
npm start
```

## Docker

Build image:

```bash
npm run dock
```

Run container with env file:

```bash
npm run containerize
```

> The Dockerfile exposes port `3000`, but this bot does not run an HTTP server by default.

## Command Surface

### LR

- `/ping` — simple health check reply.
- `/echo input:<text>` — echoes text.

### MR

- `/marks add officer:<user> amount:<number>` — add marks (MR+).
- `/marks subtract officer:<user> amount:<number>` — subtract marks (MR+).
- `/marks get [officer:<user>]` — retrieve marks.

### HR

- `/officer register [user:<user>]` — self-register (LR+) or register another user (HR+).
- `/officer info [officer:<user>]` — officer profile + marks.
- `/officer remove [officer:<user>] [id:<discord_id>]` — remove officer (HR+).
- `/officer promote` — currently scaffolded/not implemented.
- `/officer demote` — currently scaffolded/not implemented.
- `/officer blacklist` — currently scaffolded/not implemented.

### Developer-only

- `/command deploy name:<command>`
- `/command reload name:<command>`
- `/command delete name:<command>`

Developer commands are restricted by Discord user ID checks in `src/index.ts` (`BotDevelopers`).

## How it Works

1. `src/index.ts` parses `.env` (fallback to `process.env`), builds logger, then starts the bot.
2. `Bot.ts` initializes Discord client + Knex connection.
3. On startup the bot:
   - checks DB connectivity,
   - reads + registers events,
   - loads command classes dynamically from folders,
   - logs into Discord.
4. On `guildCreate`, it deploys all currently loaded commands to the configured guild.
5. On `interactionCreate`, it resolves command/subcommand and executes handler with error embeds on failure.

## Notes / Caveats

- Several command messages are intentionally opinionated/casual; normalize wording if needed for production environments.
- Some HR actions are placeholders and return a “not implemented” embed.
- `DeleteAllCommands()` exists in `Bot.ts` but is not exposed as a slash command.

## License

ISC (from `package.json`).
