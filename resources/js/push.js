// Push notification buttons ([data-push-box], partials/push-button.blade.php). Loaded only when the server has VAPID
// keys. States: off (can subscribe), on (subscribed), denied (blocked in the browser), ios (iPhone/iPad outside the
// home-screen app: Safari only allows push there), error.
const boxes = [...document.querySelectorAll('[data-push-box]')];
const vapidKey = document.querySelector('meta[name="vapid-key"]')?.content;
const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
const supported = 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
const ios = /iPhone|iPad|iPod/.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
const standalone = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
const SYNC_KEY = 'moviemov-push-synced';

function show(state) {
    for (const box of boxes) {
        box.hidden = (box.dataset.hideWhen || '').split(' ').includes(state);
        for (const el of box.querySelectorAll('[data-when]')) {
            el.hidden = !el.dataset.when.split(' ').includes(state);
        }
    }
}

function keyBytes(base64url) {
    const padded = (base64url + '='.repeat((4 - (base64url.length % 4)) % 4)).replace(/-/g, '+').replace(/_/g, '/');
    return Uint8Array.from(atob(padded), (c) => c.charCodeAt(0));
}

async function post(path, body) {
    const res = await fetch(path, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
        credentials: 'same-origin',
        body: JSON.stringify(body),
    });
    if (!res.ok) {
        throw new Error('HTTP ' + res.status);
    }
}

function save(sub) {
    const encodings = PushManager.supportedContentEncodings || ['aesgcm'];
    localStorage.setItem(SYNC_KEY, new Date().toDateString());
    return post('/push/subscribe', {
        ...sub.toJSON(),
        contentEncoding: encodings.includes('aes128gcm') ? 'aes128gcm' : 'aesgcm',
        locale: document.documentElement.lang,
    });
}

async function init() {
    if (!supported) {
        if (ios && !standalone) {
            show('ios');
        }
        return;
    }

    const reg = await navigator.serviceWorker.register('/sw.js');
    if (Notification.permission === 'denied') {
        return show('denied');
    }

    const sub = await reg.pushManager.getSubscription();
    show(sub ? 'on' : 'off');
    // Re-send an existing subscription once a day (keeps the server in sync, e.g. after a language switch).
    if (sub && localStorage.getItem(SYNC_KEY) !== new Date().toDateString()) {
        save(sub).catch(() => {});
    }

    document.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-push-action]');
        if (!button) {
            return;
        }
        button.disabled = true;
        try {
            if (button.dataset.pushAction === 'subscribe') {
                const permission = await Notification.requestPermission();
                if (permission !== 'granted') {
                    return show(permission === 'denied' ? 'denied' : 'off');
                }
                const created = await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: keyBytes(vapidKey) });
                await save(created);
                show('on');
            } else {
                const current = await reg.pushManager.getSubscription();
                if (current) {
                    await post('/push/unsubscribe', { endpoint: current.endpoint });
                    await current.unsubscribe();
                }
                localStorage.removeItem(SYNC_KEY);
                show('off');
            }
        } catch (error) {
            console.warn('Push:', error);
            show('error');
        } finally {
            button.disabled = false;
        }
    });
}

if (vapidKey && boxes.length) {
    init().catch((error) => console.warn('Push:', error));
}
