# Changelog

All notable changes to Laravel Periscope will be documented in this file.

## v0.1.0 - 2026-07-13

- Initial public package release.
- Added a Telescope-compatible `/periscope` dashboard.
- Added URL-driven date range, type, text, tag, status, method, path, and errors-only filtering.
- Added entry detail views for requests, logs, mail, queries, commands, jobs, gates, exceptions, views, models, cache, events, and HTTP client entries.
- Added request lifecycle view for Telescope batches.
- Added local browser filters, watch mode, and row-level drill-down.
- Inherited access control from `Laravel\Telescope\Telescope::check()`.
- Added automatic Periscope path exclusion from Telescope request entries.
- Added Debugbar suppression for Periscope/Telescope dashboard traffic.
