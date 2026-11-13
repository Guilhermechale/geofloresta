/*
 * Google Analytics 4 — só é carregado depois do consentimento "Analítica" e só se houver GA4_ID.
 * Consent Mode v2: tudo negado por defeito; só analytics_storage passa a "granted".
 * Eventos: generate_lead (envio do contacto) e file_download (download de dataset).
 */
let loaded = false;
const queue = [];

export function loadAnalytics(id) {
    if (!id || loaded) return;
    loaded = true;
    window.dataLayer = window.dataLayer || [];
    window.gtag = function gtag() { window.dataLayer.push(arguments); };
    window[`ga-disable-${id}`] = false;
    window.gtag('consent', 'default', {
        ad_storage: 'denied', ad_user_data: 'denied', ad_personalization: 'denied', analytics_storage: 'denied',
    });
    window.gtag('consent', 'update', { analytics_storage: 'granted' });
    window.gtag('js', new Date());
    window.gtag('config', id);

    const s = document.createElement('script');
    s.async = true;
    s.src = `https://www.googletagmanager.com/gtag/js?id=${encodeURIComponent(id)}`;
    document.head.appendChild(s);

    while (queue.length) window.gtag('event', ...queue.shift());
    sendPageEvents();
}

export function revokeAnalytics(id) {
    if (!id) return;
    window[`ga-disable-${id}`] = true;
    if (window.gtag) window.gtag('consent', 'update', { analytics_storage: 'denied' });
    // Apaga os cookies _ga* no domínio actual e no domínio-pai
    const host = location.hostname;
    const domains = ['', host, '.' + host.split('.').slice(-2).join('.')];
    document.cookie.split('; ').map((c) => c.split('=')[0]).filter((n) => n.startsWith('_ga')).forEach((n) => {
        domains.forEach((d) => { document.cookie = `${n}=; Max-Age=0; Path=/${d ? '; Domain=' + d : ''}`; });
    });
}

export function track(name, params = {}) {
    if (!window.gfConsent?.allows('analytics')) return;
    if (loaded && window.gtag) window.gtag('event', name, params);
    else queue.push([name, params]);
}

/** Eventos declarados na página (ex.: página de agradecimento do contacto). */
function sendPageEvents() {
    document.querySelectorAll('[data-track-event]').forEach((el) => {
        let params = {};
        try { params = JSON.parse(el.dataset.trackParams || '{}'); } catch { /* ignora */ }
        window.gtag('event', el.dataset.trackEvent, params);
        el.removeAttribute('data-track-event');
    });
}

export function initTracking() {
    document.addEventListener('click', (e) => {
        const a = e.target.closest('[data-track-download]');
        if (a) track('file_download', { file_name: a.dataset.trackDownload, dataset: a.dataset.dataset || '' });
    });
}
