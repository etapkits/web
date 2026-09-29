(function () {
    'use strict';

    async function endSession(button) {
        button.disabled = true;
        document.dispatchEvent(new CustomEvent('etakit:logout'));

        try {
            await fetch(button.dataset.url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
            });
        } catch {
            // Oturum kapanmasa da sayfadan çık.
        }
    }

    document.querySelectorAll('.topbar-logout').forEach((button) => {
        button.addEventListener('click', async () => {
            await endSession(button);
            window.close();
            // Tarayıcı yalnızca betikle açılan sekmeyi kapatır; kapanmadıysa bilgi sayfasını göster.
            setTimeout(() => window.location.replace(button.dataset.closed), 300);
        });
    });

    document.querySelectorAll('.topbar-forget').forEach((button) => {
        button.addEventListener('click', async () => {
            await endSession(button);

            try {
                localStorage.setItem('etakit.remember', '0');
            } catch {
                // Depolama kapalıysa kutu zaten işaretsiz gelir.
            }

            window.location.href = button.dataset.login;
        });
    });

    document.querySelectorAll('.topbar-user-toggle').forEach((button) => {
        const menu = document.getElementById(button.getAttribute('aria-controls'));

        if (!menu) {
            return;
        }

        function setOpen(open) {
            menu.hidden = !open;
            button.setAttribute('aria-expanded', open ? 'true' : 'false');
        }

        button.addEventListener('click', () => {
            setOpen(menu.hidden);

            if (!menu.hidden) {
                menu.querySelector('[role="menuitem"]')?.focus();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && !menu.hidden) {
                setOpen(false);
                button.focus();
            }
        });

        document.addEventListener('click', (event) => {
            if (!menu.contains(event.target) && !button.contains(event.target)) {
                setOpen(false);
            }
        });
    });

    document.querySelectorAll('.topbar-toggle').forEach((button) => {
        const menu = document.getElementById(button.getAttribute('aria-controls'));

        if (!menu) {
            return;
        }

        function setOpen(open) {
            menu.classList.toggle('open', open);
            button.setAttribute('aria-expanded', open ? 'true' : 'false');
            button.setAttribute('aria-label', open ? 'Menüyü kapat' : 'Menüyü aç');
        }

        button.addEventListener('click', () => {
            setOpen(!menu.classList.contains('open'));
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                setOpen(false);
            }
        });

        document.addEventListener('click', (event) => {
            if (!menu.contains(event.target) && !button.contains(event.target)) {
                setOpen(false);
            }
        });
    });
})();
