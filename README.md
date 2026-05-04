# WF – Voetbalteams

A small PHP/MySQL web application that automatically divides football players into balanced teams for the weekly Tuesday session of a walking-football group.

## What it does

- **Player selection** (`index.php`): Shows all active players (groups A–D) with checkboxes. Players who were present last week are pre-checked. A *Vernieuwen* button clears the selection for a fresh week.
- **Team building** (`teams.php`): Assigns players to 2 or 4 balanced teams using a score-based greedy algorithm that considers:
  - Hard *niet-samen* conflicts (players who must not be on the same team)
  - Group mixing (spread A/B/C/D players evenly)
  - Fitness balance (`fit` 0–10)
  - Ball skill balance (`handig` 0–10)
  - Position variety (back / midfield / forward)
  - Team size (kept equal)
  - Strength compensation (smaller teams get slightly stronger players)
- **Criteria form** (`criteria.php`): A public form colleagues can use to submit their assessment of each player's position, skill and fitness. Sends a PDF summary by e-mail.
- **Criteria admin** (`criteria-data.php`): Same form but saves directly to the database (admin only).
- **Print / PDF**: Any team result can be exported to PDF via FPDF.

## Tech stack

- PHP 8+
- MySQL (PDO, prepared statements)
- [FPDF](http://www.fpdf.org/) for PDF generation
- Plain HTML/CSS — no framework, no JavaScript

## Setup

1. Create a MySQL database and import `database/wf.sql`.
2. Create `../wf-config.php` **outside the web root** with your credentials:

```php
<?php
define('APP_RUNNING', true);
define('DB_HOST',    'localhost');
define('DB_NAME',    'wf');
define('DB_USER',    'your_user');
define('DB_PASS',    'your_password');
define('DB_CHARSET', 'utf8mb4');
```

3. Point your web server at the project folder.
4. Run `database/import.php` once to import the player list (or use `wf.sql` directly).

## Notes

- `wf-config.php` lives one level above the web root and is never committed.
- `common.php` is blocked from direct HTTP access via `.htaccess`.
- The selection is remembered in the database (`present` column) so the same players are pre-checked the following week.

---

*Built with [GitHub Copilot](https://github.com/features/copilot) (Claude Sonnet 4.6).*
