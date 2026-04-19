=== Super Fast Blog AI – AI Content Generator, Repurposer & SEO Writer ===
Contributors: waatechdigital, khan9, ikefti
Tags: ai writer, content generator, seo content, repurpose, blog ai
Requires at least: 6.3
Tested up to: 6.9
Stable tag: 2.0.0
Requires PHP: 8.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI-powered content generation for WordPress — 7 AI providers, content repurposing, brand voice, cost tracking, model routing, and more.

== Description ==

**Super Fast Blog AI** is a full-featured AI content platform built into WordPress. Write blog posts, repurpose content across 6 social platforms, track API costs, enforce brand voice, and intelligently route each task to the best AI model — all from your admin dashboard.

Whether you use OpenAI, Anthropic Claude, Google Gemini, DeepSeek, Mistral, OpenRouter, or a local Ollama instance, the plugin works with your preferred provider and switches between them automatically based on content type.

=== 🤖 7 AI Providers Supported ===

Connect one or all — the plugin works with whichever provider you configure:

* **OpenAI** — GPT-4o, GPT-4.1, GPT-4o-mini, o3, o4-mini, and more
* **Anthropic** — Claude Sonnet, Claude Haiku, Claude Opus
* **Google** — Gemini 2.5 Pro/Flash, Gemini 2.0 Flash, Gemini 1.5 Pro/Flash
* **OpenRouter** — Access 100+ models through a single API key
* **DeepSeek** — deepseek-chat, deepseek-reasoner, deepseek-coder
* **Mistral** — Mistral Large, Small, Nemo, Codestral
* **Ollama** — Run any local model (Llama, Mistral, Gemma, Phi, Qwen, DeepSeek-R1) at zero cost

The first provider you configure is automatically set as your default.

=== ✍️ AI Title & Article Generator ===

* Generate 5–10 title ideas from any topic in seconds
* Copy any title with one click
* Saved generations grouped by topic with date stamps
* Generate full SEO blog posts saved as WordPress drafts
* Choose language, tone, and writing style
* Table of contents, pros/cons sections included
* Auto-publish or save as draft with category assignment

=== ♻️ Content Repurposing (6 Platforms) ===

Transform any blog post or pasted content into platform-native pieces:

* **Twitter / X** — Threaded tweets with hook, body, and CTA
* **LinkedIn** — Professional post with hashtags and engagement question
* **Email** — Subject line, preview text, body, and CTA button
* **Facebook** — Casual friendly post with soft CTA
* **Instagram** — Caption with visual hook + hashtag block
* **YouTube** — Video title, description, and timestamped script outline

**Hashtag Language control** — choose English hashtags, source-language hashtags, or both. Server-side language detection (Unicode script ranges + Latin stopword scoring) ensures the AI always writes in the correct language — never guesses from the topic or geography.

=== 🎨 Brand Voice ===

Define your unique writing style once and apply it to every generation automatically. Ensures consistent tone, vocabulary, and personality across all AI-generated content.

=== 📅 Content Calendar ===

Generate a full AI-powered topic plan for upcoming posts. Plan weeks of content in minutes based on your niche and target audience.

=== 🔗 Internal Links ===

Smart internal linking suggestions powered by your site's keyword index. Automatically recommend relevant posts to link to within new articles.

=== 📈 Performance Tracking ===

Track Search Console impressions, clicks, and ranking trends for your published posts directly inside WordPress.

=== 💰 Cost Tracker ===

* Real-time API cost monitoring per provider, model, and feature tag
* Monthly summary with total spend, generation count, and average cost
* Budget alerts at 50%, 80%, and 100% of your monthly limit
* Cost breakdown by provider and feature (title, article, repurpose, etc.)
* Filterable generation log with per-row token and cost details
* AI-powered cost optimization suggestions (model downgrade recommendations)
* One-click log pruning for old records

=== 🔀 Model Routing ===

Route each content type to the best model for the job:

* Assign specific providers and models per content type (blog, product, social, meta, email)
* Set custom max tokens and temperature per rule
* Load recommended defaults with one click
* Rules can be updated or deleted at any time

=== ⚙️ Settings ===

* **Providers tab** — 2-column provider cards with real logos, save API keys, set default provider and model
* **Publishing tab** — Default post status, category (WordPress category dropdown), author, featured image source
* **Images tab** — Configure Pixabay API key for featured image generation
* **General tab** — Monthly budget, log retention, plugin-wide options

== API Endpoints Used ==

The plugin connects to the following external APIs based on which providers you configure:

1. **OpenAI API** — `https://api.openai.com/v1/chat/completions`
2. **Anthropic API** — `https://api.anthropic.com/v1/messages`
3. **Google Gemini API** — `https://generativelanguage.googleapis.com/v1beta/`
4. **OpenRouter API** — `https://openrouter.ai/api/v1/chat/completions`
5. **DeepSeek API** — `https://api.deepseek.com/v1/chat/completions`
6. **Mistral API** — `https://api.mistral.ai/v1/chat/completions`
7. **Ollama (local)** — `http://localhost:11434/api/generate` (no external calls)
8. **Pixabay API** — `https://pixabay.com/api/` (featured image search)

Only APIs for providers you have configured and are actively using will receive requests.

== Privacy and Data Collection ==

* API keys are stored encrypted in the WordPress options table and never transmitted except to their respective provider
* Prompts (your content) are sent to whichever AI provider you configure — review each provider's privacy policy
* No usage data is sent to the plugin developers
* Generation logs are stored locally in your WordPress database and can be pruned or deleted at any time

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/` or install via the WordPress plugin installer
2. Activate the plugin through the **Plugins** menu in WordPress
3. Navigate to **Super Fast Blog AI → Settings → Providers**
4. Add your API key for at least one AI provider
5. Start generating content from the dashboard

== Frequently Asked Questions ==

= Which AI provider should I use? =
Any of the 7 supported providers work great. For best quality use OpenAI GPT-4o or Anthropic Claude Sonnet. For lowest cost use GPT-4o-mini, Claude Haiku, Gemini Flash, or a local Ollama model (free).

= Can I use multiple providers at the same time? =
Yes. Configure as many as you like and use Model Routing to assign specific providers to specific content types automatically.

= Does repurposing work in languages other than English? =
Yes. The plugin detects the source language server-side (using Unicode script analysis) and instructs the AI to write in that language. Hashtag language is separately configurable — you can output hashtags in English, the source language, or both.

= Are generated articles saved automatically? =
Yes. All generated articles are saved as WordPress drafts by default. You can change the default post status in Settings → Publishing.

= Is there a usage cost? =
The plugin itself is free. You pay only for the AI API usage directly to each provider. Use the built-in Cost Tracker to monitor and budget your spending. Ollama (local) is always free.

= Does it work with Yoast SEO or Rank Math? =
Yes. SEO meta keywords and descriptions generated by the plugin are compatible with both Yoast SEO and Rank Math.

= How do I run Ollama locally? =
Install Ollama from ollama.com, pull a model (`ollama pull llama3.3`), then add `http://localhost:11434` as the Ollama endpoint in Settings → Providers.

== Screenshots ==

1. Dashboard — stats cards and feature module grid
2. Title Generator — generate and save AI title ideas
3. Article Generator — full SEO blog post creation
4. Repurpose Content — 6-platform content repurposing with hashtag language control
5. Brand Voice — writing style configuration
6. Content Calendar — AI topic planning
7. Cost Tracker — API spend monitoring and budget alerts
8. Model Routing — per-content-type model assignment
9. Settings — provider configuration with logos and API keys

== Changelog ==

= 2.0.0 (April 2025) =
* **New:** Support for 7 AI providers — OpenAI, Anthropic, Google Gemini, OpenRouter, DeepSeek, Mistral, Ollama
* **New:** Content Repurposing for 6 platforms (Twitter/X, LinkedIn, Email, Facebook, Instagram, YouTube)
* **New:** Hashtag Language control — English, source language, or both
* **New:** Server-side language detection for accurate multilingual output
* **New:** Brand Voice module — apply writing style to every generation
* **New:** Content Calendar — AI-generated topic planning
* **New:** Internal Links — smart linking suggestions
* **New:** Performance Tracking — Search Console integration
* **New:** Cost Tracker — per-provider/model/feature spend monitoring with budget alerts
* **New:** Model Routing — route content types to the best model automatically
* **New:** Copy button on every generated title
* **New:** Saved generations grouped card UI with topic, count, and date
* **New:** Default Categories changed to WordPress category multi-select dropdown
* **New:** Provider logos via Simple Icons CDN with emoji fallback
* **New:** Auto-set Default Provider on first API key save
* **New:** Guard against selecting unconfigured providers as default
* **Improved:** Dashboard redesigned — stats cards with icon-left layout and features grid
* **Improved:** Settings hash tab switching fixed (hashchange listener)
* **Improved:** Model Routing empty state redesigned with modern UI
* **Improved:** Cost calculation bug fixed (this_month_cost key, budget key names)
* **Requires PHP:** 8.1+ (upgraded from 8.0)

= 1.0.1 (June 12, 2025) =
* Some technical issue fixes.

= 1.0.0 (March 6, 2025) =
* Initial launch.

== Upgrade Notice ==

= 2.0.0 =
Major update. Adds 7 AI providers, content repurposing, cost tracking, model routing, brand voice, and a fully redesigned admin interface. Requires PHP 8.1+. Back up your site before upgrading.
