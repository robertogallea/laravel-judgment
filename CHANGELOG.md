# Changelog

All notable changes to this package are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the package uses [Semantic Versioning](https://semver.org/).

## [0.2.0] - 2026-09-26

Upgrading from 0.1.0 breaks five things, marked **Breaking** below. Follow the [Upgrade guide](https://robertogallea.github.io/laravel-judgment/#upgrading).

### Added

- Failed attempts are recorded as Unassessed, sync or queued, thrown or returned, with the `EngineFailed` class and message, and with the Provenance when the Engine responded with something unusable. Recording one is always best-effort ([ADR-0014](docs/adr/0014-unassessed-attempts-are-recorded-best-effort.md)). `Unassessed` and `AssessmentFailed` carry the record. ([#35](https://github.com/robertogallea/laravel-judgment/issues/35))
- `AssessmentRecord` gains the `answered()` and `unassessed()` scopes and `isUnassessed()`. Rebuilding a failed record throws `UnrebuildableAssessment`. ([#35](https://github.com/robertogallea/laravel-judgment/issues/35))
- `AssessmentDecided` fires for every decision that counts, sync or queued, so the Action for an Outcome lives in one listener. It never fires for a Replay or an `Assessment::fake()`. ([#37](https://github.com/robertogallea/laravel-judgment/issues/37))
- A dispatched Judgment with a default Decision is decided by a `DecideAssessment` job chained after the assessing job. It works on the record, so its retries (`judgment.queue.decide_tries`, default 3) never ask the Engine again ([ADR-0015](docs/adr/0015-queued-judgments-are-decided-in-a-chained-job.md)). ([#40](https://github.com/robertogallea/laravel-judgment/issues/40))
- A public Calibration API: `Calibration::for($judgment)` builds a run from Resolutions, a dataset or in-memory `LabelledCase`s, and `run()` returns a read-only `CalibrationReport`. ([#36](https://github.com/robertogallea/laravel-judgment/issues/36), [#38](https://github.com/robertogallea/laravel-judgment/issues/38))
- `judgment:eval --json` prints the Calibration report as JSON. ([#39](https://github.com/robertogallea/laravel-judgment/issues/39))
- `Judge::fake()` records scripted Assessments and Unassessed attempts like the Judge, following `judgment.persistence.*`. ([#41](https://github.com/robertogallea/laravel-judgment/issues/41))
- Documentation moved to a [GitHub Pages site](https://robertogallea.github.io/laravel-judgment/), with a guide to passing the Evidence language to the Engine ([#34](https://github.com/robertogallea/laravel-judgment/issues/34)) and an Upgrade section ([#42](https://github.com/robertogallea/laravel-judgment/issues/42)).

### Changed

- **Breaking:** a new migration alters `judgment_assessments` to hold Unassessed attempts: `answers`, `engine`, `model` and `provenance_details` become nullable, and `failure_type` and `failure_message` are added. Publish and run it. ([#35](https://github.com/robertogallea/laravel-judgment/issues/35))
- **Breaking:** `latestAssessment()` returns the latest attempt, which may be Unassessed. Use the `answered()` scope where answers are needed. ([#35](https://github.com/robertogallea/laravel-judgment/issues/35))
- **Breaking:** queued Judgments are now decided by the package. An `AssessmentCompleted` listener calling `$record->outcome()` decides them a second time: move the Action to `AssessmentDecided`. ([#37](https://github.com/robertogallea/laravel-judgment/issues/37), [#40](https://github.com/robertogallea/laravel-judgment/issues/40))
- **Breaking:** assessing through `Judge::fake()` needs the migration, or persistence turned off, and throws `AssessmentNotRecorded` otherwise. ([#41](https://github.com/robertogallea/laravel-judgment/issues/41))
- **Breaking:** `judgment:eval`'s tables are not a contract. Read the report through the Calibration API or `--json` instead of parsing the tables. ([#36](https://github.com/robertogallea/laravel-judgment/issues/36))

### Fixed

- A Replay no longer logs "Judgment decided."; the line is written only when a linked Assessment is decided. ([#33](https://github.com/robertogallea/laravel-judgment/issues/33))

## [0.1.0] - 2026-09-25

First release.

[0.2.0]: https://github.com/robertogallea/laravel-judgment/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/robertogallea/laravel-judgment/releases/tag/v0.1.0
