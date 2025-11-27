<?php
/**
 * 统一日志管理工具类
 */
class Logger {
    const PREFIX = '[TranslateDigest]';

    /**
     * 记录信息级别日志
     */
    public static function info($message) {
        error_log(self::PREFIX . ' ' . $message);
    }

    /**
     * 记录错误级别日志
     */
    public static function error($message) {
        error_log(self::PREFIX . ' [ERROR] ' . $message);
    }

    /**
     * 记录调试级别日志
     */
    public static function debug($message) {
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            error_log(self::PREFIX . ' [DEBUG] ' . $message);
        }
    }

    /**
     * 记录警告级别日志
     */
    public static function warning($message) {
        error_log(self::PREFIX . ' [WARNING] ' . $message);
    }

    /**
     * 记录异常
     */
    public static function exception(Exception $e, $context = '') {
        $msg = $context ? "$context: " : '';
        $msg .= $e->getMessage() . ' (' . $e->getFile() . ':' . $e->getLine() . ')';
        self::error($msg);
    }
}
