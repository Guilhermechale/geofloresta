/* Blocos de código: destaque de sintaxe (highlight.js, só as linguagens usadas) e botão copiar. */
import hljs from 'highlight.js/lib/core';
import javascript from 'highlight.js/lib/languages/javascript';
import python from 'highlight.js/lib/languages/python';
import r from 'highlight.js/lib/languages/r';
import bash from 'highlight.js/lib/languages/bash';
import sql from 'highlight.js/lib/languages/sql';
import json from 'highlight.js/lib/languages/json';
import xml from 'highlight.js/lib/languages/xml';

hljs.registerLanguage('javascript', javascript);
hljs.registerLanguage('python', python);
hljs.registerLanguage('r', r);
hljs.registerLanguage('bash', bash);
hljs.registerLanguage('sql', sql);
hljs.registerLanguage('json', json);
hljs.registerLanguage('xml', xml);

const article = document.querySelector('.article');
const copyLabel = article?.dataset.copyLabel || 'Copiar';
const copiedLabel = article?.dataset.copiedLabel || 'Copiado';

document.querySelectorAll('.article pre').forEach((pre) => {
    const code = pre.querySelector('code') || pre;
    if (!code.classList.contains('hljs')) hljs.highlightElement(code);

    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'copy-btn';
    btn.textContent = copyLabel;
    btn.addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText(code.innerText);
            btn.textContent = copiedLabel;
            setTimeout(() => { btn.textContent = copyLabel; }, 2000);
        } catch { /* sem acesso à área de transferência */ }
    });
    pre.classList.add('has-copy');
    pre.prepend(btn);
});

/* Pesquisas: copiar a citação */
document.querySelectorAll('.cite').forEach((box) => {
    const btn = box.querySelector('.cite__copy');
    const wrap = box.closest('[data-copy-label]');
    btn?.addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText(box.querySelector('.cite__text').innerText.trim());
            btn.textContent = wrap?.dataset.copiedLabel || 'Copiado';
            setTimeout(() => { btn.textContent = wrap?.dataset.copyLabel || 'Copiar'; }, 2000);
        } catch { /* sem acesso à área de transferência */ }
    });
});

/* Gráficos: tooltip ao passar o rato ou tocar (linha vertical nos gráficos de linhas). */
document.querySelectorAll('.chart').forEach((chart) => {
    const tip = chart.querySelector('.chart__tip');
    if (!tip) return;

    const show = (target, evt) => {
        const text = target.dataset.tip;
        if (!text) return;
        tip.textContent = text;
        tip.hidden = false;
        const svg = target.ownerSVGElement;
        const box = chart.getBoundingClientRect();
        const cross = svg.querySelector('.ch-cross');
        if (cross && target.dataset.x) {
            cross.setAttribute('x1', target.dataset.x);
            cross.setAttribute('x2', target.dataset.x);
            chart.classList.add('is-active');
        }
        const r = target.getBoundingClientRect();
        const x = evt && evt.clientX ? evt.clientX : r.left + r.width / 2;
        const y = evt && evt.clientY ? evt.clientY : r.top;
        let left = x - box.left + 12;
        if (left + tip.offsetWidth > box.width) left = Math.max(0, x - box.left - tip.offsetWidth - 12);
        tip.style.left = `${left}px`;
        tip.style.top = `${Math.max(0, y - box.top - tip.offsetHeight - 10)}px`;
    };
    const hide = () => { tip.hidden = true; chart.classList.remove('is-active'); };

    chart.addEventListener('pointermove', (e) => {
        const t = e.target.closest('[data-tip]');
        if (t && chart.contains(t)) show(t, e); else hide();
    });
    chart.addEventListener('pointerleave', hide);
    chart.addEventListener('pointerdown', (e) => {
        const t = e.target.closest('[data-tip]');
        if (t) show(t, e);
    });
});
