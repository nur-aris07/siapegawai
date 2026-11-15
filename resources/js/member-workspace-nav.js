/* REL-UI-01: isolated participant workspace navigation; CSP-safe Vite module. */
const participantBody = document.querySelector('body.shell-participant');
if (participantBody) {
    const trigger = participantBody.querySelector('[data-workspace-menu-toggle]');
    const nav = participantBody.querySelector('[data-workspace-nav]');
    const account = participantBody.querySelector('[data-workspace-account]');
    const summary = account?.querySelector('summary');
    if (trigger && nav && account && summary) {
        const closeNav = (restoreFocus = false) => {
            const wasOpen = trigger.getAttribute('aria-expanded') === 'true';
            nav.classList.remove('is-open');
            trigger.setAttribute('aria-expanded', 'false');
            if (restoreFocus && wasOpen) trigger.focus();
        };
        const closeAccount = (restoreFocus = false) => {
            if (!account.open) return;
            account.open = false;
            if (restoreFocus) summary.focus();
        };
        participantBody.classList.add('is-workspace-enhanced');
        trigger.addEventListener('click', () => {
            const opening = trigger.getAttribute('aria-expanded') !== 'true';
            closeAccount();
            nav.classList.toggle('is-open', opening);
            trigger.setAttribute('aria-expanded', String(opening));
        });
        account.addEventListener('toggle', () => {
            if (account.open) closeNav();
        });
        document.addEventListener('keydown', event => {
            if (event.key !== 'Escape') return;
            if (account.open) { event.preventDefault(); closeAccount(true); }
            else if (trigger.getAttribute('aria-expanded') === 'true') {
                event.preventDefault(); closeNav(true);
            }
        });
        document.addEventListener('pointerdown', event => {
            if (!nav.contains(event.target) && !trigger.contains(event.target)) closeNav();
            if (!account.contains(event.target)) closeAccount();
        });
        // Never keep a mobile overlay open after resizing to the desktop layout.
        const desktop = window.matchMedia('(min-width: 981px)');
        const onChange = () => { if (desktop.matches) closeNav(); };
        desktop.addEventListener?.('change', onChange);
        // Leaving an open mobile menu by keyboard should not trap the next focus.
        document.addEventListener('focusin', event => {
            if (trigger.getAttribute('aria-expanded') === 'true' && !nav.contains(event.target) && !trigger.contains(event.target)) closeNav();
        });
    }
}
