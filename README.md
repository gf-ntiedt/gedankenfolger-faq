# TYPO3 13 FAQ Extension (gedankenfolger_faq)

Compact FAQ extension using Content Blocks (Record Types + Content Elements), Site Set, SCSS, and vanilla JS.

## Features
- FAQ Item record type (question, answer (RTE), categories, color variant)
- FAQ content element: select items, optional category filter, open-first option
- Accessible accordion markup and deep-linking via configurable URL parameter
- Optional schema.org FAQPage output via Brotkrueml/Schema
- SCSS (BEM) and no jQuery

## Install
- Require in Composer and activate the extension.
- Import the site set "Gedankenfolger FAQ" in your site configuration.
- Ensure `ws_scss` is installed to compile `Resources/Public/Scss/faq.scss`.

## Usage
- Create FAQ items under the record type "FAQ Item".
- Insert the "FAQ" content element, pick items and options.
- Configure URL parameter name and schema toggle under site settings (`faq.parameterName`, `faq.schemaEnabled`).

## Notes
- Content Blocks are loaded from `ContentBlocks/` via the site set.
- JS selector: `.gf-faq`. Supports multiple elements per page.
 - To extend TypoScript for the element, use `tt_content.gedankenfolger_faq` per Content Blocks docs.