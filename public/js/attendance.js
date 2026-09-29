(function ($) {
    'use strict';

    const root = document.getElementById('attendance');

    if (!root) {
        return;
    }

    const lessonCount = Number(root.dataset.lessons) || 8;
    const classSelect = document.getElementById('attendance-class');
    const card = document.getElementById('attendance-card');
    const status = document.getElementById('attendance-status');
    const cycle = ['', 'absent', 'late'];
    const labels = { absent: 'Gelmedi', late: 'Geç' };

    let lessons = [];
    let pending = {};
    let table = null;
    let currentClass = '';

    function lessonInfo(lesson) {
        return lessons.find((item) => item.lesson === lesson) || { lesson, taken: false };
    }

    function pendingCount(lesson) {
        return Object.keys(pending[lesson] || {}).length;
    }

    function hasPending() {
        return Object.keys(pending).some((lesson) => pendingCount(lesson) > 0);
    }

    function markButton(studentId, lesson, value) {
        const state = value || '';

        return (
            '<button type="button" class="att-btn' + (state ? ' att-' + state : '') + '"' +
            ' data-student="' + studentId + '" data-lesson="' + lesson + '"' +
            ' aria-label="' + lesson + '. ders: ' + (labels[state] || 'Geldi') + '">' +
            (labels[state] || '') +
            '</button>'
        );
    }

    function savedMark(value) {
        return value
            ? '<span class="att-chip att-' + value + '">' + labels[value] + '</span>'
            : '<span class="att-present" aria-label="Geldi">&middot;</span>';
    }

    function lessonColumn(lesson) {
        return {
            data: null,
            className: 'text-center att-cell',
            searchable: false,
            render: (data, type, row) => {
                const saved = row.marks ? row.marks[lesson] : undefined;

                if (lessonInfo(lesson).taken) {
                    return savedMark(saved);
                }

                return markButton(row.id, lesson, (pending[lesson] || {})[row.id]);
            },
        };
    }

    function renderHeads() {
        document.querySelectorAll('.att-lesson-head').forEach((head) => {
            const lesson = Number(head.dataset.lesson);
            const info = lessonInfo(lesson);
            const button = head.querySelector('.att-save');
            const meta = head.querySelector('.att-head-meta');

            head.classList.toggle('is-taken', Boolean(info.taken));
            button.hidden = Boolean(info.taken);

            if (info.taken) {
                meta.textContent = info.teacher + ' · ' + info.time;
                meta.title = 'Gelmedi: ' + info.absent + ', geç: ' + info.late + ' / ' + info.total + ' öğrenci';
            } else {
                const count = pendingCount(lesson);
                meta.textContent = count ? count + ' işaret' : '';
                meta.title = '';
            }
        });
    }

    function buildTable() {
        const columns = [
            {
                data: null,
                className: 'att-col-index text-muted',
                searchable: false,
                render: (data, type, row, meta) => String(meta.row + 1),
            },
            { data: 'student_no', name: 'student_no', className: 'code att-col-no' },
            { data: 'name', name: 'name', className: 'att-name' },
        ];

        for (let lesson = 1; lesson <= lessonCount; lesson++) {
            columns.push(lessonColumn(lesson));
        }

        table = Etakit.dataTable('#attendance-table', {
            ajax: {
                url: root.dataset.dataUrl,
                data: (params) => {
                    params.class_name = currentClass;
                },
                dataSrc: (json) => {
                    lessons = json.lessons || [];

                    return json.data;
                },
            },
            paging: false,
            ordering: false,
            info: false,
            columns,
            drawCallback: renderHeads,
        });
    }

    function selectClass(value) {
        if (value === currentClass) {
            return;
        }

        if (hasPending() && !window.confirm('Kaydedilmemiş işaretler silinecek. Devam edilsin mi?')) {
            classSelect.value = currentClass;

            return;
        }

        currentClass = value;
        pending = {};
        Etakit.flash(status, '');
        card.hidden = value === '';

        if (value === '') {
            return;
        }

        if (!table) {
            buildTable();
        } else {
            table.search('');
            table.ajax.reload();
        }
    }

    classSelect.addEventListener('change', () => selectClass(classSelect.value));

    $('#attendance-table').on('click', '.att-btn', function () {
        const lesson = Number(this.dataset.lesson);
        const studentId = this.dataset.student;
        const marks = (pending[lesson] = pending[lesson] || {});
        const next = cycle[(cycle.indexOf(marks[studentId] || '') + 1) % cycle.length];

        if (next) {
            marks[studentId] = next;
        } else {
            delete marks[studentId];
        }

        this.className = 'att-btn' + (next ? ' att-' + next : '');
        this.textContent = labels[next] || '';
        this.setAttribute('aria-label', lesson + '. ders: ' + (labels[next] || 'Geldi'));
        renderHeads();
    });

    $('#attendance-table').on('click', '.att-save', function () {
        const lesson = Number(this.dataset.lesson);
        const marks = pending[lesson] || {};
        const values = Object.values(marks);
        const absent = values.filter((v) => v === 'absent').length;
        const late = values.filter((v) => v === 'late').length;

        const question =
            currentClass + ' ' + lesson + '. ders yoklaması kaydedilsin mi?\n\n' +
            'Gelmedi: ' + absent + '\nGeç: ' + late + '\nDiğerleri geldi sayılır.\n\n' +
            'Kaydedilen yoklama değiştirilemez.';

        if (!window.confirm(question)) {
            return;
        }

        this.disabled = true;

        $.ajax({
            url: root.dataset.storeUrl,
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ class_name: currentClass, lesson, marks }),
        })
            .done((json) => {
                delete pending[lesson];
                Etakit.flash(status, json.message, true);
                table.ajax.reload(null, false);
            })
            .fail((xhr) => {
                Etakit.flash(status, Etakit.errorMessage(xhr), false);

                if (xhr.status === 409) {
                    delete pending[lesson];
                    table.ajax.reload(null, false);
                }
            })
            .always(() => {
                this.disabled = false;
            });
    });

    window.addEventListener('beforeunload', (event) => {
        if (hasPending()) {
            event.preventDefault();
            event.returnValue = '';
        }
    });

    if (classSelect.value) {
        selectClass(classSelect.value);
    }
})(jQuery);
