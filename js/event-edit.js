/**
 * js/event-edit.js
 * イベント編集ページのフロントエンドロジック
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

    // 主催者確認
    if (currentUser && currentUser.id !== currentEvent.organizer_id) {
      showError('このイベントを編集する権限がありません');
      return;
    }

    populateForm(currentEvent);
    initializeForm();
  } catch (error) {
    showError(error.message);
  }
}

/**
 * フォームにイベント情報を入力
 */
function populateForm(event) {
  document.getElementById('loading').classList.add('hidden');
  document.getElementById('edit-content').classList.remove('hidden');

  document.getElementById('title').value = event.title;
  document.getElementById('category_id').value = event.category_id;
  document.getElementById('location').value = event.location;
  document.getElementById('is_online').checked = event.is_online;
  document.getElementById('description').value = event.description;

  if (event.max_participants !== null) {
    document.getElementById('max_participants').value = event.max_participants;
  }

  if (event.image_url) {
    document.getElementById('image_url').value = event.image_url;
  }

  // datetime-local 形式に変換
  const eventDate = new Date(event.event_date);
  document.getElementById('event_date').value = formatDateTimeLocal(eventDate);

  if (event.end_date) {
    const endDate = new Date(event.end_date);
    document.getElementById('end_date').value = formatDateTimeLocal(endDate);
  }

  // タイトル文字数カウンター更新
  document.getElementById('title-counter').textContent = event.title.length;
}

/**
 * フォームの初期化（イベントリスナーの設定）
 */
async function initializeForm() {
  // カテゴリ一覧を取得
  await fetchCategories();

  // フォーム送信処理
  document.getElementById('event-edit-form').addEventListener('submit', handleFormSubmit);

  // キャンセルボタン
  document.getElementById('cancel-btn').href = `/pages/event-detail.html?id=${encodeURIComponent(eventId)}`;

  // タイトル文字数カウンター
  document.getElementById('title').addEventListener('input', (e) => {
    document.getElementById('title-counter').textContent = e.target.value.length;
  });
}

/**
 * フォーム送信処理
 */
async function handleFormSubmit(e) {
  e.preventDefault();
  showMessage('', 'clear');

  const formData = new FormData(e.target);
  const payload = {
    id: eventId,
    title: formData.get('title'),
    category_id: parseInt(formData.get('category_id'), 10),
    event_date: formData.get('event_date'),
    end_date: formData.get('end_date') || null,
    location: formData.get('location'),
    is_online: e.target.is_online.checked,
    max_participants: formData.get('max_participants') ? parseInt(formData.get('max_participants'), 10) : null,
    image_url: formData.get('image_url') || null,
    description: formData.get('description')
  };

  // バリデーション
  if (payload.end_date && new Date(payload.end_date) <= new Date(payload.event_date)) {
    showMessage('終了日時は開催日時よりも後の時刻を指定してください。', 'error');
    return;
  }

  const submitBtn = document.getElementById('submit-btn');
  submitBtn.disabled = true;
  submitBtn.textContent = '更新中...';

  try {
    await api.post('/api/events/update.php', payload);
    showMessage('イベントを更新しました！詳細ページへ移動します...', 'success');
    setTimeout(() => {
      window.location.href = `/pages/event-detail.html?id=${encodeURIComponent(eventId)}`;
    }, 1500);
  } catch (error) {
    showMessage(error.message, 'error');
    submitBtn.disabled = false;
    submitBtn.textContent = '更新する';
  }
}

/**
 * カテゴリ一覧を取得してセレクトボックスに入力
 */
async function fetchCategories() {
  try {
    const response = await fetch('/api/categories/index.php');
    if (!response.ok) {
      throw new Error('カテゴリの取得に失敗しました');
    }
    const categories = await response.json();

    const select = document.getElementById('category_id');
    // 既存のオプションを削除（最初のオプションは残す）
    select.innerHTML = '<option value="">カテゴリを選択してください</option>';

    categories.forEach(category => {
      const option = document.createElement('option');
      option.value = category.id;
      option.textContent = category.name;
      select.appendChild(option);
    });

    // 現在のカテゴリを選択
    if (currentEvent && currentEvent.category_id) {
      select.value = currentEvent.category_id;
    }
  } catch (error) {
    console.error('Category fetch error:', error);
  }
}

/**
 * メッセージ表示
 */
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

/**
 * エラー表示
 */
function showError(message) {
  const errorContainer = document.getElementById('error-container');
  errorContainer.textContent = message;
  errorContainer.classList.remove('hidden');
  document.getElementById('loading').classList.add('hidden');
}

/**
 * DateオブジェクトをDatetimeLocal形式に変換
 */
function formatDateTimeLocal(date) {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  const hours = String(date.getHours()).padStart(2, '0');
  const minutes = String(date.getMinutes()).padStart(2, '0');
  return `${year}-${month}-${day}T${hours}:${minutes}`;
}
