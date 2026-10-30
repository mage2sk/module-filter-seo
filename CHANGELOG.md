# Changelog

All notable changes to this extension are documented here. The format
is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/).

## [1.1.8] - 2026-10-30

### Changed
- Released under the MIT License: this module is free and open-source software.

## [1.1.7] - 2026-10-06

### Fixed
- When several active filters each have a filter meta row, the meta title, meta description and meta keywords no longer depend on the order of the filters in layered navigation. The rows are ordered by the new Priority value (highest first), then store view rows before All Store Views rows, then by lower ID, and each value is taken from the first row that has it.

### Changed
- The filter meta form field Breadcrumbs Priority is now called Priority and decides which row wins when several rows match. It was stored before but not used.
- The README documents that every layered navigation filter counts as a facet for the facet noindex rule, including the Sale filter of Panth Sale Filter.
