/*
 * Idioma: ao escolher no selector (ou no aviso da primeira visita) grava-se o cookie
 * funcional "gf_lang" (12 meses). Nunca há redireccionamento forçado.
 */
function setLangCookie(locale) {
    const secure = location.protocol === 'https:' ? '; Secure' : '';
    // Com "Preferências" aceite dura 12 meses; sem isso é um cookie de sessão.
    const persistent = window.gfConsent?.allows?.('preferences') === true;
    const age = persistent ? `; Max-Age=${60 * 60 * 24 * 365}` : '';
    document.cookie = `gf_lang=${encodeURIComponent(locale)}${age}; Path=/; SameSite=Lax${secure}`;
}

export function initLang() {
    document.querySelectorAll('[data-set-lang]').forEach((a) => {
        a.addEventListener('click', () => setLangCookie(a.dataset.setLang));
    });
    const hint = document.querySelector('[data-lang-hint]');
    hint?.querySelector('[data-lang-hint-close]')?.addEventListener('click', () => {
        setLangCookie(document.documentElement.lang.slice(0, 2));
        hint.remove();
    });
}
