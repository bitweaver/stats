# Stats architecture

## Package role

Stats records and presents site and content activity statistics.

## Initialization

The package bootstrap is `includes/bit_setup_inc.php`. Bitweaver discovers package bootstraps during
Kernel package scanning. Treat bootstrap files as registration and wiring code:
they can define constants, register the package, load shared classes, attach
Liberty services, and expose values to Smarty.

Do not call a package bootstrap as a web endpoint. All normal controllers must
load `kernel/includes/setup_inc.php` before using framework globals.

## Architectural responsibilities

Owns statistics collection, aggregation, ranking views, and related administrative controls.

Registration attribution is **first-touch**:

- `referer_url` cookie — external `HTTP_REFERER` as received. After browsers
  stopped sending referrer query strings, this is typically `scheme://host`.
- `landing_url` cookie — first request URI that contains tracking query keys
  (`ctm_*`, `utm_*`, `gclid`, and similar). Set only if empty. This is the
  first-party stand-in for the campaign/keyword data that used to arrive on
  the referrer.

Both are written to `stats_referer_urls` / `stats_landing_urls` and
`stats_referer_users_map` at register. Do not copy landing query parameters
onto the stored referrer URL.

`ad_roas.php` compares **Commerce ROAS** to **{network} ROAS** so staff can
set that advertiser's target ROAS:

- **Cost** — `SUM(stats_ad_metrics_daily.spend)` at campaign grain. Any
  `network_code`.
- **Commerce value** — paid `order_total` in the date range from users whose
  first-touch registration is also in that range. Return on **this period's**
  spend, not old customers buying again.
- **Commerce value (click window)** — those same new users' orders within
  `stats_ad_network.click_window_days` of registration (purchases may fall
  after `until`). Comparator for the advertiser click lookback / tROAS.
- **Network value** — `stats_ad_metrics_daily.network_value`. Network ROAS is that
  over the same spend. Partial / last-click; never substitute for Commerce.

Click-through window is stored on `stats_ad_network`. If it is null, the page asks
and saves it.

`admin/ad_setup.php` (`p_stats_admin`) is the control panel for warehouse API
credentials. Instructions live on the page. Values are stored in
`stats_prefs` (lazy TEXT; not `kernel_config`) and are never assigned raw
to templates.
Microsoft OAuth uses this page as the redirect URI to obtain a refresh
token. Google unattended auth is the service-account JSON plus developer
token; access tokens are minted at pull time and not stored.

Warehouse SQL is `admin/ad_warehouse_schema.sql` (idempotent, including
`stats_prefs` for credentials that do not fit `kernel_config` C(250)).
After deploy, one command applies schema, pulls Google campaign metrics, and
backfills attribution:

`php stats/admin/sh_ad_warehouse_refresh.php --full`

Nightly: `sh_ad_warehouse_pull.php --metrics=campaign --metrics-only` then
`sh_ad_warehouse_backfill.php`. Dev: `IS_DEV=1`. Prod: `IS_LIVE=1`. These
scripts live in this package so they deploy with the site.

The ROAS page does not create warehouse tables or write to ad networks. Without
Bitcommerce, spend can still list and value is zero.

## Dependency direction

This package depends on kernel, liberty, users, themes. Calls into shared packages should
use their public classes, globals, services, and registered content types rather
than duplicating their persistence.

Does not own canonical content hit storage when that data belongs to Liberty.

## Request and rendering pattern

Most package controllers follow Bitweaver's established flow:

1. Load Kernel setup.
2. Resolve and validate request identifiers.
3. Construct or load the domain object.
4. Enforce global and content-level permissions before mutation or disclosure.
5. Perform the domain operation.
6. Assign data to Smarty and render a package template.

Package-specific controllers and templates are enumerated in
[source-reference.md](source-reference.md). Read the controller and its included
files together; many older controllers delegate substantial behavior to
`*_inc.php` files.

## Persistence

When `admin/schema_inc.php` exists it is the canonical install-time declaration
of tables, sequences, indexes, constraints, permissions, and default
preferences. Runtime SQL must be checked against that file and relevant upgrade
scripts. Never infer a deployed database's exact migration state from the base
schema alone.

`admin/upgrades/1.0.2.php` adds `stats_landing_urls` and
`stats_referer_users_map.landing_url_id`. Some deployments created those
objects out of band before the upgrade existed.

## Presentation

Templates belong to the package but are resolved through Themes/Smarty.
Controllers own request handling; templates should render assigned state rather
than perform domain mutations.
