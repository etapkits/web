const app = document.getElementById('app');
const boardsUrl = app.dataset.boardsUrl;
const canManage = app.dataset.canManage === '1';
const attendanceUrl = app.dataset.attendanceUrl || '';
const list = document.getElementById('board-list');
const searchInput = document.getElementById('board-search');
const loading = document.getElementById('board-loading');
const panelStatus = document.getElementById('panel-status');

function csrf() {
    return document.querySelector('meta[name="csrf-token"]').content;
}

async function api(url, options = {}) {
    const response = await fetch(url, {
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrf(),
            ...(options.headers || {}),
        },
        ...options,
    });

    if (response.status === 401 || response.status === 419) {
        window.location.href = app.dataset.loginUrl;
        const error = new Error('Oturum kapandı.');
        error.redirecting = true;
        throw error;
    }

    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
        const error = new Error(data.message || 'İstek tamamlanamadı.');
        error.status = response.status;
        throw error;
    }

    return data;
}

const boardNotes = new Map();
const noteLifetime = 15000;

function setBoardNote(board, message, ok = false) {
    const note = { text: message || '', ok };
    boardNotes.set(board.id, note);
    renderBoards();

    setTimeout(() => {
        if (boardNotes.get(board.id) !== note) {
            return;
        }

        boardNotes.delete(board.id);

        if (!listContainsFocus()) {
            renderBoards();
        }
    }, noteLifetime);
}

function setStatus(node, message, ok = false) {
    node.textContent = message || '';
    node.classList.toggle('is-ok', ok && Boolean(message));
}

function seenLabel(iso) {
    if (!iso) {
        return 'Henüz görülmedi';
    }

    const seconds = Math.round((Date.now() - new Date(iso).getTime()) / 1000);

    if (seconds < 15) {
        return 'Az önce';
    }

    if (seconds < 60) {
        return `${seconds} sn önce`;
    }

    const minutes = Math.round(seconds / 60);

    if (minutes < 60) {
        return `${minutes} dk önce`;
    }

    const hours = Math.round(minutes / 60);

    if (hours < 48) {
        return `${hours} sa önce`;
    }

    return new Date(iso).toLocaleString('tr-TR');
}

function actionButton(label, className, icon, onClick) {
    const element = document.createElement('button');
    element.type = 'button';
    element.className = className;
    const mark = document.createElement('i');
    mark.className = `fa-solid ${icon} me-1`;
    element.append(mark, document.createTextNode(label));
    element.addEventListener('click', onClick);

    return element;
}

function boardKind(board) {
    if (board.state === 'unlocked') {
        return 'open';
    }

    if (board.state === 'locked') {
        return 'locked';
    }

    if (board.state === 'offline' || board.state === 'shutting_down') {
        return 'closed';
    }

    return 'other';
}

function visuals(board) {
    if (board.state === 'unlocked') {
        return {
            card: 'status-open',
            badge: 'badge-open',
            icon: 'fa-desktop',
            badgeIcon: 'fa-circle-check',
            bg: 'var(--pastel-green)',
            color: '#2D5A27',
        };
    }

    if (board.state === 'locked') {
        return {
            card: 'status-locked',
            badge: 'badge-locked',
            icon: 'fa-lock',
            badgeIcon: 'fa-lock',
            bg: 'var(--pastel-yellow)',
            color: '#6A3B00',
        };
    }

    if (board.state === 'pending') {
        return {
            card: 'status-pending',
            badge: 'badge-pending',
            icon: 'fa-hourglass-half',
            badgeIcon: 'fa-hourglass-half',
            bg: 'var(--pastel-blue)',
            color: '#1A525C',
        };
    }

    if (board.state === 'rejected') {
        return {
            card: 'status-closed',
            badge: 'badge-rejected',
            icon: 'fa-ban',
            badgeIcon: 'fa-ban',
            bg: 'var(--pastel-rose)',
            color: '#8A4038',
        };
    }

    return {
        card: 'status-closed',
        badge: 'badge-closed',
        icon: 'fa-power-off',
        badgeIcon: board.state === 'shutting_down' ? 'fa-power-off' : 'fa-circle-xmark',
        bg: 'var(--pastel-mint)',
        color: '#4A6362',
    };
}

function deviceCodeLabel(board) {
    if ((board.name || '').trim() !== '') {
        return '';
    }

    const code = document.createElement('small');
    code.className = 'text-muted flex-shrink-0';
    code.textContent = board.device_code;

    return code;
}

function boardCard(board) {
    const look = visuals(board);
    const col = document.createElement('div');
    col.className = 'col-12 col-md-6 col-lg-4 col-xl-3';

    const card = document.createElement('article');
    card.className = `pc-card ${look.card} p-3 h-100 d-flex flex-column justify-content-between`;

    const head = document.createElement('div');
    const top = document.createElement('div');
    top.className = 'd-flex align-items-center gap-2 mb-3';

    const icon = document.createElement('div');
    icon.className = 'device-icon-wrapper';
    icon.style.backgroundColor = look.bg;
    icon.style.color = look.color;
    const iconMark = document.createElement('i');
    iconMark.className = `fa-solid ${look.icon}`;
    icon.append(iconMark);

    const chip = document.createElement('span');
    chip.className = `badge ${look.badge} px-2 py-1 rounded-pill flex-shrink-0`;
    const chipMark = document.createElement('i');
    chipMark.className = `fa-solid ${look.badgeIcon} me-1`;
    chip.append(chipMark, document.createTextNode(board.state_label));

    const nameSlot = document.createElement('div');
    nameSlot.className = 'device-name flex-grow-1 min-w-0';

    const identity = document.createElement('div');
    identity.className = 'mb-3';

    if (canManage) {
        const nameRow = document.createElement('div');
        nameRow.className = 'd-flex gap-2 align-items-center';
        const input = document.createElement('input');
        input.type = 'text';
        input.className = 'form-control device-name-input';
        input.maxLength = 80;
        input.value = board.name;
        input.setAttribute('aria-label', 'Tahta adı');
        nameRow.append(
            input,
            deviceCodeLabel(board),
            actionButton('Kaydet', 'btn btn-pastel-main btn-sm', 'fa-check', async () => {
                try {
                    await api(`${boardsUrl}/${board.id}`, {
                        method: 'PATCH',
                        body: JSON.stringify({ name: input.value.trim() }),
                    });
                    input.blur();
                    setBoardNote(board, 'Ad kaydedildi.', true);
                    await refresh(true);
                } catch (error) {
                    if (!error.redirecting) {
                        setBoardNote(board, error.message);
                    }
                }
            }),
        );
        nameSlot.append(nameRow);
    } else {
        const nameRow = document.createElement('div');
        nameRow.className = 'd-flex align-items-baseline gap-2 min-w-0';
        const name = document.createElement('span');
        name.className = 'device-title d-block';
        name.textContent = board.name;
        name.title = board.name;
        nameRow.append(name, deviceCodeLabel(board));
        nameSlot.append(nameRow);
    }

    top.append(icon, nameSlot, chip);

    const note = boardNotes.get(board.id);
    const seen = document.createElement('small');
    seen.setAttribute('role', 'status');

    if (note && note.text) {
        seen.className = `board-note d-block${note.ok ? ' is-ok' : ''}`;
        seen.textContent = note.text;
    } else {
        seen.className = 'text-muted d-block';
        seen.textContent = seenLabel(board.last_seen_at);
    }

    identity.append(seen);
    head.append(top, identity);

    const actions = document.createElement('div');
    actions.className = 'pt-2 border-top board-card-actions';

    const buttons = document.createElement('div');
    buttons.className = 'd-flex gap-2 w-100 flex-wrap';

    if (canManage && (board.approval === 'pending' || board.approval === 'rejected')) {
        buttons.append(actionButton('Onayla', 'btn btn-pastel-unlock flex-fill py-2 btn-sm', 'fa-check', () => changeApproval(board, 'approved')));
    }

    if (canManage && board.approval === 'pending') {
        buttons.append(actionButton('Reddet', 'btn btn-outline-secondary flex-fill py-2 btn-sm', 'fa-xmark', () => changeApproval(board, 'rejected')));
    }

    const kind = boardKind(board);
    const online = kind === 'locked' || kind === 'open';

    if (kind === 'locked' && board.can_unlock) {
        buttons.append(actionButton('Kilidi Aç', 'btn btn-pastel-unlock flex-fill py-2 btn-sm', 'fa-lock-open', () => sendCommand(board, 'unlock')));
    }

    if (kind === 'open' && board.can_lock) {
        buttons.append(actionButton('Kilitle', 'btn btn-pastel-lock flex-fill py-2 btn-sm', 'fa-lock', () => sendCommand(board, 'lock')));
    }

    if (attendanceUrl) {
        const url = new URL(attendanceUrl, window.location.href);
        url.searchParams.set('sinif', board.name || '');
        const link = document.createElement('a');
        link.className = 'btn btn-pastel-main flex-fill py-2 btn-sm';
        link.href = url.toString();
        const mark = document.createElement('i');
        mark.className = 'fa-solid fa-clipboard-user me-1';
        link.append(mark, document.createTextNode('Yoklama al'));
        buttons.append(link);
    }

    if (online && board.can_shutdown) {
        buttons.append(actionButton('Kapat', 'btn btn-pastel-off flex-fill py-2 btn-sm', 'fa-power-off', () => sendCommand(board, 'shutdown')));
    }

    if (buttons.childElementCount === 0) {
        const idle = document.createElement('div');
        idle.className = 'no-action-box w-100';
        const idleMark = document.createElement('i');
        idleMark.className = 'fa-solid fa-ban me-1';
        idle.append(idleMark, document.createTextNode('Komut düğmesi yok'));
        actions.append(idle);
    } else {
        actions.append(buttons);
    }

    card.append(head, actions);
    col.append(card);

    return col;
}

function gridMessage(icon, text) {
    const col = document.createElement('div');
    col.className = 'col-12 text-center py-5';
    const mark = document.createElement('i');
    mark.className = `fa-solid ${icon} display-4 mb-3 d-block board-empty-icon`;
    const title = document.createElement('h3');
    title.className = 'h5 text-muted';
    title.textContent = text;
    col.append(mark, title);

    return col;
}

async function changeApproval(board, approval) {
    if (approval === 'rejected' && !window.confirm(`${board.name} reddedilsin mi?`)) {
        return;
    }

    try {
        await api(`${boardsUrl}/${board.id}`, {
            method: 'PATCH',
            body: JSON.stringify({ approval }),
        });
        setBoardNote(board, approval === 'approved' ? 'Tahta onaylandı.' : 'Tahta reddedildi.', true);
        await refresh(true);
    } catch (error) {
        if (!error.redirecting) {
            setBoardNote(board, error.message);
        }
    }
}

async function sendCommand(board, type) {
    if (type === 'shutdown' && !window.confirm(`${board.name} kapatılsın mı?`)) {
        return;
    }

    try {
        const data = await api(`${boardsUrl}/${board.id}/${type}`, { method: 'POST' });
        setBoardNote(board, data.message, true);
        await refresh(true);
    } catch (error) {
        if (!error.redirecting) {
            setBoardNote(board, error.message);
        }
    }
}

function listContainsFocus() {
    return document.activeElement && list.contains(document.activeElement);
}

let boards = [];
let activeFilter = 'all';
let lastPayload = '';

function updateCounts() {
    document.getElementById('cnt-all').textContent = String(boards.length);
    document.getElementById('cnt-open').textContent = String(boards.filter((board) => boardKind(board) === 'open').length);
    document.getElementById('cnt-locked').textContent = String(boards.filter((board) => boardKind(board) === 'locked').length);
    document.getElementById('cnt-closed').textContent = String(boards.filter((board) => boardKind(board) === 'closed').length);
}

function renderBoards() {
    updateCounts();
    const query = searchInput.value.trim().toLocaleLowerCase('tr-TR');
    const visible = boards.filter((board) => {
        const matchesFilter = activeFilter === 'all' || boardKind(board) === activeFilter;
        const haystack = `${board.name} ${board.device_code}`.toLocaleLowerCase('tr-TR');

        return matchesFilter && haystack.includes(query);
    });

    if (boards.length === 0) {
        list.replaceChildren(gridMessage('fa-display', 'Henüz tahta yok. Tahta ilk kez bağlanınca burada onay bekler.'));
        return;
    }

    if (visible.length === 0) {
        list.replaceChildren(gridMessage('fa-desktop', 'Eşleşen tahta bulunamadı.'));
        return;
    }

    list.replaceChildren(...visible.map(boardCard));
}

async function refresh(force = false) {
    if (!force && listContainsFocus()) {
        return;
    }

    try {
        const data = await api(boardsUrl);
        const payload = JSON.stringify(data.boards);
        loading.hidden = true;

        if (!force && payload === lastPayload) {
            return;
        }

        lastPayload = payload;
        boards = data.boards;
        renderBoards();
    } catch (error) {
        loading.hidden = true;

        if (!error.redirecting) {
            setStatus(panelStatus, error.message);
        }
    }
}

document.getElementById('refresh').addEventListener('click', () => refresh(true));

document.querySelectorAll('.filter-btn').forEach((filterButton) => {
    filterButton.addEventListener('click', () => {
        activeFilter = filterButton.dataset.filter;
        document.querySelectorAll('.filter-btn').forEach((item) => item.classList.remove('active'));
        filterButton.classList.add('active');
        renderBoards();
    });
});

searchInput.addEventListener('input', () => renderBoards());

document.addEventListener('etakit:unlocked', () => refresh(true));

refresh(true);
setInterval(() => refresh(false), 4000);
