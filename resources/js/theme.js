/*
 * Tema: Claro / Escuro / Automático.
 * A primeira aplicação é feita pelo script inline no <head> (antes da pintura);
 * aqui só se trata do botão e da reacção a mudanças do sistema.
 * A escolha guarda-se em localStorage ("gf-theme") — categoria "Preferências".
 */
const KEY = 'gf-theme';
const ORDER = ['auto', 'light', 'dark'];
const mq = window.matchMedia('(prefers-color-scheme: dark)');

export function readChoice() {
    try { return localStorage.getItem(KEY) || sessionStorage.getItem(KEY) || 'auto'; } catch { return 'auto'; }
}

function storeChoice(choice) {
    // Com "Preferências" aceite: guarda entre visitas (localStorage).
    // Sem esse consentimento: só durante esta visita (sessionStorage).
    const persistent = window.gfConsent?.allows?.('preferences') === true;
    try {
        localStorage.removeItem(KEY);
        sessionStorage.removeItem(KEY);
        if (choice !== 'auto') (persistent ? localStorage : sessionStorage).setItem(KEY, choice);
    } catch { /* armazenamento indisponível: ignora */ }
}

export function applyTheme(choice) {
    const root = document.documentElement;
    const effective = choice === 'auto' ? (mq.matches ? 'dark' : 'light') : choice;
    root.dataset.themeChoice = choice;
    root.dataset.theme = effective;
    document.querySelectorAll('meta[name="theme-color"][data-theme-color]').forEach((m) => {
        m.media = m.dataset.themeColor === effective ? 'all' : 'not all';
    });
    document.dispatchEvent(new CustomEvent('gf:theme', { detail: { theme: effective, choice } }));
}

export function initTheme() {
    const btn = document.querySelector('[data-theme-toggle]');
    let choice = readChoice();
    applyTheme(choice);

    mq.addEventListener?.('change', () => { if (choice === 'auto') applyTheme('auto'); });

    if (!btn) return;
    const labels = JSON.parse(btn.dataset.labels || '{}');
    const sync = () => {
        const next = ORDER[(ORDER.indexOf(choice) + 1) % ORDER.length];
        btn.setAttribute('aria-label', `${labels[choice] ?? choice} — ${labels.switchTo ?? ''} ${labels[next] ?? next}`.trim());
        btn.title = labels[choice] ?? choice;
    };
    sync();
    btn.addEventListener('click', () => {
        choice = ORDER[(ORDER.indexOf(choice) + 1) % ORDER.length];
        applyTheme(choice);
        storeChoice(choice);
        sync();
    });
}
