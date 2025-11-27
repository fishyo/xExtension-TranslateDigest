<?php

require_once(__DIR__ . '/Logger.php');

class TokenCounter {
    
    const STATS_KEY = 'TranslateTitlesCN_TokenStats';

    private static $costConfig = [
        'google'   => ['prompt' => 0, 'completion' => 0],
        'deepseek' => ['prompt' => 0.14, 'completion' => 0.28],
        'qwen'  => ['prompt' => 0.002, 'completion' => 0.006]
    ];

    private static $defaultStats = [
        'deepseek' => ['prompt' => 0, 'completion' => 0, 'total' => 0, 'cost' => 0],
        'qwen'  => ['prompt' => 0, 'completion' => 0, 'total' => 0, 'cost' => 0],
    ];

    public static function recordTokens($tokenInfo, $provider, $type = 'translate') {
        if (empty($tokenInfo) || !isset(self::$costConfig[$provider])) {
            return;
        }

        $promptTokens = (int)($tokenInfo['prompt_tokens'] ?? 0);
        $completionTokens = (int)($tokenInfo['completion_tokens'] ?? 0);
        $totalTokens = $promptTokens + $completionTokens;

        if ($totalTokens <= 0) {
            Logger::debug("Token count is 0 for $provider, skipping record");
            return;
        }

        $cost = self::calculateCost($provider, $promptTokens, $completionTokens);
        
        // Update stats
        $stats = self::getStats();
        if (!isset($stats[$provider])) {
            $stats[$provider] = self::$defaultStats[$provider];
        }
        $stats[$provider]['prompt'] += $promptTokens;
        $stats[$provider]['completion'] += $completionTokens;
        $stats[$provider]['total'] += $totalTokens;
        $stats[$provider]['cost'] += $cost;
        self::saveStats($stats);

        Logger::info(sprintf(
            'Token recorded - Provider: %s | Type: %s | Tokens: %d (prompt: %d, completion: %d) | Cost: ¥%.6f',
            strtoupper($provider),
            $type,
            $totalTokens,
            $promptTokens,
            $completionTokens,
            $cost
        ));
    }

    public static function getTotalTokens($provider) {
        $stats = self::getStats();
        return $stats[$provider]['total'] ?? 0;
    }
    
    public static function getStats() {
        if (isset(FreshRSS_Context::$user_conf) && isset(FreshRSS_Context::$user_conf->{self::STATS_KEY})) {
            $stats = FreshRSS_Context::$user_conf->{self::STATS_KEY};
            if (is_array($stats)) {
                return array_merge(self::$defaultStats, $stats);
            }
        }
        return self::$defaultStats;
    }

    private static function saveStats($stats) {
        if (isset(FreshRSS_Context::$user_conf)) {
            FreshRSS_Context::$user_conf->{self::STATS_KEY} = $stats;
            FreshRSS_Context::$user_conf->save();
        }
    }
    
    public static function resetAllStats() {
        self::saveStats(self::$defaultStats);
        Logger::info("All token stats have been reset.");
    }
    
    public static function resetTotalTokens($provider) {
        $stats = self::getStats();
        if (isset($stats[$provider])) {
            $stats[$provider] = self::$defaultStats[$provider];
            self::saveStats($stats);
            Logger::info("Token stats reset for provider: $provider");
        }
    }
    
    private static function calculateCost($provider, $promptTokens, $completionTokens) {
        $config = self::$costConfig[$provider] ?? null;
        if (!$config) return 0;

        $promptCost = ($promptTokens / 1000000) * $config['prompt'];
        $completionCost = ($completionTokens / 1000000) * $config['completion'];
        
        return $promptCost + $completionCost;
    }
}
