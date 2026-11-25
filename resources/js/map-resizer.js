/* Barra entre o mapa e o painel (computador): arrastar ajusta as larguras, o botão recolhe o painel,
   duplo clique repõe; setas do teclado também funcionam. A escolha fica guardada neste navegador. */
const KEY = 'gf-map-panel';
const DEFAULT = 352; // 22rem
const MIN = 260;

export function setupResizer() {
    const shell = document.querySelector('.map-shell');
    const bar = shell?.querySelector('[data-map-resizer]');
    const toggle = shell?.querySelector('[data-map-panel-toggle]');
    if (!shell || !bar) return;
    const desktop = matchMedia('(min-width: 1080px)');
    const max = () => Math.max(MIN, Math.min(shell.clientWidth * 0.6, 760));
    const clamp = (w) => Math.round(Math.min(max(), Math.max(MIN, w)));

    let state = { w: DEFAULT, hidden: false };
    try { state = { ...state, ...JSON.parse(localStorage.getItem(KEY) || '{}') }; } catch { /* sem armazenamento: usa o padrão */ }
    const save = () => { try { localStorage.setItem(KEY, JSON.stringify(state)); } catch { /* ignora */ } };

    const apply = () => {
        shell.style.setProperty('--panel-w', clamp(state.w) + 'px');
        shell.classList.toggle('panel-hidden', state.hidden);
        bar.setAttribute('aria-valuenow', String(state.hidden ? 0 : clamp(state.w)));
        bar.setAttribute('aria-valuemin', String(MIN));
        bar.setAttribute('aria-valuemax', String(Math.round(max())));
        if (toggle) {
            const label = state.hidden ? toggle.dataset.labelShow : toggle.dataset.labelHide;
            toggle.setAttribute('aria-expanded', String(!state.hidden));
            toggle.setAttribute('aria-label', label); toggle.title = label;
        }
    };
    apply();

    bar.addEventListener('pointerdown', (e) => {
        if (!desktop.matches || e.button !== 0 || e.target.closest('[data-map-panel-toggle]')) return;
        e.preventDefault();
        bar.setPointerCapture(e.pointerId);
        shell.classList.add('is-resizing');
        const right = shell.getBoundingClientRect().right;
        const move = (ev) => { state.hidden = false; state.w = clamp(right - ev.clientX - bar.offsetWidth / 2); apply(); };
        const up = () => {
            shell.classList.remove('is-resizing');
            bar.removeEventListener('pointermove', move); bar.removeEventListener('pointerup', up); bar.removeEventListener('pointercancel', up);
            save();
        };
        bar.addEventListener('pointermove', move); bar.addEventListener('pointerup', up); bar.addEventListener('pointercancel', up);
    });
    bar.addEventListener('dblclick', (e) => { if (e.target.closest('[data-map-panel-toggle]')) return; state = { w: DEFAULT, hidden: false }; apply(); save(); });
    bar.addEventListener('keydown', (e) => {
        const step = e.shiftKey ? 64 : 24;
        if (e.key === 'ArrowLeft') state.w = clamp(state.w + step);
        else if (e.key === 'ArrowRight') state.w = clamp(state.w - step);
        else if (e.key === 'Home') state.w = clamp(max());
        else if (e.key === 'End') state.w = MIN;
        else return;
        e.preventDefault(); state.hidden = false; apply(); save();
    });
    toggle?.addEventListener('click', () => { state.hidden = !state.hidden; apply(); save(); });
    addEventListener('resize', apply);
}
