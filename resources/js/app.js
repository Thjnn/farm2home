import Alpine from 'alpinejs';

// navigator.clipboard only exists on HTTPS/localhost; on http://farm2home.test fall back to execCommand.
window.copyText = (text) => {
    if (navigator.clipboard && window.isSecureContext) {
        return navigator.clipboard.writeText(text);
    }
    const area = document.createElement('textarea');
    area.value = text;
    area.setAttribute('readonly', '');
    area.style.position = 'fixed';
    area.style.opacity = '0';
    document.body.appendChild(area);
    area.select();
    const ok = document.execCommand('copy');
    area.remove();
    return ok ? Promise.resolve() : Promise.reject(new Error('copy failed'));
};

window.Alpine = Alpine;
Alpine.start();
