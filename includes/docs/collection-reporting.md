# Stats collection and reporting

## Runtime collection

When active, package setup constructs `Statistics`. Feature flags control:

- `stats_pageviews` → `addPageview()`.
- `stats_referers` → `storeReferer()` (host-level hit counter) and first-touch
  registration cookies.

`stats_capture_first_touch()` (anonymous users only, cookie empty):

- `referer_url` from external `HTTP_REFERER` (often origin-only; browsers
  strip the query).
- `landing_url` from the first `REQUEST_URI` (path + query). Tracking keys
  (`ctm_*`, `utm_*`, `gclid`, `msclkid`, `gad_*`) live on that landing query.
  Paid vs organic is decided later from those keys, not from whether a
  landing was stored.

Cookies last 180 days, SameSite=Lax, not overwritten. On register,
`stats_persist_registration_attribution()` inserts URL rows and the user map.
On expunge the map row is deleted; URL rows are kept.

This site must **never** be a referer (paradox). `stats_store_user_first_touch`
and `referrers.php` reject own-host Referers (`kernel_server_name` and the
request host, including `www`). If that URL has `gclid` / `ctm_*` / `msclkid`,
it is the **landing** and the bucket is google.com / bing.com. Unpaid
same-site Referer is dropped (`none`). Tracking keys belong on the landing
query, not `HTTP_REFERER`. A paid click whose landing has `gclid` / empty `ctm_*`
and no named `ctm_campaign` is untracked paid traffic, not organic.

`referrers.php` nests PPC as campaign → ad group → `ctm_term`. Campaign
nodes come from `Statistics::ppcCampaignNode()`, which runs the same
resolver as the warehouse (`ads_parse_landing_keys()`): keyed by campaign id
when a numeric `utm_campaign` / `gad_campaignid` or a unique (or aliased)
`ctm_campaign` names one, else by the tracking label, else `untracked`
(paid click on `/create/{slug}`) or `Performance Max` (other paid landings
without keys). Id-keyed nodes link to `ad_roas.php` for the same period.
Revenue per registrant is lifetime paid orders plus an "in period" column
for the period's dates, fetched by `ads_roas_user_revenue_map()` in one
grouped query per 1,000 users.

`ad_roas.php` (`p_stats_admin`) is described in
[architecture.md](architecture.md): period revenue ↔ conversion-date value,
cohort revenue ↔ click-dated value, spend-weighted target from the settings
history, reconciliation of all paid orders, inline SVG trend, campaign
drill-down and CSV. The revenue source defaults to Bitcommerce paid
`order_total` and can be replaced by another package (development.md).

Rebuild a spend window (wipe derived warehouse; log re-import is a separate
importer): `admin/sh_ad_warehouse_rebuild.php --since= --wipe`. Nightly pull
defaults to the last 90 days (the network's click-through window) then
backfill. Do not copy attribution between databases.

## Warehouse pulls (Google Ads API, read only)

`admin/sh_ad_warehouse_pull.php` flags and what they fill:

| Flag | Tables | Notes |
|---|---|---|
| (entities, default) | `stats_ad_account`, `stats_ad_campaign`, `stats_ad_campaign_settings_daily`, `stats_ad_conversion_action`, ad groups, asset groups, ads, keywords | Settings snapshot is stamped with the account-local day; a rerun the same day overwrites. Portfolio bid strategies come from `accessible_bidding_strategy`; `bidding_scope` says which. |
| `--metrics=campaign,adgroup,keyword` | `stats_ad_metrics_daily` | Click-dated `network_conversions` / `network_value` plus `all_conversions*` and `*_by_conv_date` (the network's conversion-date view). Campaign grain also gets `search_is`, `search_budget_lost_is`, `search_rank_lost_is` in a second query; those are null for PMax/Display and clamped by the network to 0.0999 / 0.9001. |
| `--conversions` | `stats_ad_conversion_daily` | One row per campaign, day and conversion action. Never holds cost, clicks or impressions (the API refuses those with conversion segments). The window is deleted and re-inserted. |
| `--clicks` | `stats_ad_click` | `click_view` accepts one day per query and keeps 90 days; the pull clamps the window. Rows are one per click id. PMax click ids can outnumber billed clicks several times (engagement interactions), so this table is a lookup, not a click count. gbraid/wbraid clicks are not resolvable. Never purged. |
| `--fields-check` | — | Prints whether each GAQL field exists on the configured API version and which segments it can be selected with. Run it before changing the version pref. |

`stats_ad_network.click_window_days` is set from the catalog: the longest
click-through lookback among enabled PURCHASE actions that count in
conversions (`window_source = 'network'`). A value saved on the ROAS page
(`'user'`) is not overwritten. The schema seeds 90 (`'default'`) only when
nothing is stored.

Backfill (`admin/sh_ad_warehouse_backfill.php`) resolves landings with
`ads_parse_landing_keys()`, then matches `click_id` against `stats_ad_click`:
a user whose landing had no campaign gets the click's campaign and
`source = 'click_view'`; a user already resolved from the landing keeps that
source and gains `click_date`. Order rows copy `click_date`, `source` and
the user's `extra` (so `untracked_paid` survives at order level).
Staff map legacy tracking labels to ids in `stats_ad_campaign_alias`
(Ad API setup, Campaign aliases tab); `ads_google_campaign_lookup()` consults
aliases after exact names and they also break name ties.

## Tables

- `stats_pageviews` — aggregate/time-oriented pageview data.
- `stats_referers` — referrer host hit counters (`scheme://host`).
- `stats_referer_urls` — normalized referrer URL identities (first-touch).
- `stats_landing_urls` — first-touch landing path + query (`landing_query`
  holds `ctm_*` / `utm_*` / `gclid` when split correctly).
- `stats_referer_users_map` — `user_id` → referrer and optional landing.

Warehouse tables are declared in `admin/ad_warehouse_schema.sql`
(idempotent; applied by every warehouse CLI):

- `stats_ad_network` — network code, click/view windows, `window_source`.
- `stats_ad_account`, `stats_ad_campaign` (current settings snapshot incl.
  `target_roas`, `target_cpa`, `budget_amount`, `primary_status`),
  `stats_ad_adgroup`, `stats_ad_ad`, `stats_ad_keyword`.
- `stats_ad_campaign_settings_daily` — one row per campaign per account-local
  day: bidding type and scope, targets, budget, statuses. The target in force
  on a metric day is the latest snapshot on or before it.
- `stats_ad_metrics_daily` — date × grain × campaign/adgroup/keyword: spend,
  clicks, impressions, click-dated and conversion-dated conversions and
  value, search impression share (campaign grain).
- `stats_ad_conversion_action` — the network's conversion-action catalog with
  each action's lookback windows and whether it counts in conversions.
- `stats_ad_conversion_daily` — conversions and value per campaign, day and
  conversion action. No cost.
- `stats_ad_click` — click id → campaign/ad group/keyword/date (90-day
  retention at the network; kept here for good).
- `stats_ad_user_attribution` (PK `user_id`), `stats_ad_order_attribution`
  (PK `orders_id`) — first touch, with `click_date` when a click was matched.
- `stats_ad_campaign_alias` — staff label → campaign id.
- `stats_prefs` — API credentials and non-secret page settings.

Review exact columns and upgrade state before reporting queries. Deployed
databases may have `stats_landing_urls` before `schema_inc.php` declared it.

## Referrer handling

`HTTP_REFERER` and the attribution cookie are untrusted, optional strings.
Parse with URL helpers, bind them in SQL, limit length, and escape in output.
Do not assume the referrer proves where a user came from.

Referrer URLs can contain search terms, identifiers, or secrets in query
strings. Establish retention and access policy; consider query redaction.

## Reporting API

`Statistics` provides:

- `getRefererList()` and `expungeReferers()`.
- `registrationStats()`.
- `getSiteStats()`.
- `getContentOverview()` / `getContentStats()`.
- Pageview, usage, and content-type chart data.

List methods accept parameter hashes for filtering, sort, and pagination.
Validate sort modes and bind filter values.

## Permissions

Menu/report visibility uses `p_stats_view` and
`p_stats_view_referer`. Referrer/user attribution is more sensitive than
aggregate site statistics; preserve the narrower permission distinction in
controllers and templates. First-party ad ROAS (spend and order totals) uses
`p_stats_admin`.

## Counting semantics

Define metrics before comparing them:

- Page request versus rendered content view.
- Anonymous versus registered traffic.
- Bot/internal/health-check inclusion.
- Unique visitor versus raw count.
- Timezone/day boundary.
- Deleted/private content.

Do not combine Liberty hit counters and Stats pageviews as though they are the
same event.

## Performance

Collection runs during normal setup, so writes affect every request when
enabled. Keep it bounded, indexed, and failure-tolerant. Reporting over large
ranges should aggregate in SQL and enforce maximum windows.

## Privacy and retention

Statistics may contain URLs, search terms, timestamps, cookies, user mappings,
and potentially IP/user-agent data in adjacent logs. Document:

- Purpose and lawful/organizational basis.
- Retention and deletion.
- Administrative access.
- User expunge behavior.
- Export/backups.

## Testing

- Internal, absent, malformed, and external referrers.
- Cookie-disabled and later registration flow.
- User expunge removes attribution.
- Feature flags prevent collection.
- Permission separation for aggregate/referrer reports.
- Date boundaries and empty/large datasets.
