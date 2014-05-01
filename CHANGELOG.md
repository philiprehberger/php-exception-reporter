# Changelog

All notable changes to this project will be documented in this file.

## [1.0.0] - 2026-03-13

### Added

- `ExceptionReporter` class for capturing and reporting exceptions to multiple channels
- `ExceptionReport` value object with fingerprinting and serialization
- `ReportChannel` interface for building custom reporting channels
- `CallbackChannel` for reporting via user-provided callbacks
- `FileChannel` for appending JSON-encoded reports to log files
- Deduplication support to prevent duplicate reports for the same exception origin
- Context array support for attaching metadata to reports
