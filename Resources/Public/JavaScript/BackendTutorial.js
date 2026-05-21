(function () {
    'use strict';

    var STORAGE_KEY = 'gedankenfolger_faq_tour_v2';
    var NS = 'gedankenfolger_faq.';

    if (!document.getElementById('gf-faq-tour')) { return; }

    var TRACK_RE = /^[a-zA-Z]+$/;
    function isValidTrack(track) { return typeof track === 'string' && TRACK_RE.test(track); }

    function t(key) {
        return (typeof TYPO3 !== 'undefined' && TYPO3.lang && TYPO3.lang[NS + key]) || '';
    }

    function getState() {
        try {
            var raw = localStorage.getItem(STORAGE_KEY);
            return raw ? JSON.parse(raw) : null;
        } catch (e) { return null; }
    }

    function setState(state) {
        try {
            if (state === null) { localStorage.removeItem(STORAGE_KEY); }
            else { localStorage.setItem(STORAGE_KEY, JSON.stringify(state)); }
        } catch (e) {}
    }

    function getPanel() { return document.getElementById('gf-faq-tour'); }

    function hideAll() {
        document.querySelectorAll('[data-gf-track]').forEach(function (el) { el.hidden = true; });
        document.getElementById('gf-faq-tour-home').hidden = true;
    }

    function showHome() {
        hideAll();
        document.getElementById('gf-faq-tour-home').hidden = false;
        document.getElementById('gf-faq-tour-counter').textContent = '';
        document.getElementById('gf-faq-tour-prev').hidden = true;
        document.getElementById('gf-faq-tour-next').hidden = true;
        getPanel().classList.add('show');
        setState(null);
    }

    function showStep(track, stepIndex) {
        if (!isValidTrack(track)) { showHome(); return; }
        var steps = document.querySelectorAll('[data-gf-track="' + track + '"]');
        if (!steps.length) { showHome(); return; }
        var stepCount = steps.length;
        stepIndex = Math.max(0, Math.min(stepCount - 1, stepIndex || 0));

        hideAll();
        var target = document.querySelector('[data-gf-track="' + track + '"][data-gf-step="' + stepIndex + '"]');
        if (target) { target.hidden = false; }

        var fmt = t('tour.step_counter') || 'Schritt {0} von {1}';
        document.getElementById('gf-faq-tour-counter').textContent =
            fmt.replace('{0}', String(stepIndex + 1)).replace('{1}', String(stepCount));

        var prevBtn = document.getElementById('gf-faq-tour-prev');
        prevBtn.hidden = false;
        prevBtn.textContent = stepIndex === 0 ? (t('tour.back_home') || '← Auswahl') : (t('tour.prev') || 'Zurück');

        var nextBtn = document.getElementById('gf-faq-tour-next');
        nextBtn.hidden = false;
        nextBtn.textContent = stepIndex === stepCount - 1 ? (t('tour.finish') || 'Fertig') : (t('tour.next') || 'Weiter');

        getPanel().classList.add('show');
        setState({ track: track, step: stepIndex });
    }

    function prev() {
        var state = getState();
        if (!state || !state.track) { stop(); return; }
        if (state.step === 0) { showHome(); } else { showStep(state.track, state.step - 1); }
    }

    function next() {
        var state = getState();
        if (!state || !state.track) { return; }
        var steps = document.querySelectorAll('[data-gf-track="' + state.track + '"]');
        if (state.step < steps.length - 1) { showStep(state.track, state.step + 1); } else { showHome(); }
    }

    function start() {
        var state = getState();
        if (state && state.track && document.querySelector('[data-gf-track="' + state.track + '"]')) {
            showStep(state.track, state.step || 0);
        } else {
            showHome();
        }
    }

    function stop() {
        getPanel().classList.remove('show');
        setState(null);
    }

    document.getElementById('gf-faq-tour-close').addEventListener('click', stop);
    document.getElementById('gf-faq-tour-prev').addEventListener('click', prev);
    document.getElementById('gf-faq-tour-next').addEventListener('click', next);

    document.getElementById('gf-faq-tour-body').addEventListener('click', function (e) {
        var btn = e.target.closest('[data-gf-faq-track]');
        if (btn) { showStep(btn.getAttribute('data-gf-faq-track'), 0); }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && getPanel().classList.contains('show')) { stop(); }
    });

    window.GedankenfolgerFaqTour = { start: start, stop: stop };
}());
