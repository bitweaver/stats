# Developing Stats

## Start here

1. Read [architecture.md](architecture.md).
2. Locate the relevant controller, class, schema declaration, and template in
   [source-reference.md](source-reference.md).
3. Follow includes from the controller and inspect the parent classes it
   extends.
4. Confirm permissions and input validation before changing behavior.
5. Check upgrade scripts as well as the base schema for persistence changes.

## Change rules

- Preserve package boundaries described in [README.md](README.md).
- Load Bitweaver through Kernel setup; do not reproduce bootstrap logic.
- Use ADOdb and existing bind-variable patterns for database access.
- Use Liberty content APIs for content-bearing records.
- Use Users/Liberty permission APIs before reads that disclose protected data
  and before every mutation.
- Wrap user-visible strings with `tra()`.
- Keep business logic out of Smarty templates.
- Reuse registered package paths and URLs instead of hard-coded deployment
  paths.
- Treat request parameters as untrusted even when a controller is admin-only.
- Controllers (and CLI entry scripts) read `$_GET` / `$_POST` / `$_REQUEST`.
  Shared helpers live in `includes/<group>_lib.php` and take `$pParameters`
  (use `BitBase::getParameter`). Do not read superglobals inside those libs.

## Extension points

- **Revenue source for ROAS.** Another package can supply the value side
  without editing this package. In its `bit_setup_inc.php`:

  ```php
  $gLibertySystem->registerService( 'mypkg', 'mypkg', array(
      'stats_revenue_source_function' => 'mypkg_stats_revenue_source',
  ) );
  ```

  The function takes `$pDb` and returns `array( 'label', 'ready', 'orders_sql',
  'bind' )`. `orders_sql` must yield `order_id, user_id, purchased_at
  (timestamp), revenue (numeric), currency`; `bind` holds its placeholders.
  The default is Bitcommerce paid `order_total` (`orders_status_id > 0`).
  Use it for install rules such as excluding test or staff orders.
- **Page settings** live in `stats_prefs` through `stats_pref_get()` /
  `stats_pref_set()`: `roas_gross_margin_pct`, `roas_desired_commerce_roas`.
- **Campaign aliases** (`stats_ad_campaign_alias`) map legacy tracking labels
  to campaign ids; edited on the Ad API setup page.

## Schema changes

Update both installation and upgrade paths. Define portable schema through the
installer abstraction unless the package explicitly supports only one database.
Document new tables, indexes, constraints, sequences, preferences, and cleanup
behavior.

## Testing checklist

- Exercise anonymous, authenticated, owner, editor, and administrator paths as
  applicable.
- Test missing, malformed, and unauthorized identifiers.
- Test create, load, update, list, and expunge behavior for affected objects.
- Verify templates with empty and large result sets.
- Confirm service callbacks remain safe when optional packages are absent.
- Run syntax checks and package-specific tests where present.
- Review logs without exposing credentials, tokens, or personal data.

## Documentation maintenance

Update these documents in the same package change when a public class,
controller, table, permission, service callback, configuration key, or external
integration changes. Generated-looking inventories must still be checked against
the actual source.
