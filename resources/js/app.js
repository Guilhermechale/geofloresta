/* GEOFLORESTA — comportamento base (sem frameworks) */
import { initConsent } from './consent.js';
import { initTracking } from './analytics.js';
import { initTheme } from './theme.js';
import { initMenu } from './menu.js';
import { initLang } from './lang.js';

initConsent(); // primeiro: define window.gfConsent, usado pelo tema e pelo idioma
initTheme();
initMenu();
initLang();
initTracking();

// Vídeo de apresentação: o YouTube só é contactado quando o visitante carrega em play
document.querySelectorAll('[data-video-facade]').forEach((box) => {
    const btn = box.querySelector('[data-video-play]');
    btn?.addEventListener('click', () => {
        const id = box.dataset.videoId, lang = box.dataset.videoLang || 'pt';
        const params = new URLSearchParams({ autoplay: '1', rel: '0', modestbranding: '1', hl: lang, cc_lang_pref: lang });
        if (lang !== 'pt') params.set('cc_load_policy', '1'); // legendas na língua do visitante
        const f = document.createElement('iframe');
        f.src = `https://www.youtube-nocookie.com/embed/${encodeURIComponent(id)}?${params}`;
        f.title = box.dataset.videoTitle || 'YouTube';
        f.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share';
        f.allowFullscreen = true;
        f.referrerPolicy = 'strict-origin-when-cross-origin';
        box.replaceChildren(f);
        f.focus();
    }, { once: true });
});
