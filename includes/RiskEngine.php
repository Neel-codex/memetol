<?php
/**
 * RiskEngine - AI risk scoring + rug-pull detection heuristics.
 *
 * Produces a 0-100 safety score (higher = safer) and a structured
 * breakdown of weighted factors, plus rug-pull warning flags.
 */
class RiskEngine
{
    /**
     * Analyse a coin row / data array and return:
     *  [ 'score' => int, 'risk' => 'LOW|MEDIUM|HIGH', 'factors' => [...], 'warnings' => [...] ]
     */
    public static function analyze(array $c): array
    {
        $factors = [];

        // 1. Liquidity locked (0-18)
        $liqLocked = (int) ($c['liquidity_locked'] ?? 0);
        $factors['Liquidity Locked'] = $liqLocked ? 18 : 2;

        // 2. Holder distribution / whale concentration (0-18)
        $whale = (float) ($c['whale_percent'] ?? 0);
        if ($whale <= 0) {
            $holderScore = 12; // unknown -> neutral
        } elseif ($whale < 10) {
            $holderScore = 18;
        } elseif ($whale < 25) {
            $holderScore = 13;
        } elseif ($whale < 40) {
            $holderScore = 7;
        } else {
            $holderScore = 1;
        }
        $factors['Holder Distribution'] = $holderScore;

        // 3. Volume growth vs liquidity (0-15)
        $vol = (float) ($c['volume'] ?? 0);
        $liq = (float) ($c['liquidity'] ?? 0);
        $ratio = $liq > 0 ? $vol / $liq : 0;
        if ($ratio >= 1.5 && $ratio <= 15)      $volScore = 15;
        elseif ($ratio > 0 && $ratio < 1.5)     $volScore = 9;
        elseif ($ratio > 15)                     $volScore = 5; // suspicious wash trading
        else                                     $volScore = 4;
        $factors['Volume Growth'] = $volScore;

        // 4. Social activity (0-12)
        $social = (int) ($c['social_score'] ?? 0);
        $factors['Social Activity'] = (int) round(min(12, $social / 100 * 12));

        // 5. Token age (0-15) - older = safer
        $ageHours = (float) ($c['pair_age_hours'] ?? 0);
        if ($ageHours >= 720)      $ageScore = 15; // 30d+
        elseif ($ageHours >= 168)  $ageScore = 12; // 7d+
        elseif ($ageHours >= 48)   $ageScore = 8;
        elseif ($ageHours >= 12)   $ageScore = 5;
        else                       $ageScore = 2;
        $factors['Token Age'] = $ageScore;

        // 6. Smart money buys (0-12)
        $smartBuys = (int) ($c['smart_money_buys'] ?? 0);
        $factors['Smart Money Buys'] = (int) min(12, $smartBuys * 3);

        // 7. Liquidity depth (0-10)
        if ($liq >= 250_000)      $depth = 10;
        elseif ($liq >= 50_000)   $depth = 7;
        elseif ($liq >= 10_000)   $depth = 4;
        else                      $depth = 1;
        $factors['Liquidity Depth'] = $depth;

        $score = array_sum($factors);
        $score = max(0, min(100, (int) round($score)));

        [$label] = risk_label($score);

        return [
            'score'    => $score,
            'risk'     => $label,
            'factors'  => $factors,
            'warnings' => self::rugCheck($c),
        ];
    }

    /** Rug-pull detector. Returns an array of warning strings. */
    public static function rugCheck(array $c): array
    {
        $warnings = [];

        if (!empty($c['mint_enabled']))         $warnings[] = 'Owner can mint new tokens';
        if (!empty($c['is_honeypot']))          $warnings[] = 'Possible honeypot - selling may be blocked';
        if (empty($c['liquidity_locked']))      $warnings[] = 'Liquidity is not locked';
        if (!empty($c['owner_privileges']))     $warnings[] = 'Owner retains dangerous privileges';
        if (!empty($c['has_blacklist']))        $warnings[] = 'Contract contains a blacklist function';

        $buyTax  = (float) ($c['buy_tax'] ?? 0);
        $sellTax = (float) ($c['sell_tax'] ?? 0);
        if ($buyTax > 10 || $sellTax > 10) {
            $warnings[] = sprintf('High taxes (buy %.0f%% / sell %.0f%%)', $buyTax, $sellTax);
        }

        $whale = (float) ($c['whale_percent'] ?? 0);
        if ($whale >= 40) {
            $warnings[] = sprintf('Whale owns >%d%% of supply', (int) $whale);
        }

        $liq = (float) ($c['liquidity'] ?? 0);
        if ($liq > 0 && $liq < 5000) {
            $warnings[] = 'Very low liquidity (<$5K)';
        }

        return $warnings;
    }

    /** Convenience: produce a short AI insight sentence. */
    public static function insight(array $analysis, array $c): string
    {
        $name = $c['name'] ?? 'This token';
        switch ($analysis['risk']) {
            case 'LOW':
                return "$name shows healthy liquidity and balanced holder distribution. AI signal: favourable.";
            case 'MEDIUM':
                return "$name has moderate risk. Monitor liquidity lock status and whale activity before entering.";
            default:
                return "$name carries high risk. " . (count($analysis['warnings']) ? 'Detected: ' . $analysis['warnings'][0] . '.' : 'Trade with extreme caution.');
        }
    }
}
