const form = document.getElementById('teacher-login-form');
const error = document.getElementById('login-error');
const phoneStep = document.getElementById('phone-step');
const codeStep = document.getElementById('code-step');
const phoneInput = document.getElementById('phone');
const codeInput = document.getElementById('code');
const rememberInput = document.getElementById('remember');

function storeRemember(value) {
    try {
        localStorage.setItem('etakit.remember', value ? '1' : '0');
    } catch {
        // Depolama kapalıysa tercih bu sayfada kalır.
    }
}

try {
    rememberInput.checked = localStorage.getItem('etakit.remember') === '1';
} catch {
    rememberInput.checked = false;
}

rememberInput.addEventListener('change', () => storeRemember(rememberInput.checked));

function csrf() {
    return document.querySelector('meta[name="csrf-token"]').content;
}

function setError(message) {
    error.textContent = message || '';
    error.classList.remove('is-ok');
}

function setStatus(message) {
    error.textContent = message || '';
    error.classList.toggle('is-ok', Boolean(message));
}

function buttons(disabled) {
    form.querySelectorAll('button').forEach((button) => {
        button.disabled = disabled;
    });
}

async function post(url, body) {
    const response = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrf(),
        },
        body: JSON.stringify(body),
    });
    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
        if (response.status === 429) {
            throw new Error('Çok fazla deneme. Yaklaşık 15 dakika sonra yeniden deneyin.');
        }

        const message = data.message
            || data.errors?.phone?.[0]
            || data.errors?.code?.[0]
            || 'İşlem tamamlanamadı.';
        throw new Error(message);
    }

    return data;
}

function showCodeStep() {
    phoneStep.hidden = true;
    codeStep.hidden = false;
    phoneInput.required = false;
    codeInput.required = true;
    codeInput.focus();
}

function showPhoneStep() {
    codeStep.hidden = true;
    phoneStep.hidden = false;
    codeInput.value = '';
    codeInput.required = false;
    phoneInput.required = true;
    phoneInput.focus();
}

form.addEventListener('submit', async (event) => {
    event.preventDefault();
    setError('');
    buttons(true);

    try {
        if (codeStep.hidden) {
            const data = await post(form.dataset.otp, { phone: phoneInput.value });
            showCodeStep();
            setStatus(data.message || 'Kod gönderildi.');
            return;
        }

        await post(form.dataset.login, {
            phone: phoneInput.value,
            code: codeInput.value,
            remember: rememberInput.checked,
        });
        storeRemember(rememberInput.checked);

        window.location.href = form.dataset.panel;
    } catch (err) {
        setError(err.message || 'Sunucuya ulaşılamadı.');
    } finally {
        buttons(false);
    }
});

document.getElementById('otp-back').addEventListener('click', () => {
    setError('');
    showPhoneStep();
});
