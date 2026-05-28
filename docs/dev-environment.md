# Development environment

The repo ships a fully self-contained VS Code devcontainer that clones LibreNMS, brings up MariaDB + Redis + snmpsim, and symlinks this plugin into LibreNMS's `vendor/`. Use it. There is no other supported workflow in this repo.

## Quick start (devcontainer, recommended)

1. Open the repo in VS Code (this folder, `nmscustomfields`).
2. **Reopen in Container** (`F1` → "Dev Containers: Reopen in Container"). First build takes a few minutes — the postStart script clones LibreNMS, installs composer deps, seeds the DB, and adds the snmpsim test device.
3. Wait for `Environment setup done. Librenms installed in /var/www/html/librenms.` in the terminal.
4. Run Librenms by pressing F5 in vscode so devcontainer starts.

Once that finishes, you have:

| Thing | Where |
| --- | --- |
| Plugin repo (your edits) | `/workspaces/nmscustomfields` (mounted from host) |
| LibreNMS install | `/var/www/html/librenms` (env: `$LIBRENMS_FOLDER`) |
| Plugin symlinked into LibreNMS | `vendor/dot-mike/nmscustomfields` → `/workspaces/nmscustomfields` |
| DB | MariaDB on `127.0.0.1:3306`, db `librenms`, user `librenms`, pw `asupersecretpassword` |
| Redis | service `redis`, used for cache + session |
| Test device | hostname `snmpsim`, SNMP v2c community `demo`, port 1161 |
| Admin users | `admin` / `admin` and `librenms` / `librenms` |

The composer symlink means **edits in the plugin repo are live in LibreNMS instantly**. No re-install / no rebuild after PHP/blade edits. Cache and view clears may still be needed after config changes (see below).

## Running Claude Code (and any PHP command)

PHP is **only installed inside the devcontainer** (PHP 8.2 from `.devcontainer/Dockerfile`). The WSL host does not have PHP. Claude Code is preinstalled inside the container via the [`anthropics/devcontainer-features/claude-code`](https://github.com/anthropics/devcontainer-features/tree/main/src/claude-code) feature; sign-in state persists across rebuilds in the named volume `claude-code-config-${devcontainerId}` (defined in `devcontainer.json` and mounted at `/home/vscode/.claude`).

1. Open the repo in VS Code on the host.
2. **Reopen in Container** (`F1` → "Dev Containers: Reopen in Container"). Wait for postStart to finish.
3. Open a terminal in VS Code — it's inside the container.
4. Run `claude`. First run prompts for auth; the token lands in the persisted volume so subsequent rebuilds don't re-prompt.

Do not run Claude Code from a WSL terminal on the host for tasks that involve PHP verification — you'll hit `php: command not found` on every check.

## Running commands

All `php artisan` / `php lnms` commands run from **`$LIBRENMS_FOLDER`** (i.e. `/var/www/html/librenms`) inside the container, not the plugin repo. The plugin repo has no `artisan`.

```bash
cd $LIBRENMS_FOLDER
php artisan migrate
php artisan tinker
php lnms --force -n migrate
```

The provider (`src/Providers/CustomFieldsProvider.php`) calls `$this->loadMigrationsFrom(...)`, so plugin migrations are **auto-discovered** by Laravel — no `--path=` needed. `php artisan migrate` picks them up.

## Running the web UI for browser testing

```bash
cd $LIBRENMS_FOLDER
php artisan serve --host=0.0.0.0 --port=8000
```

Then browse to `http://localhost:8000` on the host (port 8000 is forwarded by the devcontainer when running). Log in as `admin` / `admin`.

## After code/config changes

Most edits don't need anything — Laravel picks them up live.

| If you changed | Run |
| --- | --- |
| A migration file | `php artisan migrate` (forward) or `php artisan migrate:rollback` |
| Routes (`routes/*.php`) | `php artisan route:clear` |
| Views (`resources/views/`) | nothing (no cache by default in dev) |
| Provider / `composer.json` extras | `php artisan config:clear && php artisan cache:clear` |
| Config in `.env` | `php artisan config:clear` |

If something behaves stale, the nuke option:

```bash
php artisan optimize:clear
```

## Verifying the plugin is loaded

```bash
cd $LIBRENMS_FOLDER
php artisan route:list | grep nmscustomfields
```

Expected: a list of `plugin.nmscustomfields.*` routes. If empty, the plugin isn't registered — check `composer show dot-mike/nmscustomfields` resolves to the symlink, and that Plugins Admin shows it enabled.

To enable in the UI: navigate to `Overview → Plugins → Plugins Admin`, toggle `nmscustomfields` on.

## Running the test suite

The plugin ships a PHPUnit suite covering the v2 risk surface: model
accessor/mutator (including the false-zero regression guard), type-aware
controller validation (#4), helper, and the v1 → v2 migration (dedup,
last-id-wins backfill, integer/non-integer split).

```bash
cd /workspaces/nmscustomfields
DBTEST=1 /var/www/html/librenms/vendor/bin/phpunit --testdox
```

- `DBTEST=1` is required (gates DB-dependent tests).
- Tests use `phpunit.xml.dist` at the plugin root and `tests/bootstrap.php`,
  which boots the LibreNMS Laravel app.
- Most tests use `DatabaseTransactions` for per-test isolation against the
  live dev DB.
- The migration test (`FlattenMigrationTest`, group `migration`) rolls the
  plugin back to v1 schema, seeds raw v1 data, migrates forward, asserts,
  then re-migrates so the DB ends in v2 state. It can't use transactions
  because MySQL DDL doesn't roll back. To run only that test:
  `DBTEST=1 .../phpunit --group migration`.
- ⚠ "Risky / error handler" warnings are inherited from LibreNMS's bootstrap
  and appear in upstream LibreNMS tests too; assertions still pass.

## Resetting the DB

The devcontainer DB is throwaway. Two options:

**Targeted (preferred during normal dev):**

```bash
cd $LIBRENMS_FOLDER
php artisan migrate:rollback --path=vendor/dot-mike/nmscustomfields/database/migrations
php artisan migrate
```

**Nuke everything (only for end-to-end smoke tests on this disposable instance):**

```bash
cd $LIBRENMS_FOLDER
php artisan migrate:fresh   # drops ALL tables in the LibreNMS DB
php artisan db:seed
php lnms --force -n migrate
# Re-add the test device:
php lnms -n device:add -r 1161 -2 -c demo -- snmpsim
```

Or restart the `db` service in docker compose to wipe the volume.

## Quick verification recipes

Run a snippet against the DB / models:

```bash
cd $LIBRENMS_FOLDER
php artisan tinker --execute='
use DotMike\NmsCustomFields\Models\CustomField;
use DotMike\NmsCustomFields\Models\CustomFieldDevice;
print_r(CustomField::all()->toArray());
'
```

Hit a route from inside the container (bypassing browser):

```bash
curl -s -u admin:admin -H 'Content-Type: application/json' \
  http://localhost:8000/api/v0/devices/1/customfields | jq .
```

## Alternative: manual symlink without devcontainer

If you can't use devcontainer (e.g. native LibreNMS dev install on host), symlink the plugin into a local LibreNMS clone and add it as a composer path repo:

```bash
# In your local LibreNMS checkout root (NOT this plugin repo):
composer config repositories.local '{"type":"path","url":"/path/to/nmscustomfields","options":{"symlink":true}}'
composer require dot-mike/nmscustomfields
php artisan migrate
```
