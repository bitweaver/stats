# Stats source reference

> Generated from the current checkout and then intended for human review.
> Paths are relative to the package root.

## Inventory summary

| Artifact | Count |
|---|---:|
| PHP files | 13 |
| Smarty templates | 10 |
| JavaScript files | 0 |
| CSS files | 1 |

## Bootstrap and schema artifacts

- `admin/schema_inc.php`
- `admin/upgrade_inc.php`
- `admin/upgrades/1.0.1.php`
- `includes/bit_setup_inc.php`

## First-party classes and interfaces

- `includes/classes/Statistics.php:13` — `class Statistics extends BitBase {`

## Web-facing PHP controllers

- `admin/admin_stats_inc.php`
- `admin/schema_inc.php`
- `admin/upgrade_inc.php`
- `admin/upgrades/1.0.1.php`
- `index.php`
- `item_chart.php`
- `pv_chart.php`
- `referrers.php`
- `usage_chart.php`
- `users.php`

## Declared schema tables

- `stats_pageviews`
- `stats_referer_urls`
- `stats_referer_users_map`
- `stats_referers`

## Plugin and module directories


## Templates

- `templates/admin_stats.tpl`
- `templates/footer_inc.tpl`
- `templates/html_head_inc.tpl`
- `templates/menu_stats.tpl`
- `templates/menu_stats_admin.tpl`
- `templates/referrer_stats.tpl`
- `templates/referrer_stats_ctm_inc.tpl`
- `templates/search_stats.tpl`
- `templates/stats.tpl`
- `templates/user_stats.tpl`

## Reading cautions

- Presence in this inventory does not make a file a supported public API.
- Bundled third-party libraries must be distinguished from package-owned code.
- Base schema files do not prove the migration state of a deployed database.
- Controllers may rely on include files, globals, services, and template callbacks not visible from their filename alone.
