<h1>TYPO3 Extension Gedankenfolger FAQ<br/>(gedankenfolger-faq)</h1>
<p>
    Compact FAQ extension using Content Blocks (Record Types + Content Elements), Site Set, SCSS, and vanilla JS.
</p>
<p>
    First of all many thanks to the hole TYPO3 community, all supporters of TYPO3.
    Especially to <a href="https://typo3.org/" target="_blank">TYPO3-Team</a> and <a href="https://www.gedankenfolger.de/" target="_blank">Gedankenfolger GmbH</a>.
</p>

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
</ol>

<h3 id="install">
    Install
</h3>
<ol>
    <li>
        Require in Composer and activate the extension.
    </li>
    <li>
        Import the site set "Gedankenfolger FAQ" in your site configuration.
    </li>
    <li>
        Ensure `ws_scss` is installed to compile `Resources/Public/Scss/faq.scss`.
    </li>
</ol>

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
        Configure URL parameter name and schema toggle under site settings (`faq.parameterName`, `faq.schemaEnabled`).
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