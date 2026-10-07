# Stats source reference

> Generated from the current checkout and then intended for human review.
> Paths are relative to the package root.

## Inventory summary

| Artifact | Count |
|---|---:|
| PHP files | 20 |
| Smarty templates | 12 |
| JavaScript files | 0 |
| CSS files | 1 |

## Bootstrap and schema artifacts

- `admin/schema_inc.php`
- `admin/upgrade_inc.php`
- `admin/upgrades/1.0.1.php`
- `admin/upgrades/1.0.2.php` — landing URL table and map column
- `includes/bit_setup_inc.php`
- `includes/stats_functions_inc.php` — first-touch cookies and persist helpers
- `includes/ads_roas_lib.php` — ROAS report: period/preset helpers, revenue source seam, spend/target/revenue/reconciliation queries, series + inline SVG, campaign drill-down, per-user revenue map, CSV spec
- `includes/ads_setup_lib.php` — ad API key catalog and Microsoft OAuth helper
- `includes/ads_api_lib.php` — Google Ads read-only API (config credentials)
- `includes/ads_warehouse_lib.php` — warehouse upsert helpers, schema apply, landing-key parser, campaign aliases, `stats_pref_get/set`

## Declared schema tables

- `stats_pageviews`
- `stats_referer_urls`
- `stats_landing_urls`
- `stats_referer_users_map`
- `stats_referers`

Warehouse tables (`admin/ad_warehouse_schema.sql`, raw PostgreSQL DDL, no
`BIT_DB_PREFIX`): `stats_prefs`, `stats_ad_network`, `stats_ad_account`,
`stats_ad_campaign`, `stats_ad_campaign_settings_daily`, `stats_ad_adgroup`,
`stats_ad_ad`, `stats_ad_keyword`, `stats_ad_metrics_daily`,
`stats_ad_conversion_action`, `stats_ad_conversion_daily`, `stats_ad_click`,
`stats_ad_user_attribution`, `stats_ad_order_attribution`,
`stats_ad_campaign_alias`.

## First-party classes and interfaces

- `includes/classes/Statistics.php:13` — `class Statistics extends BitBase {`

## Web-facing PHP controllers

- `admin/ad_warehouse_schema.sql`
- `admin/sh_ad_warehouse_pull.php`
- `admin/sh_ad_warehouse_backfill.php`
- `admin/sh_ad_warehouse_refresh.php`
- `admin/sh_ad_warehouse_rebuild.php`
- `admin/admin_stats_inc.php`
- `admin/schema_inc.php`
- `admin/upgrade_inc.php`
- `admin/upgrades/1.0.1.php`
- `admin/upgrades/1.0.2.php`
- `index.php`
- `item_chart.php`
- `pv_chart.php`
- `referrers.php`
- `ad_roas.php`
- `admin/ad_setup.php`
- `usage_chart.php`
- `users.php`

## Plugin and module directories


## Templates

- `templates/ad_roas.tpl`
- `templates/ad_roas_campaign_inc.tpl` — campaign drill-down included by `ad_roas.tpl`
- `templates/ad_setup.tpl`
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
