(function ($) {
    'use strict';

    const token = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    const langUrl = document.querySelector('meta[name="dt-lang"]')?.content;

    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': token,
            Accept: 'application/json',
        },
    });

    const Etakit = (window.Etakit = window.Etakit || {});

    Etakit.dataTable = function (selector, options) {
        const columns = (options.columns || []).map((column) =>
            column.data && column.render === undefined
                ? { ...column, render: DataTable.render.text() }
                : column,
        );

        return new DataTable(selector, {
            serverSide: true,
            processing: true,
            autoWidth: false,
            pageLength: 25,
            lengthMenu: [10, 25, 50, 100],
            language: langUrl ? { url: langUrl } : {},
            ...options,
            columns,
        });
    };

    Etakit.escape = function (value) {
        return DataTable.util.escapeHtml(value == null ? '' : String(value));
    };

    Etakit.errorMessage = function (xhr) {
        const json = xhr.responseJSON || {};

        if (xhr.status === 419) {
            return 'Oturum süresi doldu. Sayfayı yenileyin.';
        }

        if (json.errors) {
            const first = Object.values(json.errors)[0];

            return Array.isArray(first) ? first[0] : String(first);
        }

        return json.message || 'İşlem tamamlanamadı.';
    };

    Etakit.flash = function (element, message, ok) {
        if (!element) {
            return;
        }

        element.textContent = message;
        element.classList.toggle('is-ok', Boolean(ok));
    };

    Etakit.clearErrors = function (form) {
        form.querySelectorAll('.is-invalid').forEach((el) => el.classList.remove('is-invalid'));
        form.querySelectorAll('.invalid-feedback').forEach((el) => {
            el.textContent = '';
        });
    };

    Etakit.showErrors = function (form, errors) {
        Object.entries(errors || {}).forEach(([name, messages]) => {
            const input = form.elements.namedItem(name);

            if (!input) {
                return;
            }

            input.classList.add('is-invalid');
            const feedback = input.parentElement.querySelector('.invalid-feedback');

            if (feedback) {
                feedback.textContent = Array.isArray(messages) ? messages[0] : String(messages);
            }
        });
    };

    Etakit.request = function (url, method, data) {
        return $.ajax({ url, method, data });
    };
})(jQuery);
