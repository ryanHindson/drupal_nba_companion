# NBA Companion

NBA Companion is a Drupal 11 site for browsing NBA teams, players, rosters, and season statistics. A Python service retrieves NBA data, a custom Drupal module imports it as content, and a custom theme presents it through team pages, player pages, Views, and search.

## Project structure

```text
.
├── .ddev/
│   ├── config.yaml                    # Drupal 11 DDEV project settings
│   └── docker-compose.python.yaml     # Separate Python service
├── config/
│   └── sync/                          # Exported Drupal site configuration
├── python-service/
│   ├── Dockerfile                     # Python container image
│   ├── requirements.txt               # Flask, Gunicorn, nba_api
│   └── nba_stat_manager.py            # NBA HTTP endpoints
├── web/
│   ├── modules/Custom/nba_import/      # Custom Drupal import module
│   └── themes/custom/nba_companion/   # Custom Drupal theme
├── composer.json                      # Drupal PHP dependencies
└── composer.lock                      # Locked dependency versions
```

### Python service

`python-service/nba_stat_manager.py` is a Flask application. Gunicorn serves it on port `8000` inside the Python container. It uses the Python `nba_api` package to provide these endpoints:

| Endpoint | Purpose |
|---|---|
| `GET /health` | Service health check |
| `GET /nba/teams` | Team names and identifiers |
| `GET /nba/season-players?season=2025-26` | Players associated with a team for a season |
| `GET /nba/team-season-stats?season=2025-26` | Team statistics per game |
| `GET /nba/player-season-stats?season=2025-26&season_type=Regular%20Season` | Player statistics per game |

The teams endpoint uses the team list bundled with `nba_api`. The season endpoints request NBA statistics through `nba_api`. The player list excludes rows without a team ID. A player can still have a missing jersey number.

The service is defined in `.ddev/docker-compose.python.yaml` and is reachable from the Drupal container by the hostname `python`.

### Drupal import module

`web/modules/Custom/nba_import` contains the `nba_import` module. Its `hook_cron()` runs imports in this order:

1. Teams
2. Players
3. Team season stats
4. Player season stats

Importing parent records first lets Player content reference Team content, and stats content reference its corresponding Team or Player.

The module creates or updates:

| Content type | What it stores | Record matching |
|---|---|---|
| Team | Team identity and descriptive fields | NBA team ID |
| Player | Name, NBA player ID, position, jersey, and Team reference | NBA player ID |
| Team Season Stats | Per-game team stats and Team reference | Team reference + season |
| Player Season Stats | Per-game player stats and Player reference | Player reference + season + season type |

For stats, the importer maps numeric API keys to Drupal fields by name. For example, `FG_PCT` maps to `field_stat_fg_pct` on Team Season Stats and `field_ps_fg_pct` on Player Season Stats. The importer only writes a value when the matching field exists.

A Team logo added manually in Drupal is left untouched by the team import. A blank jersey number from the API does not erase an existing saved jersey number.

The Player import can delete imported players who no longer appear in a successful response. It also deletes their linked Player Season Stats. Safety checks cancel this deletion if rows were skipped or the response is unexpectedly smaller than the previous successful response. Players without an NBA player ID are left alone.

Each import has a 24-hour guard in Drupal state. Running cron more often will not force every import to run again. **Cron still needs to be invoked by a scheduler or manually** for imports to occur.

### Drupal content and Views

The content types and many fields were created through Drupal configuration. Their exported YAML files are in `config/sync`.

Drupal Views assemble related content for display:

- The home page contains a team picker and a Find players block.
- The Team page displays season stats and a roster using Views filtered to the current Team.
- The Player page displays season stats using a View filtered to the current Player.
- The navigation contains a Teams menu and a player search panel.

The theme builds some Views in `nba_companion.theme` and passes them to Twig templates. Team and Player relationships use entity references rather than matching names in titles.

### Custom theme

`web/themes/custom/nba_companion` contains the dark sports-style theme:

- `nba_companion.theme` prepares Team, Player, and navigation data for Twig.
- `templates/layout/` defines the page layouts.
- `templates/content/` defines Home, Team, and Player page markup.
- `templates/navigation/site-nav.html.twig` defines the shared navigation.
- `templates/block/` and `templates/views/` customize blocks and View results.
- `css/` contains shared colors and component styling.
- `js/nav.js` makes Back use browser history when available, with Home as its fallback.

Theme colors, fonts, spacing, and radii are defined as CSS variables in `css/base.css`.

## Local development

This project uses DDEV with Drupal 11, PHP 8.4, MariaDB, and a separate Python container.

From the repository root:

```bash
ddev start
ddev composer install
```

Check that Drupal can reach Python:

```bash
ddev exec curl http://python:8000/health
```

A healthy response is:

```json
{"status":"healthy"}
```

Open the Drupal site with:

```bash
ddev launch
```

Run Drupal cron manually to start eligible imports:

```bash
ddev drush cron
```

Clear Drupal caches after changing Twig files, theme libraries, or PHP code:

```bash
ddev drush cr
```

## Restoring an existing site

Git contains the application code, Composer dependencies list, DDEV setup, and exported configuration. It does **not** contain the Drupal database or uploaded files. To reproduce the current site exactly, obtain its database backup and uploaded files separately.

After starting DDEV and installing Composer dependencies, import the backups:

```bash
ddev import-db --file=PATH_TO_DB_BACKUP.sql.gz
ddev import-files --source=PATH_TO_FILES_ARCHIVE.tar.gz
ddev drush cr
```

Replace the example backup paths with your own. Uploaded Team logos are among the files that must be restored.

The `config/sync` directory is a full site configuration export. Drupal uses a site UUID when importing full configuration, so it is intended for another copy of this site rather than an unrelated fresh installation. See [Drupal configuration management](https://www.drupal.org/docs/administering-a-drupal-site/configuration-management/managing-your-sites-configuration).

## Current configuration notes

- The importer currently requests the **2025-26** season. Player stats use **Regular Season**.
- The importer currently calls `http://python:8000` directly. Another hosting environment must provide a reachable Python service and update that URL.
- The Python service and custom theme are separate from the Drupal import module. Installing `nba_import` alone does not install the complete application.
- Some Views and the Home page configuration may exist only in the current Drupal database if they have not been exported since their latest changes. Export configuration before relying on `config/sync` to reproduce a new environment.
- Content imported from the NBA is stored in Drupal's database. Git tracks the code and configuration, not the resulting Team, Player, and stats records.

## Technology

- Drupal 11
- PHP 8.4
- MariaDB
- DDEV and Docker Compose
- Python 3.12
- Flask and Gunicorn
- `nba_api`
- Twig, CSS, and JavaScript

## Repository

This repository contains the complete NBA Companion site. The reusable Drupal import code lives in `web/modules/Custom/nba_import`. Making that module independently installable would require packaging its content types, fields, and Views and making the Python service URL and season configurable.
