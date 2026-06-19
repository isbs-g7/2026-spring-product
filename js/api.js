/**
 * api.js — fetch共通ラッパー
 *
 * 使い方:
 *   const data = await apiGet('/api/events/search.php', { page: 1, keyword: 'test' });
 *   const data = await apiPost('/api/auth/login.php', { email, password });
 *   const data = await api.get('/api/users/me.php');
 */

let _csrfToken = null;

async function _fetchCsrfToken() {
    const res = await fetch('/api/auth/csrf.php', { credentials: 'same-origin' });
    if (!res.ok) throw new Error('CSRFトークンの取得に失敗しました');
    const data = await res.json();
    _csrfToken = data.csrf_token;
}

function setCsrfToken(token) {
    _csrfToken = token;
}

async function _request(url, options = {}) {
    const method = (options.method || 'GET').toUpperCase();

    if (method !== 'GET' && !_csrfToken) {
        await _fetchCsrfToken();
    }

    const headers = { 'Accept': 'application/json' };
    if (method !== 'GET') {
        headers['Content-Type'] = 'application/json';
    }
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
    return handleResponse(res);
}

/**
 * GETリクエスト
 * @param {string} path  - APIのパス（例: '/api/events/search.php'）
 * @param {Object} params - クエリパラメータ（例: { page: 1, keyword: 'test' }）
 * @returns {Promise<any>}
 */
async function apiGet(path, params = {}) {
    const url = new URL(path, location.origin);
    Object.entries(params).forEach(([key, value]) => {
        if (value !== '' && value !== null && value !== undefined) {
            url.searchParams.set(key, value);
        }
    });

    return _request(url.toString(), { method: 'GET' });
}

/**
 * POSTリクエスト（JSON body）
 * @param {string} path  - APIのパス
 * @param {Object} body  - リクエストボディ
 * @returns {Promise<any>}
 */
async function apiPost(path, body = {}) {
    return _request(path, { method: 'POST', body });
}

/**
 * レスポンス共通処理
 * @param {Response} res
 * @returns {Promise<any>}
 */
async function handleResponse(res) {
    const text = await res.text();
    const data = text ? JSON.parse(text) : null;

    if (!res.ok) {
        const message = data?.error ?? `HTTPエラー: ${res.status}`;
        throw new ApiError(message, res.status);
    }

    return data;
}

/**
 * CSRFトークンをmetaタグから取得
 * <meta name="csrf-token" content="..."> が必要
 */
function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

var ApiError = class ApiError extends Error {
    constructor(message, status) {
        super(message);
        this.name = 'ApiError';
        this.status = status;
    }
};

var api = {
    get: (url) => apiGet(url),
    post: (url, body) => apiPost(url, body),
};

if (typeof window !== 'undefined') {
    window.apiGet = apiGet;
    window.apiPost = apiPost;
    window.api = api;
    window.ApiError = ApiError;
    window.setCsrfToken = setCsrfToken;
}
