/**
 * APIリクエスト共通ラッパー。
 * CSRFトークンの自動付与・JSONパース・エラー処理を担う。
 * 使い方: api.get('/api/users/me.php')
 *          api.post('/api/auth/login.php', { email, password })
 */

let _csrfToken = null;

async function _fetchCsrfToken() {
    const res = await fetch('/api/auth/csrf.php');
    if (!res.ok) throw new Error('CSRFトークンの取得に失敗しました');
    const data = await res.json();
    _csrfToken = data.csrf_token;
}

// me.php のレスポンスからトークンをセット（ログイン済み時は2回取得不要）
function setCsrfToken(token) {
    _csrfToken = token;
}

async function _request(url, options = {}) {
    const method = (options.method || 'GET').toUpperCase();

    if (method !== 'GET' && !_csrfToken) {
        await _fetchCsrfToken();
    }

    const headers = { 'Content-Type': 'application/json' };
    if (method !== 'GET' && _csrfToken) {
        headers['X-CSRF-Token'] = _csrfToken;
    }

    const fetchOptions = {
        method,
        headers,
        credentials: 'same-origin',
    };
    if (options.body !== undefined) {
        fetchOptions.body = JSON.stringify(options.body);
    }

    const res = await fetch(url, fetchOptions);

    // 204 No Content など body が空の場合の対処
    const text = await res.text();
    const data = text ? JSON.parse(text) : null;

    if (!res.ok) {
        const message = data?.error || `エラー (${res.status})`;
        throw Object.assign(new Error(message), { status: res.status, data });
    }

    return data;
}

const api = {
    get:  (url)         => _request(url, { method: 'GET' }),
    post: (url, body)   => _request(url, { method: 'POST', body }),
};
