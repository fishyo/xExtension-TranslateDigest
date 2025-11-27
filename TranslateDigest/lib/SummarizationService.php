<?php
require_once(__DIR__ . '/TextUtil.php');
require_once(__DIR__ . '/Logger.php');
require_once(__DIR__ . '/TokenCounter.php');
require_once(__DIR__ . '/providers/ITranslationProvider.php');
require_once(__DIR__ . '/providers/GoogleProvider.php');
require_once(__DIR__ . '/providers/DeepSeekProvider.php');
require_once(__DIR__ . '/providers/QwenProvider.php');

class SummarizationService {
    
    private $provider;
    private $targetLang;
    private $maxChars;
    private static $cache = [];

    public function __construct($serviceKey, $targetLang = null, $maxChars = 200) {
        $this->targetLang = $targetLang ?: (FreshRSS_Context::$user_conf->TranslateTargetLang ?? 'zh');
        $this->maxChars = $maxChars;
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

    public function summarize($text) {
        $clean = TextUtil::prepare($text, FreshRSS_Context::$user_conf->TokenMaxChars ?? 4000);
        if (empty($clean)) {
            return '';
        }

        $serviceName = $this->provider->getName();
        $cacheKey = md5('summarize|' . $serviceName . '|' . $this->targetLang . '|' . $this->maxChars . '|' . $clean);
        
        // 检查内存缓存
        if (isset(self::$cache[$cacheKey])) {
            Logger::debug('Summarization result - Service: ' . strtoupper($serviceName) . ' | Cache: MEMORY HIT | Text: ' . mb_substr($clean, 0, 40));
            return self::$cache[$cacheKey];
        }

        $output = null;

        Logger::info('Summarization start - Service: ' . strtoupper($serviceName) . ' | Target: ' . $this->targetLang . ' | MaxChars: ' . $this->maxChars);
        Logger::debug('Text to summarize: ' . mb_substr($clean, 0, 50));

        try {
            if ($this->provider->supportsSummarize()) {
                $output = $this->provider->summarize($clean, $this->targetLang, $this->maxChars);
                
                // 记录 token 使用 (非 Google 免费服务)
                if ($serviceName !== 'google' && $output !== null) {
                    try {
                        $tokenInfo = $this->provider->getLastTokenInfo();
                        if (!empty($tokenInfo)) {
                            TokenCounter::recordTokens($tokenInfo, $serviceName, 'summarize');
                        }
                    } catch (Exception $e) {
                        Logger::debug('Token recording error: ' . $e->getMessage());
                    }
                }

                if (!empty($output)) {
                    Logger::info('Summarization API call - Service: ' . strtoupper($serviceName) . ' | Result: ' . mb_substr($output, 0, 50));
                    // 保存到内存缓存
                    self::$cache[$cacheKey] = $output;
                }
            } else {
                // fallback: 简单截断
                $output = mb_substr($clean, 0, $this->maxChars);
                Logger::info('Summarization not supported by ' . strtoupper($serviceName) . ' - Fallback: TRUNCATION');
            }
        } catch (Exception $e) {
            Logger::exception($e, 'Summarization with ' . strtoupper($serviceName));
            $output = mb_substr($clean, 0, $this->maxChars);
            Logger::info('Summarization fallback to truncation due to error');
        }

        self::$cache[$cacheKey] = $output;
        return $output;
    }
}
