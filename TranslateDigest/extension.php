<?php
require_once('lib/Logger.php');
require_once('lib/TextUtil.php');
require_once('lib/TranslateController.php');
require_once('lib/TranslationService.php');
require_once('lib/SummarizationService.php');
require_once('lib/TokenCounter.php');

class TranslateDigestExtension extends Minz_Extension {

    public function init() {
        Logger::info('Plugin initializing...');
        
        if (!extension_loaded('mbstring')) {
            Logger::error('Plugin requires PHP mbstring extension');
        }
        
        // 处理 CLI 模式下的用户上下文
        if (php_sapi_name() === 'cli' && !FreshRSS_Context::$user_conf) {
            $this->initializeCliContext();
        }
        
        // 注册钩子
        $this->registerHook('feed_before_insert', [$this, 'addTranslationOption']);
        $this->registerHook('entry_before_insert', [$this, 'handleEntry']);

        // 日志输出当前配置用于调试（直接从 user_conf 读取）
        $defaultService = strtoupper(FreshRSS_Context::$user_conf->TranslateDefaultService ?? 'GOOGLE');
        $targetLang = FreshRSS_Context::$user_conf->TranslateTargetLang ?? 'zh';
        Logger::info('Plugin configuration - Service: ' . $defaultService . ' | Target Language: ' . $targetLang);
        
        Logger::info('Initialization complete');
    }

    /**
     * 初始化 CLI 模式下的用户上下文
     */
    private function initializeCliContext() {
        $usernames = $this->listUsers();
        if (!empty($usernames)) {
            $username = $usernames[0];
            FreshRSS_Context::$user_conf = new FreshRSS_UserConfiguration($username);
            FreshRSS_Context::$user_conf->load();
            Logger::debug("CLI mode: initialized context for user '$username'");
        }
    }

    public function handleConfigureAction() {
        // 优先处理 Token 重置请求 (GET)
        if (Minz_Request::param('_action') === 'reset_tokens' && FreshRSS_Auth::checkCsrf(Minz_Request::param('_csrf'))) {
            $provider = Minz_Request::param('provider');
            if ($provider === 'deepseek' || $provider === 'qwen') {
                TokenCounter::resetTotalTokens($provider);
                
                // 重定向回配置页面
                $redirect_url = _url('extension', 'configure', 'e', 'TranslateDigest');
                header('Location: ' . $redirect_url);
                exit();
            }
        }

        if (Minz_Request::isPost()) {
            Logger::info('Configure action: POST request received');

            $translateService = Minz_Request::param('TranslateDefaultService', 'google');
            $translateTitles = Minz_Request::param('TranslateTitles', []);
            $summarizeContents = Minz_Request::param('SummarizeContents', []);
            $translateSkipIfSame = Minz_Request::param('TranslateSkipIfSame', 0) ? '1' : '0';
            $targetLang = Minz_Request::param('TranslateTargetLang', 'zh');
            $deepseekKey = Minz_Request::param('DeepSeekApiKey', '');
            $deepseekMasked = empty($deepseekKey) ? '' : (substr($deepseekKey, 0, 4) . '...' . substr($deepseekKey, -4));
            $deepseekModel = Minz_Request::param('DeepSeekModel', 'deepseek-chat');
            $qwenKey = Minz_Request::param('QwenApiKey', '');
            $qwenMasked = empty($qwenKey) ? '' : (substr($qwenKey, 0, 4) . '...' . substr($qwenKey, -4));
            $qwenModel = Minz_Request::param('QwenModel', 'qwen-plus');
            $tokenMaxChars = Minz_Request::param('TokenMaxChars', '4000');
            $summarizeContent = Minz_Request::param('SummarizeContent', 0) ? '1' : '0';
            $maxSummaryChars = Minz_Request::param('MaxSummaryChars', '200');

            // 用于存储验证错误信息
            $validationErrors = [];
            
            // DeepSeek Key 验证（只记录日志，不阻止保存）
            if (!empty($deepseekKey)) {
                require_once('lib/providers/DeepSeekProvider.php');
                try {
                    $provider = new DeepSeekProvider();
                    $provider->setApiKey($deepseekKey);
                    $result = $provider->validateKey();
                    if (!$result['ok']) {
                        $errorMsg = 'DeepSeek API Key 验证失败: ' . $result['message'];
                        Logger::warning($errorMsg);
                        $validationErrors['deepseek'] = $errorMsg;
                    } else {
                        Logger::info('DeepSeek key validated successfully');
                    }
                } catch (Exception $e) {
                    $errorMsg = 'DeepSeek API Key 验证异常: ' . $e->getMessage();
                    Logger::warning($errorMsg);
                    $validationErrors['deepseek'] = $errorMsg;
                }
            }

            // Qwen Key 验证（只记录日志，不阻止保存）
            if (!empty($qwenKey)) {
                require_once('lib/providers/QwenProvider.php');
                try {
                    $provider = new QwenProvider();
                    $provider->setApiKey($qwenKey);
                    $result = $provider->validateKey();
                    if (!$result['ok']) {
                        $errorMsg = '通义千问 API Key 验证失败: ' . $result['message'];
                        Logger::warning($errorMsg);
                        $validationErrors['qwen'] = $errorMsg;
                    } else {
                        Logger::info('Qwen key validated successfully');
                    }
                } catch (Exception $e) {
                    $errorMsg = '通义千问 API Key 验证异常: ' . $e->getMessage();
                    Logger::warning($errorMsg);
                    $validationErrors['qwen'] = $errorMsg;
                }
            }
            
            // 将验证错误信息存储到视图中
            $this->view['validationErrors'] = $validationErrors;

            // 验证通过后保存配置
            FreshRSS_Context::$user_conf->TranslateDefaultService = $translateService;
            FreshRSS_Context::$user_conf->TranslateTitles = json_encode($translateTitles);
            FreshRSS_Context::$user_conf->SummarizeContents = json_encode($summarizeContents);
            FreshRSS_Context::$user_conf->TranslateSkipIfSame = $translateSkipIfSame;
            FreshRSS_Context::$user_conf->TranslateTargetLang = $targetLang;
            FreshRSS_Context::$user_conf->DeepSeekApiKey = $deepseekKey;
            Logger::info('DeepSeek key ' . (empty($deepseekKey) ? 'EMPTY' : ('SET ' . $deepseekMasked)));
            FreshRSS_Context::$user_conf->DeepSeekModel = $deepseekModel;
            FreshRSS_Context::$user_conf->QwenApiKey = $qwenKey;
            Logger::info('Qwen key ' . (empty($qwenKey) ? 'EMPTY' : ('SET ' . $qwenMasked)));
            FreshRSS_Context::$user_conf->QwenModel = $qwenModel;
            FreshRSS_Context::$user_conf->TokenMaxChars = $tokenMaxChars;
            FreshRSS_Context::$user_conf->SummarizeContent = $summarizeContent;
            FreshRSS_Context::$user_conf->MaxSummaryChars = $maxSummaryChars;

            $saved = FreshRSS_Context::$user_conf->save();
            Logger::info('Config save attempt: ' . ($saved ? 'SUCCESS' : 'FAILED'));
        } 
        
        // 无论请求类型是什么，都获取token统计信息
        $this->view['token_stats'] = TokenCounter::getStats();

        // 只有在GET请求时才记录API Key状态
        // FreshRSS 使用 Minz_Request::isPost() 来检查是否为 POST 请求
        if (!Minz_Request::isPost()) {
            Logger::debug('Configure action: GET request (loading page)');
            // 读取并记录当前已保存的 API Key 状态（掩码显示）
            $deepseekSaved = FreshRSS_Context::$user_conf->DeepSeekApiKey ?? '';
            $qwenSaved = FreshRSS_Context::$user_conf->QwenApiKey ?? '';
            $deepseekMasked = empty($deepseekSaved) ? 'EMPTY' : (substr($deepseekSaved, 0, 4) . '...' . substr($deepseekSaved, -4));
            $qwenMasked = empty($qwenSaved) ? 'EMPTY' : (substr($qwenSaved, 0, 4) . '...' . substr($qwenSaved, -4));
            Logger::info('Key status on load: DeepSeek=' . $deepseekMasked . ', Qwen=' . $qwenMasked);
        }
    }

    public function handleUninstallAction() {
        // 清除插件配置项（从 FreshRSS_Context 中）
        $configKeys = [
            'TranslateDefaultService', 'TranslateTitles', 'FeedServiceMap',
            'TranslateTargetLang', 'TranslateSkipIfSame', 'SummarizeContent', 
            'SummarizeContents', 'MaxSummaryChars', 'TokenMaxChars',
            'DeepSeekApiKey', 'DeepSeekModel', 'QwenApiKey', 'QwenModel',
            TokenCounter::STATS_KEY
        ];
        
        foreach ($configKeys as $key) {
            if (isset(FreshRSS_Context::$user_conf->$key)) {
                unset(FreshRSS_Context::$user_conf->$key);
            }
        }
        
        FreshRSS_Context::$user_conf->save();
        Logger::info('Configuration cleared on uninstall');
    }


    public function handleEntry($entry) {
        try {
            $controller = new TranslateController();
            return $controller->processEntry($entry);
        } catch (Exception $e) {
            Logger::exception($e, 'Entry processing');
            return $entry;
        }
    }

    /**
     * 获取所有用户列表
     */
    private function listUsers(): array {
        $users = [];
        $path = DATA_PATH . '/users';
        
        if (!is_dir($path)) {
            Logger::error("Users directory not found: $path");
            return $users;
        }
        
        $handle = opendir($path);
        if ($handle === false) {
            Logger::error("Failed to open users directory: $path");
            return $users;
        }
        
        while (($entry = readdir($handle)) !== false) {
            if ($entry !== '.' && $entry !== '..' && is_dir($path . '/' . $entry)) {
                $users[] = $entry;
            }
        }
        closedir($handle);
        
        return $users;
    }

    public function addTranslationOption($feed) {
        $feed->TranslateTitles = '0';
        return $feed;
    }

    public function handleTestAction() {
        header('Content-Type: application/json');
        $text = Minz_Request::param('test-text', '');
        
        if (empty($text)) {
            return $this->view->_error(404);
        }
        
        try {
            // 从 FreshRSS_Context 读取配置
            $service = FreshRSS_Context::$user_conf->TranslateDefaultService ?? 'google';
            $targetLang = FreshRSS_Context::$user_conf->TranslateTargetLang ?? 'zh';
            
            $translationService = new TranslationService($service, $targetLang);
            $translated = $translationService->translate($text);
            
            $summary = '';
            $sumService = new SummarizationService($service, $targetLang, 200);
            $summary = $sumService->summarize($text);
            
            $this->view->_path('configure');
            $this->view->testResult = [
                'success' => true,
                'translated' => $translated,
                'summary' => $summary
            ];
        } catch (Exception $e) {
            Logger::exception($e, 'Test action');
            $this->view->_path('configure');
            $this->view->testResult = [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function handleValidateKeyAction() {
        header('Content-Type: application/json');
        $service = Minz_Request::param('service', '');
        $apiKey = Minz_Request::param('api-key', '');
        
        // 记录请求（API Key 使用掩码避免泄露）
        $maskedKey = empty($apiKey) ? '' : (substr($apiKey, 0, 4) . '...' . substr($apiKey, -4));
        Logger::info("ValidateKeyAction received: service=$service, apiKeyMasked=$maskedKey");

        if (empty($service) || empty($apiKey)) {
            Logger::error('ValidateKeyAction missing parameters');
            echo json_encode(['success' => false, 'message' => '缺少参数']);
            return;
        }
        
        try {
            require_once('lib/providers/AbstractProvider.php');
            
            $providerClass = null;
            if ($service === 'deepseek') {
                require_once('lib/providers/DeepSeekProvider.php');
                $providerClass = 'DeepSeekProvider';
                } elseif ($service === 'qwen') {
                require_once('lib/providers/QwenProvider.php');
                $providerClass = 'QwenProvider';
            } else {
                Logger::error("ValidateKeyAction unsupported service: $service");
                echo json_encode(['success' => false, 'message' => '不支持的服务']);
                return;
            }
            
            if (!class_exists($providerClass)) {
                Logger::error("ValidateKeyAction provider class not found: $providerClass");
                echo json_encode(['success' => false, 'message' => '提供商类不存在']);
                return;
            }
            
            Logger::debug("ValidateKeyAction instantiating provider: $providerClass");
            $provider = new $providerClass();
            $result = $provider->validateKey($apiKey);
            $ok = is_array($result) ? ($result['ok'] ?? false) : (bool)$result;
            $msg = is_array($result) ? ($result['message'] ?? ($ok ? 'API Key 有效' : 'API Key 无效或无法连接')) : ($ok ? 'API Key 有效' : 'API Key 无效或无法连接');
            Logger::info("ValidateKeyAction result: ok=" . ($ok ? 'true' : 'false') . ", message=$msg");

            echo json_encode(['success' => $ok, 'message' => $msg]);
        } catch (Exception $e) {
            Logger::exception($e, 'Validate key action');
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function handleTokenStatsAction() {
        header('Content-Type: application/json; charset=utf-8');
        
        try {
            $stats = TokenCounter::getStats();
            
            echo json_encode([
                'success' => true,
                'stats' => $stats
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            Logger::exception($e, 'Token stats action');
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function handleResetStatsAction() {
        header('Content-Type: application/json; charset=utf-8');
        
        try {
            TokenCounter::resetAllStats();
            echo json_encode(['success' => true, 'message' => 'Token 统计已重置']);
        } catch (Exception $e) {
            Logger::exception($e, 'Reset stats action');
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function handleDebugAction() {
        header('Content-Type: application/json');
        
        try {
            // 从 FreshRSS_Context 读取配置
            $feeds = FreshRSS_Factory::createFeedDao()->listFeeds();
            
            $feedList = [];
            foreach ($feeds as $feedId => $feed) {
                $feedList[] = [
                    'id' => $feedId,
                    'name' => $feed->name(),
                    'translateEnabled' => isset($config['TranslateTitles'][$feedId]) && $config['TranslateTitles'][$feedId] === '1',
                    'summarizeEnabled' => isset($config['SummarizeContents'][$feedId]) && $config['SummarizeContents'][$feedId] === '1'
                ];
            }
            
            echo json_encode([
                'success' => true,
                'config' => $config,
                'feeds' => $feedList,
                'hasApiKey' => [
                'deepseek' => !empty($config['DeepSeekApiKey']),
                    'qwen' => !empty($config['QwenApiKey'])
                ]
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            Logger::exception($e, 'Debug action');
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
}
