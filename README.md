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
        <a href="#notes">Notes</a>
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
        FAQ content element: select items, optional category filter, open-first option
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
        Insert the "FAQ" content element, pick items and options.
    </li>
    <li>
        Configure URL parameter name and schema toggle under site settings (`faq.parameterName`, `faq.schemaEnabled`).
    </li>
</ol>

<h3 id="notes">
    Notes
</h3>