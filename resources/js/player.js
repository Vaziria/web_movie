// Premium (ad-free) player: plays the movie's HLS stream from /stream/<slug>.m3u8 (which forwards to the current
// vidara link). hls.js is loaded only here, only for members/admins, and only when the browser needs it.
// Nothing is downloaded until the viewer presses play, and the buffer is kept small (~30-60 s) to save mobile data.
const video = document.querySelector('.premium-player video[data-src]');

function showError() {
    video?.closest('.premium-player')?.querySelector('[data-player-error]')?.removeAttribute('hidden');
}

async function start() {
    if (!video) return;
    const src = video.dataset.src;

    const { default: Hls } = await import('hls.js/light');
    if (Hls.isSupported()) {
        const hls = new Hls({
            autoStartLoad: false,
            maxBufferLength: 30,
            maxMaxBufferLength: 60,
            maxBufferSize: 15 * 1000 * 1000,
        });
        hls.on(Hls.Events.ERROR, (_e, data) => {
            if (!data.fatal) return;
            if (data.type === Hls.ErrorTypes.MEDIA_ERROR) {
                hls.recoverMediaError();
            } else {
                hls.destroy();
                showError();
            }
        });
        hls.loadSource(src);
        hls.attachMedia(video);
        video.addEventListener('play', () => hls.startLoad(), { once: true });
    } else if (video.canPlayType('application/vnd.apple.mpegurl')) {
        video.src = src; // Safari / iOS play HLS natively (preload="none": loads on play)
        video.addEventListener('error', showError, { once: true });
    } else {
        showError();
    }
}

start().catch(showError);
