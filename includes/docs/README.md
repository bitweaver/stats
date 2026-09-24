# Stats package documentation

> Engineering documentation derived from the source in this package. The
> package's `includes/` directory must be denied to direct HTTP requests.

## Purpose

Stats records and presents site and content activity statistics.

## Responsibility

Owns statistics collection, aggregation, ranking views, and related administrative controls.

## Dependencies

kernel, liberty, users, themes.

Dependency direction matters: this package may depend on the packages above;
the dependencies do not thereby depend on this package.

## Boundary

Does not own canonical content hit storage when that data belongs to Liberty.
Does not own advertising-account configuration (final URLs, tracking
templates). Installs that put campaign keys on landing URLs (for example
`ctm_*`) persist those keys here as first-touch landing query data.
Ad-network warehouse tables (`stats_ad_metrics_daily`, `stats_ad_*_attribution`) are
optional. ROAS cost is warehouse `spend` (any network). Commerce ROAS value is
this install's Bitcommerce paid `order_total` in the spend range for the
campaign's first-touch customers, including customers who registered earlier,
plus the click-window tail after `until` for registrations in the range.
Network ROAS is `network_value / spend` for comparison. LTV ROAS is those
customers' paid `order_total` from the start of the range on, with no day cap.
Each network's click-through conversion window is stored on
`stats_ad_network` (asked if unknown). Service credentials live in `stats_prefs`,
loaded only by ads setup and warehouse CLI. Rebuild a window with
`admin/sh_ad_warehouse_rebuild.php` (wipe derived rows, pull, attribute).

## Documentation map

- [Architecture](architecture.md) — initialization, components, and request flow.
  Includes first-touch **referrer** vs **landing** cookies.
- [Source reference](source-reference.md) — source-derived files, classes,
  controllers, schema artifacts, plugins, and templates.
- [Development guide](development.md) — safe change workflow, extension points,
  validation, and maintenance guidance.
- [Security](security.md) — trust boundaries and direct-HTTP access requirements.
- [Collection and reporting](collection-reporting.md) — pageviews, referrers,
  first-touch landing / tracking-query attribution, content summaries, privacy,
  and retention.
