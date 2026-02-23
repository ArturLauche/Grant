# Grant (PHP Edition)

Grant is now a **pure PHP Discord interactions bot** designed for deployment on shared hosting with a **MariaDB** backend.

This rewrite removes the Node.js gateway dependency and replaces it with Discord's HTTP interactions model (slash commands posted to your web endpoint).

## What changed in this rewrite

- Rebuilt from TypeScript/discord.js to PHP 8.1+.
- Switched persistence to MariaDB using PDO.
- Added strict request signature verification using Discord public-key Ed25519 validation.
- Added database-backed audit logging for administrative actions.
- Implemented previously scaffolded officer actions (`promote`, `demote`, `blacklist`) with persistent state.
- Improved marks handling:
  - only positive integers accepted,
  - subtraction clamps at zero (no negative marks).
- Added command registration script for Discord REST deployment.

## Architecture

```text
app/
├── Config.php                      # .env + getenv loading and required variable validation
├── Database.php                    # PDO MariaDB connection factory
├── Discord/
│   ├── CommandCatalog.php          # Slash command JSON definitions
│   └── InteractionHandler.php      # Command router + command execution
├── Repository/
│   ├── OfficerRepository.php       # Officer CRUD + rank/blacklist/marks operations
│   └── AuditRepository.php         # Audit trail inserts
└── Service/
    └── RoleGate.php                # Role-based permission checks
public/
└── index.php                       # Web entry point for Discord interactions
scripts/
└── register_commands.php           # Deploy slash commands to Discord
sql/
└── schema.sql                      # MariaDB schema
```

## Requirements

- PHP 8.1+ (8.2 recommended)
- Extensions:
  - `pdo`
  - `pdo_mysql`
  - `sodium` (for Discord signature verification)
  - `curl` (for command deployment script)
- MariaDB 10.5+ (or compatible MySQL)
- HTTPS-enabled host

## Environment configuration

Copy `.env.example` to `.env` and fill values:

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

- `DISCORD_GUILD_ID`:
  - Set it for fast guild-scoped command updates during development.
  - Leave empty for global commands in production (can take up to ~1 hour to propagate).
- `ROLE_IDS_MR_AND_HIGHER` and `ROLE_IDS_HR_AND_HIGHER`:
  - comma-separated Discord role IDs,
  - used for command authorization.
- `DEVELOPER_USER_IDS`:
  - comma-separated Discord user IDs,
  - only these users can run `/command export` and `/command import`.

## Database setup (MariaDB)

Run:

```bash
mysql -u <user> -p <database> < sql/schema.sql
```

This creates:

- `officers`: source of truth for officer profile, marks, rank, blacklist state.
- `officer_audit_logs`: append-only audit trail for moderation/HR actions.

## Deploying on shared PHP hosting (step-by-step)

1. **Upload project files** to your web space.
2. **Set document root** to `public/` (or route requests to `public/index.php`).
3. **Create MariaDB DB/user** in hosting panel and import `sql/schema.sql`.
4. **Create `.env`** in project root (one level above `public/` usually).
5. **Enable required PHP extensions** (`pdo_mysql`, `sodium`, `curl`).
6. **Create Discord app + bot** in Discord Developer Portal.
7. In **General Information**, copy Application ID and Public Key to `.env`.
8. In **Bot**, copy token to `.env`.
9. In **Interactions Endpoint URL**, set:
   - `https://your-domain.tld/index.php` (or your routed endpoint)
10. **Register slash commands**:

```bash
php scripts/register_commands.php
```

11. Invite the bot with scopes:
   - `bot`
   - `applications.commands`

### Example Apache rewrite (if document root cannot be changed)

Use this in project root `.htaccess`:

```apache
RewriteEngine On
RewriteRule ^$ public/index.php [L]
RewriteRule ^(.*)$ public/$1 [L]
```

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
- `/command export [limit:<int>]`
  - exports up to `limit` officers (default `50`, max `500`) as base64-encoded JSON.
- `/command import payload:<base64>`
  - imports officers from a payload produced by `/command export`.

> Note: Discord command option size and message length limits apply. For large migrations, run multiple smaller exports/imports.

## Security and reliability notes

- Every interaction request is signature-validated before processing.
- Failed checks return 401 and do not touch DB.
- All admin-sensitive actions are audit logged.
- Mark subtraction prevents negative values.

## Local smoke checks

```bash
php -l public/index.php
php -l app/Discord/InteractionHandler.php
php -l scripts/register_commands.php
```

## License

ISC.
