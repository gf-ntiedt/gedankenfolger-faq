<h1>TYPO3 Extension Gedankenfolger FAQ<br/>(gedankenfolger-faq)</h1>
<p>
    Compact FAQ extension using Content Blocks (Record Types + Content Elements), Site Set, SCSS, and vanilla JS.
    Requires TYPO3 14.
</p>
<p>
    First of all many thanks to the hole TYPO3 community, all supporters of TYPO3.
    Especially to <a href="https://typo3.org/" target="_blank">TYPO3-Team</a> and <a href="https://www.gedankenfolger.de/" target="_blank">Gedankenfolger GmbH</a>.
</p>

> **TYPO3 13 support** is maintained on the [`13.x`](../../tree/13.x) branch.

<h3>
    Contents of this file
</h3>
<ol>
    <li>
        <a href="#features">Features</a>
    </li>
    <li>
        <a href="#install">Install</a>
    </li>
    <li>
        <a href="#usage">Usage</a>
    </li>
    <li>
        <a href="#options">Options</a>
    </li>
    <li>
        <a href="#settings">Settings / Constants</a>
    </li>
    <li>
        <a href="#template-overrides">Template Overrides</a>
    </li>
    <li>
        <a href="#notes">Notes</a>
    </li>
    <li>
        <a href="#noticetrademark">Notice on Logo / Trademark Use</a>
    </li>
</ol>
<hr/>
<h3 id="features">
    Features:
</h3>
<ol>
    <li>
        FAQ Item record type (question, answer (RTE), categories, color variant)
    </li>
    <li>
        FAQ content element: select items from a storage folder; options for open-first, single-open-only, and grouping by category with optional category headings
    </li>
    <li>
        Accessible accordion markup and deep-linking via configurable URL parameter
    </li>
    <li>
        Optional schema.org FAQPage output via Brotkrueml/Schema
    </li>
    <li>
        SCSS (BEM) and no jQuery
    </li>
    <li>
        Template and partial overrides from your site package without forking the extension
    </li>
</ol>

<h3 id="install">
    Install
</h3>

<h4>1. Require via Composer</h4>

```bash
composer require gedankenfolger/gedankenfolger-faq
```

Activate the extension in the TYPO3 backend (Extensions module) if not done automatically.

<h4>2a. Include TypoScript via Site Set (recommended)</h4>

Add the set to your site configuration:

```yaml
# config/sites/my-site/config.yaml
sets:
  - gedankenfolger/gedankenfolger-faq
```

<h4>2b. Include TypoScript via Classic Static Template</h4>

For installations without Site Sets, include the static template in your TypoScript template record:

`Web > Template > Edit > Includes > Include Static (from extensions)` → **Gedankenfolger FAQ**

<h4>3. SCSS compilation</h4>

Ensure `ws_scss` (`^14`) is installed to compile `Resources/Public/Scss/faq.scss`.
Alternatively set `faq.scss.default = 0` and `faq.css.default = 1` to use the pre-compiled CSS.

<h3 id="usage">
    Usage
</h3>
<ol>
    <li>
        Create FAQ items under the record type "FAQ Item".
    </li>
    <li>
        Insert the "FAQ" content element, point it to a storage folder with your FAQ items via "Storage folder".
    </li>
    <li>
        Configure behaviour and style via site settings (SiteSet) or the TypoScript constant editor (Classic).
    </li>
</ol>

<h3 id="options">
    Options
</h3>
<ul>
  <li><strong>Open first</strong>: opens the first FAQ initially if none is open yet.</li>
  <li><strong>Open single only</strong>: ensures only one FAQ can be open at a time (per component instance).</li>
  <li><strong>Group by category</strong>: groups items by their assigned sys_category.</li>
  <li><strong>Show category titles</strong>: when grouping is enabled, renders category headings above each group.</li>
</ul>

<h4>Behavior and accessibility</h4>
<ul>
  <li>Markup uses native <code>&lt;details&gt;</code>/<code>&lt;summary&gt;</code> for accessible accordion behavior.</li>
  <li>Deep-linking via hash (<code>#faq-{uid}</code>) opens the targeted item and, when single-open-only is enabled, closes siblings within the same component.</li>
  <li>JavaScript is lightweight and instance-scoped via data attributes on the wrapper: <code>data-open-first</code>, <code>data-open-single-only</code>, and <code>data-faq-parameter</code>.</li>
</ul>

<h3 id="settings">
    Settings / Constants
</h3>

All settings can be configured via Site Set settings (recommended) or the TypoScript constant editor.

| Constant | Default | Description |
|---|---|---|
| `faq.parameterName` | `faq` | URL parameter name used for deep-linking |
| `faq.schemaEnabled` | `1` | Output schema.org FAQPage markup |
| `faq.partialRootPath` | _(empty)_ | Override path for partials (see below) |
| `faq.css.default` | `1` | Load pre-compiled default CSS |
| `faq.scss.default` | `1` | Load and compile default SCSS via ws_scss |
| `faq.color.default` | `#ffffff` | Default text color |
| `faq.bgcolor.default` | `#2f3b4a` | Default background color |
| `faq.color.primary` | `#000000` | Primary text color |
| `faq.bgcolor.primary` | `#005bbb` | Primary background color |
| `faq.color.secondary` | `#000000` | Secondary text color |
| `faq.bgcolor.secondary` | `#6c757d` | Secondary background color |
| `faq.color.tertiary` | `#000000` | Tertiary text color |
| `faq.bgcolor.tertiary` | `#b35f00` | Tertiary background color |
| `faq.font.family` | system-ui, … | Font family |

<h3 id="template-overrides">
    Template Overrides
</h3>

Templates and partials can be overridden from your site package without modifying the extension.
Only the files you actually want to change need to be created — all others fall back to the extension defaults.

### Template override

Set `file =` directly in your sitepackage TypoScript to replace the main template:

```typoscript
tt_content.gedankenfolger_faq {
    file = EXT:my_sitepackage/Resources/Private/Extensions/GedankenfolgerFaq/frontend.html
}
```

**Available template:**
- `frontend.html` – main content element template

### Partial overrides

Set `faq.partialRootPath` to a directory in your sitepackage. Only the partials you place there will be used; all others fall back to the extension defaults.

**Via Site Set** (`config/sites/my-site/config.yaml`):

```yaml
settings:
  faq.partialRootPath: 'EXT:my_sitepackage/Resources/Private/Extensions/GedankenfolgerFaq/Partials/'
```

**Via TypoScript constant editor:**

```typoscript
faq.partialRootPath = EXT:my_sitepackage/Resources/Private/Extensions/GedankenfolgerFaq/Partials/
```

**Available partials:**
- `Frontend/Layout0/Faqs.html`
- `Frontend/Layout0/FaqsByCategories.html`
- `Frontend/Layout100/Faqs.html` _(Bootstrap accordion)_
- `Frontend/Layout100/FaqsByCategories.html` _(Bootstrap accordion)_
- `Frontend/Schema.html`

<h3 id="notes">
    Notes
</h3>
<p>...</p>

<h3 id="noticetrademark">
    Notice on Logo / Trademark Use
</h3>
<p>
The logo used in this extension is protected by copyright and, where applicable, trademark law and remains the exclusive property of Gedankenfolger GmbH.

Use of the logo is only permitted in the form provided here. Any changes, modifications, or adaptations of the logo, as well as its use in other projects, applications, or contexts, require the prior written consent of Gedankenfolger GmbH.

In forks, derivatives, or further developments of this extension, the logo may only be used if explicit consent has been granted by Gedankenfolger GmbH. Otherwise, the logo must be removed or replaced with an own, non-protected logo.

All other logos and icons bundled with this extension are either subject to the TYPO3 licensing terms (The MIT License (MIT), see https://typo3.org) or are in the public domain.
</p>
<p>
For full license terms covering all graphic assets, see <a href="LICENSE-ICONS">LICENSE-ICONS</a>.
</p>
