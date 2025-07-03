# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/) and this project adheres to
[Semantic Versioning](http://semver.org/).

## [Unreleased]

## [v1.6.1] - 2025-07-03

### Added
- Improve response code evaluation in the `check-links` CLI command.

## [v1.6.0] - 2025-07-03

### Added
- New `check-links` CLI command. Closes issue [5](https://github.com/raphaelstolt/llms-txt-php/issues/5).

## [v1.5.1] - 2025-07-02

### Fixed
- Guard `info` CLI command against non-existing files.

## [v1.5.0] - 2025-07-02

### Added
- New `info` CLI command.

## [v1.4.0] - 2025-07-02

### Added
- New `init` CLI command.

## [v1.3.1] - 2025-07-01

### Improved
- The `validate` method checks now also for the availability of at least one section link.

## [v1.3.0] - 2025-06-30

### Added
- The `validate` CLI command can also take a URL as an argument. Closes issue [4](https://github.com/raphaelstolt/llms-txt-php/issues/4).

## [v1.2.2] - 2025-06-30

### Improved
- The validation status of the `validate` CLI command is now also visible.

## [v1.2.1] - 2025-06-27

### Added
- New shortcut method `addSections` to set an array of sections.

## [v1.2.0] - 2025-06-27

### Added
- New `validate` CLI command. Closes issue [2](https://github.com/raphaelstolt/llms-txt-php/issues/2).

## [v1.1.0] - 2025-06-27

### Added
- New `validate` method.

## [v1.0.1] - 2025-06-26

### Fixed
- Fix llms.txt parsing with more complex files i.e. the one from `uv`.

## v1.0.0 - 2025-06-25

- Initial release.

[Unreleased]: https://github.com/raphaelstolt/llms-txt-php/compare/v1.6.1...HEAD
[v1.6.1]: https://github.com/raphaelstolt/llms-txt-php/compare/v1.6.0...v1.6.1
[v1.6.0]: https://github.com/raphaelstolt/llms-txt-php/compare/v1.5.1...v1.6.0
[v1.5.1]: https://github.com/raphaelstolt/llms-txt-php/compare/v1.5.0...v1.5.1
[v1.5.0]: https://github.com/raphaelstolt/llms-txt-php/compare/v1.4.0...v1.5.0
[v1.4.0]: https://github.com/raphaelstolt/llms-txt-php/compare/v1.3.1...v1.4.0
[v1.3.1]: https://github.com/raphaelstolt/llms-txt-php/compare/v1.3.0...v1.3.1
[v1.3.0]: https://github.com/raphaelstolt/llms-txt-php/compare/v1.2.2...v1.3.0
[v1.2.2]: https://github.com/raphaelstolt/llms-txt-php/compare/v1.2.1...v1.2.2
[v1.2.1]: https://github.com/raphaelstolt/llms-txt-php/compare/v1.2.0...v1.2.1
[v1.2.0]: https://github.com/raphaelstolt/llms-txt-php/compare/v1.1.0...v1.2.0
[v1.1.0]: https://github.com/raphaelstolt/llms-txt-php/compare/v1.0.1...v1.1.0
[v1.0.1]: https://github.com/raphaelstolt/llms-txt-php/compare/v1.0.0...v1.0.1
