(() => {
  /**
   * Minimale Hash-Navigation für FAQ-Details
   * <details> funktioniert vollständig nativ, JS handled nur Deep-Linking
   */
  const handleFaqHash = () => {
    const hash = window.location.hash;
    const match = hash.match(/^#faq-(\d+)$/);
    
    if (match) {
      const faqId = match[1];
      const details = document.getElementById(`faq-item${faqId}`);
      
      if (details) {
        // Alle anderen Details schließen
        document.querySelectorAll('.gf-faq__item').forEach(el => {
          if (el.id !== `faq-item${faqId}`) {
            el.open = false;
          }
        });
        
        // Ziel-Detail öffnen und in Sicht scrollen
        details.open = true;
        details.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    }
  };
  
  // On Page Load
  document.addEventListener('DOMContentLoaded', handleFaqHash);
  
  // On Hash Change (z.B. via Browser-Navigation)
  window.addEventListener('hashchange', handleFaqHash);
})();
