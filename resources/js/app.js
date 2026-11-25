document.querySelectorAll('[data-nav-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
        document.querySelector('[data-nav]')?.classList.toggle('hidden');
    });
});

document.querySelectorAll('[data-add-line]').forEach((button) => {
    button.addEventListener('click', () => {
        const wrap = document.querySelector('[data-lines]');
        const template = document.querySelector('#line-template');
        if (!wrap || !template) {
            return;
        }
        const index = wrap.querySelectorAll('[data-line]').length;
        wrap.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', String(index)));
    });
});

document.addEventListener('click', (event) => {
    const remove = event.target.closest('[data-remove-line]');
    if (!remove) {
        return;
    }
    const line = remove.closest('[data-line]');
    const wrap = document.querySelector('[data-lines]');
    if (line && wrap && wrap.querySelectorAll('[data-line]').length > 1) {
        line.remove();
    }
});

if ('serviceWorker' in navigator) {
    const hadController = Boolean(navigator.serviceWorker.controller);
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(() => {});
    });
    if (hadController) {
        let refreshing = false;
        navigator.serviceWorker.addEventListener('controllerchange', () => {
            if (refreshing) {
                return;
            }
            refreshing = true;
            window.location.reload();
        });
    }
}
