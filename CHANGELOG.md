# Changelog

All notable changes to this extension are documented here. The format
is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/).

## [1.1.5] - 2026-10-03

### Fixed
- Filter URL slugs generated from attribute option labels now transliterate accented and non-Latin letters to plain ASCII (an umlaut u becomes u, an accented e becomes e, Cyrillic is romanised) instead of dropping them. The slug suggestions in the Filter URL Rewrite form use the same rule.
- The filter rewrite and category filter meta repositories now save records to the database (insert a new row or update an existing one) instead of silently doing nothing.
