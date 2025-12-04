<?php
class GoogleProvider implements ITranslationProvider {
    private $baseUrl = 'https://translate.googleapis.com/translate_a/single';
    
    public function getName() { return 'google'; }
    
    public function translate($text, $sourceLang = 'auto', $targetLang = 'zh') {
        $queryParams = http_build_query([
            'client' => 'gtx',
            'sl' => $sourceLang,
            'tl' => $targetLang,
            'dt' => 't',
            'q' => $text,
        ]);
        $url = $this->baseUrl . '?' . $queryParams;
        $options = [ 'http' => [ 'method' => 'GET', 'timeout' => 5 ] ];
        $context = stream_context_create($options);
        try {
            $result = @file_get_contents($url, false, $context);
            if ($result === false) return '';
            $response = json_decode($result, true);
            return !empty($response[0][0][0]) ? $response[0][0][0] : '';
        } catch (Exception $e) { return ''; }
    }
    
    public function supportsSummarize() { return true; }
    
    public function summarize($text, $targetLang = 'zh', $maxChars = 200) {
        // Google 摘要方式：简单截断（无 API 调用，始终可用）
        // 这是一个兜底方案，确保摘要服务总是可用
        $summary = mb_substr($text, 0, $maxChars);
        // 尝试在句末截断而不是中间
        if (mb_strlen($text) > $maxChars) {
            $lastPeriod = mb_strrpos($summary, '。');
            if ($lastPeriod > $maxChars / 2) {
                $summary = mb_substr($summary, 0, $lastPeriod + 1);
            } else {
                $lastPeriod = mb_strrpos($summary, '，');
                if ($lastPeriod > $maxChars / 2) {
                    $summary = mb_substr($summary, 0, $lastPeriod + 1);
                }
            }
        }
        return $summary;
    }
    
    public function validateKey() { return ['ok' => true, 'message' => 'Google provider does not require API key.']; }
    
    public function getDefaultModel() { return ''; }
    
    public function getLastTokenInfo() { return ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0]; }
}
