# Stats collection and reporting

## Runtime collection

When active, package setup constructs `Statistics`. Feature flags control:

- `stats_pageviews` → `addPageview()`.
- `stats_referers` → `storeReferer()`.

External referrer information can be stored in a cookie for later registration
attribution. User service callbacks map that referrer when a user registers and
remove mappings on user expunge.

## Tables

- `stats_pageviews` — aggregate/time-oriented pageview data.
- `stats_referers` — referrer counters/records.
- `stats_referer_urls` — normalized referrer URL identities.
- `stats_referer_users_map` — registration attribution.

Review exact columns and upgrade state before reporting queries.

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
