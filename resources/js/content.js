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
