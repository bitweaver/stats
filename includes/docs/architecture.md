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
- `landing_url` cookie — first request URI (path + query). Set only if empty.
  Tracking keys (`ctm_*`, `utm_*`, `gclid`, and similar) on that query are the
  first-party stand-in for campaign data that used to arrive on the referrer.

Both are written to `stats_referer_urls` / `stats_landing_urls` and
`stats_referer_users_map` at register. Do not copy landing query parameters
onto the stored referrer URL.

`ad_roas.php` (`p_stats_admin`) puts three things side by side per campaign
so staff can set the network's bid target from this install's books: what
the network reports, what our order books show, and the gap between them.

- **Cost** — `SUM(stats_ad_metrics_daily.spend)` at campaign grain on the
  network's click dates in the range.
- **Period revenue** — the revenue source's paid orders purchased in the
  range by the campaign's first-touch customers, whenever they registered.
  Pairs with the network's conversion-date value (`value_by_conv_date`).
- **Cohort revenue (Nd)** — customers whose first touch (stored click date,
  else registration) is in the range; their paid orders within the click
  window of that first touch, even after `until`. Pairs with the network's
  click-dated value (`network_value`).
- **Cohort LTV** — the same customers' paid orders to date, no cap.
- **Target** — the network's target ROAS, spend-weighted over
  `stats_ad_campaign_settings_daily`; days before the first snapshot use the
  current target (`target_source` = history / mixed / current). The target
  is a bid target the network delivers toward on budget-limited campaigns,
  not a floor it beats.
- **Gap** — value ratio = network click-dated value ÷ cohort revenue;
  suggested network target = desired commerce ROAS × value ratio; break-even
  ROAS = 100 ÷ gross margin %. Margin and desired ROAS are page settings in
  `stats_prefs`.
- **Budget-limited** — search budget-lost impression share ≥ 10% or the
  network's `BUDGET_CONSTRAINED` status.

The page also reconciles every paid order in the range (attributed by id,
label only, paid click without a campaign, other networks, organic, no first
touch), draws ROAS per day/week/month as inline SVG, and drills into one
campaign (settings history, conversion actions, ad groups, days, orders).
Range comes from `period`+`timeframe` (the registration report's labels),
a `preset`, or `since`/`until`. Queries live in `includes/ads_roas_lib.php`;
the revenue source is pluggable (see development.md).

Click-through window is stored on `stats_ad_network`. The entity pull sets
it from the network's own purchase-action lookback; the page asks only when
nothing is stored, and a value typed there wins.

`admin/ad_setup.php` (`p_stats_admin`) is the control panel for warehouse API
credentials. Instructions live on the page. Values are stored in
`stats_prefs` (lazy TEXT; not `kernel_config`) and are never assigned raw
to templates.
Microsoft OAuth uses this page as the redirect URI to obtain a refresh
token. Google unattended auth is the service-account JSON plus developer
token; access tokens are minted at pull time and not stored.

Warehouse SQL is `admin/ad_warehouse_schema.sql` (idempotent, including
`stats_prefs` for credentials that do not fit `kernel_config` C(250)).
Wipe derived warehouse rows and rebuild Google metrics + attribution for a
date window:

`php stats/admin/sh_ad_warehouse_rebuild.php --site_name=example --since=2026-01-01 --wipe`

That keeps `stats_prefs` and `stats_ad_network`. Log re-import (first-touch
landings) is a separate products CLI; the OEM wrapper calls both. `--full`
history pull is `sh_ad_warehouse_refresh.php --full` and is slow.

Nightly: `sh_ad_warehouse_pull.php --metrics=campaign,adgroup --conversions
--clicks` (entities included, which refreshes the settings history) then
`sh_ad_warehouse_backfill.php`. CLI host class sets `IS_DEV`; live rebuilds
require `--live`. These scripts live in this package so they deploy with the
site. Pull flags and the tables they fill are in
[collection-reporting.md](collection-reporting.md).

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
