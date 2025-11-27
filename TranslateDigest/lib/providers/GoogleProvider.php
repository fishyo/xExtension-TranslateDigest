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
    public function supportsSummarize() { return false; }
    public function summarize($text, $targetLang = 'zh', $maxChars = 200) { return mb_substr($text, 0, $maxChars); }
    public function validateKey() { return ['ok' => true, 'message' => 'Google provider does not require API key.']; }
    public function getDefaultModel() { return ''; }
    public function getLastTokenInfo() { return ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0]; }
}
