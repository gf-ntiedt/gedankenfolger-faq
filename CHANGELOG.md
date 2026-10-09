# Changelog

All notable changes to this project will be documented in this file.

## [13.13.0] - 2026-10-09

### Added

- **upgrade:** Add wizard replacing translated categories in the category filter (7037496)


### Documentation

- Add upgrade note for the category filter wizard (9a32f86)


### Fixed

- **license:** Replace GPLv3 text with GPLv2 to match composer.json (872829f)


### Miscellaneous

- Translate German comments in gitignore to English (c603d1b)


## [13.12.0] - 2026-10-09

### Added

- **faq:** Render category filter field as tree to show the category hierarchy (e4c7b83)

- **faq:** Add option to include items from child categories in the category filter (b6a6c26)

- **content-block:** Show more of the category tree in the filter field (94464b4)

- **faq:** Add separate sort direction fields for FAQ and category ordering (8a6f72b)

- **upgrade:** Add wizard that splits legacy sort direction values into separate fields (41bfa93)

- **upgrade:** Add extension prefix to the sort direction wizard title (3cc792b)

- **config:** Add ifEmpty defaults for the sort direction in the TypoScript set (c10cd58)


### Changed

- **upgrade:** Pass explicit string type to the wizard LIKE parameter (876ca5f)


### Documentation

- Document category filter, sorting options and sort direction upgrade note (eb21577)

- Document the sort direction upgrade wizard and the manual TypoScript migration (38030b5)

- **labels:** Explain that Sort by fields take a single column name and direction is a separate field (03b3e95)

- **upgrade:** Add PHPDoc to the wizard constructor (cc93f58)

- **upgrade:** Document the DBAL exception of the wizard update (731dc17)

- **upgrade:** Document the DBAL exception of updateNecessary (503eae8)


### Fixed

- **labels:** Correct xliff namespace URN in German labels (a4fbb01)

- **typoscript:** Rename filterField to filterByCategoryField so the processor reads it (c3b2d95)

- **upgrade:** Use the TYPO3 13 Install namespaces for the sort direction wizard (bd58dd9)

- **deps:** Require typo3/cms-install for the upgrade wizard classes on TYPO3 13 (0c65829)

- **content-block:** Remove searchable option unsupported by Content Blocks v1 (d8ceff9)

- **content-block:** Limit the category filter tree to default-language categories (7117e95)


### Miscellaneous

- Ignore local review notes file (9350b0e)

- Translate German section comment in gitignore (df0957b)


## [13.11.1] - 2026-10-02

### Fixed

- **typoscript:** Combine chained conditions into single bracket (75c97a4)

- **config:** Move static template registration out of ext_tables.php (a7cd3ed)


## [13.11.0] - 2026-09-23

### Changed

- **testing:** Remove Playwright E2E test setup and related CLI commands (5acc4b9)


### Fixed

- **di:** Remove dangling Command service resource after Playwright removal (3ca0d79)


## [13.10.1] - 2026-09-10

### Fixed

- **accordion:** Correct arrow indicator and open-state markup for both layouts (bda167e)


## [13.10.0] - 2026-07-23

### Fixed

- **typoscript:** Remove partialRootPaths.100 override to unblock sitepackage overrides (3c5c1eb)


## [13.9.0] - 2026-06-23

### Added

- **testing:** Add CLI commands and Playwright tests for content element variants (bf3cf72)


## [13.8.5] - 2026-06-22

### Fixed

- **deps:** Require brotkrueml/schema and wapplersystems/ws-scss (fcf51f1)


## [13.8.4] - 2026-05-29

### Documentation

- **readme:** Add changelog and acknowledgements sections, remove misplaced code quality block (cef49c4)


## [13.8.2] - 2026-05-22

### Fixed

- **ext_emconf:** Define \$_EXTKEY variable to prevent phpstan undefined variable error (599f6eb)


## [13.8.1] - 2026-05-13

### Miscellaneous

- Fix metadata – author, email, state, deps (6353cac)


## [13.8.0] - 2026-05-13

### Added

- RTE parseFunc for FAQ answers and schema, add libs.typoscript (8be5456)


## [13.7.7] - 2026-05-13

### Added

- Update icons, add LICENSE-ICONS, reference in README (48a7e95)


## [13.7.6] - 2026-05-06

### Added

- Add global page.tsconfig for classic (non-SiteSet) installations (a60bf6a)


## [13.7.5] - 2026-05-06

### Fixed

- Validate orderBy column against TCA before use in SQL ORDER BY (0e76ee7)


## [13.7.4] - 2026-05-06

### Fixed

- Remove templateRootPaths – template override is via file = in sitepackage (7f66651)


## [13.7.3] - 2026-05-05

### Documentation

- Fix frontend.html casing in README template override section (7867ad0)


## [13.7.2] - 2026-05-05

### Fixed

- Correct templateRootPaths/partialRootPaths for Content Blocks compatibility (d75bdc8)


## [13.7.1] - 2026-05-05

### Documentation

- Update README for v13.7.1 (259107f)


## [13.7.0] - 2026-05-05

### Added

- Allow template and partial overrides via TypoScript constants (0a43ad5)


## [13.6.4] - 2026-05-05

### Added

- Add classic static template for installations without SiteSets (88f4178)


### Miscellaneous

- Update to version 13.6.4 (9b60c2d)


## [13.6.3] - 2026-01-23

### Added

- **faq:** Add XML namespaces to backend preview (30878cf)


### Miscellaneous

- Update to version 13.6.3 (56f783c)


## [13.6.2] - 2026-01-12

### Fixed

- Wrong IDs (b1da80b)


### Miscellaneous

- Update to version 13.6.2 (1653530)


## [13.6.1] - 2026-01-12

### Fixed

- **faq:** Render header and align accordion markup across layouts (7900c86)


### Miscellaneous

- Update to version 13.6.1 (f2f0a70)


## [13.6.0] - 2025-12-18

### Added

- Implement loading of css and scss setting (b5682f1)

- **tsconfig:** Add page TSconfig to tailor tt_content layout options for the gedankenfolger_faq type (f7635ba)

- **config:** Add basic TYPO3/Appearance tab to tailor layout options (2b2018a)

- **typoscript:** Expose faq root name and update stylesheet includes (0bd44b9)

- **tsconfig:** Add page TSconfig to tailor tt_content layout options for the gedankenfolger_faq type (6450766)

- **settings:** Add readonly faq.rootName default (9fcf4ff)

- **faq:** Add Bootstrap accordion layout and layout-based partials (1fc9e6c)


### Changed

- Replace static rootName with settings definition rootName (594054e)


### Miscellaneous

- Update to version 13.5.2 (52148a7)

- Update to version 13.6.0 (acde8cf)


## [13.5.1] - 2025-12-18

### Changed

- Backend preview (00dea6e)

- Rename standard to default for color modes (40fecd1)


### Miscellaneous

- Update to version 13.5.1 (1443d69)


## [13.5.0] - 2025-12-17

### Added

- **faq:** Add categoryOrderBy for category sorting (c3f6e26)


### Miscellaneous

- Update to version 13.5.0 (f6d3e6a)


## [13.4.0] - 2025-12-17

### Added

- **faq:** Allow configuring initial FAQ item by UID (6542a77)

- **faq-item:** Change categories relation to many-to-many (97b4944)

- **faq:** Add category filter selection to content element (87ce448)

- **faq:** Add translation for category filter selection (22c15b4)

- **faq:** Add category filtering and ordering (c6686c0)

- **faq:** ⚠ **BREAKING** Replace GroupByCategoryProcessor with FaqProcessor (e37a730)
  - **BREAKING CHANGE:** TypoScript using GroupByCategoryProcessor must switch to

FaqProcessor and update processor configuration keys.


### Changed

- **faq:** Render category groups via shared Items section (ea24c69)


### Fixed

- Backend preview and support new openItem option (9eeec6c)


### Miscellaneous

- Update to version 13.3.1 (96b64df)

- Update to version 13.4.0 (5cc72f7)


## [13.3.0] - 2025-12-17

### Added

- **faq:** Allow opening FAQ item by UID and also the first item (d38f122)


### Miscellaneous

- Update to version 13.3.0 (6dc082a)


## [13.2.3] - 2025-12-16

### Added

- Add header_link config and header generation. add correct locallang element title (4ee6930)


### Fixed

- OpenFirst opened all first element from within category - FaqsByCategories.html (1d7c255)


### Miscellaneous

- Update to version 13.2.1 (feaafac)

- Update to version 13.2.2 (3fa35da)

- Update to version 13.2.3 (5e3f742)


## [13.2.0] - 2025-12-15

### Add

- Gitignore (206374a)

- Trademark notice and extension icon (5f7d59f)


### Added

- **faq:** Add SCSS variables and settings for FAQ (aea5e17)

- **faq:** Add settings tab and new fields (ee5092b)

- **data-processing:** Implement category grouping logic (e86fe09)

- **faq:** Add groupByCategory data processing (562246f)

- **faq:** Add new configuration options for FAQs (d89a73b)

- **faq:** Implement openSingleOnly option (bdca597)

- **faq:** Add detailed backend preview template (e575ae5)

- **faq:** Add recursive and orderBy fields to config (23c66c6)

- **GroupByCategoryProcessor:** Add recursive fetching support and enhance ordering configuration (839331c)


### Change

- Icon (0259319)

- First words for readme (091e7f3)


### Changed

- **faq:** Simplify FAQ initialization and handling (db7d506)

- **faq:** Restructure FAQ template for better semantics (af979a0)

- **faq:** Update CSS styles for FAQ section (727947a)

- **faq:** Restructure FAQ rendering logic (ded25ee)

- **faq:** Clean up frontend template code (fa1f708)

- **faq:** Clean up HTML structure in Faqs.html (c3e1012)

- **GroupByCategoryProcessor:** Recursive and orderBy from CE (3090971)


### Documentation

- Update and extend documentation (20e8103)


### Fixed

- **typoscript:** Correct storage folder field in FAQ setup (b63d0e9)


### Miscellaneous

- **license:** Update license to GNU GPL v3 (b7a03c0)

- **version:** Update version to 13.1.0 (7b28e0a)

- **version:** Bump version to 13.1.1 (7a70e3d)

- **version:** Update version to 13.2.0 (81f4653)



