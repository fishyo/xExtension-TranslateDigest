<?php
require_once(__DIR__ . '/TextUtil.php');
require_once(__DIR__ . '/Logger.php');
require_once(__DIR__ . '/TokenCounter.php');
require_once(__DIR__ . '/providers/ITranslationProvider.php');
require_once(__DIR__ . '/providers/GoogleProvider.php');
require_once(__DIR__ . '/providers/DeepSeekProvider.php');
require_once(__DIR__ . '/providers/QwenProvider.php');

class TranslationService {
    
    private $provider;
    private $targetLang;
    private static $cache = [];

    public function __construct($serviceKey, $targetLang = null) {
        $this->targetLang = $targetLang ?: (FreshRSS_Context::$user_conf->TranslateTargetLang ?? 'zh');
        $this->provider = $this->makeProvider($serviceKey);
    }

    private function makeProvider($key) {
        switch ($key) {
            case 'deepseek':
                return new DeepSeekProvider();
            case 'qwen':
                return new QwenProvider();
            case 'google':
            default:
                return new GoogleProvider();
        }
    }

    public function getProvider() {
        return $this->provider;
    }

    public function translate($text, &$usedService = null) {
        $text = trim($text);
        if (empty($text)) {
            return '';
        }

        $serviceName = $this->provider->getName();
        $cacheKey = md5('translate|' . $serviceName . '|' . $this->targetLang . '|' . $text);
        
        // 检查内存缓存
        if (isset(self::$cache[$cacheKey])) {
            Logger::debug('Translation result - Service: ' . strtoupper($serviceName) . ' | Cache: MEMORY HIT | Text: ' . mb_substr($text, 0, 40));
            $usedService = $serviceName;
            return self::$cache[$cacheKey];
        }
        
        $translatedText = null;
        $usedFallback = false;
        $actualService = $serviceName;

        Logger::info('Translation start - Service: ' . strtoupper($serviceName) . ' | Target: ' . $this->targetLang);
        Logger::debug('Text to translate: ' . mb_substr($text, 0, 50));

        try {
            $translated = $this->provider->translate($text, 'auto', $this->targetLang);
            $translatedText = $translated;
            
            // 记录 token 使用 (非 Google 免费服务)
            if ($serviceName !== 'google' && $translatedText !== null) {
                try {
                    $tokenInfo = $this->provider->getLastTokenInfo();
                    if (!empty($tokenInfo)) {
                        TokenCounter::recordTokens($tokenInfo, $serviceName, 'translate');
                    }
                } catch (Exception $e) {
                    Logger::debug('Token recording error: ' . $e->getMessage());
                }
            }
            
            if (empty($translated)) {
                // fallback 到 Google
                if ($serviceName !== 'google') {
                    Logger::info('Translation API call to ' . strtoupper($serviceName) . ' returned empty');
                    $fallback = new GoogleProvider();
                    $translated = $fallback->translate($text, 'auto', $this->targetLang) ?: $text;
                    $usedFallback = true;
                    $actualService = 'google'; // 更新实际使用的服务
                    Logger::info('Translation fallback - Service: GOOGLE | Result: ' . mb_substr($translated, 0, 50));
                } else {
                    $translated = $text;
                }
            } else {
                Logger::info('Translation API call - Service: ' . strtoupper($serviceName) . ' | Result: ' . mb_substr($translated, 0, 50));
            }
        } catch (Exception $e) {
            Logger::exception($e, 'Translation with ' . strtoupper($serviceName));
            
            // 尝试 fallback
            if ($serviceName !== 'google') {
                try {
                    $fallback = new GoogleProvider();
                    $translated = $fallback->translate($text, 'auto', $this->targetLang) ?: $text;
                    $usedFallback = true;
                    $actualService = 'google'; // 更新实际使用的服务
                    Logger::info('Translation fallback to Google due to error - Service: GOOGLE');
                } catch (Exception $fallbackErr) {
                    Logger::exception($fallbackErr, 'Fallback translation to Google');
                    $translated = $text;
                }
            } else {
                $translated = $text;
                Logger::error('Google translation failed');
            }
        }

        $usedService = $actualService; // 设置实际使用的服务
        self::$cache[$cacheKey] = $translated;
        return $translated;
    }
}
