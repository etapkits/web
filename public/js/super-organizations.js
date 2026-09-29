(function () {
    'use strict';

    document.querySelectorAll('form.org-delete').forEach((form) => {
        form.addEventListener('submit', (event) => {
            const typed = window.prompt(
                `"${form.dataset.name}" kurumu; tahtaları, öğretmenleri, öğrencileri ve yoklamalarıyla birlikte kalıcı olarak silinecek.\n\nOnaylamak için kurum kodunu (${form.dataset.code}) yazın:`,
            );

            if (typed === null || typed.trim() !== form.dataset.code) {
                event.preventDefault();

                if (typed !== null) {
                    window.alert('Kurum kodu eşleşmedi. Silinmedi.');
                }

                return;
            }

            form.querySelector('input[name="confirm_code"]').value = typed.trim();
        });
    });
})();
