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

`referrers.php` nests PPC as campaign → ad group → `ctm_term`. Named Search
uses `ctm_campaign` / `ctm_adgroup`. Paid clicks without CTM that land on
`/create/{slug}` stay under `untracked` with the slug as ad group (Search
final URLs). Other paid landings (`/help/…`, home, etc.) are Performance
Max: Google can promote any site URL. If `gad_campaignid` matches a
warehouse `PERFORMANCE_MAX` campaign, that campaign name is used.
Warehouse backfill and ROAS key on `campaign_id` only. Numeric `utm_campaign`
wins (exact warehouse id), then `gad_campaignid` if it is a campaign id, then
a unique warehouse match on `ctm_campaign`. ValueTrack names are labels, not
join keys; unmatched names are not ROAS rows. Commerce ROAS revenue is
paid orders in the spend window for the campaign's first-touch customers,
including customers who registered before the window, plus orders after
`until` that are still within N days of a registration in the window.
Unpaid/organic groups by landing path
with the query stripped (`srsltid`). Revenue is lifetime commerce totals
when bitcommerce is active.

`ad_roas.php` (`p_stats_admin`) compares Commerce ROAS to the selected
network's ROAS (for setting that network's target — a bid target, not a
floor). Cost is warehouse `stats_ad_metrics_daily.spend` on the click dates
in the range. Commerce value is Bitcommerce paid `order_total` in that
range for the campaign's first-touch customers, including customers who
registered earlier. Purchases after `until` count when registration is in
the range and still inside `stats_ad_network.click_window_days`. LTV is
those customers' paid `order_total` from `since` on, with no day cap.
`network_value / spend` is the advertiser's click-dated conversion value. If the click
window is unknown, the page asks and stores it. Queries live in
`includes/ads_roas_lib.php`.

Rebuild a spend window (wipe derived warehouse, optional log re-import lives
in the products log importer): `admin/sh_ad_warehouse_rebuild.php --since=
--wipe`. Nightly pull defaults to the last 90 days (Google click-through
window) then backfill. Do not copy attribution between databases.

## Tables

- `stats_pageviews` — aggregate/time-oriented pageview data.
- `stats_referers` — referrer host hit counters (`scheme://host`).
- `stats_referer_urls` — normalized referrer URL identities (first-touch).
- `stats_landing_urls` — first-touch landing path + query (`landing_query`
  holds `ctm_*` / `utm_*` / `gclid` when split correctly).
- `stats_referer_users_map` — `user_id` → referrer and optional landing.

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
