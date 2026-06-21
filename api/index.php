<?php
/**
 * REST API router.
 * Routes resolve via .htaccess rewrite: /api/{route} -> /api/index.php?route={route}
 * Direct access also works: /api/index.php?route={route}
 */
require __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$route  = trim((string)($_GET['route'] ?? ''), '/');
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

// JSON body support
$body = [];
if (in_array($method, ['POST','PUT','DELETE'], true)) {
    $raw = file_get_contents('php://input');
    if ($raw) {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) $body = $decoded;
    }
    $body = array_merge($_POST, $body);
}

/** Require an authenticated user, else 401. */
$requireUser = function () {
    if (!Auth::check()) {
        json_response(['ok' => false, 'error' => 'Authentication required'], 401);
    }
    return Auth::id();
};

/** For state-changing requests, verify CSRF (header or body). */
$checkCsrf = function () use ($body, $method) {
    if ($method === 'GET') return;
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($body['csrf_token'] ?? null);
    if (!Security::verifyCsrf($token)) {
        json_response(['ok' => false, 'error' => 'Invalid CSRF token'], 419);
    }
};

try {
    switch ($route) {

        // ---- GET /api/coins ----
        case 'coins': {
            $chain = $_GET['chain'] ?? '';
            $limit = min(100, max(1, (int)($_GET['limit'] ?? 50)));
            $sql   = 'SELECT id,name,symbol,chain,price,price_change_24h,market_cap,liquidity,volume,ai_score,risk_level FROM coins WHERE is_hidden = 0';
            $p     = [];
            if ($chain !== '') { $sql .= ' AND chain = ?'; $p[] = $chain; }
            $sql  .= ' ORDER BY volume DESC LIMIT ' . $limit;
            json_response(['ok' => true, 'coins' => Database::instance()->fetchAll($sql, $p)]);
            break;
        }

        // ---- GET /api/coin?id= ----
        case 'coin': {
            $id   = (int)($_GET['id'] ?? 0);
            $coin = Database::instance()->fetch('SELECT * FROM coins WHERE id = ? AND is_hidden = 0', [$id]);
            if (!$coin) json_response(['ok' => false, 'error' => 'Not found'], 404);
            $coin['analysis'] = RiskEngine::analyze($coin);
            json_response(['ok' => true, 'coin' => $coin]);
            break;
        }

        // ---- GET /api/search?q= ----
        case 'search': {
            $q = trim($_GET['q'] ?? '');
            if (strlen($q) < 2) json_response(['ok' => false, 'error' => 'Query too short'], 400);
            $results = [];
            foreach (Scanner::searchDex($q) as $hit) {
                if (($hit['liquidity'] ?? 0) >= 500) {
                    Scanner::enrichAndStore($hit);
                    $results[] = $hit;
                }
            }
            // Also include DB matches
            $like = "%$q%";
            $db   = Database::instance()->fetchAll(
                'SELECT id,name,symbol,chain,price,ai_score,risk_level FROM coins
                 WHERE is_hidden=0 AND (name LIKE ? OR symbol LIKE ? OR contract LIKE ?) LIMIT 25',
                [$like, $like, $like]
            );
            json_response(['ok' => true, 'live' => count($results), 'coins' => $db]);
            break;
        }

        // ---- POST /api/scan ----  (run the scanner)
        case 'scan': {
            $checkCsrf();
            if (!Security::rateLimit('api_scan', 3, 60)) {
                json_response(['ok' => false, 'error' => 'Rate limit: try again in a minute'], 429);
            }
            $count = Scanner::scanTrending();
            json_response(['ok' => true, 'scanned' => $count, 'last_scan' => Settings::get('last_scan_at')]);
            break;
        }

        // ---- POST /api/watchlist  { coin_id } ---- toggles
        case 'watchlist': {
            $uid = $requireUser();
            $checkCsrf();
            $coinId = (int)($body['coin_id'] ?? 0);
            $db     = Database::instance();
            if (!$db->scalar('SELECT COUNT(*) FROM coins WHERE id = ?', [$coinId])) {
                json_response(['ok' => false, 'error' => 'Coin not found'], 404);
            }
            $exists = $db->scalar('SELECT id FROM watchlist WHERE user_id = ? AND coin_id = ?', [$uid, $coinId]);
            if ($exists) {
                $db->run('DELETE FROM watchlist WHERE user_id = ? AND coin_id = ?', [$uid, $coinId]);
                json_response(['ok' => true, 'watching' => false]);
            } else {
                $db->run('INSERT INTO watchlist (user_id, coin_id) VALUES (?, ?)', [$uid, $coinId]);
                json_response(['ok' => true, 'watching' => true]);
            }
            break;
        }

        // ---- POST /api/follow  { wallet } ---- toggles
        case 'follow': {
            $uid = $requireUser();
            $checkCsrf();
            $wallet = trim($body['wallet'] ?? '');
            if ($wallet === '') json_response(['ok' => false, 'error' => 'Wallet required'], 400);
            $db = Database::instance();
            $exists = $db->scalar('SELECT id FROM wallet_follows WHERE user_id = ? AND wallet = ?', [$uid, $wallet]);
            if ($exists) {
                $db->run('DELETE FROM wallet_follows WHERE user_id = ? AND wallet = ?', [$uid, $wallet]);
                json_response(['ok' => true, 'following' => false]);
            } else {
                $db->run('INSERT INTO wallet_follows (user_id, wallet) VALUES (?, ?)', [$uid, $wallet]);
                json_response(['ok' => true, 'following' => true]);
            }
            break;
        }

        // ---- GET /api/smart-money ----
        case 'smart-money': {
            $rows = Database::instance()->fetchAll('SELECT * FROM smart_money ORDER BY tx_date DESC LIMIT 30');
            json_response(['ok' => true, 'transactions' => $rows]);
            break;
        }

        // ---- GET /api/stats ----
        case 'stats': {
            $db = Database::instance();
            json_response(['ok' => true, 'stats' => [
                'coins'     => (int)$db->scalar('SELECT COUNT(*) FROM coins WHERE is_hidden=0'),
                'new_today' => (int)$db->scalar('SELECT COUNT(*) FROM coins WHERE created_at >= CURDATE()'),
                'ai_buys'   => (int)$db->scalar('SELECT COUNT(*) FROM coins WHERE ai_score >= 70'),
                'volume'    => (float)$db->scalar('SELECT COALESCE(SUM(volume),0) FROM coins WHERE is_hidden=0'),
                'last_scan' => Settings::get('last_scan_at'),
            ]]);
            break;
        }

        // ---- GET /api/csrf ----
        case 'csrf': {
            json_response(['ok' => true, 'token' => Security::csrfToken()]);
            break;
        }

        default:
            json_response(['ok' => false, 'error' => 'Unknown route', 'route' => $route], 404);
    }
} catch (Throwable $e) {
    if (config('app.debug')) {
        json_response(['ok' => false, 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()], 500);
    }
    json_response(['ok' => false, 'error' => 'Server error'], 500);
}
