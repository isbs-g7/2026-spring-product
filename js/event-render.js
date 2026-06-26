/**
 * event-render.js — カード描画・ページネーション・ユーティリティ
 * events.js から分割。描画ロジックのみを担当する。
 */

// ---- カード描画 ----

/**
 * 件数表示
 */
export function renderResultCount(el, total) {
    el.textContent = `${total}件のイベントが見つかりました`;
}

/**
 * イベントカード一覧を描画
 */
export function renderEvents(el, events) {
    if (events.length === 0) {
        el.innerHTML = '<p class="no-results">条件に一致するイベントはありませんでした。</p>';
        return;
    }
    el.innerHTML = events.map(buildEventCard).join('');
}

/**
 * イベントカードのHTML生成
 */
function buildEventCard(event) {
    const statusBadge     = buildStatusBadge(event);
    const dateStr         = formatDate(event.event_date);
    const location        = event.is_online ? 'オンライン' : event.location;
    const locationIcon    = event.is_online ? 'ti-video' : 'ti-map-pin';
    const participantText = event.max_participants !== null
        ? `${event.participant_count} / ${event.max_participants}名`
        : `${event.participant_count}名参加中`;

    return `
        <a href="/pages/event-detail.html?id=${event.id}" class="event-card">
            <div class="event-card__header">
                <span class="category-badge" style="background:${event.category_color}22;color:${event.category_color}">
                    ${escapeHtml(event.category_name)}
                </span>
            </div>
            <p class="event-card__title">${escapeHtml(event.title)}</p>
            <p class="event-card__meta">
                <i class="ti ti-calendar" aria-hidden="true"></i>${escapeHtml(dateStr)}
            </p>
            <p class="event-card__meta">
                <i class="ti ${locationIcon}" aria-hidden="true"></i>${escapeHtml(location)}
            </p>
            <div class="event-card__footer">
                <span class="event-card__participants">
                    <i class="ti ti-users" aria-hidden="true"></i>${escapeHtml(participantText)}
                </span>
                ${statusBadge}
            </div>
        </a>
    `;
}

/**
 * 参加状況バッジを生成
 */
function buildStatusBadge(event) {
    if (event.max_participants === null) {
        return '<span class="status-badge status-badge--open">参加受付中</span>';
    }
    const remaining = event.max_participants - event.participant_count;
    if (remaining <= 0) {
        return '<span class="status-badge status-badge--full">満員</span>';
    }
    if (remaining <= 5) {
        return '<span class="status-badge status-badge--few">残りわずか</span>';
    }
    return '<span class="status-badge status-badge--open">参加受付中</span>';
}

// ---- ページネーション ----

/**
 * ページネーションを描画し、ページ変更時のコールバックをバインド
 * @param {HTMLElement} el - ページネーションコンテナ
 * @param {number} total - 総件数
 * @param {number} currentPage - 現在ページ
 * @param {number} perPage - 1ページあたりの件数
 * @param {function} onPageChange - ページ変更時に呼ばれるコールバック(page: number)
 */
export function renderPagination(el, total, currentPage, perPage, onPageChange) {
    const totalPages = Math.ceil(total / perPage);

    if (totalPages <= 1) {
        el.innerHTML = '';
        return;
    }

    const prevDisabled = currentPage <= 1;
    const nextDisabled = currentPage >= totalPages;
    const pageNumbers  = buildPageRange(currentPage, totalPages);

    const pageButtons = pageNumbers.map(p => {
        if (p === '...') {
            return '<span class="pagination__ellipsis">…</span>';
        }
        const isActive = p === currentPage;
        return `
            <button class="pagination__btn ${isActive ? 'pagination__btn--active' : ''}"
                    data-page="${p}" ${isActive ? 'aria-current="page"' : ''}>
                ${p}
            </button>
        `;
    }).join('');

    el.innerHTML = `
        <button class="pagination__btn" data-page="${currentPage - 1}"
                ${prevDisabled ? 'disabled' : ''} aria-label="前のページ">
            <i class="ti ti-chevron-left" aria-hidden="true"></i>
        </button>
        ${pageButtons}
        <button class="pagination__btn" data-page="${currentPage + 1}"
                ${nextDisabled ? 'disabled' : ''} aria-label="次のページ">
            <i class="ti ti-chevron-right" aria-hidden="true"></i>
        </button>
    `;

    el.querySelectorAll('[data-page]').forEach(btn => {
        btn.addEventListener('click', () => {
            const page = parseInt(btn.dataset.page, 10);
            if (!isNaN(page) && page >= 1 && page <= totalPages) {
                onPageChange(page);
            }
        });
    });
}

/**
 * 表示するページ番号の配列を生成（省略記号含む）
 */
function buildPageRange(current, total) {
    if (total <= 7) {
        return Array.from({ length: total }, (_, i) => i + 1);
    }
    const pages = new Set(
        [1, total, current - 1, current, current + 1].filter(p => p >= 1 && p <= total)
    );
    const sorted = [...pages].sort((a, b) => a - b);

    const result = [];
    sorted.forEach((p, i) => {
        if (i > 0 && p - sorted[i - 1] > 1) result.push('...');
        result.push(p);
    });
    return result;
}

// ---- ユーティリティ ----

export function showLoading(gridEl, countEl, paginationEl) {
    gridEl.innerHTML       = '<p class="loading">読み込み中...</p>';
    countEl.textContent    = '';
    paginationEl.innerHTML = '';
}

export function showError(gridEl, message) {
    gridEl.innerHTML = `<p class="error">${escapeHtml(message)}</p>`;
}

export function formatDate(isoString) {
    return new Date(isoString).toLocaleString('ja-JP', {
        year:   'numeric',
        month:  'long',
        day:    'numeric',
        hour:   '2-digit',
        minute: '2-digit',
    });
}

// XSS対策: HTMLエスケープ
export function escapeHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}