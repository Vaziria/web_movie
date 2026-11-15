/* MovieMov service worker: shows push notifications and opens the page when one is tapped. Kept tiny on purpose:
   no offline caching, so it can never serve stale pages. */
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));

self.addEventListener('push', (event) => {
    let data = {};
    try {
        data = event.data ? event.data.json() : {};
    } catch (e) {
        data = { title: 'MovieMov', body: event.data ? event.data.text() : '' };
    }

    const options = {
        body: data.body || '',
        icon: data.icon || '/icons/icon-192.png',
        badge: data.badge || '/icons/badge-96.png',
        tag: data.tag || 'moviemov',
        data: { url: data.url || '/' },
    };
    if (data.image) {
        options.image = data.image;
    }

    event.waitUntil(self.registration.showNotification(data.title || 'MovieMov', options));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = new URL((event.notification.data && event.notification.data.url) || '/', self.location.origin);
    // Only ever open pages of this site.
    const target = url.origin === self.location.origin ? url.href : self.location.origin + '/';

    event.waitUntil((async () => {
        const windows = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
        for (const client of windows) {
            if (client.url === target && 'focus' in client) {
                return client.focus();
            }
        }
        return self.clients.openWindow(target);
    })());
});
