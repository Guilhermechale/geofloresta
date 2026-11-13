/* Menu móvel acessível: aria-expanded, Esc fecha e devolve o foco. */
export function initMenu() {
    const btn = document.querySelector('[data-menu-toggle]');
    const nav = document.getElementById('mobile-nav');
    if (!btn || !nav) return;

    const set = (open) => {
        btn.setAttribute('aria-expanded', String(open));
        nav.hidden = !open;
        if (open) nav.querySelector('a')?.focus();
    };
    btn.addEventListener('click', () => set(btn.getAttribute('aria-expanded') !== 'true'));
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && btn.getAttribute('aria-expanded') === 'true') { set(false); btn.focus(); }
    });
}
