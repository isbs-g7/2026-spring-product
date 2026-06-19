/**
 * js/events.js
 * * イベント関連のフロントエンドロジックを担当するスクリプト。
 * [AGENTS.md 準拠] 単一責任、読みやすさ最優先、エラーハンドリング。
 * 他メンバー担当の「④イベント一覧」「⑤イベント詳細」もこのファイルを利用するため、
 * イベント作成に関するロジックのみを関数・イベントリスナーとして独立させて実装。
 */

document.addEventListener('DOMContentLoaded', () => {
    const createForm = document.getElementById('event-create-form');
    
    // イベント作成画面（event-new.html）が存在する場合のみロジックを実行する
    if (createForm) {
        initEventCreateForm(createForm);
    }
});

/**
 * イベント作成フォームの初期化処理
 * @param {HTMLFormElement} form 
 */
function initEventCreateForm(form) {
    const categorySelect = document.getElementById('category_id');

    // 1. 起動時にカテゴリ一覧をAPIから取得してセレクトボックスを埋める
    // [理由] 設計書の「⑥カテゴリ一覧 (api/categories/index.php)」と連携するため
    fetchCategories(categorySelect);

    // 2. フォーム送信時のイベントハンドラ
    form.addEventListener('submit', async (e) => {
        e.preventDefault(); // 通常のページ遷移（フォーム送信）を防止

        // メッセージ表示をリセット
        showMessage('', 'clear');

        // フォームデータから送信用のオブジェクトを構築
        const formData = new FormData(form);
        const payload = {
            title: formData.get('title'),
            category_id: parseInt(formData.get('category_id'), 10),
            event_date: formData.get('event_date'),
            end_date: formData.get('end_date') || null, // 空欄ならnull
            location: formData.get('location'),
            is_online: form.is_online.checked, // チェックボックスの真偽値
            max_participants: formData.get('max_participants') ? parseInt(formData.get('max_participants'), 10) : null,
            image_url: formData.get('image_url') || null,
            description: formData.get('description')
        };

        // フロント側での簡易的な論理チェック（開始日時と終了日時の関係）
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

/**
 * カテゴリマスタをAPIから動的に取得し、セレクトボックスを生成する関数
 * @param {HTMLSelectElement} selectElement 
 */
async function fetchCategories(selectElement) {
    try {
        const response = await fetch('../api/categories/index.php');
        if (!response.ok) {
            throw new Error('カテゴリの取得に失敗しました');
        }
        const categories = await response.json();

        // 取得したカテゴリデータをoptionタグにして追加
        categories.forEach(category => {
            const option = document.createElement('option');
            option.value = category.id;
            option.textContent = category.name;
            selectElement.appendChild(option);
        });
    } catch (error) {
        console.error('Category fetch error:', error);
        // カテゴリ取得が失敗しても画面操作が完全に崩壊しないよう、仮の選択肢を出すかログに留める
        // チーム開発の進捗（api/categories/index.php がまだ未完成の場合など）を考慮
    }
}

/**
 * 画面上のメッセージエリアにテキストを表示するヘルパー関数
 * @param {string} msg 
 * @param {'success' | 'error' | 'clear'} type 
 */
function showMessage(msg, type) {
    const container = document.getElementById('message-container');
    if (!container) return;

    if (type === 'clear') {
        container.classList.add('hidden');
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