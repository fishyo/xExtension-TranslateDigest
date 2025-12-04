<?php
require_once('AbstractProvider.php');

class GeminiProvider extends AbstractProvider {
    private $allowedModels = [
        // Gemini 3 系列（最新）
        'gemini-3-pro-preview',
        // Gemini 2.5 系列（稳定版本，推荐使用）
        'gemini-2.5-flash',
        'gemini-2.5-flash-lite',
        'gemini-2.5-pro',
        // Gemini 1.5 系列
        'gemini-1.5-flash',
        'gemini-1.5-pro'
        // 注意：Gemini 2.0 系列将于 2026 年 2 月弃用，已移除
    ];

    public function __construct() {
        $this->apiKey = FreshRSS_Context::$user_conf->GeminiApiKey ?? '';
        $configuredModel = FreshRSS_Context::$user_conf->GeminiModel ?? 'gemini-2.5-flash';
        $this->model = in_array($configuredModel, $this->allowedModels, true) ? $configuredModel : 'gemini-2.5-flash';
        $this->endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/' . $this->model . ':generateContent';
    }

    public function setApiKey($apiKey) {
        $this->apiKey = $apiKey;
    }

    public function getName() {
        return 'gemini';
    }

    protected function getHeaders() {
        return [
            'Content-Type: application/json',
            'x-goog-api-key: ' . $this->apiKey
        ];
    }

    public function translate($text, $sourceLang = 'auto', $targetLang = 'zh') {
        if (empty($this->apiKey) || empty(trim($text))) {
            return '';
        }

        $prompt = self::$prompts['translate'] . '\n\nTranslate into ' . $targetLang . ': ' . $text;
        
        $payload = [
            'contents' => [
                ['parts' => [['text' => $prompt]]]
            ],
            'generationConfig' => [
                'temperature' => self::$payloadDefaults['translate']['temperature'],
                'maxOutputTokens' => self::$payloadDefaults['translate']['max_tokens']
            ]
        ];

        $data = $this->makeRequest($payload);
        if ($data === null) {
            return '';
        }

        $output = $this->extractMessage($data);
        return $this->postProcessOutput($output);
    }

    public function supportsSummarize() {
        return true;
    }

    public function summarize($text, $targetLang = 'zh', $maxChars = 200) {
        if (empty($this->apiKey)) {
            return mb_substr($text, 0, $maxChars);
        }

        $clean = $this->prepareText($text, $maxChars * 5);
        if (empty($clean)) {
            return '';
        }

        $prompt = self::$prompts['summarize'] . '\n\nSummarize in ' . $targetLang . ' within ' . $maxChars . ' characters: ' . $clean;
        
        $payload = [
            'contents' => [
                ['parts' => [['text' => $prompt]]]
            ],
            'generationConfig' => [
                'temperature' => self::$payloadDefaults['summarize']['temperature'],
                'maxOutputTokens' => self::$payloadDefaults['summarize']['max_tokens']
            ]
        ];

        $data = $this->makeRequest($payload);
        if ($data === null) {
            return mb_substr($clean, 0, $maxChars);
        }

        $output = $this->extractMessage($data);
        $output = $this->postProcessOutput($output);
        return mb_substr($output, 0, $maxChars);
    }

    public function validateKey() {
        if (empty($this->apiKey)) {
            return ['ok' => false, 'message' => '未配置 Gemini API Key'];
        }

        $payload = [
            'contents' => [
                ['parts' => [['text' => 'ping']]]
            ],
            'generationConfig' => [
                'maxOutputTokens' => 5,
                'temperature' => 0.0
            ]
        ];

        $data = $this->makeRequest($payload, 10);
        if ($data === null) {
            return ['ok' => false, 'message' => '请求失败或认证失败'];
        }

        return ['ok' => true, 'message' => 'Gemini Key 验证通过'];
    }

    public function getDefaultModel() {
        return 'gemini-2.5-flash';
    }

    /**
     * 覆盖父类的 makeRequest 方法，因为 Gemini API 使用 API key 作为 URL 参数
     */
    protected function makeRequest($payload, $timeout = 20) {
        $url = $this->endpoint . '?key=' . $this->apiKey;
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $options = [
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $this->getHeaders()),
                'content' => $json,
                'timeout' => $timeout
            ]
        ];
        
        $ctx = stream_context_create($options);
        try {
            $res = @file_get_contents($url, false, $ctx);
            if ($res === false) {
                Logger::error($this->getName() . ' request failed');
                return null;
            }
            
            $data = json_decode($res, true);
            
            Logger::debug($this->getName() . ' raw response: ' . substr($res, 0, 500));
            
            if (isset($data['error'])) {
                Logger::error($this->getName() . ' error: ' . ($data['error']['message'] ?? 'unknown'));
                return null;
            }
            
            // Gemini API 使用不同的 token 统计结构
            $this->extractTokenInfo($data);
            
            Logger::debug($this->getName() . ' extracted token info: ' . print_r($this->lastTokenInfo, true));
            
            return $data;
        } catch (Exception $e) {
            Logger::exception($e, $this->getName() . ' request');
            return null;
        }
    }

    /**
     * 覆盖 token 信息提取方法，适配 Gemini API 响应格式
     */
    protected function extractTokenInfo($data) {
        if (isset($data['usageMetadata'])) {
            $this->lastTokenInfo = [
                'prompt_tokens' => $data['usageMetadata']['promptTokenCount'] ?? 0,
                'completion_tokens' => $data['usageMetadata']['candidatesTokenCount'] ?? 0,
                'total_tokens' => $data['usageMetadata']['totalTokenCount'] ?? 0
            ];
            Logger::debug($this->getName() . ' tokens - prompt: ' . $this->lastTokenInfo['prompt_tokens'] 
                . ', completion: ' . $this->lastTokenInfo['completion_tokens'] 
                . ', total: ' . $this->lastTokenInfo['total_tokens']);
        }
    }

    /**
     * 覆盖消息提取方法，适配 Gemini API 响应格式
     */
    protected function extractMessage($data) {
        return $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
    }
}
