/**
 * js/events.js
 * イベント関連のフロントエンドロジック（一覧表示・作成フォーム）を担当するスクリプト。
 */

const eventsContainer = document.getElementById('events-container');
const pagination = document.getElementById('pagination');

let currentPage = 1;

document.addEventListener('DOMContentLoaded', async () => {
    if (eventsContainer) {
        await loadEvents();
    }

    const createForm = document.getElementById('event-create-form');
    if (createForm) {
        initEventCreateForm(createForm);
    }
});

// ---- イベント一覧 ----

function escapeHtml(str) {
    return String(str ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

async function loadEvents(page = 1) {
    try {
        const params = new URLSearchParams({ page });

        const response = await fetch(
            `/api/events/index.php?${params.toString()}`
        );

        const data = await response.json();

        renderEvents(data.events);
        renderPagination(data.total_pages, page);

    } catch (error) {
        console.error(error);
    }
}

function renderEvents(events) {
    eventsContainer.innerHTML = '';

    if (events.length === 0) {
        eventsContainer.innerHTML = `
            <div class="col-span-full text-center text-gray-500">
                イベントが見つかりません
            </div>
        `;
        return;
    }

    events.forEach(event => {
        const card = document.createElement('div');

        card.className =
            'bg-white rounded-lg shadow p-4 flex flex-col';

        const description =
            event.description.length > 100
                ? event.description.slice(0, 100) + '...'
                : event.description;

        card.innerHTML = `
            <div class="flex justify-between items-center mb-2">
                <h2 class="font-bold text-lg">
                    ${escapeHtml(event.title)}
                </h2>

                <span
                    class="text-white text-xs px-2 py-1 rounded"
                    style="background:${escapeHtml(event.category_color)}"
                >
                    ${escapeHtml(event.category_name)}
                </span>
            </div>

            <p class="text-sm text-gray-600 mb-2">
                ${new Date(event.event_date).toLocaleString('ja-JP')}
            </p>

            <p class="text-sm text-gray-600 mb-2">
                📍 ${escapeHtml(event.location)}
            </p>

            <p class="text-sm text-gray-600 mb-3">
                定員:
                ${event.max_participants != null ? escapeHtml(event.max_participants) : '制限なし'}
            </p>

            <p class="text-gray-700 flex-grow">
                ${escapeHtml(description)}
            </p>

            <a
                href="/pages/event-detail.html?id=${encodeURIComponent(event.id)}"
                class="mt-4 text-blue-600 hover:underline"
            >
                詳細を見る
            </a>
        `;

        eventsContainer.appendChild(card);
    });
}

function renderPagination(totalPages, current) {
    pagination.innerHTML = '';

    if (totalPages <= 1) {
        return;
    }

    for (let page = 1; page <= totalPages; page++) {
        const button = document.createElement('button');

        button.textContent = page;

        button.className =
            page === current
                ? 'px-3 py-2 bg-indigo-600 text-white rounded'
                : 'px-3 py-2 bg-white border rounded';

        button.addEventListener('click', () => {
            currentPage = page;
            loadEvents(page);
        });

        pagination.appendChild(button);
    }
}

// ---- イベント作成フォーム ----

function initEventCreateForm(form) {
    const categorySelect = document.getElementById('category_id');
    fetchCategories(categorySelect);

    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        showMessage('', 'clear');

        const formData = new FormData(form);
        const payload = {
            title: formData.get('title'),
            category_id: parseInt(formData.get('category_id'), 10),
            event_date: formData.get('event_date'),
            end_date: formData.get('end_date') || null,
            location: formData.get('location'),
            is_online: form.is_online.checked,
            max_participants: formData.get('max_participants') ? parseInt(formData.get('max_participants'), 10) : null,
            image_url: formData.get('image_url') || null,
            description: formData.get('description')
        };

        if (payload.end_date && new Date(payload.end_date) <= new Date(payload.event_date)) {
            showMessage('終了日時は開催日時よりも後の時刻を指定してください。', 'error');
            return;
        }

        const submitBtn = document.getElementById('submit-btn');
        if (submitBtn) submitBtn.disabled = true;

        try {
            await api.post('../api/events/create.php', payload);

            showMessage('イベントを作成しました！一覧画面へ移動します...', 'success');
            setTimeout(() => {
                window.location.href = 'index.html';
            }, 1500);

        } catch (error) {
            showMessage(error.message, 'error');
            if (submitBtn) submitBtn.disabled = false;
        }
    });
}

async function fetchCategories(selectElement) {
    try {
        const response = await fetch('../api/categories/index.php');
        if (!response.ok) {
            throw new Error('カテゴリの取得に失敗しました');
        }
        const categories = await response.json();

        categories.forEach(category => {
            const option = document.createElement('option');
            option.value = category.id;
            option.textContent = category.name;
            selectElement.appendChild(option);
        });
    } catch (error) {
        console.error('Category fetch error:', error);
    }
}

function showMessage(msg, type) {
    const container = document.getElementById('message-container');
    if (!container) return;

    if (type === 'clear') {
        container.className = 'hidden mb-4 p-4 rounded-md text-sm';
        return;
    }

    container.textContent = msg;
    container.classList.remove('hidden');

    if (type === 'success') {
        container.className = 'mb-4 p-4 rounded-md text-sm bg-green-50 text-green-800 border border-green-200';
    } else if (type === 'error') {
        container.className = 'mb-4 p-4 rounded-md text-sm bg-red-50 text-red-800 border border-red-200';
    }
}

// タイトル文字カウンター
const titleInput = document.getElementById('title');
const titleCounter = document.getElementById('title-counter');
if (titleInput && titleCounter) {
    titleInput.addEventListener('input', () => {
        titleCounter.textContent = titleInput.value.length;
    });
}
