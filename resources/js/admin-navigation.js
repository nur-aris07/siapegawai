/* ADM-NAV-01: enhancement only; semantic links/disclosures remain usable without JS. */
(() => {
    const sidebar = document.getElementById('admin-primary-navigation');
    const toggle = document.getElementById('admin-menu-toggle');
    const closeButton = document.getElementById('admin-nav-close');
    const collapseButton = document.getElementById('admin-nav-collapse');
    const overlay = document.getElementById('admin-nav-overlay');
    const main = document.getElementById('main');
    const header = document.querySelector('.admin-v1-header');
    const skipLink = document.querySelector('.skip-link');
    if (!sidebar || !toggle || !closeButton || !collapseButton || !overlay || !main || !header) return;

    const mobile = window.matchMedia('(max-width: 760px)');
    const noMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const page = document.body;
    const headerElements = [header.querySelector('.admin-v1-brand'), header.querySelector('.admin-v1-profile'), header.querySelector('form'), skipLink].filter(Boolean);
    let drawerOpen = false;
    let collapsed = false;
    const preferenceKey = 'siapegawai.admin-nav.collapsed.v1';
    try { collapsed = window.localStorage.getItem(preferenceKey) === '1'; } catch (_) { /* browser storage disabled */ }

    function setCollapsed(value, persist = false) {
        collapsed = Boolean(value);
        page.classList.toggle('admin-nav-collapsed', collapsed && !mobile.matches);
        collapseButton.setAttribute('aria-expanded', String(!collapsed));
        collapseButton.setAttribute('aria-label', collapsed ? 'Perluas sidebar' : 'Ciutkan sidebar');
        collapseButton.setAttribute('title', collapsed ? 'Perluas sidebar' : 'Ciutkan sidebar');
        if (persist) {
            try { window.localStorage.setItem(preferenceKey, collapsed ? '1' : '0'); } catch (_) { /* no persistence */ }
        }
    }
    function setBackgroundInert(value) {
        main.inert = value;
        headerElements.forEach(element => { element.inert = value; });
    }
    function visibleFocusables() {
        return [...sidebar.querySelectorAll('button, a[href], summary, [tabindex]:not([tabindex="-1"])')]
            .filter(el => !el.hasAttribute('disabled') && el.getClientRects().length > 0 && (el.matches('summary') || !el.closest('details:not([open])')));
    }
    function closeDrawer(restoreFocus = true) {
        if (!drawerOpen) return;
        drawerOpen = false;
        sidebar.classList.remove('is-open');
        sidebar.removeAttribute('role');
        sidebar.removeAttribute('aria-modal');
        overlay.hidden = true;
        document.documentElement.classList.remove('admin-nav-scroll-locked');
        toggle.setAttribute('aria-expanded', 'false');
        toggle.setAttribute('aria-label', 'Buka navigasi admin');
        setBackgroundInert(false);
        if (restoreFocus) {
            if (mobile.matches) toggle.focus();
            else sidebar.querySelector('.admin-nav-link')?.focus();
        }
    }
    function openDrawer() {
        if (!mobile.matches || drawerOpen) return;
        drawerOpen = true;
        sidebar.classList.add('is-open');
        sidebar.setAttribute('role', 'dialog');
        sidebar.setAttribute('aria-modal', 'true');
        overlay.hidden = false;
        document.documentElement.classList.add('admin-nav-scroll-locked');
        toggle.setAttribute('aria-expanded', 'true');
        toggle.setAttribute('aria-label', 'Tutup navigasi admin');
        setBackgroundInert(true);
        closeButton.focus({ preventScroll: true });
    }
    function handleResize() {
        if (!mobile.matches) closeDrawer(true);
        setCollapsed(collapsed);
    }

    toggle.addEventListener('click', () => drawerOpen ? closeDrawer() : openDrawer());
    closeButton.addEventListener('click', () => closeDrawer());
    overlay.addEventListener('click', () => closeDrawer());
    collapseButton.addEventListener('click', () => setCollapsed(!collapsed, true));
    sidebar.querySelectorAll('.admin-nav-group > summary').forEach(summary => {
        summary.addEventListener('click', event => {
            if (collapsed && !mobile.matches) {
                event.preventDefault();
                setCollapsed(false, true);
                summary.parentElement.open = true;
                summary.focus();
            }
        });
    });
    sidebar.querySelectorAll('a[href]').forEach(link => link.addEventListener('click', () => {
        if (drawerOpen) closeDrawer(false);
    }));
    document.addEventListener('keydown', event => {
        if (!drawerOpen) return;
        if (event.key === 'Escape') { event.preventDefault(); closeDrawer(); return; }
        if (event.key !== 'Tab') return;
        const elements = visibleFocusables();
        if (!elements.length) { event.preventDefault(); closeButton.focus(); return; }
        const first = elements[0];
        const last = elements[elements.length - 1];
        if (event.shiftKey && (document.activeElement === first || !sidebar.contains(document.activeElement))) {
            event.preventDefault(); last.focus();
        } else if (!event.shiftKey && (document.activeElement === last || !sidebar.contains(document.activeElement))) {
            event.preventDefault(); first.focus();
        }
    });
    // Screen reader and keyboard focus cannot escape a modal via programmatic focus.
    document.addEventListener('focusin', event => {
        if (drawerOpen && !sidebar.contains(event.target)) closeButton.focus({ preventScroll: true });
    });
    if (typeof mobile.addEventListener === 'function') mobile.addEventListener('change', handleResize);
    else mobile.addListener(handleResize);

    // Install all listeners before enabling the CSS-only enhanced drawer rules.
    page.classList.add('admin-nav-enhanced');
    setCollapsed(collapsed);
    // Motion remains CSS-controlled; no timers/animated focus dependencies.
    page.classList.toggle('admin-nav-reduced-motion', noMotion.matches);
    noMotion.addEventListener?.('change', e => page.classList.toggle('admin-nav-reduced-motion', e.matches));
})();
