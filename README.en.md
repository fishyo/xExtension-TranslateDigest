# TranslateDigest (FreshRSS Extension)

## Introduction

**TranslateDigest** is a powerful FreshRSS extension designed to enhance your reading efficiency using AI technology. It not only automatically translates article titles from subscribed feeds into your preferred language but also leverages advanced Large Language Models (LLM) to generate concise summaries for long articles. Whether you subscribe to a large number of foreign language news sources or want to quickly filter daily news, TranslateDigest has you covered.

### Core Features

- **Multi-language title translation**: Supports automatic translation of article titles into Chinese, English, Japanese, French, or German.
- **AI intelligent summarization**: Integrates DeepSeek and Qwen (Tongyi Qianwen) large models to automatically extract core content and generate high-quality summaries.
- **Flexible feed management**: Supports per-feed granular control. You can enable "translation" or "summarization" features for specific feeds individually.
- **Multi-service support**:
  - **Google Translate**: Free, fast, suitable for basic title translation.
  - **DeepSeek / Qwen**: Powerful AI models that support both title translation and content summarization.
- **Cost control**: Built-in token consumption statistics and character limit functionality to help you effectively monitor and control API usage costs.

## Configuration

Before using this plugin, you need to perform simple configuration. Please find TranslateDigest in the extension management page of FreshRSS and click the configure button.

### 1. Select translation service

- **Google Translate**: Default option, no API Key required, only supports title translation.
- **DeepSeek / Qwen**: Recommended option. To use the "generate summary" feature, you must select one of them.
  - Need to apply for and fill in the corresponding **API Key** by yourself.
  - Supports custom model names (such as `deepseek-chat`, `qwen-plus`, etc.).

### 2. General settings

- **Target language**: Select the language you want to translate titles into (default is Chinese).
- **Skip same language**: It is recommended to enable this option. After enabling, if the original language is detected to be the same as the target language, translation will be automatically skipped to save resources.
- **Maximum character count**: Set the maximum number of characters for articles sent to AI for processing (recommended 3000-5000). The excess part will be truncated to avoid excessive token consumption or exceeding model limits.

### 3. Feed Settings

At the bottom of the configuration page, you will see a list of all subscribed feeds.

- **Translate title**: After checking, new articles grabbed from this feed will have their titles translated.
- **Generate summary**: After checking, new articles grabbed from this feed will be summarized by AI and displayed in the body.
- _Tip: It is recommended to enable this feature only for important or foreign language feeds to save API calls._

## Usage

1.  **Install the plugin**:

    - Download the `TranslateDigest` folder and place it in the `extensions` folder under your FreshRSS installation directory.
    - Or clone to this directory via git: `git clone https://github.com/fishyo/TranslateDigest.git`

2.  **Enable the plugin**:

    - Log in to FreshRSS, click the settings icon in the upper right corner, and go to "Management" -> "Extensions".
    - Find "TranslateDigest" in the list and click enable.

3.  **Daily use**:
    - After the plugin is configured, it will run automatically in the background of FreshRSS.
    - Whenever FreshRSS grabs new articles, the plugin will automatically process them according to your settings.
    - **View effect**: On the reading list page, you will see the translated title; click the article to enter the detail page, the summary will usually be displayed at the beginning of the article content.

## Notes

- **API cost**: Using DeepSeek or Qwen services will incur API call fees. Please refer to the official instructions of the corresponding service provider for specific rates. The plugin provides token statistics for your reference.
- **Processing time**: Enabling the AI summary feature may slightly increase the time required for feed grabbing because it needs to wait for the AI interface to return data.
- **Dependency environment**: This plugin relies on the PHP `mbstring` extension. Please ensure that this extension is installed on your server (personal testing shows that you don’t need to worry about this, just put it in the extension folder and it will work).

---

## Acknowledgments

This project is inspired by [FreshRSS-TranslateTitlesCN](https://github.com/jacob2826/FreshRSS-TranslateTitlesCN). Thanks to [@jacob2826](https://github.com/jacob2826)

---

_If you have any questions or suggestions, please submit an Issue._
