/**
 * 認証状態管理・共通ナビゲーション描画。
 * 各ページで <script src="/js/api.js"></script> の後に読み込む。
 *
 * 使い方:
 *   await initAuth();          // ログイン状態確認 + ナビ描画
 *   await initAuth(true);      // ログイン必須ページ（未ログインならloginへリダイレクト）
 *   currentUser                // ログイン中ユーザー情報（未ログインなら null）
 */

let currentUser = null;

async function initAuth(requireLogin = false) {
    try {
        const data = await api.get('/api/users/me.php');
        if (data) {
            currentUser = data.user;
            setCsrfToken(data.csrf_token);
        }
    } catch (e) {
        // 401 = 未ログイン。それ以外のエラーは無視してゲスト扱い。
    }

    if (requireLogin && !currentUser) {
        window.location.href = '/pages/login.html';
        return;
    }

    _renderNav();
    return currentUser;
}

function _renderNav() {
    const nav = document.getElementById('main-nav');
    if (!nav) return;

    if (currentUser) {
        nav.innerHTML = `
            <a href="/pages/index.html"
               class="text-gray-700 hover:text-indigo-600 font-medium transition-colors">
               イベント一覧
            </a>
            <a href="/pages/mypage.html"
               class="flex items-center gap-1 text-gray-700 hover:text-indigo-600 font-medium transition-colors">
               <span class="inline-block w-7 h-7 rounded-full bg-indigo-100 text-indigo-600 text-xs
                            flex items-center justify-center font-bold">
                 ${escapeHtml(currentUser.display_name.charAt(0))}
               </span>
               ${escapeHtml(currentUser.display_name)}
            </a>
            <button onclick="logout()"
                    class="text-sm text-gray-500 hover:text-red-500 transition-colors">
              ログアウト
            </button>
        `;
    } else {
        nav.innerHTML = `
            <a href="/pages/index.html"
               class="text-gray-700 hover:text-indigo-600 font-medium transition-colors">
               イベント一覧
            </a>
            <a href="/pages/login.html"
               class="text-gray-700 hover:text-indigo-600 font-medium transition-colors">
               ログイン
            </a>
            <a href="/pages/register.html"
               class="bg-indigo-600 text-white px-4 py-1.5 rounded-lg text-sm font-medium
                      hover:bg-indigo-700 transition-colors">
               新規登録
            </a>
        `;
    }
}

async function logout() {
    try {
        await api.post('/api/auth/logout.php', {});
    } catch (_) {
        // セッション切れなどでエラーになっても画面遷移する
    }
    window.location.href = '/pages/login.html';
}

function escapeHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}
