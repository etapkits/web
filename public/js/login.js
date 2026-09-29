const form = document.getElementById('login-form');
const error = document.getElementById('login-error');

form.addEventListener('submit', async (event) => {
    event.preventDefault();
    error.textContent = '';
    const button = form.querySelector('button');
    button.disabled = true;

    try {
        const response = await fetch(form.dataset.url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: JSON.stringify({
                email: form.email.value,
                password: form.password.value,
            }),
        });
        const data = await response.json().catch(() => ({}));

        if (!response.ok) {
            error.textContent = data.message || data.errors?.email?.[0] || 'Giriş yapılamadı.';
            button.disabled = false;
            return;
        }

        window.location.href = form.dataset.panel;
    } catch {
        error.textContent = 'Sunucuya ulaşılamadı.';
        button.disabled = false;
    }
});
