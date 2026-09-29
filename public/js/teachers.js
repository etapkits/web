(function ($) {
    'use strict';

    const root = document.getElementById('teachers');

    if (!root) {
        return;
    }

    const status = document.getElementById('teacher-status');
    const statusFilter = document.getElementById('teacher-status-filter');

    const table = Etakit.dataTable('#teacher-table', {
        ajax: {
            url: root.dataset.dataUrl,
            data: (params) => {
                params.status = statusFilter.value;
            },
        },
        order: [],
        columns: [
            { data: 'first_name', name: 'first_name' },
            { data: 'last_name', name: 'last_name' },
            { data: 'phone_display', name: 'phone', className: 'code' },
            {
                data: 'approved',
                name: 'approved_at',
                searchable: false,
                render: (approved) =>
                    approved
                        ? '<span class="badge badge-open">Onaylı</span>'
                        : '<span class="badge badge-pending">Onay bekliyor</span>',
            },
            {
                data: null,
                orderable: false,
                searchable: false,
                className: 'text-end text-nowrap',
                render: (data, type, row) =>
                    (row.approved
                        ? ''
                        : '<button class="btn btn-sm btn-primary me-2" type="button" data-action="approve">Onayla</button>') +
                    '<button class="btn btn-sm btn-outline-danger" type="button" data-action="delete">Sil</button>',
            },
        ],
    });

    statusFilter.addEventListener('change', () => table.ajax.reload());

    $('#teacher-table tbody').on('click', 'button[data-action]', function () {
        const row = table.row($(this).closest('tr')).data();

        if (!row) {
            return;
        }

        const approve = this.dataset.action === 'approve';

        if (!approve && !window.confirm(row.first_name + ' ' + row.last_name + ' silinsin mi?')) {
            return;
        }

        Etakit.request(approve ? row.approve_url : row.destroy_url, approve ? 'POST' : 'DELETE')
            .done((json) => {
                Etakit.flash(status, json.message, true);
                table.ajax.reload(null, false);
            })
            .fail((xhr) => Etakit.flash(status, Etakit.errorMessage(xhr), false));
    });
})(jQuery);
