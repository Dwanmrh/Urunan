/*
| Service worker Udunan.
|
| - Aset build (CSS, JS, font, ikon) disimpan supaya aplikasi terbuka cepat.
| - Halaman selalu diambil dari server supaya data tagihan tidak pernah basi,
|   dan halaman berisi data pribadi tidak disimpan di perangkat.
| - Kalau sedang offline, tampilkan halaman offline.
*/
const VERSI = 'udunan-v2';
const HALAMAN_OFFLINE = '/offline.html';

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(VERSI).then((cache) => cache.addAll([HALAMAN_OFFLINE, '/ikon/ikon-192.png', '/favicon.svg'])),
    );
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((kunci) => Promise.all(kunci.filter((k) => k !== VERSI).map((k) => caches.delete(k)))),
    );
    self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    const permintaan = event.request;

    if (permintaan.method !== 'GET') {
        return;
    }

    const url = new URL(permintaan.url);

    if (url.origin !== self.location.origin) {
        return;
    }

    if (permintaan.mode === 'navigate') {
        event.respondWith(fetch(permintaan).catch(() => caches.match(HALAMAN_OFFLINE)));

        return;
    }

    const asetStatis = url.pathname.startsWith('/build/') || url.pathname.startsWith('/ikon/') || url.pathname.startsWith('/fonts/');

    if (asetStatis) {
        event.respondWith(
            caches.match(permintaan).then((tersimpan) => tersimpan || fetch(permintaan).then((respons) => {
                if (respons.ok) {
                    const salinan = respons.clone();
                    caches.open(VERSI).then((cache) => cache.put(permintaan, salinan));
                }

                return respons;
            })),
        );
    }
});
