(function () {
    'use strict';

    const dialog = document.getElementById('qr-dialog');
    const openButton = document.getElementById('qr-open');
    const video = document.getElementById('qr-camera');
    const note = document.getElementById('qr-note');
    const result = document.getElementById('qr-result');

    if (!dialog || !openButton || !video || !result) {
        return;
    }

    const unlockUrl = dialog.dataset.unlockUrl;
    const loginUrl = dialog.dataset.loginUrl;
    let scanning = false;
    let stream = null;
    let busy = false;
    let lastCode = '';
    let lastAt = 0;

    function csrf() {
        return document.querySelector('meta[name="csrf-token"]').content;
    }

    function setStatus(message, ok) {
        result.textContent = message || '';
        result.classList.toggle('is-ok', Boolean(ok && message));
    }

    function stopCamera() {
        scanning = false;

        if (stream) {
            stream.getTracks().forEach((track) => track.stop());
            stream = null;
        }

        video.srcObject = null;
        video.hidden = true;
    }

    async function submitCode(code) {
        const now = Date.now();

        if (busy || (code === lastCode && now - lastAt < 4000)) {
            return;
        }

        busy = true;
        lastCode = code;
        lastAt = now;

        try {
            const response = await fetch(unlockUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf(),
                },
                body: JSON.stringify({ code }),
            });

            if (response.status === 401 || response.status === 419) {
                window.location.href = loginUrl;
                return;
            }

            const data = await response.json().catch(() => ({}));

            if (!response.ok) {
                setStatus(data.message || 'İstek tamamlanamadı.');
                return;
            }

            setStatus(`${data.message || 'Tahta açıldı.'} ${data.board?.name || ''}`.trim(), true);
            document.dispatchEvent(new CustomEvent('etakit:unlocked'));
        } catch {
            setStatus('Sunucuya ulaşılamadı.');
        } finally {
            busy = false;
        }
    }

    function scanFrame() {
        if (!scanning) {
            return;
        }

        if (video.readyState === video.HAVE_ENOUGH_DATA && typeof window.jsQR === 'function') {
            const width = Math.min(video.videoWidth || 0, 480);

            if (width > 0) {
                const height = Math.round(video.videoHeight * (width / video.videoWidth));
                const canvas = scanFrame.canvas || (scanFrame.canvas = document.createElement('canvas'));
                const context = canvas.getContext('2d', { willReadFrequently: true });
                canvas.width = width;
                canvas.height = height;
                context.drawImage(video, 0, 0, width, height);
                const image = context.getImageData(0, 0, width, height);
                const found = window.jsQR(image.data, image.width, image.height, { inversionAttempts: 'dontInvert' });

                if (found?.data) {
                    submitCode(found.data);
                }
            }
        }

        requestAnimationFrame(scanFrame);
    }

    async function startCamera() {
        if (!window.isSecureContext) {
            note.hidden = false;
            return;
        }

        if (typeof window.jsQR !== 'function') {
            setStatus('Karekod okuyucu yüklenemedi.');
            return;
        }

        try {
            stream = await navigator.mediaDevices.getUserMedia({
                audio: false,
                video: { facingMode: { ideal: 'environment' } },
            });
            video.srcObject = stream;
            video.hidden = false;
            scanning = true;
            requestAnimationFrame(scanFrame);
        } catch {
            setStatus('Kameraya erişilemedi.');
        }
    }

    openButton.addEventListener('click', () => {
        setStatus('');
        dialog.showModal();
        startCamera();
    });

    dialog.addEventListener('close', stopCamera);
    document.addEventListener('etakit:logout', () => {
        stopCamera();

        if (dialog.open) {
            dialog.close();
        }
    });
})();
