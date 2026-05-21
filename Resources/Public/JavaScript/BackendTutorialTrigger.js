(function () {
    document.addEventListener('click', function (e) {
        var btn = e.target && e.target.closest && e.target.closest('[data-gf-faq-tour-start]');
        if (!btn) { return; }
        try {
            if (window.top && window.top.GedankenfolgerFaqTour) {
                window.top.GedankenfolgerFaqTour.start();
            }
        } catch (ex) {}
    });
}());
