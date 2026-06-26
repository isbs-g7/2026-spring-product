// event-edit.js
// イベント詳細取得・編集・削除API連携

document.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    const eventId = urlParams.get('id');
    const form = document.getElementById('event-edit-form');
    const messageContainer = document.getElementById('message-container');
    const deleteBtn = document.getElementById('delete-btn');

    if (!eventId) {
        showMessage('イベントIDが指定されていません', true);
        form.style.display = 'none';
        return;
    }

    // 詳細取得
    fetch(`/api/events/detail.php?id=${encodeURIComponent(eventId)}`)
        .then(res => res.ok ? res.json() : Promise.reject(res))
        .then(event => {
            form.title.value = event.title;
            form.description.value = event.description;
            form.location.value = event.location;
            form.event_date.value = event.event_date;
            form.status.value = event.status;
        })
        .catch(() => {
            showMessage('イベント情報の取得に失敗しました', true);
            form.style.display = 'none';
        });

    // 編集送信
    form.addEventListener('submit', e => {
        e.preventDefault();
        const fd = new FormData(form);
        fd.append('id', eventId);
        fetch('/api/events/edit.php', {
            method: 'POST',
            body: fd
        })
        .then(res => res.ok ? res.json() : res.json().then(e => Promise.reject(e)))
        .then(() => showMessage('イベントを更新しました'))
        .catch(e => showMessage(e.error || '更新に失敗しました', true));
    });

    // 削除
    deleteBtn.addEventListener('click', () => {
        if (!confirm('本当に削除しますか？')) return;
        const fd = new FormData();
        fd.append('id', eventId);
        fetch('/api/events/delete.php', {
            method: 'POST',
            body: fd
        })
        .then(res => res.ok ? res.json() : res.json().then(e => Promise.reject(e)))
        .then(() => {
            showMessage('イベントを削除しました');
            setTimeout(() => location.href = 'index.html', 1200);
        })
        .catch(e => showMessage(e.error || '削除に失敗しました', true));
    });

    function showMessage(msg, isError = false) {
        messageContainer.textContent = msg;
        messageContainer.className = isError
            ? 'mb-4 p-4 rounded-md text-sm bg-red-100 text-red-700'
            : 'mb-4 p-4 rounded-md text-sm bg-green-100 text-green-700';
        messageContainer.style.display = 'block';
    }
});
