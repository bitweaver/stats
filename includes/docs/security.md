# Stats security notes

## Direct HTTP access

The entire `includes/` subtree is private implementation material. The
package-level `.htaccess` denies Apache access recursively and explicitly
protects both `.htaccess` and `web.config`. The package-level `web.config`
denies all IIS users.

Nginx and Caddy do not consume directory-local access files. Their site
configuration must deny any URI path segment named `includes`:

### Nginx

```nginx
location ~ (^|/)includes(?:/|$) {
    deny all;
    return 403;
}
```

### Caddy

```caddyfile
@packageIncludes path_regexp packageIncludes (^|/)includes(?:/|$)
respond @packageIncludes 403
```

After deployment, request a known file beneath this package's `includes/`
directory and require HTTP 403 or 404. A PHP 500 response is a failure because
it proves that the server executed a directly requested implementation file.

## Application trust boundaries

- Request, cookie, header, upload, webhook, and API data are untrusted.
- Authentication does not imply authorization.
- Content-level access can be stricter than a global package permission.
- Identifiers must be validated before use in SQL, paths, redirects, or object
  construction.
- Ad API tokens on `admin/ad_setup.php` require `p_stats_admin`. Saved values
  are `stats_prefs`, not templates or `includes/docs/`. Masked status only.
- Secrets and credentials must remain in protected configuration, never in
  templates, responses, logs, or these documents.
- File operations must use validated storage helpers and must prevent traversal.

## Referrer and landing URLs

`p_stats_view_referer` is the sensitive report. Stored referrer and landing
strings may contain search terms, click ids, and email-like query values.
Treat them as untrusted. Escape in templates. Do not log cookie values into
package documentation.

## Package boundary

Does not own canonical content hit storage when that data belongs to Liberty.
