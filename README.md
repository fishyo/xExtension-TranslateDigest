# TranslateDigest

[中文](#中文) | [English](#english)

---

## 中文

### 📖 简介

**TranslateDigest** 是一个功能强大的 FreshRSS 扩展插件，旨在通过 AI 技术提升您的 RSS 阅读体验。它能够自动翻译订阅源的文章标题，并利用先进的大语言模型（LLM）为文章生成智能摘要。

### ✨ 核心功能

- **🌍 多语言标题翻译**  
  支持自动翻译文章标题为中文、英文、日文、法文或德文

- **🤖 AI 智能摘要**  
  集成 DeepSeek 和通义千问（Qwen）大模型，自动提炼文章核心内容

- **⚙️ 灵活的订阅源管理**  
  支持按订阅源粒度控制，可为特定 Feed 单独开启翻译或摘要功能

- **🔌 多服务提供商支持**

  - **Google 翻译**：免费、快速，适用于基础标题翻译
  - **DeepSeek**：强大的 AI 模型，支持翻译和摘要
  - **通义千问**：阿里云 AI 服务，支持翻译和摘要
  - **Google Gemini**：Google 最新 AI 模型，支持翻译和摘要（免费）

- **💰 成本控制**  
  内置 Token 消耗统计与字符数限制功能，有效监控 API 使用成本

- **🛡️ 智能容错机制**
  - API 失败自动 Fallback 到 Google 服务
  - 摘要功能永不失败（多级兜底策略）
  - 智能错误分类，避免浪费 Token（永久性错误不重试）

### 📦 安装

1. **下载插件**
   `ash
cd /path/to/FreshRSS/extensions
git clone https://github.com/fishyo/TranslateDigest.git
`

2. **启用插件**
   - 登录 FreshRSS
   - 进入 设置 → 扩展
   - 找到 TranslateDigest 并点击启用

### ⚙️ 配置

在 FreshRSS 扩展管理页面找到 TranslateDigest 并点击配置。

#### 1️⃣ 选择翻译服务

- **Google 翻译**（默认）

  - 无需 API Key
  - 仅支持标题翻译
  - 完全免费

- **DeepSeek / 通义千问 / Gemini**（推荐用于摘要）
  - 需要申请并填写 API Key
  - 支持标题翻译和内容摘要
  - 支持自定义模型（如 `deepseek-chat`, `qwen-plus`, `gemini-2.0-flash-exp`）
  - Gemini API Key 可在 [Google AI Studio](https://aistudio.google.com/app/apikey) 免费获取

#### 2️⃣ 通用设置

| 选项           | 说明                                       |
| -------------- | ------------------------------------------ |
| **目标语言**   | 选择翻译目标语言（默认：中文）             |
| **同语言跳过** | 自动检测并跳过已是目标语言的文章，节省资源 |
| **最大字符数** | 限制发送给 AI 的文本长度（建议 3000-5000） |

#### 3️⃣ 订阅源设置

在配置页面底部的订阅源列表中：

- ✅ **翻译标题**：勾选后自动翻译该订阅源的标题
- ✅ **生成摘要**：勾选后为文章生成 AI 摘要

> 💡 **提示**：建议仅对重要的或外语订阅源开启，以节省 API 调用

### 🚀 使用

配置完成后，插件会自动运行：

1. FreshRSS 抓取新文章时，插件自动处理
2. 在文章列表查看翻译后的标题
3. 打开文章详情查看 AI 生成的摘要（显示在文章开头）

### 📊 Token 统计

插件内置了 Token 使用统计功能：

- 实时跟踪 DeepSeek、通义千问和 Gemini 的 Token 消耗
- 自动计算预估费用（基于官方定价）
- 可在配置页面查看详细统计数据
- 支持重置统计记录

> 💡 **提示**：Gemini 2.0 Flash 目前完全免费，适合大量使用

### 🔍 诊断日志

配置页面提供详细的诊断信息帮助排查问题：

#### API Key 状态验证

- **保存配置时**：自动验证每个 API Key 的有效性
- **验证结果显示**：
  - ✓ VALID：Key 有效，可正常使用
  - ✗ INVALID：Key 格式错误或已过期，显示具体错误信息
  - ✗ ERROR：验证过程中发生异常，显示错误详情
  - EMPTY：未设置 API Key
  - SKIPPED：提供的 Key 为空

#### 智能容错信息

- **翻译出错自动 Fallback**：当首选服务失败时，自动尝试 Google 翻译
- **摘要多级兜底**：
  1. 尝试首选服务（DeepSeek/Qwen/Gemini）
  2. 失败 → 尝试 Google 智能截断
  3. 仍失败 → 最终简单截断（保证摘要可用）

#### 优化建议

- 运行时错误会在日志中显示完整的错误栈跟踪
- 支持按服务商筛选日志
- Token 消耗记录帮助优化成本

### ⚠️ 注意事项

- **API 成本**：DeepSeek 和通义千问会产生调用费用，请查看对应服务商的费率
- **处理时间**：AI 摘要功能会增加少量抓取时间
- **依赖环境**：需要 PHP mbstring 扩展（一般已默认安装）

### 🙏 致谢

本项目受到 [FreshRSS-TranslateTitlesCN](https://github.com/jacob2826/FreshRSS-TranslateTitlesCN) 的启发，感谢 [@jacob2826](https://github.com/jacob2826)

### 📝 许可证

[MIT License](LICENSE)

### 🐛 问题反馈

如有问题或建议，欢迎提交 [Issue](https://github.com/fishyo/TranslateDigest/issues)

---

## English

### 📖 Introduction

**TranslateDigest** is a powerful FreshRSS extension designed to enhance your RSS reading experience through AI technology. It automatically translates article titles and generates intelligent summaries using advanced Large Language Models (LLM).

### ✨ Key Features

- **🌍 Multi-language Title Translation**  
  Automatically translate article titles to Chinese, English, Japanese, French, or German

- **🤖 AI-Powered Summaries**  
  Integrated with DeepSeek and Qwen models to extract core content

- **⚙️ Flexible Feed Management**  
  Granular control per feed - enable translation or summarization for specific feeds

- **🔌 Multiple Service Providers**

  - **Google Translate**: Free, fast, suitable for basic title translation
  - **DeepSeek**: Powerful AI model supporting both translation and summarization
  - **Qwen (Tongyi Qianwen)**: Alibaba Cloud AI service with translation and summary capabilities
  - **Google Gemini**: Google's latest AI model with translation and summary features (Free)

- **💰 Cost Control**  
  Built-in token usage statistics and character limits to monitor API costs

- **🛡️ Intelligent Fault Tolerance**
  - Automatic Fallback to Google service on API failure
  - Summarization never fails (multi-level fallback strategy)
  - Smart error classification to avoid wasting tokens (permanent errors not retried)

### 📦 Installation

1. **Download the Extension**
   `ash
cd /path/to/FreshRSS/extensions
git clone https://github.com/fishyo/TranslateDigest.git
`

2. **Enable the Extension**
   - Log into FreshRSS
   - Navigate to Settings → Extensions
   - Find TranslateDigest and enable it

### ⚙️ Configuration

Go to the FreshRSS extension management page and click configure for TranslateDigest.

#### 1️⃣ Choose Translation Service

- **Google Translate** (Default)

  - No API Key required
  - Title translation only
  - Completely free

- **DeepSeek / Qwen / Gemini** (Recommended for summaries)
  - Requires API Key
  - Supports both translation and summarization
  - Customizable models (e.g., `deepseek-chat`, `qwen-plus`, `gemini-2.0-flash-exp`)
  - Gemini API Key available free at [Google AI Studio](https://aistudio.google.com/app/apikey)

#### 2️⃣ General Settings

| Option                 | Description                                              |
| ---------------------- | -------------------------------------------------------- |
| **Target Language**    | Choose translation target language (default: Chinese)    |
| **Skip Same Language** | Auto-detect and skip articles already in target language |
| **Max Characters**     | Limit text length sent to AI (recommended: 3000-5000)    |

#### 3️⃣ Feed Settings

In the feed list at the bottom of the configuration page:

- ✅ **Translate Title**: Auto-translate titles for this feed
- ✅ **Generate Summary**: Generate AI summaries for articles

> 💡 **Tip**: Enable only for important or foreign-language feeds to save API calls

### 🚀 Usage

After configuration, the extension runs automatically:

1. When FreshRSS fetches new articles, the plugin processes them
2. View translated titles in the article list
3. Open article details to see AI-generated summaries (displayed at the beginning)

### 📊 Token Statistics

The plugin includes built-in token usage tracking:

- Real-time monitoring of DeepSeek, Qwen and Gemini token consumption
- Automatic cost estimation (based on official pricing)
- View detailed statistics in the configuration page
- Support for resetting statistics

> 💡 **Tip**: Gemini 2.0 Flash is currently completely free, ideal for heavy usage

### 🔍 Diagnostic Logs

The configuration page provides detailed diagnostic information to troubleshoot issues:

#### API Key Status Verification

- **On Configuration Save**: Automatically validate each API Key
- **Validation Result Display**:
  - ✓ VALID: Key is valid and ready to use
  - ✗ INVALID: Key format error or expired, with specific error message
  - ✗ ERROR: Exception during validation, with error details
  - EMPTY: API Key not set
  - SKIPPED: Provided Key is empty

#### Intelligent Fault Tolerance Information

- **Translation Error Auto-Fallback**: When primary service fails, automatically try Google Translate
- **Multi-level Summarization Fallback**:
  1. Try primary service (DeepSeek/Qwen/Gemini)
  2. Failure → Try Google intelligent truncation
  3. Still failure → Final simple truncation (ensure summary availability)

#### Optimization Suggestions

- Runtime errors display complete error stack traces in logs
- Support filtering logs by service provider
- Token consumption records help optimize costs

### ⚠️ Notes

- **API Costs**: DeepSeek and Qwen services incur usage fees - check provider pricing
- **Processing Time**: AI summary feature adds slight delay to feed fetching
- **Requirements**: Requires PHP mbstring extension (usually pre-installed)

### 🙏 Acknowledgments

This project is inspired by [FreshRSS-TranslateTitlesCN](https://github.com/jacob2826/FreshRSS-TranslateTitlesCN). Thanks to [@jacob2826](https://github.com/jacob2826)

### 📝 License

[MIT License](LICENSE)

### 🐛 Feedback

For issues or suggestions, please submit an [Issue](https://github.com/fishyo/TranslateDigest/issues)

---

## 📋 更新日志

### 2025-12-04

**✨ 功能改进**
- 修复配置保存问题（移除 initializeDefaultConfig 导致的冲突）
- 改进摘要服务的 Fallback 策略（支持多级兜底）
- 增强 Google Provider 的摘要能力（智能在句末截断）
- 优化 API Key 验证日志（显示具体验证结果）

**🔧 技术优化**
- 添加错误分类机制（避免浪费 Token 重试永久性错误）
- 实现完整的 Fallback 链（翻译和摘要都有兜底方案）
- 改进 TextUtil 文本处理（HTML 实体解码、标点空格修复）
- 优化 Token 计数和成本控制

**📝 文档更新**
- 添加诊断日志说明
- 更新 API Key 状态验证文档
- 补充智能容错机制说明
