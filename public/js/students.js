(function ($) {
    'use strict';

    const root = document.getElementById('students');

    if (!root) {
        return;
    }

    const status = document.getElementById('student-status');
    const classFilter = document.getElementById('class-filter');
    const classOptions = document.getElementById('class-options');
    const modal = new bootstrap.Modal(document.getElementById('student-modal'));
    const form = document.getElementById('student-form');
    const formError = document.getElementById('student-form-error');
    const title = document.getElementById('student-modal-title');
    const saveButton = document.getElementById('student-save');
    let editUrl = null;

    const table = Etakit.dataTable('#student-table', {
        ajax: {
            url: root.dataset.dataUrl,
            data: (params) => {
                params.class_name = classFilter.value;
            },
        },
        order: [],
        columns: [
            { data: 'student_no', name: 'student_no', className: 'code' },
            { data: 'first_name', name: 'first_name' },
            { data: 'last_name', name: 'last_name' },
            { data: 'class_name', name: 'class_name' },
            { data: 'parent_phone_display', name: 'parent_phone', className: 'code' },
            {
                data: null,
                orderable: false,
                searchable: false,
                className: 'text-end text-nowrap',
                render: () =>
                    '<button class="btn btn-sm btn-outline-secondary me-2" type="button" data-action="edit">Düzenle</button>' +
                    '<button class="btn btn-sm btn-outline-danger" type="button" data-action="delete">Sil</button>',
            },
        ],
    });

    function refreshClasses(names) {
        if (!Array.isArray(names)) {
            return;
        }

        const selected = classFilter.value;
        classFilter.replaceChildren(new Option('Tüm sınıflar', ''));
        classOptions.replaceChildren();

        names.forEach((name) => {
            classFilter.append(new Option(name, name));
            const option = document.createElement('option');
            option.value = name;
            classOptions.append(option);
        });

        classFilter.value = names.includes(selected) ? selected : '';

        if (classFilter.value !== selected) {
            syncExportLink();
            table.ajax.reload();
        }
    }

    function openForm(row) {
        form.reset();
        Etakit.clearErrors(form);
        Etakit.flash(formError, '');
        editUrl = row ? row.update_url : null;
        title.textContent = row ? 'Öğrenciyi düzenle' : 'Öğrenci ekle';

        if (row) {
            form.elements.student_no.value = row.student_no;
            form.elements.class_name.value = row.class_name;
            form.elements.first_name.value = row.first_name;
            form.elements.last_name.value = row.last_name;
            form.elements.parent_phone.value = row.parent_phone_display;
        } else if (classFilter.value) {
            form.elements.class_name.value = classFilter.value;
        }

        modal.show();
    }

    const exportLink = document.getElementById('student-export');

    function syncExportLink() {
        const url = new URL(exportLink.dataset.baseUrl, window.location.href);

        if (classFilter.value) {
            url.searchParams.set('class_name', classFilter.value);
        }

        exportLink.href = url.toString();
        exportLink.textContent = classFilter.value ? classFilter.value + " Excel'e aktar" : "Excel'e aktar";
    }

    classFilter.addEventListener('change', () => {
        syncExportLink();
        table.ajax.reload();
    });
    document.getElementById('student-add').addEventListener('click', () => openForm(null));
    document.getElementById('student-modal').addEventListener('shown.bs.modal', () => form.elements.student_no.focus());

    $('#student-table tbody').on('click', 'button[data-action]', function () {
        const row = table.row($(this).closest('tr')).data();

        if (!row) {
            return;
        }

        if (this.dataset.action === 'edit') {
            openForm(row);

            return;
        }

        if (!window.confirm(row.first_name + ' ' + row.last_name + ' silinsin mi?')) {
            return;
        }

        Etakit.request(row.destroy_url, 'DELETE')
            .done((json) => {
                Etakit.flash(status, json.message, true);
                table.ajax.reload(null, false);
                refreshClasses(json.class_names);
            })
            .fail((xhr) => Etakit.flash(status, Etakit.errorMessage(xhr), false));
    });

    const importModal = new bootstrap.Modal(document.getElementById('import-modal'));
    const importForm = document.getElementById('import-form');
    const importError = document.getElementById('import-error');
    const importResult = document.getElementById('import-result');
    const importWarnings = document.getElementById('import-warnings');
    const importSave = document.getElementById('import-save');

    document.getElementById('student-import').addEventListener('click', () => {
        importForm.reset();
        Etakit.clearErrors(importForm);
        Etakit.flash(importError, '');
        importResult.hidden = true;
        importModal.show();
    });

    importForm.addEventListener('submit', (event) => {
        event.preventDefault();
        Etakit.clearErrors(importForm);
        Etakit.flash(importError, '');
        importResult.hidden = true;

        const removeMissing = importForm.elements.remove_missing.checked;

        if (removeMissing && !window.confirm('Dosyada olmayan öğrenciler silinecek. Devam edilsin mi?')) {
            return;
        }

        importSave.disabled = true;

        $.ajax({
            url: root.dataset.importUrl,
            method: 'POST',
            data: new FormData(importForm),
            processData: false,
            contentType: false,
        })
            .done((json) => {
                document.getElementById('import-summary').textContent = json.message;
                const list = importWarnings.querySelector('ul');
                list.replaceChildren();

                (json.warnings || []).forEach((warning) => {
                    const item = document.createElement('li');
                    item.textContent = warning;
                    list.append(item);
                });

                if (json.warning_count > (json.warnings || []).length) {
                    const item = document.createElement('li');
                    item.textContent = '… ve ' + (json.warning_count - json.warnings.length) + ' uyarı daha.';
                    list.append(item);
                }

                importWarnings.hidden = list.children.length === 0;
                importResult.hidden = false;
                importForm.elements.file.value = '';
                Etakit.flash(status, json.message, true);
                refreshClasses(json.class_names);
                table.ajax.reload(null, false);
            })
            .fail((xhr) => {
                if (xhr.status === 422) {
                    Etakit.showErrors(importForm, xhr.responseJSON?.errors);

                    return;
                }

                Etakit.flash(importError, Etakit.errorMessage(xhr), false);
            })
            .always(() => {
                importSave.disabled = false;
            });
    });

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        Etakit.clearErrors(form);
        Etakit.flash(formError, '');
        saveButton.disabled = true;

        Etakit.request(editUrl || root.dataset.storeUrl, editUrl ? 'PUT' : 'POST', $(form).serialize())
            .done((json) => {
                modal.hide();
                Etakit.flash(status, json.message, true);
                table.ajax.reload(null, false);
                refreshClasses(json.class_names);
            })
            .fail((xhr) => {
                if (xhr.status === 422) {
                    Etakit.showErrors(form, xhr.responseJSON?.errors);

                    return;
                }

                Etakit.flash(formError, Etakit.errorMessage(xhr), false);
            })
            .always(() => {
                saveButton.disabled = false;
            });
    });
})(jQuery);
