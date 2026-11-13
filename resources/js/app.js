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
