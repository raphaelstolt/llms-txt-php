# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/) and this project adheres to
[Semantic Versioning](http://semver.org/).

## [Unreleased]

## [v3.3.0] - 2025-09-20

### Added
- Optional parameter `$parsed` for extraction via `extractFromFile` or `extractFromHtml` as an already parsed llms.txt object.

## [v3.2.0] - 2025-08-28

### Added
- New `extractFromFile` method to get the LLM instructions of a given HTML file.

## [v3.1.0] - 2025-08-28

### Added
- New `extractFromHtml` method to get the LLM instructions in a given HTML content.

## [v3.0.0] - 2025-08-25

### Added
- Renamed `toEmbedInScriptTag` method to `toEmbeddedInScriptTag`. Which is more accurate and now an alias for the new 
  `asScripTag` method.

## [v2.1.1] - 2025-08-25

### Added
- New `asScripTag` method to embed the LLM instructions in a HTML `<script>` tag. ~~Deprecates the `toEmbedInScriptTag` method~~.

## [v2.1.0] - 2025-08-22

### Added
- Support for inline LLM instructions in HTML as [proposed](https://vercel.com/blog/a-proposal-for-inline-llm-instructions-in-html)
  by Vercel.

## [v2.0.1] - 2025-07-29

### Improved
- Introduce alias methods.

## [v2.0.0] - 2025-07-29

### Improved
- Extracted the CLI commands into an own package.

## [v1.6.3] - 2025-07-18

### Improved
- The `validate` CLI command now can take a directory as an argument.

## [v1.6.2] - 2025-07-10

### Improved
- The `parse` method has been optimised.

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

[Unreleased]: https://github.com/raphaelstolt/llms-txt-php/compare/v3.3.0...HEAD
[v3.3.0]: https://github.com/raphaelstolt/llms-txt-php/compare/v3.2.0...v3.3.0
[v3.2.0]: https://github.com/raphaelstolt/llms-txt-php/compare/v3.1.0...v3.2.0
[v3.1.0]: https://github.com/raphaelstolt/llms-txt-php/compare/v3.0.0...v3.1.0
[v3.0.0]: https://github.com/raphaelstolt/llms-txt-php/compare/v2.1.1...v3.0.0
[v2.1.1]: https://github.com/raphaelstolt/llms-txt-php/compare/v2.0.0...v2.1.1
[v2.1.0]: https://github.com/raphaelstolt/llms-txt-php/compare/v2.0.1...v2.1.0
[v2.0.1]: https://github.com/raphaelstolt/llms-txt-php/compare/v2.0.0...v2.0.1
[v2.0.0]: https://github.com/raphaelstolt/llms-txt-php/compare/v1.6.3...v2.0.0
[v1.6.3]: https://github.com/raphaelstolt/llms-txt-php/compare/v1.6.2...v1.6.3
[v1.6.2]: https://github.com/raphaelstolt/llms-txt-php/compare/v1.6.1...v1.6.2
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
