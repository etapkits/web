(function () {
    'use strict';

    const root = document.getElementById('attendance-list');

    if (!root) {
        return;
    }

    const form = document.getElementById('list-filters');
    const dateLabel = document.getElementById('list-date-label');
    const sessionsBox = document.getElementById('list-sessions');
    const fields = ['tarih', 'sinif', 'ogretmen', 'ders', 'durum'];

    function filterValues() {
        const values = {};

        fields.forEach((name) => {
            const value = form.elements[name].value;

            if (value !== '') {
                values[name] = value;
            }
        });

        return values;
    }

    function syncUrl() {
        const url = new URL(window.location.href);
        url.search = new URLSearchParams(filterValues()).toString();
        window.history.replaceState(null, '', url.toString());
    }

    function renderSessions(sessions) {
        sessionsBox.replaceChildren();
        sessionsBox.hidden = form.elements.sinif.value === '';

        if (sessionsBox.hidden) {
            return;
        }

        if (!sessions.length) {
            const empty = document.createElement('p');
            empty.className = 'text-secondary small mb-0';
            empty.textContent = 'Seçili filtrelere uyan yoklama yok.';
            sessionsBox.append(empty);

            return;
        }

        sessions.forEach((s) => {
            const chip = document.createElement('div');
            chip.className = 'list-session';
            const title = document.createElement('strong');
            title.textContent = s.class_name + ' · ' + s.lesson + '. ders';
            const meta = document.createElement('span');
            meta.textContent = s.teacher + ' · ' + s.time + ' · ' + s.absent + ' gelmedi, ' + s.late + ' geç / ' + s.total;
            chip.append(title, meta);
            sessionsBox.append(chip);
        });
    }

    const table = Etakit.dataTable('#list-table', {
        ajax: {
            url: root.dataset.dataUrl,
            data: (params) => Object.assign(params, filterValues()),
            dataSrc: (json) => {
                dateLabel.textContent = json.date_label || '';
                renderSessions(json.sessions || []);

                return json.data;
            },
        },
        order: [],
        pageLength: 50,
        columns: [
            { data: 'class_name', name: 'class_name', className: 'fw-semibold' },
            { data: 'lesson', name: 's.lesson', searchable: false, render: (v) => Etakit.escape(v) + '. ders' },
            { data: 'student_no', name: 'student_no', className: 'code' },
            { data: 'student_name', name: 'attendance_records.student_name' },
            {
                data: 'status',
                name: 'attendance_records.status',
                searchable: false,
                render: (v, type, row) => '<span class="att-chip att-' + Etakit.escape(v) + '">' + Etakit.escape(row.status_label) + '</span>',
            },
            { data: 'teacher_name', name: 's.teacher_name' },
            { data: 'time', name: 's.created_at', searchable: false },
        ],
    });

    form.addEventListener('change', (event) => {
        syncUrl();

        if (event.target.name === 'tarih') {
            window.location.reload();

            return;
        }

        table.ajax.reload();
    });

    document.getElementById('f-clear').addEventListener('click', () => {
        ['sinif', 'ogretmen', 'ders', 'durum'].forEach((name) => {
            form.elements[name].value = '';
        });
        syncUrl();
        table.ajax.reload();
    });

    document.getElementById('list-copy').addEventListener('click', async (event) => {
        const button = event.currentTarget;

        try {
            await navigator.clipboard.writeText(window.location.href);
            button.textContent = 'Kopyalandı';
        } catch {
            window.prompt('Linki kopyalayın:', window.location.href);
        }

        setTimeout(() => {
            button.textContent = 'Linki kopyala';
        }, 2000);
    });

    syncUrl();
})();
