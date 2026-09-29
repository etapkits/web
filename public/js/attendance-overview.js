(function () {
    'use strict';

    const root = document.getElementById('attendance-overview');

    if (!root) {
        return;
    }

    const lessonCount = Number(root.dataset.lessons) || 8;
    const dateInput = document.getElementById('overview-date');
    const dateLabel = document.getElementById('overview-date-label');
    const summary = document.getElementById('overview-summary');

    function listLink(params) {
        const url = new URL(root.dataset.listUrl, window.location.href);
        url.search = new URLSearchParams({ tarih: dateInput.value, ...params }).toString();

        return url.toString();
    }

    function lessonCell(info, className, lesson) {
        if (!info) {
            return '<span class="ov-missing">Yoklama alınmadı</span>';
        }

        const detail = [info.time];

        if (info.absent) {
            detail.push(info.absent + ' gelmedi');
        }

        if (info.late) {
            detail.push(info.late + ' geç');
        }

        return (
            '<a class="ov-taken" href="' + Etakit.escape(listLink({ sinif: className, ders: String(lesson) })) + '" title="Yoklama listesini aç">' +
            '<span class="ov-teacher">' + Etakit.escape(info.teacher) + '</span>' +
            '<span class="ov-meta">' + Etakit.escape(detail.join(' · ')) + '</span>' +
            '</a>'
        );
    }

    const columns = [
        {
            data: 'class_name',
            name: 'class_name',
            className: 'att-col-class fw-semibold',
            render: (value, type) =>
                type === 'display'
                    ? '<a class="ov-class-link" href="' + Etakit.escape(listLink({ sinif: value })) + '">' + Etakit.escape(value) + '</a>'
                    : value,
        },
    ];

    for (let lesson = 1; lesson <= lessonCount; lesson++) {
        columns.push({
            data: null,
            searchable: false,
            className: 'text-center ov-cell',
            render: (data, type, row) => lessonCell(row.lessons ? row.lessons[lesson] : null, row.class_name, lesson),
            createdCell: (td, cellData, row) => {
                td.classList.toggle('is-taken', Boolean(row.lessons && row.lessons[lesson]));
            },
        });
    }

    const table = Etakit.dataTable('#overview-table', {
        ajax: {
            url: root.dataset.dataUrl,
            data: (params) => {
                params.date = dateInput.value;
            },
            dataSrc: (json) => {
                dateLabel.textContent = json.date_label || '';

                if (json.date && dateInput.value !== json.date) {
                    dateInput.value = json.date;
                }

                document.getElementById('overview-list').href = listLink({});

                const s = json.summary || { classes: 0, taken: 0, expected: 0 };
                summary.textContent = s.classes
                    ? s.classes + ' sınıf · ' + s.taken + ' / ' + s.expected + ' ders yoklaması alındı.'
                    : 'Kayıtlı sınıf yok. Önce Öğrenciler sayfasından liste yükleyin.';

                return json.data;
            },
        },
        paging: false,
        ordering: false,
        info: false,
        columns,
    });

    dateInput.addEventListener('change', () => table.ajax.reload());
    document.getElementById('overview-refresh').addEventListener('click', () => table.ajax.reload(null, false));
    document.getElementById('overview-today').addEventListener('click', (event) => {
        dateInput.value = event.currentTarget.dataset.today;
        table.ajax.reload();
    });

    setInterval(() => {
        if (document.visibilityState === 'visible' && dateInput.value === dateInput.max) {
            table.ajax.reload(null, false);
        }
    }, 60000);
})();
