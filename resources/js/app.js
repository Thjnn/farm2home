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

// Trang quản trị hỏi máy chủ mỗi 15 giây xem có gì mới (đơn, trạng thái, nông hộ, phản hồi), không cần F5.
// ponytail: polling 15s; chuyển sang WebSocket (Laravel Reverb) khi cần tức thì từng giây.
Alpine.data('adminNotifications', ({ url, since, badges }) => ({
    since,
    badges,
    toasts: [],
    nextId: 0,
    init() {
        setInterval(() => this.poll(), 15000);
        document.addEventListener('visibilitychange', () => document.hidden || this.poll());
    },
    async poll() {
        if (document.hidden) {
            return;
        }
        try {
            const response = await fetch(`${url}?since=${this.since}`, { headers: { Accept: 'application/json' } });
            if (!response.ok) {
                return;
            }
            const data = await response.json();
            this.since = data.now;
            this.badges = data.badges;
            data.events.forEach((event) => this.show(event));
        } catch (e) {
            // Mất mạng: lần sau hỏi lại.
        }
    },
    show(event) {
        const toast = { ...event, id: this.nextId++ };
        this.toasts.push(toast);
        setTimeout(() => this.dismiss(toast.id), 20000);
    },
    dismiss(id) {
        this.toasts = this.toasts.filter((toast) => toast.id !== id);
    },
}));

// PWA: cho phép "Cài đặt ứng dụng" (cần HTTPS; trên http://farm2home.test trình duyệt bỏ qua).
if ('serviceWorker' in navigator && window.isSecureContext) {
    window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}));
}

// Trang khách: mời "cài ứng dụng". Chrome/Android có sự kiện beforeinstallprompt; iPhone thì chỉ hướng dẫn tay.
let deferredInstall = null;
window.addEventListener('beforeinstallprompt', (event) => {
    event.preventDefault();
    deferredInstall = event;
    window.dispatchEvent(new Event('f2h-installable'));
});

Alpine.data('installPrompt', () => ({
    show: false,
    ios: false,
    init() {
        const installed = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone;
        let dismissed = false;
        try {
            dismissed = localStorage.getItem('f2h-install-dismissed') === '1';
        } catch (e) {}
        if (installed || dismissed) {
            return;
        }
        this.ios = /iphone|ipad|ipod/i.test(navigator.userAgent);
        this.show = this.ios || deferredInstall !== null;
        window.addEventListener('f2h-installable', () => (this.show = true));
        window.addEventListener('appinstalled', () => (this.show = false));
    },
    async install() {
        if (!deferredInstall) {
            return;
        }
        deferredInstall.prompt();
        await deferredInstall.userChoice;
        deferredInstall = null;
        this.show = false;
    },
    dismiss() {
        this.show = false;
        try {
            localStorage.setItem('f2h-install-dismissed', '1');
        } catch (e) {}
    },
}));

window.Alpine = Alpine;
Alpine.start();
