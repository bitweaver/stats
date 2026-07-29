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
