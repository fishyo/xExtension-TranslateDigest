<?php
/**
 * 抽象翻译提供商基类
 * 提取公共的 HTTP 请求和文本处理逻辑
 */
abstract class AbstractProvider implements ITranslationProvider {
    
    protected $apiKey;
    protected $model;
    protected $endpoint;
    
    // Token 使用信息（从最后一次请求获取）
    protected $lastTokenInfo = [
        'prompt_tokens' => 0,
        'completion_tokens' => 0,
        'total_tokens' => 0
    ];
    
    // Prompt 模板
    protected static $prompts = [
        'translate' => 'You are a professional translation engine. Output ONLY the translated text without explanation.',
        'summarize' => 'Summarize the article, and provide three parts: summary, abstract, and opinions. The opinion section should be listed as a list, using Markdown to reply.'
    ];
    // 'summarize' => 'You are a concise summarization engine. Output ONLY the summary.'
    // 请求参数默认值
    protected static $payloadDefaults = [
        'translate' => ['temperature' => 0.2, 'max_tokens' => 800],
        'summarize' => ['temperature' => 0.1, 'max_tokens' => 600]
    ];

    /**
     * 获取请求头
     */
    protected abstract function getHeaders();

    /**
     * 执行 HTTP POST 请求
     * @param array $payload 请求负载
     * @param int $timeout 超时时间（秒）
     * @return array|null 响应数据
     */
    protected function makeRequest($payload, $timeout = 20) {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $options = [
            'http' => [
                'method' => 'POST',
                'header' => $this->getHeaders(),
                'content' => $json,
                'timeout' => $timeout
            ]
        ];
        
        $ctx = stream_context_create($options);
        try {
            $res = @file_get_contents($this->endpoint, false, $ctx);
            if ($res === false) {
                Logger::error($this->getName() . ' request failed');
                return null;
            }
            
            $data = json_decode($res, true);
            
            // 记录原始响应数据，以便调试
            Logger::debug($this->getName() . ' raw response: ' . substr($res, 0, 500));
            
            if (isset($data['error'])) {
                Logger::error($this->getName() . ' error: ' . ($data['error']['message'] ?? 'unknown'));
                return null;
            }
            
            // 提取 token 使用信息
            $this->extractTokenInfo($data);
            
            // 记录提取到的 token 信息
            Logger::debug($this->getName() . ' extracted token info: ' . print_r($this->lastTokenInfo, true));
            
            return $data;
        } catch (Exception $e) {
            Logger::exception($e, $this->getName() . ' request');
            return null;
        }
    }

    /**
     * 从响应数据中提取 token 信息
     */
    protected function extractTokenInfo($data) {
        if (isset($data['usage'])) {
            $this->lastTokenInfo = [
                'prompt_tokens' => $data['usage']['prompt_tokens'] ?? 0,
                'completion_tokens' => $data['usage']['completion_tokens'] ?? 0,
                'total_tokens' => $data['usage']['total_tokens'] ?? 0
            ];
            Logger::debug($this->getName() . ' tokens - prompt: ' . $this->lastTokenInfo['prompt_tokens'] 
                . ', completion: ' . $this->lastTokenInfo['completion_tokens'] 
                . ', total: ' . $this->lastTokenInfo['total_tokens']);
        }
    }

    /**
     * 获取最后一次请求的 token 使用情况
     */
    public function getLastTokenInfo() {
        return $this->lastTokenInfo;
    }

    /**
     * 获取翻译 Prompt
     */
    protected function getTranslatePrompt($text, $targetLang) {
        return self::$prompts['translate'] . '\n\nTranslate into ' . $targetLang . ': ' . $text;
    }

    /**
     * 获取摘要 Prompt
     */
    protected function getSummarizePrompt($text, $targetLang, $maxChars) {
        return self::$prompts['summarize'] . '\n\nSummarize in ' . $targetLang . ' within ' . $maxChars . ' characters: ' . $text;
    }

    /**
     * 准备文本
     */
    protected function prepareText($text, $limit = 4000) {
        return TextUtil::prepare($text, $limit);
    }

    /**
     * 后处理输出
     */
    protected function postProcessOutput($text) {
        return TextUtil::postProcess($text);
    }

    /**
     * 提取消息内容
     */
    protected function extractMessage($data) {
        return $data['choices'][0]['message']['content'] ?? '';
    }
}
