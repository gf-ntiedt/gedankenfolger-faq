(() => {
  /**
   * Handle deep-linking via hash (#faq-<uid>) for <details> items.
   * If the targeted FAQ lives within a component root that has data-open-single-only="1",
   * close sibling items within the same root before opening the target.
   */
  const handleFaqHash = () => {
    const hash = window.location.hash;
    const match = hash.match(/^#faq-(\d+)$/);
    if (!match) return;

    const rootName = TYPO3.settings.TS.gf_faq_rootname;
    if (!rootName) return;

    const faqId = match[1];
    const item = document.getElementById(`faq-item${faqId}`);
    if (!item) return;

    const root = item.closest('.' + rootName);
    const layout = root.getAttribute('data-layout') ? root.getAttribute('data-layout') : 0;

    if (layout === '0') {
      if (root && root.getAttribute('data-open-single-only') === '1') {
        root.querySelectorAll(rootName + '__accordionitem').forEach(el => {
          if (el !== item && el.open) el.open = false;
        });
      }

      item.open = true;
    }

    try {
      item.scrollIntoView({ behavior: 'smooth', block: 'start' });
    } catch (e) {
      // scrollIntoView not critical
    }
  };

  /**
   * Initialize all FAQ components on the page.
   * - Enforce single-open mode when data-open-single-only="1"
   * - Optionally open the first item when data-open-first="1" and none is open
   */
  const initFaqComponents = () => {
    const rootName = TYPO3.settings.TS.gf_faq_rootname;
    if (!rootName) return;
    document.querySelectorAll('.' + rootName + '.layout-0').forEach((root) => {
      const openSingleOnly = root.getAttribute('data-open-single-only') === '1';
      const openFirst = root.getAttribute('data-open-first') === '1';
      const items = Array.from(root.querySelectorAll('details'));
      if (!items.length) return;

      // Open first item if requested and none is currently open
      if (openFirst && !items.some(d => d.open)) {
        items[0].open = true;
      }

      if (openSingleOnly) {
        items.forEach((details) => {
          details.addEventListener('toggle', () => {
            if (!details.open) return;
            items.forEach((other) => {
              if (other !== details && other.open) other.open = false;
            });
          });
        });
      }
    });
  };

  document.addEventListener('DOMContentLoaded', () => {
    if (TYPO3.settings && TYPO3.settings.TS.gf_faq_rootname) {
      initFaqComponents();
      handleFaqHash();
    }
  });

  window.addEventListener('hashchange', handleFaqHash);
})();
