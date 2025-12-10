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

    const faqId = match[1];
    const details = document.getElementById(`faq-item${faqId}`);
    if (!details) return;

    const root = details.closest('[data-faq-parameter]');
    if (root && root.getAttribute('data-open-single-only') === '1') {
      root.querySelectorAll('details').forEach(el => {
        if (el !== details && el.open) el.open = false;
      });
    }

    details.open = true;
    try {
      details.scrollIntoView({ behavior: 'smooth', block: 'start' });
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
    document.querySelectorAll('[data-faq-parameter]').forEach((root) => {
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
    initFaqComponents();
    handleFaqHash();
  });

  window.addEventListener('hashchange', handleFaqHash);
})();
