(() => {
  const initFaq = (root) => {
    const list = root.querySelector('.gf-faq__list');
    if (!list) return;
    const paramName = root.getAttribute('data-faq-parameter') || 'faq';

    const items = [...root.querySelectorAll('.gf-faq__item')];
    const byId = new Map(items.map((el) => [el.getAttribute('data-faq-id'), el]));

    const closeAll = () => {
      items.forEach((el) => {
        const btn = el.querySelector('.gf-faq__question');
        const panel = el.querySelector('.gf-faq__answer');
        if (btn && panel) {
          btn.setAttribute('aria-expanded', 'false');
          panel.hidden = true;
        }
      });
    };

    const openItem = (el, scroll = false) => {
      const btn = el.querySelector('.gf-faq__question');
      const panel = el.querySelector('.gf-faq__answer');
      if (!btn || !panel) return;
      closeAll();
      btn.setAttribute('aria-expanded', 'true');
      panel.hidden = false;
      if (scroll) {
        el.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    };

    // Setup initial state
    items.forEach((el, idx) => {
      const btn = el.querySelector('.gf-faq__question');
      const panel = el.querySelector('.gf-faq__answer');
      if (!btn || !panel) return;
      const expanded = btn.getAttribute('aria-expanded') === 'true';
      panel.hidden = !expanded;
      btn.addEventListener('click', () => {
        const isOpen = btn.getAttribute('aria-expanded') === 'true';
        if (isOpen) {
          btn.setAttribute('aria-expanded', 'false');
          panel.hidden = true;
        } else {
          openItem(el, false);
          const id = el.getAttribute('data-faq-id');
          if (id) {
            const url = new URL(window.location.href);
            url.searchParams.set(paramName, id);
            history.replaceState(null, '', url);
          }
        }
      });
    });

    // Deep-linking
    const url = new URL(window.location.href);
    const deeplink = url.searchParams.get(paramName);
    if (deeplink && byId.has(deeplink)) {
      openItem(byId.get(deeplink), true);
    }
  };

  const selector = '.gf-faq';
  document.querySelectorAll(selector).forEach(initFaq);

  // If content is injected dynamically
  const obs = new MutationObserver((mutations) => {
    mutations.forEach((m) => {
      m.addedNodes.forEach((n) => {
        if (n.nodeType === 1 && n.matches && n.matches(selector)) {
          initFaq(n);
        }
      });
    });
  });
  obs.observe(document.documentElement, { childList: true, subtree: true });
})();
