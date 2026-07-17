# Changelog

All notable changes to Laravel Periscope will be documented in this file.

## v0.3.1 - 2026-07-17

- Added a schedule runs view for inspecting scheduled command executions from Telescope entries.
- Added tracking and display of scheduled command durations.
- Linked schedule run summaries from the request overview navigation.

## v0.2.2 - 2026-07-16

- Removed redundant submarine-specific style overrides now covered by shared theme rules
- Narrowed grouped harbor/meadow/abyss selectors to harbor/meadow where abyss has dedicated overrides
- add --blue color token to root and each theme (submarine, harbor, meadow, abyss)
- `severity-info` styles now use the new theme-specific blue values

## v0.2.1 - 2026-07-16

- Added Harbor, Meadow, and Abyss theme variants to the sidebar theme switcher.
- Documented the expanded light and dark theme options while keeping Open Water as the default theme.

## v0.2.0 - 2026-07-15

- Added stronger status badges with dark red 5xx/error states and clearer warning/success styling.
- Added stack trace support for exception details and related error views.
- Added expandable source previews with syntax highlighting, compact default context, method-level expansion, surrounding whitespace, and PHPDoc inclusion.
- Added request lifecycle debugging improvements including page load flow, timeline tabs, overview/error sections, debug insights, query health, external call health, and pre-error context.
- Added copyable debug bundles for lifecycle investigations.
- Hid vendor and Laravel core frames from application-focused stack traces and page load flow views.
- Added request detail referrer display when Telescope captures `referer` or `referrer` headers.
- Added Telescope recording suppression for Periscope pages so dashboard refreshes do not create request, query, cache, model, view, log, or similar Telescope entries.
- Added reusable partials for status badges and stack traces.
- Hardened Telescope entry normalization so array values do not break titles, subtitles, callers, previews, paths, or mail addresses.
- Refined the visual palette so red remains the dominant error signal and warnings/brand accents are less visually noisy.

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
