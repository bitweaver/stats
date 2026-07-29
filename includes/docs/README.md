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

## Documentation map

- [Architecture](architecture.md) — initialization, components, and request flow.
- [Source reference](source-reference.md) — source-derived files, classes,
  controllers, schema artifacts, plugins, and templates.
- [Development guide](development.md) — safe change workflow, extension points,
  validation, and maintenance guidance.
- [Security](security.md) — trust boundaries and direct-HTTP access requirements.
- [Collection and reporting](collection-reporting.md) — pageviews, referrers,
  registration attribution, content summaries, privacy, and retention.
