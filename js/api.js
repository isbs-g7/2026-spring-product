/**
 * api.js — fetch共通ラッパー
 *
 * 使い方:
 *   const data = await apiGet('/api/events/search.php', { page: 1, keyword: 'test' });
 *   const data = await apiPost('/api/auth/login.php', { email, password });
 */

/**
 * GETリクエスト
 * @param {string} path  - APIのパス（例: '/api/events/search.php'）
 * @param {Object} params - クエリパラメータ（例: { page: 1, keyword: 'test' }）
 * @returns {Promise<any>}
 */
export async function apiGet(path, params = {}) {
    const url = new URL(path, location.origin);
    Object.entries(params).forEach(([key, value]) => {
        // 空文字・null・undefinedはクエリに含めない
        if (value !== '' && value !== null && value !== undefined) {
            url.searchParams.set(key, value);
        }
    });

    const res = await fetch(url.toString(), {
        method: 'GET',
        headers: { 'Accept': 'application/json' },
    });

    return handleResponse(res);
}

/**
 * POSTリクエスト（JSON body）
 * @param {string} path  - APIのパス
 * @param {Object} body  - リクエストボディ
 * @returns {Promise<any>}
 */
export async function apiPost(path, body = {}) {
    const csrfToken = getCsrfToken();

    const res = await fetch(path, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            // CSRFトークンをヘッダーに付与（AGENTS.md: CSRF対策必須）
            'X-CSRF-Token': csrfToken,
        },
        body: JSON.stringify(body),
    });

    return handleResponse(res);
}

/**
 * レスポンス共通処理
 * @param {Response} res
 * @returns {Promise<any>}
 */
async function handleResponse(res) {
    let data;
    try {
        data = await res.json();
    } catch {
        throw new ApiError('レスポンスの解析に失敗しました', res.status);
    }

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

/**
 * APIエラークラス
 */
export class ApiError extends Error {
    constructor(message, status) {
        super(message);
        this.name    = 'ApiError';
        this.status  = status;
    }
}