# Stats collection and reporting

## Runtime collection

When active, package setup constructs `Statistics`. Feature flags control:

- `stats_pageviews` → `addPageview()`.
- `stats_referers` → `storeReferer()` (host-level hit counter) and first-touch
  registration cookies.

`stats_capture_first_touch()` (anonymous users only, cookie empty):

- `referer_url` from external `HTTP_REFERER`.
- `landing_url` from `REQUEST_URI` when the query contains tracking keys
  (`ctm_*`, `utm_*`, `gclid`, `msclkid`, `gad_*`).

Cookies last 180 days, SameSite=Lax, not overwritten. On register,
`stats_persist_registration_attribution()` inserts URL rows and the user map.
On expunge the map row is deleted; URL rows are kept.

Do not treat `HTTP_REFERER` as the campaign record. Tracking keys belong on
the **landing** query. A paid click whose landing has `gclid` / empty `ctm_*`
and no named `ctm_campaign` is untracked paid traffic, not organic.

`referrers.php` nests named `ctm_campaign` → `ctm_adgroup` → `ctm_term` from
`Statistics::trackingParamsFromRow()` (landing first, then legacy referrer
`adurl=`). Revenue is lifetime commerce totals when bitcommerce is active.

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
controllers and templates.

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
