/**
 * js/event-detail.js
 * イベント詳細ページのフロントエンドロジック（表示・編集・削除）
 */

let currentEvent = null;
let currentUser = null;
let eventId = null;

document.addEventListener('DOMContentLoaded', async () => {
  // URLからイベントIDを取得
  const params = new URLSearchParams(window.location.search);
  eventId = params.get('id');

  if (!eventId) {
    showError('イベントIDが指定されていません');
    return;
  }

  // 認証情報とイベント詳細を読み込み
  await Promise.all([
    initAuth(),
    loadEventDetail(eventId)
  ]);
});

/**
 * イベント詳細を読み込み
 */
async function loadEventDetail(id) {
  try {
    const response = await fetch(`/api/events/detail.php?id=${encodeURIComponent(id)}`);
    if (!response.ok) {
      const data = await response.json();
      throw new Error(data.error || 'イベントの読み込みに失敗しました');
    }

    currentEvent = await response.json();
    renderEventDetail(currentEvent);
  } catch (error) {
    showError(error.message);
  }
}

/**
 * イベント詳細を表示
 */
function renderEventDetail(event) {
  // ローディング非表示、コンテンツ表示
  document.getElementById('loading').classList.add('hidden');
  document.getElementById('event-content').classList.remove('hidden');

  // タイトル
  document.getElementById('event-title').textContent = escapeHtml(event.title);

  // カテゴリバッジ
  const categoryEl = document.getElementById('event-category');
  categoryEl.textContent = escapeHtml(event.category_name);
  categoryEl.style.backgroundColor = event.category_color;

  // 開催日時
  const eventDate = new Date(event.event_date);
  document.getElementById('event-date').textContent = eventDate.toLocaleString('ja-JP', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  });

  // 終了日時
  const endDateEl = document.getElementById('event-end-date');
  if (event.end_date) {
    const endDate = new Date(event.end_date);
    endDateEl.textContent = endDate.toLocaleString('ja-JP', {
      year: 'numeric',
      month: 'long',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });
  } else {
    endDateEl.textContent = '—';
  }

  // オンライン/オフライン
  document.getElementById('event-online').textContent = event.is_online ? 'オンライン開催' : '対面開催';

  // 開催場所
  document.getElementById('event-location').textContent = escapeHtml(event.location);

  // 定員
  const capacityEl = document.getElementById('event-capacity');
  if (event.max_participants !== null) {
    capacityEl.textContent = `${event.max_participants}名`;
  } else {
    capacityEl.textContent = '制限なし';
  }

  // 説明
  document.getElementById('event-description').textContent = escapeHtml(event.description);

  // 主催者情報
  document.getElementById('organizer-name').textContent = escapeHtml(event.organizer_name);
  document.getElementById('organizer-email').textContent = escapeHtml(event.organizer_email);
  const avatar = document.getElementById('organizer-avatar');
  avatar.textContent = event.organizer_name.charAt(0).toUpperCase();

  // 主催者のみ編集・削除ボタンを表示
  if (currentUser && currentUser.id === event.organizer_id) {
    const actionButtons = document.getElementById('action-buttons');
    actionButtons.classList.remove('hidden');

    // 編集ボタン：edit ページへリダイレクト
    document.getElementById('edit-btn').href = `/pages/event-edit.html?id=${encodeURIComponent(event.id)}`;

    // 削除ボタン
    document.getElementById('delete-btn').addEventListener('click', openDeleteModal);
  }

  // 削除モーダルの処理
  document.getElementById('modal-cancel').addEventListener('click', closeDeleteModal);
  document.getElementById('modal-confirm').addEventListener('click', deleteEvent);
}

/**
 * 削除モーダルを開く
 */
function openDeleteModal() {
  document.getElementById('delete-modal').classList.remove('hidden');
}

/**
 * 削除モーダルを閉じる
 */
function closeDeleteModal() {
  document.getElementById('delete-modal').classList.add('hidden');
}

/**
 * イベントを削除
 */
async function deleteEvent() {
  const btn = document.getElementById('modal-confirm');
  btn.disabled = true;
  btn.textContent = '削除中...';

  try {
    await api.post(`/api/events/delete.php`, {
      id: currentEvent.id
    });

    // 削除成功：イベント一覧に遷移
    alert('イベントを削除しました');
    window.location.href = '/pages/index.html';
  } catch (error) {
    showError('削除に失敗しました: ' + error.message);
    btn.disabled = false;
    btn.textContent = '削除する';
    closeDeleteModal();
  }
}

/**
 * XSS対策：HTMLエスケープ
 */
function escapeHtml(str) {
  return String(str ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

/**
 * エラー表示
 */
function showError(message) {
  const errorContainer = document.getElementById('error-container');
  errorContainer.textContent = message;
  errorContainer.classList.remove('hidden');
  document.getElementById('loading').classList.add('hidden');
}
