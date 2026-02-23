# Grant (PHP Edition)

Grant is a **PHP Discord Interactions** bot for NFSF officer workflows, designed for **shared hosting** with a **MariaDB** database.

Unlike the old gateway-based runtime, this version uses Discord's HTTP interactions model: Discord sends slash-command payloads to your HTTPS endpoint, and your PHP app responds with JSON.

## What changed

- Rebuilt from TypeScript/discord.js to PHP 8.1+.
- Replaced SQLite/Knex with MariaDB/PDO.
- Added strict Ed25519 Discord signature verification.
- Added audit logging for administrative actions.
- Implemented officer actions that were previously scaffolded (`promote`, `demote`, `blacklist`).
- Added developer-only DB transfer commands:
  - `/command export`
  - `/command import`

## Architecture

```text
app/
├── Config.php                      # Loads env values and validates required vars
├── Database.php                    # PDO MariaDB connection factory
├── Discord/
│   ├── CommandCatalog.php          # Slash command JSON definitions
│   └── InteractionHandler.php      # Command router + business logic
├── Repository/
│   ├── OfficerRepository.php       # Officer CRUD + marks/rank/blacklist/import/export
│   └── AuditRepository.php         # Audit trail inserts
└── Service/
    └── RoleGate.php                # Role-based authorization helpers
public/
└── index.php                       # Web entrypoint for Discord interactions
scripts/
└── register_commands.php           # Registers slash commands via Discord REST
sql/
└── schema.sql                      # MariaDB schema
```

## Requirements

- PHP 8.1+ (8.2 recommended)
- Extensions:
  - `pdo`
  - `pdo_mysql`
  - `sodium` (required for request signature verification)
  - `curl` (required for slash command registration script)
- MariaDB 10.5+ (or compatible MySQL)
- Public HTTPS endpoint (Discord requires HTTPS)

## Environment configuration

Copy `.env.example` to `.env`:

```env
APP_ENV=production

DISCORD_BOT_TOKEN=
DISCORD_APPLICATION_ID=
DISCORD_PUBLIC_KEY=
DISCORD_GUILD_ID=

DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=grant
DB_USER=grant_user
DB_PASSWORD=

ROLE_IDS_MR_AND_HIGHER=
ROLE_IDS_HR_AND_HIGHER=

# Comma-separated Discord user IDs allowed to run developer data commands
DEVELOPER_USER_IDS=
```

### Variable notes

- `DISCORD_BOT_TOKEN`: bot token from Discord Developer Portal → **Bot**.
- `DISCORD_APPLICATION_ID`: application ID from Developer Portal → **General Information**.
- `DISCORD_PUBLIC_KEY`: required for validating interaction signatures.
- `DISCORD_GUILD_ID`:
  - set for fast guild-scoped command iteration in dev,
  - leave empty for global commands in production (global propagation may take time).
- `ROLE_IDS_MR_AND_HIGHER`, `ROLE_IDS_HR_AND_HIGHER`: comma-separated role IDs used for permission gates.
- `DEVELOPER_USER_IDS`: comma-separated user IDs allowed to run `/command export` and `/command import`.

## MariaDB setup

Import schema:

```bash
mysql -u <user> -p <database> < sql/schema.sql
```

Tables:

- `officers`: officer records, marks, rank, blacklist state.
- `officer_audit_logs`: audit trail for admin/developer actions.

## Shared hosting deployment (detailed)

### 1) Upload files

Upload the repository to your hosting account (FTP/SFTP/file manager).

### 2) Point web root correctly

Preferred: set document root to `public/`.

If your host cannot change document root, use a rewrite rule to route to `public/index.php`.

Example root `.htaccess`:

```apache
RewriteEngine On
RewriteRule ^$ public/index.php [L]
RewriteRule ^(.*)$ public/$1 [L]
```

### 3) Configure PHP extensions

Enable these in your hosting panel:

- `pdo_mysql`
- `sodium`
- `curl`

### 4) Create and connect database

- Create a MariaDB database + user in hosting panel.
- Import `sql/schema.sql`.
- Fill DB values in `.env`.

### 5) Set file placement for `.env`

Place `.env` in project root (sibling to `app/`, `public/`, `scripts/`).
Do **not** put `.env` inside `public/`.

### 6) Prepare Discord application and bot

In https://discord.com/developers/applications:

1. Create (or open) your app.
2. In **General Information**:
   - copy **Application ID** → `DISCORD_APPLICATION_ID`
   - copy **Public Key** → `DISCORD_PUBLIC_KEY`
3. In **Bot**:
   - create/reset token
   - copy token → `DISCORD_BOT_TOKEN`

### 7) Connect Discord to your hosted endpoint

In **General Information** set **Interactions Endpoint URL** to your production endpoint, e.g.:

- `https://your-domain.tld/index.php`
- or `https://your-domain.tld/discord/interactions` (if routed there)

Discord validates this URL by sending signed requests. If validation fails:

- ensure HTTPS is valid,
- ensure the endpoint returns proper JSON,
- ensure `DISCORD_PUBLIC_KEY` is correct,
- ensure `sodium` extension is enabled.

### 8) Register slash commands

Run from the project root:

```bash
php scripts/register_commands.php
```

Use `DISCORD_GUILD_ID` for fast dev updates. Remove it for global commands when ready.

### 9) Invite bot to your server

OAuth2 URL builder (Developer Portal → OAuth2 → URL Generator):

- Scopes:
  - `bot`
  - `applications.commands`
- Bot permissions:
  - `Send Messages`
  - `Use Slash Commands`
  - `Read Message History` (optional, helpful)

Then invite the bot to your target guild.

### 10) Verify end-to-end

- Run `/ping` in Discord.
- If it fails, inspect your PHP error logs + web server logs.
- Confirm `officer_audit_logs` is receiving admin/developer action entries.

## Command surface

### LR
- `/ping`
- `/echo input:<text>`

### MR+
- `/marks add officer:<user> amount:<int>`
- `/marks subtract officer:<user> amount:<int>`
- `/marks get [officer:<user>]`

### HR+
- `/officer register [user:<user>]`
- `/officer info [officer:<user>]`
- `/officer remove officer:<user>`
- `/officer promote officer:<user> rank:<text>`
- `/officer demote officer:<user> rank:<text>`
- `/officer blacklist officer:<user> state:<on|off>`

### Developer-only
- `/command export [limit:<int>] [offset:<int>]`
  - exports officer rows to base64 JSON.
  - use `offset` to paginate through large datasets (e.g. 0, 100, 200...).
- `/command import payload:<base64>`
  - imports rows from an export payload.

> For large data transfers, split work into smaller batches because Discord option/message sizes are limited.

Example pagination flow for 1,200 officers:
- `/command export limit:200 offset:0`
- `/command export limit:200 offset:200`
- `/command export limit:200 offset:400`
- continue until the returned payload `pagination.count` is less than `pagination.limit`.

## Security notes

- Every interaction request is signature-verified before processing.
- Invalid signatures return `401` and are rejected.
- Sensitive admin/developer actions are audit logged.
- Mark subtraction clamps at zero.

## Local smoke checks

```bash
php -l public/index.php
php -l app/Discord/InteractionHandler.php
php -l scripts/register_commands.php
```

## License

ISC.
