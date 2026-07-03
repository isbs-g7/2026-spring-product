/**
 * participations.js — 参加登録・キャンセルのAPI呼び出しとボタン描画
 */

export async function joinEvent(eventId) {
    return apiPost('/api/participations/join.php', { event_id: eventId });
}

export async function cancelParticipation(eventId) {
    return apiPost('/api/participations/cancel.php', { event_id: eventId });
}

/**
 * イベントの状態・ログイン状態に応じた参加ボタン（またはその代替表示）のHTMLを返す
 */
export function renderParticipationButton(event, currentUser) {
    if (!currentUser) {
        return '<a href="/pages/login.html" class="inline-block text-sm font-medium text-indigo-600 hover:text-indigo-700">ログインして参加する</a>';
    }

    if (event.is_organizer) {
        return '';
    }

    const hasStarted = new Date(event.event_date).getTime() <= Date.now();

    if (event.is_participating) {
        if (hasStarted) {
            return '<span class="inline-block text-sm text-gray-500">参加済み</span>';
        }
        return `<button type="button" class="bg-white border border-red-300 text-red-600 hover:bg-red-50 text-sm font-medium px-4 py-2 rounded-lg transition-colors" data-participation-action="cancel" data-event-id="${event.id}">参加をキャンセル</button>`;
    }

    const isFull = event.max_participants !== null && event.participant_count >= event.max_participants;

    if (isFull) {
        return '<button type="button" class="bg-gray-100 text-gray-400 text-sm font-medium px-4 py-2 rounded-lg cursor-not-allowed" disabled>満員</button>';
    }

    if (hasStarted) {
        return '<button type="button" class="bg-gray-100 text-gray-400 text-sm font-medium px-4 py-2 rounded-lg cursor-not-allowed" disabled>受付終了</button>';
    }

    return `<button type="button" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors" data-participation-action="join" data-event-id="${event.id}">参加する</button>`;
}

/**
 * コンテナ内の参加ボタンのクリックをイベント委譲でハンドリングする。
 * カード全体がリンクになっている場合に備え、参加ボタン押下時はリンク遷移を止める。
 * @param {HTMLElement} containerEl
 * @param {() => (void|Promise<void>)} onSuccess - 参加登録/キャンセル成功時に呼ばれるコールバック
 */
export function bindParticipationActions(containerEl, onSuccess) {
    containerEl.addEventListener('click', async (e) => {
        const btn = e.target.closest('[data-participation-action]');
        if (!btn) return;

        e.preventDefault();
        e.stopPropagation();

        const action  = btn.dataset.participationAction;
        const eventId = btn.dataset.eventId;

        btn.disabled = true;

        try {
            if (action === 'join') {
                await joinEvent(eventId);
            } else if (action === 'cancel') {
                await cancelParticipation(eventId);
            }
            await onSuccess();
        } catch (err) {
            alert(err instanceof ApiError ? err.message : '操作に失敗しました');
            btn.disabled = false;
        }
    });
}
