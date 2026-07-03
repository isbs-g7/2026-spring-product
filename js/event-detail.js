/**
 * event-detail.js — イベント詳細ページの表示制御
 */
import { escapeHtml, formatDate } from './event-render.js';
import { renderParticipationButton, bindParticipationActions } from './participations.js';

const loadingEl              = document.getElementById('detail-loading');
const errorEl                = document.getElementById('detail-error');
const contentEl              = document.getElementById('detail-content');
const imageEl                = document.getElementById('detail-image');
const categoryEl             = document.getElementById('detail-category');
const statusBadgeEl          = document.getElementById('detail-status-badge');
const titleEl                = document.getElementById('detail-title');
const dateEl                 = document.getElementById('detail-date');
const locationEl             = document.getElementById('detail-location');
const locationIconEl         = document.getElementById('detail-location-icon');
const participantsEl         = document.getElementById('detail-participants');
const organizerEl            = document.getElementById('detail-organizer');
const descriptionEl          = document.getElementById('detail-description');
const participationActionEl  = document.getElementById('detail-participation-action');
const participantsSectionEl  = document.getElementById('detail-participants-section');
const participantsListEl     = document.getElementById('detail-participants-list');

let currentUser = null;

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

function renderEvent(event) {
    if (event.image_url) {
        imageEl.src = event.image_url;
        imageEl.alt = event.title;
        imageEl.classList.remove('hidden');
    }

    categoryEl.textContent = event.category_name;
    categoryEl.style.background = `${event.category_color}22`;
    categoryEl.style.color = event.category_color;

    statusBadgeEl.innerHTML = buildStatusBadge(event);

    titleEl.textContent = event.title;
    dateEl.textContent = formatDate(event.event_date)
        + (event.end_date ? ` 〜 ${formatDate(event.end_date)}` : '');

    locationEl.textContent = event.is_online ? 'オンライン' : event.location;
    locationIconEl.className = `ti ${event.is_online ? 'ti-video' : 'ti-map-pin'}`;

    participantsEl.textContent = event.max_participants !== null
        ? `${event.participant_count} / ${event.max_participants}名`
        : `${event.participant_count}名参加中`;

    organizerEl.textContent = event.organizer_name;
    descriptionEl.textContent = event.description;

    participationActionEl.innerHTML = renderParticipationButton(event, currentUser);

    if (event.is_organizer) {
        loadParticipants(event.id);
    } else {
        participantsSectionEl.classList.add('hidden');
    }

    loadingEl.classList.add('hidden');
    contentEl.classList.remove('hidden');
}

async function loadParticipants(eventId) {
    try {
        const data = await apiGet('/api/events/participants.php', { id: eventId });
        participantsListEl.innerHTML = data.participants.length
            ? data.participants.map(name => `<li>${escapeHtml(name)}</li>`).join('')
            : '<li class="text-gray-500 list-none">まだ参加者はいません</li>';
        participantsSectionEl.classList.remove('hidden');
    } catch (e) {
        participantsSectionEl.classList.add('hidden');
    }
}

function renderError(message) {
    loadingEl.classList.add('hidden');
    errorEl.textContent = message;
    errorEl.classList.remove('hidden');
}

async function init() {
    currentUser = await initAuth();

    const eventId = new URLSearchParams(location.search).get('id');
    if (!eventId) {
        renderError('イベントIDが指定されていません');
        return;
    }

    const fetchAndRenderDetail = async () => {
        const data = await apiGet('/api/events/detail.php', { id: eventId });
        renderEvent(data.event);
    };

    try {
        await fetchAndRenderDetail();
    } catch (e) {
        renderError(e instanceof ApiError ? e.message : 'イベントの取得に失敗しました');
        return;
    }

    bindParticipationActions(participationActionEl, fetchAndRenderDetail);
}

document.addEventListener('DOMContentLoaded', init);
