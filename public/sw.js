// Service worker của Farm2Home: chỉ để cài được app và báo khi mất mạng.
// Không lưu sẵn trang nào khác, để đơn hàng / kế hoạch luôn là số mới nhất từ máy chủ.
const CACHE = 'f2h-offline-v1';
const OFFLINE_URL = '/offline.html';

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(CACHE).then((cache) => cache.add(OFFLINE_URL)));
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => Promise.all(keys.filter((key) => key !== CACHE).map((key) => caches.delete(key)))),
    );
    self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    if (event.request.mode !== 'navigate') {
        return;
    }
    event.respondWith(fetch(event.request).catch(() => caches.match(OFFLINE_URL)));
});
