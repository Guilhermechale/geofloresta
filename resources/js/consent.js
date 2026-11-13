/*
 * Consentimento de cookies — implementação própria, sem serviços externos.
 * Cookie "gf_consent" (6 meses): v=<versão>&id=<uuid>&p=0|1&a=0|1&t=<epoch>
 * Nada não essencial é carregado antes de uma escolha que o permita.
 */
import { loadAnalytics, revokeAnalytics } from './analytics.js';

const NAME = 'gf_consent';
const MAX_AGE = 60 * 60 * 24 * 182; // ~6 meses
const meta = (n) => document.querySelector(`meta[name="${n}"]`)?.content || '';
const VERSION = meta('gf-consent-version');

function read() {
    const raw = document.cookie.split('; ').find((c) => c.startsWith(NAME + '='));
    if (!raw) return null;
    const p = new URLSearchParams(decodeURIComponent(raw.slice(NAME.length + 1)));
    if (p.get('v') !== VERSION) return null;
    const age = Date.now() / 1000 - Number(p.get('t') || 0);
    if (age > MAX_AGE) return null;
    return { id: p.get('id'), preferences: p.get('p') === '1', analytics: p.get('a') === '1' };
}

function uuid() {
    if (crypto.randomUUID) return crypto.randomUUID();
    const b = crypto.getRandomValues(new Uint8Array(16));
    b[6] = (b[6] & 0x0f) | 0x40; b[8] = (b[8] & 0x3f) | 0x80;
    const h = [...b].map((x) => x.toString(16).padStart(2, '0')).join('');
    return `${h.slice(0, 8)}-${h.slice(8, 12)}-${h.slice(12, 16)}-${h.slice(16, 20)}-${h.slice(20)}`;
}

function write(state) {
    const value = new URLSearchParams({
        v: VERSION, id: state.id, p: state.preferences ? '1' : '0', a: state.analytics ? '1' : '0',
        t: String(Math.floor(Date.now() / 1000)),
    }).toString();
    const secure = location.protocol === 'https:' ? '; Secure' : '';
    document.cookie = `${NAME}=${encodeURIComponent(value)}; Max-Age=${MAX_AGE}; Path=/; SameSite=Lax${secure}`;
}

/** Aplica as consequências da escolha nas preferências já guardadas. */
function applyPreferences(allowed) {
    try {
        const theme = localStorage.getItem('gf-theme') || sessionStorage.getItem('gf-theme');
        localStorage.removeItem('gf-theme');
        sessionStorage.removeItem('gf-theme');
        if (theme) (allowed ? localStorage : sessionStorage).setItem('gf-theme', theme);
    } catch { /* sem armazenamento */ }
    const lang = document.cookie.split('; ').find((c) => c.startsWith('gf_lang='));
    if (lang) {
        const secure = location.protocol === 'https:' ? '; Secure' : '';
        const age = allowed ? `; Max-Age=${60 * 60 * 24 * 365}` : '';
        document.cookie = `${lang}${age}; Path=/; SameSite=Lax${secure}`;
    }
}

const analyticsId = meta('gf-ga');

window.gfConsent = {
    allows(cat) {
        if (cat === 'necessary') return true;
        const s = read();
        if (!s) return false;
        if (cat === 'analytics') return !!analyticsId && s.analytics;
        return !!s[cat];
    },
};

async function save(preferences, analytics) {
    const previous = read();
    const state = { id: previous?.id || uuid(), preferences, analytics: !!analyticsId && analytics };
    write(state);
    applyPreferences(state.preferences);

    if (state.analytics) loadAnalytics(analyticsId);
    else if (previous?.analytics) revokeAnalytics(analyticsId);

    document.querySelector('[data-cookie-banner]')?.remove();
    const dialog = document.querySelector('[data-cookie-dialog]');
    if (dialog?.open) dialog.close();

    const status = document.querySelector('[data-consent-status]');
    if (status) status.textContent = status.dataset.savedText || '';

    // Registo anónimo da prova de consentimento (falha silenciosa)
    try {
        await fetch('/consent', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': meta('csrf-token') },
            credentials: 'same-origin',
            body: JSON.stringify({ id: state.id, preferences: state.preferences, analytics: state.analytics, locale: document.documentElement.lang.slice(0, 2) }),
        });
    } catch { /* sem rede: a escolha fica válida no navegador */ }
}

export function initConsent() {
    const dialog = document.querySelector('[data-cookie-dialog]');
    const form = document.querySelector('[data-cookie-form]');
    let opener = null;

    const openPanel = (btn) => {
        if (!dialog) return;
        opener = btn;
        const s = read();
        form?.querySelectorAll('input[type="checkbox"]').forEach((i) => { i.checked = !!s?.[i.name]; });
        dialog.showModal();
    };
    dialog?.addEventListener('close', () => opener?.focus?.());

    document.addEventListener('click', (e) => {
        const prefs = e.target.closest('[data-cookie-prefs]');
        if (prefs) { e.preventDefault(); openPanel(prefs); return; }
        const btn = e.target.closest('[data-consent]');
        if (!btn) return;
        const mode = btn.dataset.consent;
        if (mode === 'all') save(true, true);
        else if (mode === 'none') save(false, false);
        else if (mode === 'save') {
            const val = (n) => !!form?.querySelector(`input[name="${n}"]`)?.checked;
            save(val('preferences'), val('analytics'));
        }
    });

    if (window.gfConsent.allows('analytics')) loadAnalytics(analyticsId);
}
