# Changelog

All notable changes to `php-exception-reporter` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.1.0] - 2026-03-22

### Added
- `withContext()` method for attaching persistent context fields to all exception reports
- `setFilter()` method for skipping certain exception types from being reported
- `count()` method for tracking the number of reported exceptions

## [1.0.3] - 2026-03-20

### Added
- Expanded test suite with dedicated FileChannel and ExceptionReport tests

## [1.0.2] - 2026-03-17

### Changed
- Standardized package metadata, README structure, and CI workflow per package guide

## [1.0.1] - 2026-03-16

### Changed
- Standardize composer.json: add type, homepage, scripts

## [1.0.0] - 2026-03-13

### Added

- `ExceptionReporter` class for capturing and reporting exceptions to multiple channels
- `ExceptionReport` value object with fingerprinting and serialization
- `ReportChannel` interface for building custom reporting channels
- `CallbackChannel` for reporting via user-provided callbacks
- `FileChannel` for appending JSON-encoded reports to log files
- Deduplication support to prevent duplicate reports for the same exception origin
- Context array support for attaching metadata to reports
