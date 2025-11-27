<?php
require_once('TranslationService.php');
require_once('SummarizationService.php');
require_once('TextUtil.php');
require_once('Logger.php');

class TranslateController {
    
    public function processEntry($entry) {
        try {
            $feedId = $entry->feed()->id();
            
            // 从 FreshRSS_Context 读取配置
            $translateMap = json_decode(FreshRSS_Context::$user_conf->TranslateTitles ?? '{}', true) ?: [];
            $summarizeMap = json_decode(FreshRSS_Context::$user_conf->SummarizeContents ?? '{}', true) ?: [];
            
            // 构建配置数组
            $config = [
                'TranslateDefaultService' => FreshRSS_Context::$user_conf->TranslateDefaultService ?? 'google',
                'TranslateTargetLang' => FreshRSS_Context::$user_conf->TranslateTargetLang ?? 'zh',
                'TranslateSkipIfSame' => (FreshRSS_Context::$user_conf->TranslateSkipIfSame ?? '0') === '1',
                'TokenMaxChars' => FreshRSS_Context::$user_conf->TokenMaxChars ?? '4000',
                'TranslateTitles' => $translateMap,
                'SummarizeContents' => $summarizeMap,
            ];
            
            // 检查此订阅源是否启用翻译
            $translationEnabled = isset($config['TranslateTitles'][$feedId]) && $config['TranslateTitles'][$feedId] === '1';
            $summarizeEnabled = isset($config['SummarizeContents'][$feedId]) && $config['SummarizeContents'][$feedId] === '1';
            
            $defaultService = strtoupper($config['TranslateDefaultService']);
            Logger::info("Processing entry - Feed: $feedId | Service: $defaultService | Translate: " . ($translationEnabled ? 'YES' : 'NO') . " | Summarize: " . ($summarizeEnabled ? 'YES' : 'NO'));
            
            if (!$translationEnabled && !$summarizeEnabled) {
                Logger::debug("Feed $feedId: both disabled, skipping");
                return $entry;
            }

            // 翻译标题
            if ($translationEnabled) {
                $entry = $this->translateTitle($entry, $config, $feedId);
            }
            
            // 生成摘要
            if ($summarizeEnabled) {
                $entry = $this->summarizeContent($entry, $config, $feedId);
            }
            
            return $entry;
        } catch (Exception $e) {
            Logger::exception($e, 'Entry processing');
            return $entry;
        }
    }

    /**
     * 翻译标题
     */
    private function translateTitle($entry, $config, $feedId) {
        $targetLang = $config['TranslateTargetLang'];
        $skipIfSame = $config['TranslateSkipIfSame'];
        $serviceKey = $config['TranslateDefaultService'];

        $title = $entry->title();
        if (empty($title)) {
            return $entry;
        }
        
        Logger::debug("Original title: $title (feedId: $feedId)");
        
        // 检查是否同语言
        if ($skipIfSame && $this->isTargetLang($title, $targetLang)) {
            Logger::debug("Skipping translation - same language detected: $title");
            return $entry;
        }

            try {
                $translationService = new TranslationService($serviceKey, $targetLang);
                $usedService = null;
                $translatedTitle = $translationService->translate($title, $usedService);

                if ($translatedTitle && $translatedTitle !== $title) {
                    // 在标题中添加使用的翻译引擎标识
                    $serviceTag = '[' . strtoupper($usedService) . ']';
                    $displayTitle = $translatedTitle . ' - ' . $title . ' - ' . $serviceTag;
                    $entry->_title($displayTitle);
                    Logger::debug("Translated title: " . mb_substr($displayTitle, 0, 50));
                } else {
                    Logger::debug("Translation returned empty or same: $translatedTitle");
                }
            } catch (Exception $e) {
            Logger::exception($e, 'Title translation for: ' . $title);
        }

        return $entry;
    }

    /**
     * 生成内容摘要
     */
    private function summarizeContent($entry, $config, $feedId) {
        try {
            $content = $entry->content();
            if (empty($content)) {
                Logger::debug("Feed $feedId: no content to summarize");
                return $entry;
            }

            $serviceKey = $config['TranslateDefaultService'];
            $targetLang = $config['TranslateTargetLang'];
            $maxChars = $config['TokenMaxChars'];

            Logger::debug("Summarizing content for feed $feedId (service: $serviceKey, lang: $targetLang)");

            $summaryService = new SummarizationService($serviceKey, 'en', 200);
            $summary = $summaryService->summarize($content);

            if (!empty($summary)) {
                // 翻译摘要到目标语言
                if ($targetLang !== 'en') {
                    try {
                        $translationService = new TranslationService($serviceKey, $targetLang);
                        $translatedSummary = $translationService->translate($summary);
                        if (!empty($translatedSummary)) {
                            $summary = $translatedSummary;
                            Logger::debug("Summary translated to $targetLang");
                        }
                    } catch (Exception $e) {
                        Logger::debug("Failed to translate summary: " . $e->getMessage());
                    }
                }
                
                $safeSummary = TextUtil::escapeHtml($summary);
                $quote = '<blockquote style="margin:0 0 1em;color:#555;">'
                    . '<p><em>✨ AI 摘要：</em> ' . $safeSummary . '</p>'
                    . '</blockquote>';
                $entry->_content($quote . $content);
                Logger::debug("Added summary: " . mb_substr($summary, 0, 40));
            } else {
                Logger::debug("Summary service returned empty result");
            }
        } catch (Exception $e) {
            Logger::exception($e, 'Summary generation for feed: ' . $feedId);
        }

        return $entry;
    }

    /**
     * 检测文本是否为目标语言
     */
    private function isTargetLang($text, $targetLang) {
        $text = trim($text);
        if (empty($text)) {
            return false;
        }

        switch ($targetLang) {
            case 'zh':
                return $this->isChinese($text);
            case 'en':
                return $this->isEnglish($text);
            case 'ja':
                return $this->isJapanese($text);
            default:
                return false;
        }
    }

    /**
     * 检测是否为中文
     */
    private function isChinese($text) {
        $len = mb_strlen($text);
        if ($len === 0) {
            return false;
        }
        
        $chineseChars = preg_match_all('/[\x{4e00}-\x{9fa5}]/u', $text, $m);
        return ($chineseChars / max($len, 1)) > 0.6;
    }

    /**
     * 检测是否为英文
     */
    private function isEnglish($text) {
        $len = mb_strlen($text);
        if ($len === 0) {
            return false;
        }
        
        $nonAscii = preg_match_all('/[^\x00-\x7F]/u', $text, $m);
        return $nonAscii < ($len * 0.1);
    }

    /**
     * 检测是否为日文
     */
    private function isJapanese($text) {
        // 简单检测：包含日文假名和汉字
        $hiragana = preg_match_all('/[\x{3040}-\x{309F}]/u', $text, $m);
        $katakana = preg_match_all('/[\x{30A0}-\x{30FF}]/u', $text, $m);
        $kanji = preg_match_all('/[\x{4E00}-\x{9FFF}]/u', $text, $m);
        
        return ($hiragana + $katakana + $kanji) > 2;
    }
}
