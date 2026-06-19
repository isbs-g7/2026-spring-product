const categorySelect = document.getElementById('category');
const eventsContainer = document.getElementById('eventsContainer');
const pagination = document.getElementById('pagination');
const searchBtn = document.getElementById('searchBtn');

let currentPage = 1;

document.addEventListener('DOMContentLoaded', async () => {
    await loadCategories();
    await loadEvents();
});

searchBtn.addEventListener('click', () => {
    currentPage = 1;
    loadEvents();
});

async function loadCategories() {
    try {
        const response = await fetch('../api/categories/index.php');
        const categories = await response.json();

        categories.forEach(category => {
            const option = document.createElement('option');

            option.value = category.id;
            option.textContent = category.name;

            categorySelect.appendChild(option);
        });

    } catch (error) {
        console.error(error);
    }
}

async function loadEvents(page = 1) {
    try {
        const keyword = document.getElementById('keyword').value;
        const category = document.getElementById('category').value;
        const from = document.getElementById('from').value;
        const to = document.getElementById('to').value;

        const params = new URLSearchParams({
            page
        });

        if (keyword) {
            params.append('keyword', keyword);
        }

        if (category) {
            params.append('category_id', category);
        }

        if (from) {
            params.append('from', from);
        }

        if (to) {
            params.append('to', to);
        }

        const response = await fetch(
            `../api/events/index.php?${params.toString()}`
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
                    ${event.title}
                </h2>

                <span
                    class="text-white text-xs px-2 py-1 rounded"
                    style="background:${event.category_color}"
                >
                    ${event.category_name}
                </span>
            </div>

            <p class="text-sm text-gray-600 mb-2">
                ${new Date(event.event_date).toLocaleString('ja-JP')}
            </p>

            <p class="text-sm text-gray-600 mb-2">
                📍 ${event.location}
            </p>

            <p class="text-sm text-gray-600 mb-3">
                定員:
                ${event.max_participants ?? '制限なし'}
            </p>

            <p class="text-gray-700 flex-grow">
                ${description}
            </p>

            <a
                href="event-detail.html?id=${event.id}"
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
                ? 'px-3 py-2 bg-blue-600 text-white rounded'
                : 'px-3 py-2 bg-white border rounded';

        button.addEventListener('click', () => {
            currentPage = page;
            loadEvents(page);
        });

        pagination.appendChild(button);
    }
}