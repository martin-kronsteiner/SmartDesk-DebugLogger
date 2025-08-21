## [1.0.2] - 2025-08-21
### Added
- WP-IMPLEMENTATION.md: Composer/manual install, robust plugin bootstrap (autoloader fallback),
  theme & multisite setup, hook/action tracking, DB query logging, WP-CLI, security, troubleshooting.

### Changed
- DebugLoggerUtil: English messages; start/mid/total timers; exception logging downgraded to DEBUG in self-test.

### Fixed
- Docs: correct public API signature usage (`DebugLogger::<level>($data, $hooks, $title)`) and examples.

### Docs
- README: link to WP-IMPLEMENTATION.md under “Plugin Integration”.
