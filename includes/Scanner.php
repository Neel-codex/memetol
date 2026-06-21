<?php
/**
 * Scanner - fetches meme coin data from free public APIs
 * (DexScreener, GeckoTerminal, CoinGecko) and upserts into the DB.
 */
class Scanner
{
    /** Chains we track -> DexScreener chainId mapping. */
    public const CHAINS = [
        'ethereum' => 'Ethereum',
        'bsc'      => 'BNB Chain',
        'solana'   => 'Solana',
        'base'     => 'Base',
        'polygon'  => 'Polygon',
    ];

    /** Whether a given API is enabled in admin settings. */
    public static function apiEnabled(string $name): bool
    {
        $row = Database::instance()->fetch(
            'SELECT enabled FROM api_settings WHERE name = ? LIMIT 1',
            [$name]
        );
        return $row ? (bool) $row['enabled'] : true;
    }

    public static function apiBaseUrl(string $name, string $fallback): string
    {
        $row = Database::instance()->fetch(
            'SELECT base_url FROM api_settings WHERE name = ? LIMIT 1',
            [$name]
        );
        return $row && !empty($row['base_url']) ? $row['base_url'] : $fallback;
    }

    /** Search DexScreener for a query (contract / name / symbol). */
    public static function searchDex(string $query): array
    {
        if (!self::apiEnabled('dexscreener')) {
            return [];
        }
        $base = self::apiBaseUrl('dexscreener', 'https://api.dexscreener.com/latest/dex');
        $url  = rtrim($base, '/') . '/search?q=' . urlencode($query);
        $data = Http::getJson($url);
        if (!$data || empty($data['pairs'])) {
            return [];
        }
        $out = [];
        foreach ($data['pairs'] as $pair) {
            $coin = self::mapDexPair($pair);
            if ($coin) {
                $out[] = $coin;
            }
        }
        return $out;
    }

    /** Map a DexScreener pair object to our coin schema. */
    public static function mapDexPair(array $p): ?array
    {
        if (empty($p['baseToken']['address'])) {
            return null;
        }
        $txns24 = $p['txns']['h24'] ?? ['buys' => 0, 'sells' => 0];
        $buys   = (int) ($txns24['buys'] ?? 0);
        $sells  = (int) ($txns24['sells'] ?? 0);
        $ageMs  = $p['pairCreatedAt'] ?? 0;
        $ageHrs = $ageMs ? max(0, (time() * 1000 - $ageMs) / 3_600_000) : 0;

        $chainRaw = strtolower((string) ($p['chainId'] ?? ''));
        $chainMap = [
            'ethereum' => 'ethereum', 'bsc' => 'bsc', 'solana' => 'solana',
            'base' => 'base', 'polygon' => 'polygon',
        ];
        $chain = $chainMap[$chainRaw] ?? $chainRaw;

        return [
            'contract'       => $p['baseToken']['address'],
            'name'           => $p['baseToken']['name'] ?? ($p['baseToken']['symbol'] ?? 'Unknown'),
            'symbol'         => strtoupper($p['baseToken']['symbol'] ?? '???'),
            'chain'          => $chain,
            'price'          => (float) ($p['priceUsd'] ?? 0),
            'price_change_24h' => (float) ($p['priceChange']['h24'] ?? 0),
            'market_cap'     => (float) ($p['marketCap'] ?? $p['fdv'] ?? 0),
            'liquidity'      => (float) ($p['liquidity']['usd'] ?? 0),
            'volume'         => (float) ($p['volume']['h24'] ?? 0),
            'pair_age_hours' => round($ageHrs, 1),
            'buys_24h'       => $buys,
            'sells_24h'      => $sells,
            'buy_sell_ratio' => $sells > 0 ? round($buys / $sells, 2) : (float) $buys,
            'pair_url'       => $p['url'] ?? '',
            'logo'           => $p['info']['imageUrl'] ?? '',
        ];
    }

    /**
     * Scan trending meme coins across all chains and persist them.
     * Returns the number of coins upserted.
     */
    public static function scanTrending(): int
    {
        $queries = ['pepe', 'doge', 'shib', 'meme', 'bonk', 'wif', 'floki', 'cat'];
        $count   = 0;
        $seen    = [];

        foreach ($queries as $q) {
            foreach (self::searchDex($q) as $coin) {
                $key = strtolower($coin['chain'] . ':' . $coin['contract']);
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;

                // Only keep liquid, real pairs
                if ($coin['liquidity'] < 1000) {
                    continue;
                }
                self::enrichAndStore($coin);
                $count++;
                if ($count >= 60) {
                    break 2;
                }
            }
        }
        Settings::set('last_scan_at', date('Y-m-d H:i:s'));
        log_event('scan', "Scan complete: {$count} coins upserted");
        return $count;
    }

    /** Add heuristic on-chain risk attributes then store the coin. */
    public static function enrichAndStore(array $coin): int
    {
        // Heuristic enrichment (free APIs don't expose all of these).
        $coin['holders']          = $coin['holders']          ?? 0;
        $coin['whale_percent']    = $coin['whale_percent']    ?? self::estimateWhale($coin);
        $coin['social_score']     = $coin['social_score']     ?? self::estimateSocial($coin);
        $coin['liquidity_locked'] = $coin['liquidity_locked'] ?? ($coin['liquidity'] > 20000 ? 1 : 0);
        $coin['smart_money_buys'] = $coin['smart_money_buys'] ?? 0;
        $coin['mint_enabled']     = $coin['mint_enabled']     ?? 0;
        $coin['is_honeypot']      = $coin['is_honeypot']      ?? 0;
        $coin['owner_privileges'] = $coin['owner_privileges'] ?? 0;
        $coin['has_blacklist']    = $coin['has_blacklist']    ?? 0;
        $coin['buy_tax']          = $coin['buy_tax']          ?? 0;
        $coin['sell_tax']         = $coin['sell_tax']         ?? 0;

        $analysis = RiskEngine::analyze($coin);
        $coin['ai_score']  = $analysis['score'];
        $coin['risk_level']= $analysis['risk'];
        $coin['warnings']  = json_encode($analysis['warnings']);

        return self::upsert($coin);
    }

    private static function estimateWhale(array $c): float
    {
        // crude: lower liquidity tends to mean higher concentration
        $liq = (float) ($c['liquidity'] ?? 0);
        if ($liq > 500000) return 8;
        if ($liq > 100000) return 18;
        if ($liq > 20000)  return 30;
        return 45;
    }

    private static function estimateSocial(array $c): int
    {
        $vol = (float) ($c['volume'] ?? 0);
        return (int) max(0, min(100, log10(max(1, $vol)) * 12));
    }

    /** Insert or update a coin keyed by (chain, contract). */
    public static function upsert(array $c): int
    {
        $db = Database::instance();
        $sql = 'INSERT INTO coins
            (contract, name, symbol, chain, price, price_change_24h, market_cap, liquidity, volume,
             pair_age_hours, buys_24h, sells_24h, buy_sell_ratio, holders, whale_percent, social_score,
             liquidity_locked, smart_money_buys, mint_enabled, is_honeypot, owner_privileges, has_blacklist,
             buy_tax, sell_tax, ai_score, risk_level, warnings, logo, pair_url, updated_at)
            VALUES
            (:contract,:name,:symbol,:chain,:price,:price_change_24h,:market_cap,:liquidity,:volume,
             :pair_age_hours,:buys_24h,:sells_24h,:buy_sell_ratio,:holders,:whale_percent,:social_score,
             :liquidity_locked,:smart_money_buys,:mint_enabled,:is_honeypot,:owner_privileges,:has_blacklist,
             :buy_tax,:sell_tax,:ai_score,:risk_level,:warnings,:logo,:pair_url, NOW())
            ON DUPLICATE KEY UPDATE
             name=VALUES(name), symbol=VALUES(symbol), price=VALUES(price),
             price_change_24h=VALUES(price_change_24h), market_cap=VALUES(market_cap),
             liquidity=VALUES(liquidity), volume=VALUES(volume), pair_age_hours=VALUES(pair_age_hours),
             buys_24h=VALUES(buys_24h), sells_24h=VALUES(sells_24h), buy_sell_ratio=VALUES(buy_sell_ratio),
             whale_percent=VALUES(whale_percent), social_score=VALUES(social_score),
             liquidity_locked=VALUES(liquidity_locked), ai_score=VALUES(ai_score),
             risk_level=VALUES(risk_level), warnings=VALUES(warnings), logo=VALUES(logo),
             pair_url=VALUES(pair_url), updated_at=NOW()';

        $params = [
            ':contract' => $c['contract'],
            ':name' => mb_substr((string) $c['name'], 0, 120),
            ':symbol' => mb_substr((string) $c['symbol'], 0, 32),
            ':chain' => $c['chain'],
            ':price' => $c['price'],
            ':price_change_24h' => $c['price_change_24h'] ?? 0,
            ':market_cap' => $c['market_cap'],
            ':liquidity' => $c['liquidity'],
            ':volume' => $c['volume'],
            ':pair_age_hours' => $c['pair_age_hours'] ?? 0,
            ':buys_24h' => $c['buys_24h'] ?? 0,
            ':sells_24h' => $c['sells_24h'] ?? 0,
            ':buy_sell_ratio' => $c['buy_sell_ratio'] ?? 0,
            ':holders' => $c['holders'] ?? 0,
            ':whale_percent' => $c['whale_percent'] ?? 0,
            ':social_score' => $c['social_score'] ?? 0,
            ':liquidity_locked' => $c['liquidity_locked'] ?? 0,
            ':smart_money_buys' => $c['smart_money_buys'] ?? 0,
            ':mint_enabled' => $c['mint_enabled'] ?? 0,
            ':is_honeypot' => $c['is_honeypot'] ?? 0,
            ':owner_privileges' => $c['owner_privileges'] ?? 0,
            ':has_blacklist' => $c['has_blacklist'] ?? 0,
            ':buy_tax' => $c['buy_tax'] ?? 0,
            ':sell_tax' => $c['sell_tax'] ?? 0,
            ':ai_score' => $c['ai_score'] ?? 0,
            ':risk_level' => $c['risk_level'] ?? 'MEDIUM',
            ':warnings' => $c['warnings'] ?? '[]',
            ':logo' => $c['logo'] ?? '',
            ':pair_url' => $c['pair_url'] ?? '',
        ];
        $db->run($sql, $params);
        return (int) $db->pdo()->lastInsertId();
    }
}
