

import Alpine from 'alpinejs';

window.Alpine = Alpine;
Alpine.start();

const loader = document.getElementById('site-loader');

const hideLoader = () => {
    if (!loader) return;

    loader.classList.add('is-hidden');

    window.setTimeout(() => {
        loader.setAttribute('aria-hidden', 'true');
        loader.style.display = 'none';
    }, 500);
};

const showLoader = () => {
    if (!loader) return;

    loader.style.display = 'flex';
    loader.classList.remove('is-hidden');
    loader.setAttribute('aria-hidden', 'false');
};

window.addEventListener('load', hideLoader);

document.addEventListener('readystatechange', () => {
    if (document.readyState === 'complete') {
        hideLoader();
    }
});

document.addEventListener('click', (event) => {
    const target = event.target.closest('a[href]');

    if (!target) return;

    const href = target.getAttribute('href');
    const isSamePage = href && !href.startsWith('#') && !href.startsWith('mailto:') && !href.startsWith('tel:');

    if (isSamePage && !event.ctrlKey && !event.metaKey && !event.shiftKey && target.target !== '_blank') {
        showLoader();
    }
});

window.addEventListener('beforeunload', showLoader);

if (document.readyState === 'complete') {
    hideLoader();
}
