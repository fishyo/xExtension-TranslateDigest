<?php
/**
 * 文本处理工具类
 */
class TextUtil {
    
    /**
     * 准备文本：清理HTML、规范化空白、去重标点
     * @param string $text 原始文本
     * @param int $limit 字符限制
     * @return string 处理后的文本
     */
    public static function prepare($text, $limit = 4000) {
        // 移除HTML标签
        $text = strip_tags($text);
        
        // 规范化空白字符
        $text = preg_replace('/\s+/u', ' ', $text);
        
        // 去重重复标点
        $text = preg_replace('/([。！？!?])\1+/u', '$1', $text);
        
        // 截断到限制长度
        $text = mb_substr(trim($text), 0, $limit);
        
        return $text;
    }

    /**
     * 后处理：移除包裹的引号
     */
    public static function postProcess($text) {
        $text = trim($text);
        
        // 移除包裹的双引号
        $text = preg_replace('/^"(.+)"$/u', '$1', $text);
        
        // 移除包裹的单引号
        $text = preg_replace("/^'(.+)'$/u", '$1', $text);
        
        return $text;
    }

    /**
     * 安全转义HTML
     */
    public static function escapeHtml($text) {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * 截断文本到指定长度
     */
    public static function truncate($text, $length = 200, $suffix = '...') {
        if (mb_strlen($text) <= $length) {
            return $text;
        }
        return mb_substr($text, 0, $length - mb_strlen($suffix)) . $suffix;
    }
}
