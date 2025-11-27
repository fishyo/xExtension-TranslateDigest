<?php
interface ITranslationProvider {
    public function getName();
    public function translate($text, $sourceLang = 'auto', $targetLang = 'zh');
    public function supportsSummarize();
    public function summarize($text, $targetLang = 'zh', $maxChars = 200);
    public function validateKey();
    public function getDefaultModel();
    public function getLastTokenInfo();
}
