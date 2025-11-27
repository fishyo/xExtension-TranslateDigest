<?php
require_once('AbstractProvider.php');

class QwenProvider extends AbstractProvider {
    private $allowedModels = ['qwen-plus', 'qwen-turbo', 'qwen-max'];

    public function __construct() {
        $this->apiKey = FreshRSS_Context::$user_conf->QwenApiKey ?? '';
        $configuredModel = FreshRSS_Context::$user_conf->QwenModel ?? 'qwen-plus';
        $this->model = in_array($configuredModel, $this->allowedModels, true) ? $configuredModel : 'qwen-plus';
        $this->endpoint = 'https://dashscope.aliyuncs.com/compatible-mode/v1/chat/completions';
    }

    public function setApiKey($apiKey) {
        $this->apiKey = $apiKey;
    }

    public function getName() {
        return 'qwen';
    }

    protected function getHeaders() {
        return [
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json'
        ];
    }

    public function translate($text, $sourceLang = 'auto', $targetLang = 'zh') {
        if (empty($this->apiKey) || empty(trim($text))) {
            return '';
        }

        $payload = [
            'model' => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => self::$prompts['translate']],
                ['role' => 'user', 'content' => 'Translate into ' . $targetLang . ': ' . $text]
            ],
            'temperature' => self::$payloadDefaults['translate']['temperature'],
            'max_tokens' => self::$payloadDefaults['translate']['max_tokens'],
            'stream' => false
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

        $payload = [
            'model' => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => self::$prompts['summarize']],
                ['role' => 'user', 'content' => 'Summarize in ' . $targetLang . ' within ' . $maxChars . ' characters: ' . $clean]
            ],
            'temperature' => self::$payloadDefaults['summarize']['temperature'],
            'max_tokens' => self::$payloadDefaults['summarize']['max_tokens'],
            'stream' => false
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
            return ['ok' => false, 'message' => '未配置 通义千问 API Key'];
        }

        $payload = [
            'model' => $this->model,
            'messages' => [['role' => 'user', 'content' => 'ping']],
            'max_tokens' => 5,
            'temperature' => 0.0,
            'stream' => false
        ];

        $data = $this->makeRequest($payload, 10);
        if ($data === null) {
            return ['ok' => false, 'message' => '请求失败或认证失败'];
        }

        return ['ok' => true, 'message' => '通义千问 Key 验证通过'];
    }

    public function getDefaultModel() {
        return 'qwen-plus';
    }
}
