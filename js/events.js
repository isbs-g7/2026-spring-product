/**
 * events.js — イベント一覧・検索・ページネーション（イベント制御）
 * 描画ロジックは event-render.js に委譲する
 */
import {
    renderResultCount,
    renderEvents,
    renderPagination,
    showLoading,
    showError,
} from './event-render.js';

// --- 状態管理 ---
const state = {
    page:       1,
    keyword:    '',
    categoryId: '',
    from:       '',
    to:         '',
};

// --- DOM参照 ---
const inputKeyword    = document.getElementById('input-keyword');
const categoryButtons = document.getElementById('category-buttons');
const btnSearch       = document.getElementById('btn-search');
const btnToggleDate   = document.getElementById('btn-toggle-date');
const dateFilter      = document.getElementById('date-filter');
const iconChevron     = document.getElementById('icon-chevron');
const inputFrom       = document.getElementById('input-from');
const inputTo         = document.getElementById('input-to');
const resultCount     = document.getElementById('result-count');
const eventsGrid      = document.getElementById('events-grid');
const pagination      = document.getElementById('pagination');

// --- 初期化 ---
document.addEventListener('DOMContentLoaded', async () => {
    await loadCategories();
    await fetchAndRender();
    bindSearchEvents();
});

/**
 * カテゴリ一覧をAPIから取得してボタンとして描画
 * 「すべて」ボタンを先頭に追加し、初期選択状態にする
 */
async function loadCategories() {
    categoryButtons.appendChild(buildCategoryButton({ id: '', name: 'すべて' }, true));

    try {
        const data = await apiGet('/api/categories/search.php');
        data.categories.forEach(cat => {
            categoryButtons.appendChild(buildCategoryButton(cat, false));
        });
    } catch (e) {
        console.error('カテゴリの取得に失敗しました', e);
    }
}

/**
 * カテゴリボタン要素を生成して返す
 */
function buildCategoryButton(cat, isActive) {
    const btn = document.createElement('button');
    btn.type        = 'button';
    btn.dataset.id  = cat.id;
    btn.textContent = cat.name;
    btn.className   = isActive ? 'category-filter-btn category-filter-btn--active' : 'category-filter-btn';
    btn.setAttribute('aria-pressed', String(isActive));

    btn.addEventListener('click', () => {
        categoryButtons.querySelectorAll('.category-filter-btn').forEach(b => {
            b.classList.remove('category-filter-btn--active');
            b.setAttribute('aria-pressed', 'false');
        });
        btn.classList.add('category-filter-btn--active');
        btn.setAttribute('aria-pressed', 'true');

        state.categoryId = cat.id;
        state.page       = 1;
        fetchAndRender();
    });

    return btn;
}

/**
 * 検索フォームのイベントをバインド
 * キーワードは検索ボタン押下 or Enterキーで発火
 */
function bindSearchEvents() {
    btnSearch.addEventListener('click', () => {
        state.keyword = inputKeyword.value.trim();
        state.page    = 1;
        fetchAndRender();
    });

    inputKeyword.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            state.keyword = inputKeyword.value.trim();
            state.page    = 1;
            fetchAndRender();
        }
    });

    btnToggleDate.addEventListener('click', () => {
        const isOpen = !dateFilter.classList.contains('hidden');
        dateFilter.classList.toggle('hidden', isOpen);
        iconChevron.style.transform = isOpen ? '' : 'rotate(180deg)';
        btnToggleDate.setAttribute('aria-expanded', String(!isOpen));
    });

    inputFrom.addEventListener('change', () => {
        state.from = inputFrom.value;
        state.page = 1;
        fetchAndRender();
    });

    inputTo.addEventListener('change', () => {
        state.to   = inputTo.value;
        state.page = 1;
        fetchAndRender();
    });
}

/**
 * APIからイベントを取得して描画
 */
async function fetchAndRender() {
    showLoading(eventsGrid, resultCount, pagination);

    try {
        const data = await apiGet('/api/events/search.php', {
            page:        state.page,
            keyword:     state.keyword,
            category_id: state.categoryId,
            from:        state.from,
            to:          state.to,
        });

        renderResultCount(resultCount, data.total);
        renderEvents(eventsGrid, data.events);
        renderPagination(pagination, data.total, data.page, data.per_page, (page) => {
            state.page = page;
            fetchAndRender();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    } catch (e) {
        showError(eventsGrid, e instanceof ApiError ? e.message : 'イベントの取得に失敗しました');
    }
}