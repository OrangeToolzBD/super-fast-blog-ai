<?php
/**
 * Settings admin page template.
 *
 * Variables:
 *   $providers     array  — slug => {slug, name, has_key}
 *   $voice_summary array|null
 *   $settings      array  — safe settings (no raw API keys)
 *   $nonce         string
 *   $api_base      string
 *
 * @package SuperFastBlogAI
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$content  = $settings['content']        ?? [];
$seo      = $settings['seo']            ?? [];
$images   = $settings['images']         ?? [];
$pub      = $settings['publishing']     ?? [];
$schedule = $settings['schedule']       ?? [];
$sc       = $settings['search_console'] ?? [];

$provider_icons = [
	'anthropic'  => '🧠',
	'openai'     => '🤖',
	'gemini'     => '💎',
	'mistral'    => '🌊',
	'groq'       => '⚡',
	'cohere'     => '🔵',
	'deepseek'   => '🔍',
	'pixabay'    => '🖼️',
	'unsplash'   => '📷',
	'dalle3'     => '🎨',
];
?>
<div class="wrap sfba-admin-wrap" style="max-width:1060px;">

	<!-- Hero -->
	<div class="sfba-dash-hero" style="margin-bottom:20px;">
		<div class="sfba-dash-hero-brand">
			<div class="sfba-dash-logo-ring">
				<img src="<?php echo esc_url( SFBA_PLUGIN_URL . 'assets/images/menu_icon.svg' ); ?>" alt="" style="width:28px;height:28px;">
			</div>
			<div>
				<h1 style="margin:0 0 2px;"><?php esc_html_e( 'Settings', 'super-fast-blog-ai' ); ?></h1>
				<span class="sfba-dash-ver">v<?php echo esc_html( SFBA_VERSION ); ?></span>
			</div>
		</div>
	</div>

	<div id="sfba-settings-notice" class="sfba-notice" style="display:none;margin-bottom:12px;"></div>

	<!-- Tabs -->
	<div class="sfba-tabs" style="margin-bottom:20px;">
		<button type="button" class="sfba-tab-btn sfba-tab-active" data-tab="providers">
			🔌 <?php esc_html_e( 'Providers', 'super-fast-blog-ai' ); ?>
		</button>
		<button type="button" class="sfba-tab-btn" data-tab="content">
			✍️ <?php esc_html_e( 'Content', 'super-fast-blog-ai' ); ?>
		</button>
		<button type="button" class="sfba-tab-btn" data-tab="seo">
			🔍 <?php esc_html_e( 'SEO', 'super-fast-blog-ai' ); ?>
		</button>
		<button type="button" class="sfba-tab-btn" data-tab="images">
			🖼️ <?php esc_html_e( 'Images', 'super-fast-blog-ai' ); ?>
		</button>
		<button type="button" class="sfba-tab-btn" data-tab="publishing">
			📤 <?php esc_html_e( 'Publishing', 'super-fast-blog-ai' ); ?>
		</button>
		<button type="button" class="sfba-tab-btn" data-tab="schedule">
			⏰ <?php esc_html_e( 'Schedule', 'super-fast-blog-ai' ); ?>
		</button>
		<button type="button" class="sfba-tab-btn" data-tab="search-console">
			📊 <?php esc_html_e( 'Search Console', 'super-fast-blog-ai' ); ?>
		</button>
		<button type="button" class="sfba-tab-btn" data-tab="advanced">
			🛠️ <?php esc_html_e( 'Advanced', 'super-fast-blog-ai' ); ?>
		</button>
	</div>

	<!-- ===== TAB: Providers ===== -->
	<div id="sfba-tab-providers" class="sfba-tab-panel">

		<div class="sfba-info-box" style="margin-bottom:16px;">
			<p><?php esc_html_e( 'Add API keys for AI providers. Keys are encrypted before being saved. At least one provider is required for content generation.', 'super-fast-blog-ai' ); ?></p>
		</div>

		<?php
		$provider_colors = [
			'anthropic' => [ 'accent' => '#e07b39', 'bg' => '#fff8f4' ],
			'openai'    => [ 'accent' => '#10a37f', 'bg' => '#f0fdf9' ],
			'gemini'    => [ 'accent' => '#4285f4', 'bg' => '#eff6ff' ],
			'mistral'   => [ 'accent' => '#2d75ca', 'bg' => '#eff6ff' ],
			'groq'      => [ 'accent' => '#f55036', 'bg' => '#fff5f4' ],
			'cohere'    => [ 'accent' => '#39594d', 'bg' => '#f0fdf4' ],
			'deepseek'  => [ 'accent' => '#4d6bfe', 'bg' => '#f0f0ff' ],
			'ollama'    => [ 'accent' => '#8b5cf6', 'bg' => '#f5f3ff' ],
		];
		// Simple Icons CDN logos — only for providers with confirmed icons.
		$provider_logos = [
			'anthropic' => 'https://cdn.simpleicons.org/anthropic/e07b39',
			'openai'    => 'https://cdn.simpleicons.org/openai/10a37f',
			'gemini'    => 'https://cdn.simpleicons.org/googlegemini/4285f4',
			'mistral'   => 'https://cdn.simpleicons.org/mistralai/ff7000',
			'cohere'    => 'https://cdn.simpleicons.org/cohere/39594d',
			'deepseek'  => 'https://cdn.simpleicons.org/deepseek/4d6bfe',
			'ollama'    => 'https://cdn.simpleicons.org/ollama/8b5cf6',
			// groq: not in Simple Icons yet — falls back to emoji
		];
		?>
		<div style="display:grid;grid-template-columns:repeat(2,1fr);gap:16px;">
		<?php foreach ( $providers as $slug => $p ) :
			$icon      = $provider_icons[ $slug ] ?? '🤖';
			$colors    = $provider_colors[ $slug ] ?? [ 'accent' => '#6b7280', 'bg' => '#f9fafb' ];
			$logo_url  = $provider_logos[ $slug ] ?? null;
			$connected = ( 'ollama' === $slug ) ? $ollama_configured : $p['has_key'];
		?>
		<div class="sfba-card sfba-provider-card" data-provider="<?php echo esc_attr( $slug ); ?>"
		     style="border-top:3px solid #e5e7eb;display:flex;flex-direction:column;gap:14px;padding:18px;">

			<!-- Header: icon + name + status badge -->
			<div style="display:flex;align-items:center;gap:12px;">
				<div style="width:44px;height:44px;flex-shrink:0;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:22px;background:<?php echo esc_attr( $colors['bg'] ); ?>;border:1.5px solid <?php echo esc_attr( $colors['accent'] ); ?>44;">
					<?php if ( $logo_url ) : ?>
					<img src="<?php echo esc_url( $logo_url ); ?>"
					     alt="<?php echo esc_attr( $p['name'] ); ?>"
					     width="24" height="24"
					     style="object-fit:contain;display:block;"
					     onerror="this.style.display='none';this.nextElementSibling.style.display='inline';">
					<span style="display:none;font-size:22px;"><?php echo esc_html( $icon ); ?></span>
					<?php else : ?>
					<span style="font-size:22px;"><?php echo esc_html( $icon ); ?></span>
					<?php endif; ?>
				</div>
				<div>
					<strong style="font-size:14px;display:block;color:#111827;"><?php echo esc_html( $p['name'] ); ?></strong>
					<?php if ( $connected ) : ?>
					<span class="sfba-badge sfba-badge-green" style="margin-top:3px;">✓ <?php esc_html_e( 'Connected', 'super-fast-blog-ai' ); ?></span>
					<?php else : ?>
					<span class="sfba-badge sfba-badge-gray" style="margin-top:3px;"><?php esc_html_e( 'Not connected', 'super-fast-blog-ai' ); ?></span>
					<?php endif; ?>
				</div>
			</div>

			<?php if ( 'ollama' === $slug ) : ?>
			<!-- Ollama: Base URL + optional Bearer Token -->
			<div style="display:flex;flex-direction:column;gap:8px;">
				<div>
					<label class="sfba-label" for="sfba-ollama-base-url"><?php esc_html_e( 'Base URL', 'super-fast-blog-ai' ); ?></label>
					<input type="url" id="sfba-ollama-base-url" class="sfba-input"
					       value="<?php echo esc_attr( $ollama_base_url ?: 'http://localhost:11434' ); ?>"
					       placeholder="http://localhost:11434" style="width:100%;">
					<span class="sfba-field-hint"><?php esc_html_e( 'Address of your running Ollama instance.', 'super-fast-blog-ai' ); ?></span>
				</div>
				<div>
					<label class="sfba-label" for="sfba-ollama-token">
						<?php esc_html_e( 'Bearer Token', 'super-fast-blog-ai' ); ?>
						<span style="font-weight:400;color:#9ca3af;"> — <?php esc_html_e( 'optional', 'super-fast-blog-ai' ); ?></span>
					</label>
					<input type="password" id="sfba-ollama-token" class="sfba-input"
					       placeholder="<?php echo esc_attr( $ollama_has_token ? '••••••••••••••••' : __( 'Only for secured remote instances', 'super-fast-blog-ai' ) ); ?>"
					       style="width:100%;" autocomplete="new-password">
				</div>
			</div>
			<div style="display:flex;gap:8px;flex-wrap:wrap;">
				<button type="button" id="sfba-ollama-save-btn" class="sfba-btn sfba-btn-primary sfba-btn-sm">
					<?php esc_html_e( 'Save', 'super-fast-blog-ai' ); ?>
				</button>
				<button type="button" class="sfba-btn sfba-btn-secondary sfba-btn-sm sfba-test-key-btn" data-provider="ollama">
					🔌 <?php esc_html_e( 'Test', 'super-fast-blog-ai' ); ?>
				</button>
				<?php if ( $ollama_has_token ) : ?>
				<button type="button" class="sfba-btn sfba-btn-danger sfba-btn-sm sfba-remove-key-btn" data-provider="ollama">
					🗑 <?php esc_html_e( 'Remove Token', 'super-fast-blog-ai' ); ?>
				</button>
				<?php endif; ?>
			</div>

			<?php else : ?>
			<!-- Standard API-key provider -->
			<div>
				<label class="sfba-label"><?php esc_html_e( 'API Key', 'super-fast-blog-ai' ); ?></label>
				<input type="password"
				       class="sfba-input sfba-api-key-input"
				       data-provider="<?php echo esc_attr( $slug ); ?>"
				       placeholder="<?php echo esc_attr( $p['has_key'] ? '••••••••••••••••' : __( 'Enter API key…', 'super-fast-blog-ai' ) ); ?>"
				       style="width:100%;" autocomplete="new-password">
			</div>
			<div style="display:flex;gap:8px;flex-wrap:wrap;">
				<button type="button" class="sfba-btn sfba-btn-primary sfba-btn-sm sfba-save-key-btn"
				        data-provider="<?php echo esc_attr( $slug ); ?>">
					<?php esc_html_e( 'Save', 'super-fast-blog-ai' ); ?>
				</button>
				<?php if ( $p['has_key'] ) : ?>
				<button type="button" class="sfba-btn sfba-btn-secondary sfba-btn-sm sfba-test-key-btn"
				        data-provider="<?php echo esc_attr( $slug ); ?>">
					🔌 <?php esc_html_e( 'Test', 'super-fast-blog-ai' ); ?>
				</button>
				<button type="button" class="sfba-btn sfba-btn-danger sfba-btn-sm sfba-remove-key-btn"
				        data-provider="<?php echo esc_attr( $slug ); ?>">
					🗑 <?php esc_html_e( 'Remove', 'super-fast-blog-ai' ); ?>
				</button>
				<?php endif; ?>
			</div>
			<?php endif; ?>

			<!-- Inline feedback message -->
			<div class="sfba-provider-feedback" style="display:none;font-size:12.5px;padding:8px 12px;border-radius:8px;"></div>

		</div>
		<?php endforeach; ?>
		</div>

		<!-- Default provider / model -->
		<div class="sfba-card" style="margin-top:16px;">
			<h3><?php esc_html_e( 'Default Provider', 'super-fast-blog-ai' ); ?></h3>
			<div style="display:flex;flex-direction:column;gap:14px;margin-top:12px;">
				<div>
					<label class="sfba-label"><?php esc_html_e( 'Default Provider', 'super-fast-blog-ai' ); ?></label>
					<select id="sfba-default-provider" class="sfba-input" style="width:260px;">
						<?php foreach ( $providers as $slug => $p ) :
							$is_cfg = ( 'ollama' === $slug ) ? $ollama_configured : $p['has_key'];
						?>
						<option value="<?php echo esc_attr( $slug ); ?>"
						        data-configured="<?php echo $is_cfg ? '1' : '0'; ?>"
						        <?php selected( $settings['default_provider'] ?? '', $slug ); ?>>
							<?php echo esc_html( $p['name'] ); ?>
						</option>
						<?php endforeach; ?>
					</select>
					<div id="sfba-default-provider-warning"
					     style="display:none;margin-top:8px;padding:10px 14px;background:#fffbeb;border:1px solid #fcd34d;border-radius:8px;font-size:12.5px;color:#92400e;">
						⚠️ <?php esc_html_e( 'This provider is not configured yet. Please add an API key in the Providers section above, then select it as your default.', 'super-fast-blog-ai' ); ?>
					</div>
				</div>
				<div>
					<label class="sfba-label"><?php esc_html_e( 'Default Model', 'super-fast-blog-ai' ); ?></label>
					<input type="text" id="sfba-default-model" class="sfba-input" style="width:280px;"
					       value="<?php echo esc_attr( $settings['default_model'] ?? '' ); ?>"
					       placeholder="e.g. claude-haiku-4-5-20251001">
					<p class="sfba-muted"><?php esc_html_e( 'Leave blank to use the provider\'s default model. Model Routing rules override this.', 'super-fast-blog-ai' ); ?></p>
				</div>
				<div style="display:flex;align-items:center;justify-content:space-between;">
					<div>
						<strong style="font-size:13px;"><?php esc_html_e( 'Enable Model Routing', 'super-fast-blog-ai' ); ?></strong>
						<p class="sfba-muted"><?php esc_html_e( 'Route different content types to the most suitable provider and model.', 'super-fast-blog-ai' ); ?></p>
					</div>
					<label class="sfba-toggle">
						<input type="checkbox" id="sfba-routing-enabled"
						       <?php checked( $settings['routing_enabled'] ?? false ); ?>>
						<span class="sfba-toggle-slider"></span>
					</label>
				</div>
				<div>
					<button type="button" id="sfba-save-provider-btn" class="sfba-btn sfba-btn-primary">
						<?php esc_html_e( 'Save Provider Settings', 'super-fast-blog-ai' ); ?>
					</button>
				</div>
			</div>
		</div>

	</div><!-- /providers -->

	<!-- ===== TAB: Content ===== -->
	<div id="sfba-tab-content" class="sfba-tab-panel" style="display:none;">
		<div class="sfba-card">
			<h3><?php esc_html_e( 'Content Generation', 'super-fast-blog-ai' ); ?></h3>
			<div style="display:flex;flex-direction:column;gap:16px;margin-top:12px;">

				<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
					<div>
						<label class="sfba-label"><?php esc_html_e( 'Language', 'super-fast-blog-ai' ); ?></label>
						<input type="text" id="sfba-content-language" class="sfba-input"
						       value="<?php echo esc_attr( $content['language'] ?? 'English' ); ?>"
						       placeholder="English">
					</div>
					<div>
						<label class="sfba-label"><?php esc_html_e( 'Writing Style', 'super-fast-blog-ai' ); ?></label>
						<select id="sfba-content-writing-style" class="sfba-input">
							<?php foreach ( [ 'informative', 'conversational', 'professional', 'creative', 'academic' ] as $style ) : ?>
							<option value="<?php echo esc_attr( $style ); ?>"
							        <?php selected( $content['writing_style'] ?? 'informative', $style ); ?>>
								<?php echo esc_html( ucfirst( $style ) ); ?>
							</option>
							<?php endforeach; ?>
						</select>
					</div>
					<div>
						<label class="sfba-label"><?php esc_html_e( 'Tone', 'super-fast-blog-ai' ); ?></label>
						<select id="sfba-content-tone" class="sfba-input">
							<?php foreach ( [ 'neutral', 'friendly', 'formal', 'casual', 'authoritative', 'empathetic' ] as $tone ) : ?>
							<option value="<?php echo esc_attr( $tone ); ?>"
							        <?php selected( $content['tone'] ?? 'neutral', $tone ); ?>>
								<?php echo esc_html( ucfirst( $tone ) ); ?>
							</option>
							<?php endforeach; ?>
						</select>
					</div>
					<div>
						<label class="sfba-label"><?php esc_html_e( 'Heading Tag', 'super-fast-blog-ai' ); ?></label>
						<select id="sfba-content-heading-tag" class="sfba-input">
							<?php foreach ( [ 'h2', 'h3', 'h4' ] as $tag ) : ?>
							<option value="<?php echo esc_attr( $tag ); ?>"
							        <?php selected( $content['heading_tag'] ?? 'h2', $tag ); ?>>
								<?php echo esc_html( strtoupper( $tag ) ); ?>
							</option>
							<?php endforeach; ?>
						</select>
					</div>
					<div>
						<label class="sfba-label"><?php esc_html_e( 'Target Word Count', 'super-fast-blog-ai' ); ?></label>
						<input type="number" id="sfba-content-word-count" class="sfba-input"
						       value="<?php echo esc_attr( $content['word_count'] ?? 1200 ); ?>"
						       min="300" max="10000" step="100">
					</div>
					<div>
						<label class="sfba-label"><?php esc_html_e( 'Number of Headings', 'super-fast-blog-ai' ); ?></label>
						<input type="number" id="sfba-content-heading-count" class="sfba-input"
						       value="<?php echo esc_attr( $content['heading_count'] ?? 5 ); ?>"
						       min="1" max="20">
					</div>
				</div>

				<hr style="border:none;border-top:1px solid #e5e7eb;margin:4px 0;">
				<h4 style="margin:0;"><?php esc_html_e( 'Content Sections', 'super-fast-blog-ai' ); ?></h4>

				<?php
				$toggles = [
					'subheadings' => __( 'Include Subheadings', 'super-fast-blog-ai' ),
					'faq'         => __( 'Include FAQ Section', 'super-fast-blog-ai' ),
					'toc'         => __( 'Include Table of Contents', 'super-fast-blog-ai' ),
					'pros_cons'   => __( 'Include Pros & Cons', 'super-fast-blog-ai' ),
				];
				foreach ( $toggles as $key => $label ) :
				?>
				<div style="display:flex;align-items:center;justify-content:space-between;">
					<strong style="font-size:13px;"><?php echo esc_html( $label ); ?></strong>
					<label class="sfba-toggle">
						<input type="checkbox" class="sfba-content-toggle" data-key="<?php echo esc_attr( $key ); ?>"
						       <?php checked( $content[ $key ] ?? false ); ?>>
						<span class="sfba-toggle-slider"></span>
					</label>
				</div>
				<?php endforeach; ?>

				<div>
					<button type="button" id="sfba-save-content-btn" class="sfba-btn sfba-btn-primary">
						<?php esc_html_e( 'Save Content Settings', 'super-fast-blog-ai' ); ?>
					</button>
				</div>
			</div>
		</div>
	</div><!-- /content -->

	<!-- ===== TAB: SEO ===== -->
	<div id="sfba-tab-seo" class="sfba-tab-panel" style="display:none;">
		<div class="sfba-card">
			<h3><?php esc_html_e( 'SEO Options', 'super-fast-blog-ai' ); ?></h3>
			<div style="display:flex;flex-direction:column;gap:14px;margin-top:12px;">

				<div style="display:flex;align-items:center;justify-content:space-between;">
					<div>
						<strong style="font-size:13px;"><?php esc_html_e( 'Generate Target Keywords', 'super-fast-blog-ai' ); ?></strong>
						<p class="sfba-muted"><?php esc_html_e( 'Suggest SEO keywords for each post during generation.', 'super-fast-blog-ai' ); ?></p>
					</div>
					<label class="sfba-toggle">
						<input type="checkbox" id="sfba-seo-keywords"
						       <?php checked( $seo['keywords'] ?? true ); ?>>
						<span class="sfba-toggle-slider"></span>
					</label>
				</div>

				<div style="display:flex;align-items:center;justify-content:space-between;">
					<div>
						<strong style="font-size:13px;"><?php esc_html_e( 'Generate Meta Description', 'super-fast-blog-ai' ); ?></strong>
						<p class="sfba-muted"><?php esc_html_e( 'Auto-fill the meta description field in Yoast SEO or RankMath.', 'super-fast-blog-ai' ); ?></p>
					</div>
					<label class="sfba-toggle">
						<input type="checkbox" id="sfba-seo-meta-desc"
						       <?php checked( $seo['meta_desc'] ?? true ); ?>>
						<span class="sfba-toggle-slider"></span>
					</label>
				</div>

				<div>
					<button type="button" id="sfba-save-seo-btn" class="sfba-btn sfba-btn-primary">
						<?php esc_html_e( 'Save SEO Settings', 'super-fast-blog-ai' ); ?>
					</button>
				</div>
			</div>
		</div>
	</div><!-- /seo -->

	<!-- ===== TAB: Images ===== -->
	<div id="sfba-tab-images" class="sfba-tab-panel" style="display:none;">
		<div class="sfba-card">
			<h3><?php esc_html_e( 'Image Source', 'super-fast-blog-ai' ); ?></h3>
			<div style="display:flex;flex-direction:column;gap:20px;margin-top:12px;">

				<!-- Default source selector -->
				<div>
					<label class="sfba-label"><?php esc_html_e( 'Default Image Source', 'super-fast-blog-ai' ); ?></label>
					<select id="sfba-images-source" class="sfba-input" style="width:240px;">
						<option value="dalle3"   <?php selected( $images['source'] ?? 'dalle3', 'dalle3' ); ?>>🎨 DALL-E 3 (AI generated)</option>
						<option value="pixabay"  <?php selected( $images['source'] ?? '', 'pixabay' ); ?>>📷 Pixabay (free stock)</option>
						<option value="unsplash" <?php selected( $images['source'] ?? '', 'unsplash' ); ?>>🖼️ Unsplash (free stock)</option>
					</select>
					<p class="sfba-muted"><?php esc_html_e( 'Which service to use when auto-generating featured images.', 'super-fast-blog-ai' ); ?></p>
				</div>

				<hr style="border:none;border-top:1px solid #e5e7eb;margin:0;">

				<!-- DALL-E 3 — reuses OpenAI key -->
				<div style="background:#f9fafb;border:1.5px solid #e5e7eb;border-radius:10px;padding:16px;">
					<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;">
						<div style="display:flex;align-items:center;gap:10px;">
							<span style="font-size:20px;">🎨</span>
							<div>
								<strong style="font-size:13px;">DALL-E 3</strong>
								<p class="sfba-muted" style="margin:2px 0 0;font-size:11px;"><?php esc_html_e( 'Uses your OpenAI API key — no separate key needed.', 'super-fast-blog-ai' ); ?></p>
							</div>
						</div>
						<?php if ( $image_keys['dalle3'] ) : ?>
						<span class="sfba-badge sfba-badge-green">✓ <?php esc_html_e( 'OpenAI Connected', 'super-fast-blog-ai' ); ?></span>
						<?php else : ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=sfba-settings' ) ); ?>#providers"
						   class="sfba-btn sfba-btn-secondary sfba-btn-sm" style="text-decoration:none;">
							🔌 <?php esc_html_e( 'Add OpenAI Key', 'super-fast-blog-ai' ); ?>
						</a>
						<?php endif; ?>
					</div>
				</div>

				<!-- Pixabay -->
				<div class="sfba-img-service-card" data-service="pixabay" style="background:#f9fafb;border:1.5px solid #e5e7eb;border-radius:10px;padding:16px;">
					<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:12px;">
						<div style="display:flex;align-items:center;gap:10px;">
							<span style="font-size:20px;">📷</span>
							<div>
								<strong style="font-size:13px;">Pixabay</strong>
								<p class="sfba-muted" style="margin:2px 0 0;font-size:11px;"><?php esc_html_e( 'Free stock photos. Get a free API key at pixabay.com/api/docs/', 'super-fast-blog-ai' ); ?></p>
							</div>
						</div>
						<?php if ( $image_keys['pixabay'] ) : ?>
						<span class="sfba-badge sfba-badge-green">✓ <?php esc_html_e( 'Connected', 'super-fast-blog-ai' ); ?></span>
						<?php else : ?>
						<span class="sfba-badge sfba-badge-gray"><?php esc_html_e( 'No key saved', 'super-fast-blog-ai' ); ?></span>
						<?php endif; ?>
					</div>
					<div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
						<input type="password"
						       class="sfba-input sfba-img-key-input"
						       placeholder="<?php echo esc_attr( $image_keys['pixabay'] ? '••••••••••••••••' : __( 'Paste Pixabay API key…', 'super-fast-blog-ai' ) ); ?>"
						       style="flex:1;min-width:180px;"
						       autocomplete="new-password">
						<button type="button" class="sfba-btn sfba-btn-primary sfba-btn-sm sfba-save-img-key-btn" data-service="pixabay">
							<?php esc_html_e( 'Save Key', 'super-fast-blog-ai' ); ?>
						</button>
						<?php if ( $image_keys['pixabay'] ) : ?>
						<button type="button" class="sfba-btn sfba-btn-danger sfba-btn-sm sfba-remove-img-key-btn" data-service="pixabay">
							<?php esc_html_e( 'Remove', 'super-fast-blog-ai' ); ?>
						</button>
						<?php endif; ?>
					</div>
					<div class="sfba-img-feedback" style="display:none;margin-top:8px;font-size:12px;"></div>
				</div>

				<!-- Unsplash -->
				<div class="sfba-img-service-card" data-service="unsplash" style="background:#f9fafb;border:1.5px solid #e5e7eb;border-radius:10px;padding:16px;">
					<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:12px;">
						<div style="display:flex;align-items:center;gap:10px;">
							<span style="font-size:20px;">🖼️</span>
							<div>
								<strong style="font-size:13px;">Unsplash</strong>
								<p class="sfba-muted" style="margin:2px 0 0;font-size:11px;"><?php esc_html_e( 'High-quality free photos. Get a free key at unsplash.com/developers', 'super-fast-blog-ai' ); ?></p>
							</div>
						</div>
						<?php if ( $image_keys['unsplash'] ) : ?>
						<span class="sfba-badge sfba-badge-green">✓ <?php esc_html_e( 'Connected', 'super-fast-blog-ai' ); ?></span>
						<?php else : ?>
						<span class="sfba-badge sfba-badge-gray"><?php esc_html_e( 'No key saved', 'super-fast-blog-ai' ); ?></span>
						<?php endif; ?>
					</div>
					<div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
						<input type="password"
						       class="sfba-input sfba-img-key-input"
						       placeholder="<?php echo esc_attr( $image_keys['unsplash'] ? '••••••••••••••••' : __( 'Paste Unsplash Access Key…', 'super-fast-blog-ai' ) ); ?>"
						       style="flex:1;min-width:180px;"
						       autocomplete="new-password">
						<button type="button" class="sfba-btn sfba-btn-primary sfba-btn-sm sfba-save-img-key-btn" data-service="unsplash">
							<?php esc_html_e( 'Save Key', 'super-fast-blog-ai' ); ?>
						</button>
						<?php if ( $image_keys['unsplash'] ) : ?>
						<button type="button" class="sfba-btn sfba-btn-danger sfba-btn-sm sfba-remove-img-key-btn" data-service="unsplash">
							<?php esc_html_e( 'Remove', 'super-fast-blog-ai' ); ?>
						</button>
						<?php endif; ?>
					</div>
					<div class="sfba-img-feedback" style="display:none;margin-top:8px;font-size:12px;"></div>
				</div>

				<div>
					<button type="button" id="sfba-save-images-btn" class="sfba-btn sfba-btn-primary">
						<?php esc_html_e( 'Save Image Settings', 'super-fast-blog-ai' ); ?>
					</button>
				</div>
			</div>
		</div>
	</div><!-- /images -->

	<!-- ===== TAB: Publishing ===== -->
	<div id="sfba-tab-publishing" class="sfba-tab-panel" style="display:none;">
		<div class="sfba-card">
			<h3><?php esc_html_e( 'Publishing', 'super-fast-blog-ai' ); ?></h3>
			<div style="display:flex;flex-direction:column;gap:14px;margin-top:12px;">

				<div>
					<label class="sfba-label"><?php esc_html_e( 'Default Categories', 'super-fast-blog-ai' ); ?></label>
					<?php if ( ! empty( $wp_categories ) ) : ?>
					<select id="sfba-pub-categories" class="sfba-input" multiple
					        style="height:150px;min-width:300px;padding:6px 4px;line-height:1.8;">
						<?php
						$saved_cats = array_map( 'intval', $pub['categories'] ?? [] );
						foreach ( $wp_categories as $cat ) :
						?>
						<option value="<?php echo esc_attr( $cat->term_id ); ?>"
						        <?php echo in_array( (int) $cat->term_id, $saved_cats, true ) ? 'selected' : ''; ?>>
							<?php echo esc_html( $cat->name ); ?>
							<?php if ( $cat->count > 0 ) : ?>
							(<?php echo esc_html( $cat->count ); ?>)
							<?php endif; ?>
						</option>
						<?php endforeach; ?>
					</select>
					<p class="sfba-muted" style="margin-top:5px;">
						<?php esc_html_e( 'Hold Ctrl (Windows) or ⌘ Cmd (Mac) to select multiple categories.', 'super-fast-blog-ai' ); ?>
					</p>
					<?php else : ?>
					<p class="sfba-muted"><?php esc_html_e( 'No categories found. Create categories in Posts → Categories first.', 'super-fast-blog-ai' ); ?></p>
					<input type="hidden" id="sfba-pub-categories" value="">
					<?php endif; ?>
				</div>

				<div style="display:flex;align-items:center;justify-content:space-between;">
					<div>
						<strong style="font-size:13px;"><?php esc_html_e( 'Email Notification on Publish', 'super-fast-blog-ai' ); ?></strong>
						<p class="sfba-muted"><?php esc_html_e( 'Send the site admin an email when a generated post is published.', 'super-fast-blog-ai' ); ?></p>
					</div>
					<label class="sfba-toggle">
						<input type="checkbox" id="sfba-pub-email-notify"
						       <?php checked( $pub['email_notify'] ?? false ); ?>>
						<span class="sfba-toggle-slider"></span>
					</label>
				</div>

				<div>
					<button type="button" id="sfba-save-publishing-btn" class="sfba-btn sfba-btn-primary">
						<?php esc_html_e( 'Save Publishing Settings', 'super-fast-blog-ai' ); ?>
					</button>
				</div>
			</div>
		</div>
	</div><!-- /publishing -->

	<!-- ===== TAB: Schedule ===== -->
	<div id="sfba-tab-schedule" class="sfba-tab-panel" style="display:none;">
		<div class="sfba-card">
			<h3><?php esc_html_e( 'Publishing Schedule', 'super-fast-blog-ai' ); ?></h3>
			<div style="display:flex;flex-direction:column;gap:16px;margin-top:12px;">

				<div>
					<label class="sfba-label"><?php esc_html_e( 'Schedule Type', 'super-fast-blog-ai' ); ?></label>
					<div style="display:flex;flex-direction:column;gap:8px;margin-top:6px;">
						<?php
						$sched_types = [
							'sameday'   => __( 'Same Day', 'super-fast-blog-ai' ),
							'later'     => __( 'Later Date', 'super-fast-blog-ai' ),
							'recurring' => __( 'Recurring', 'super-fast-blog-ai' ),
						];
						foreach ( $sched_types as $val => $label ) :
						?>
						<label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer;">
							<input type="radio" name="sfba-schedule-type" value="<?php echo esc_attr( $val ); ?>"
							       <?php checked( $schedule['type'] ?? 'sameday', $val ); ?>>
							<?php echo esc_html( $label ); ?>
						</label>
						<?php endforeach; ?>
					</div>
				</div>

				<!-- Same Day sub-options -->
				<div id="sfba-sched-sameday-opts">
					<label class="sfba-label"><?php esc_html_e( 'When to Publish', 'super-fast-blog-ai' ); ?></label>
					<select id="sfba-schedule-same-day-type" class="sfba-input" style="width:220px;">
						<option value="immediately"    <?php selected( $schedule['same_day_type'] ?? 'immediately', 'immediately' ); ?>><?php esc_html_e( 'Immediately', 'super-fast-blog-ai' ); ?></option>
						<option value="later_same_day" <?php selected( $schedule['same_day_type'] ?? '', 'later_same_day' ); ?>><?php esc_html_e( 'Later same day (delay)', 'super-fast-blog-ai' ); ?></option>
					</select>
					<div style="display:flex;gap:12px;margin-top:8px;">
						<div>
							<label class="sfba-label"><?php esc_html_e( 'Delay (hours)', 'super-fast-blog-ai' ); ?></label>
							<input type="number" id="sfba-schedule-hours-delay" class="sfba-input" style="width:80px;"
							       value="<?php echo esc_attr( $schedule['hours_delay'] ?? 0 ); ?>" min="0" max="23">
						</div>
						<div>
							<label class="sfba-label"><?php esc_html_e( 'Delay (minutes)', 'super-fast-blog-ai' ); ?></label>
							<input type="number" id="sfba-schedule-minutes-delay" class="sfba-input" style="width:80px;"
							       value="<?php echo esc_attr( $schedule['minutes_delay'] ?? 0 ); ?>" min="0" max="59">
						</div>
					</div>
				</div>

				<!-- Later date sub-options -->
				<div id="sfba-sched-later-opts" style="display:none;">
					<div style="display:flex;gap:12px;flex-wrap:wrap;">
						<div>
							<label class="sfba-label"><?php esc_html_e( 'Days from now', 'super-fast-blog-ai' ); ?></label>
							<input type="number" id="sfba-schedule-later-days" class="sfba-input" style="width:100px;"
							       value="<?php echo esc_attr( $schedule['later_days'] ?? 1 ); ?>" min="1" max="365">
						</div>
						<div>
							<label class="sfba-label"><?php esc_html_e( 'Publish time (HH:MM)', 'super-fast-blog-ai' ); ?></label>
							<input type="time" id="sfba-schedule-later-time" class="sfba-input"
							       value="<?php echo esc_attr( $schedule['later_time'] ?? '09:00' ); ?>">
						</div>
					</div>
				</div>

				<!-- Recurring sub-options -->
				<div id="sfba-sched-recurring-opts" style="display:none;">
					<div style="display:flex;gap:12px;flex-wrap:wrap;">
						<div>
							<label class="sfba-label"><?php esc_html_e( 'Every N days', 'super-fast-blog-ai' ); ?></label>
							<input type="number" id="sfba-schedule-recur-days" class="sfba-input" style="width:100px;"
							       value="<?php echo esc_attr( $schedule['recur_days'] ?? 7 ); ?>" min="1" max="365">
						</div>
						<div>
							<label class="sfba-label"><?php esc_html_e( 'Publish time (HH:MM)', 'super-fast-blog-ai' ); ?></label>
							<input type="time" id="sfba-schedule-recur-time" class="sfba-input"
							       value="<?php echo esc_attr( $schedule['recur_time'] ?? '09:00' ); ?>">
						</div>
					</div>
				</div>

				<div>
					<button type="button" id="sfba-save-schedule-btn" class="sfba-btn sfba-btn-primary">
						<?php esc_html_e( 'Save Schedule Settings', 'super-fast-blog-ai' ); ?>
					</button>
				</div>
			</div>
		</div>
	</div><!-- /schedule -->

	<!-- ===== TAB: Search Console ===== -->
	<div id="sfba-tab-search-console" class="sfba-tab-panel" style="display:none;">
		<div class="sfba-card">
			<h3><?php esc_html_e( 'Google Search Console', 'super-fast-blog-ai' ); ?></h3>

			<div class="sfba-info-box" style="margin:12px 0 16px;">
				<p><?php esc_html_e( 'Connect Google Search Console to track impressions, clicks, and ranking positions for your AI-generated posts. You need a Google OAuth 2.0 access token with Search Console API access.', 'super-fast-blog-ai' ); ?></p>
			</div>

			<div style="display:flex;flex-direction:column;gap:16px;">
				<div>
					<label class="sfba-label"><?php esc_html_e( 'OAuth Access Token', 'super-fast-blog-ai' ); ?></label>
					<div style="display:flex;gap:8px;align-items:center;">
						<input type="password"
						       class="sfba-input sfba-api-key-input"
						       style="width:340px;"
						       placeholder="<?php echo esc_attr( isset( $providers['google_sc'] ) && $providers['google_sc']['has_key'] ? '••••••••••••••••' : __( 'Paste OAuth access token…', 'super-fast-blog-ai' ) ); ?>"
						       autocomplete="new-password"
						       data-provider="google_sc">
						<button type="button"
						        class="sfba-btn sfba-btn-primary sfba-btn-sm sfba-save-key-btn"
						        data-provider="google_sc">
							<?php esc_html_e( 'Save', 'super-fast-blog-ai' ); ?>
						</button>
					</div>
					<div class="sfba-provider-feedback" data-provider="google_sc" style="display:none;margin-top:6px;font-size:13px;"></div>
				</div>

				<div>
					<label class="sfba-label"><?php esc_html_e( 'Verified Site URL', 'super-fast-blog-ai' ); ?></label>
					<input type="url" id="sfba-sc-property" class="sfba-input" style="width:340px;"
					       value="<?php echo esc_attr( $sc['property'] ?? '' ); ?>"
					       placeholder="https://example.com/">
					<p class="sfba-muted"><?php esc_html_e( 'Must match the property verified in Google Search Console.', 'super-fast-blog-ai' ); ?></p>
				</div>

				<div>
					<button type="button" id="sfba-save-sc-btn" class="sfba-btn sfba-btn-primary">
						<?php esc_html_e( 'Save Search Console Settings', 'super-fast-blog-ai' ); ?>
					</button>
				</div>
			</div>
		</div>
	</div><!-- /search-console -->

	<!-- ===== TAB: Advanced ===== -->
	<div id="sfba-tab-advanced" class="sfba-tab-panel" style="display:none;">
		<div class="sfba-card">
			<h3><?php esc_html_e( 'Advanced', 'super-fast-blog-ai' ); ?></h3>
			<div style="display:flex;flex-direction:column;gap:14px;margin-top:12px;">

				<div style="display:flex;align-items:center;justify-content:space-between;">
					<div>
						<strong style="font-size:13px;"><?php esc_html_e( 'Debug Mode', 'super-fast-blog-ai' ); ?></strong>
						<p class="sfba-muted"><?php esc_html_e( 'Log detailed provider requests and responses to the PHP error log.', 'super-fast-blog-ai' ); ?></p>
					</div>
					<label class="sfba-toggle">
						<input type="checkbox" id="sfba-debug-mode"
						       <?php checked( $settings['debug_mode'] ?? false ); ?>>
						<span class="sfba-toggle-slider"></span>
					</label>
				</div>

				<div>
					<label class="sfba-label"><?php esc_html_e( 'Monthly Budget (USD)', 'super-fast-blog-ai' ); ?></label>
					<input type="number" id="sfba-monthly-budget" class="sfba-input" style="width:140px;"
					       value="<?php echo esc_attr( number_format( (float) ( $settings['monthly_budget'] ?? 0 ), 2, '.', '' ) ); ?>"
					       min="0" step="0.50" placeholder="0.00">
					<p class="sfba-muted"><?php esc_html_e( 'Set to 0 to disable budget tracking. A warning is shown at 80% and 100%.', 'super-fast-blog-ai' ); ?></p>
				</div>

				<div>
					<button type="button" id="sfba-save-advanced-btn" class="sfba-btn sfba-btn-primary">
						<?php esc_html_e( 'Save Advanced Settings', 'super-fast-blog-ai' ); ?>
					</button>
				</div>
			</div>
		</div>
	</div><!-- /advanced -->

</div><!-- .wrap -->

<script>
( function () {
	'use strict';
	const apiBase             = <?php echo wp_json_encode( $api_base ); ?>;
	const nonce               = <?php echo wp_json_encode( $nonce ); ?>;
	const savedDefaultProvider = <?php echo wp_json_encode( $settings['default_provider'] ?? '' ); ?>;

	// ── Helpers ────────────────────────────────────────────────────────────────
	function notice( msg, type ) {
		const el = document.getElementById( 'sfba-settings-notice' );
		if ( ! el ) return;
		el.textContent = msg; el.className = 'sfba-notice sfba-notice--' + type; el.style.display = 'block';
		setTimeout( () => { el.style.display = 'none'; }, 6000 );
	}
	function providerFeedback( provider, msg, type, card ) {
		// Show in the top notice bar.
		notice( msg, type );
		// Scope the fallback lookup to .sfba-provider-card to avoid matching key inputs (which also carry data-provider).
		if ( ! card ) card = document.querySelector( '.sfba-provider-card[data-provider="' + provider + '"]' );
		if ( ! card ) return;
		const fb = card.querySelector( '.sfba-provider-feedback' );
		if ( ! fb ) return;
		fb.textContent = msg;
		const ok = ( type === 'success' );
		fb.style.cssText = 'display:block;font-size:12.5px;padding:8px 12px;border-radius:8px;' +
			( ok
				? 'background:#f0fdf4;color:#15803d;border:1px solid #bbf7d0;'
				: 'background:#fef2f2;color:#b91c1c;border:1px solid #fecaca;' );
		setTimeout( () => { fb.style.display = 'none'; }, 6000 );
	}
	async function apiPost( path, body ) {
		const r = await fetch( apiBase + path, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce }, body: JSON.stringify( body ) } );
		return r.json();
	}

	// ── Provider → default model map ──────────────────────────────────────────
	const providerModels = <?php
		$map = [];
		foreach ( $providers as $slug => $p ) {
			$map[ $slug ] = $p['default_model'] ?? '';
		}
		echo wp_json_encode( $map );
	?>;

	// Track the last valid (configured) provider so we can revert.
	let lastValidProvider = ( function () {
		const sel = document.getElementById( 'sfba-default-provider' );
		if ( ! sel ) return '';
		// Walk the current options to find the first configured one that matches the saved value.
		const saved = sel.value;
		const opt   = sel.querySelector( 'option[value="' + saved + '"]' );
		return ( opt && opt.dataset.configured === '1' ) ? saved : '';
	} )();

	document.getElementById( 'sfba-default-provider' )?.addEventListener( 'change', function () {
		const opt          = this.options[ this.selectedIndex ];
		const isConfigured = opt?.dataset.configured === '1';
		const warning      = document.getElementById( 'sfba-default-provider-warning' );

		if ( ! isConfigured ) {
			// Revert selection and show warning.
			this.value = lastValidProvider;
			if ( warning ) {
				warning.style.display = 'block';
				setTimeout( () => { warning.style.display = 'none'; }, 5000 );
			}
			return;
		}

		if ( warning ) warning.style.display = 'none';
		lastValidProvider = this.value;

		// Update the default model hint.
		const modelInput = document.getElementById( 'sfba-default-model' );
		if ( modelInput ) {
			const defaultModel = providerModels[ this.value ] || '';
			modelInput.value       = defaultModel;
			modelInput.placeholder = defaultModel || 'e.g. gpt-4o-mini';
		}
	} );

	// ── Tab switching ──────────────────────────────────────────────────────────
	function switchSettingsTab( tabSlug ) {
		document.querySelectorAll( '.sfba-tab-btn' ).forEach( b => b.classList.remove( 'sfba-tab-active' ) );
		document.querySelectorAll( '.sfba-tab-panel' ).forEach( p => { p.style.display = 'none'; } );
		const btn   = document.querySelector( '.sfba-tab-btn[data-tab="' + tabSlug + '"]' );
		const panel = document.getElementById( 'sfba-tab-' + tabSlug );
		if ( btn )   btn.classList.add( 'sfba-tab-active' );
		if ( panel ) panel.style.display = 'block';
		// Keep URL hash in sync so back-button / deep-links work.
		if ( history.replaceState ) {
			history.replaceState( null, '', '#' + tabSlug );
		}
	}

	document.querySelectorAll( '.sfba-tab-btn' ).forEach( btn => {
		btn.addEventListener( 'click', function () {
			switchSettingsTab( this.dataset.tab );
		} );
	} );

	// Activate tab from URL hash on load (e.g. #search-console).
	function activateHashTab() {
		const hash = window.location.hash.replace( '#', '' ).trim();
		if ( hash && document.querySelector( '.sfba-tab-btn[data-tab="' + hash + '"]' ) ) {
			switchSettingsTab( hash );
		}
	}
	activateHashTab();

	// Re-run when hash changes without a page reload (e.g. clicking an <a href="#providers"> link).
	window.addEventListener( 'hashchange', activateHashTab );

	// ── Save API key ───────────────────────────────────────────────────────────
	// Use this.closest() so that duplicate provider slugs across tabs
	// (e.g. dalle3 appears in both Providers and Images tabs) always scope
	// to the card the clicked button actually belongs to.
	document.querySelectorAll( '.sfba-save-key-btn' ).forEach( btn => {
		btn.addEventListener( 'click', async function () {
			const provider = this.dataset.provider;
			const card     = this.closest( '.sfba-provider-card' );
			const input    = card ? card.querySelector( '.sfba-api-key-input' ) : null;
			const api_key  = input ? input.value.trim() : '';
			if ( ! api_key ) { notice( '<?php echo esc_js( __( 'Please enter an API key.', 'super-fast-blog-ai' ) ); ?>', 'error' ); return; }
			const orig = this.innerHTML;
			this.disabled = true; this.innerHTML = '⏳ <?php echo esc_js( __( 'Saving…', 'super-fast-blog-ai' ) ); ?>';
			try {
				const data = await apiPost( '/settings/api-key', { provider, api_key } );
				if ( data.success ) {
					if ( input ) input.value = '';

					// ── Auto-set default provider when none is configured yet ──────
					if ( ! savedDefaultProvider ) {
						try {
							await apiPost( '/settings', {
								default_provider: provider,
								default_model:    providerModels[ provider ] || '',
								routing_enabled:  document.getElementById( 'sfba-routing-enabled' )?.checked ?? false,
							} );
							providerFeedback( provider, '✓ <?php echo esc_js( __( 'Key saved and set as your default provider.', 'super-fast-blog-ai' ) ); ?>', 'success', card );
						} catch ( _e ) {
							providerFeedback( provider, '✓ <?php echo esc_js( __( 'Key saved.', 'super-fast-blog-ai' ) ); ?>', 'success', card );
						}
					} else {
						providerFeedback( provider, '✓ <?php echo esc_js( __( 'Key saved.', 'super-fast-blog-ai' ) ); ?>', 'success', card );
					}

					setTimeout( () => window.location.reload(), 1600 );
				} else {
					providerFeedback( provider, data.message || '<?php echo esc_js( __( 'Save failed.', 'super-fast-blog-ai' ) ); ?>', 'error', card );
				}
			} catch ( e ) {
				providerFeedback( provider, '<?php echo esc_js( __( 'Network error.', 'super-fast-blog-ai' ) ); ?>', 'error', card );
			}
			this.disabled = false; this.innerHTML = orig;
		} );
	} );

	// ── Remove API key ─────────────────────────────────────────────────────────
	document.querySelectorAll( '.sfba-remove-key-btn' ).forEach( btn => {
		btn.addEventListener( 'click', async function () {
			const provider = this.dataset.provider;
			if ( ! confirm( '<?php echo esc_js( __( 'Remove this API key? This cannot be undone.', 'super-fast-blog-ai' ) ); ?>' ) ) return;
			const card = this.closest( '.sfba-provider-card' );
			const orig = this.innerHTML;
			this.disabled = true; this.innerHTML = '⏳ <?php echo esc_js( __( 'Removing…', 'super-fast-blog-ai' ) ); ?>';
			try {
				const data = await apiPost( '/settings/api-key', { provider, api_key: '' } );
				if ( data.success ) {
					providerFeedback( provider, '<?php echo esc_js( __( 'Key removed.', 'super-fast-blog-ai' ) ); ?>', 'success', card );
					setTimeout( () => window.location.reload(), 1600 );
				} else {
					providerFeedback( provider, data.message || '<?php echo esc_js( __( 'Remove failed.', 'super-fast-blog-ai' ) ); ?>', 'error', card );
				}
			} catch ( e ) { providerFeedback( provider, '<?php echo esc_js( __( 'Network error.', 'super-fast-blog-ai' ) ); ?>', 'error', card ); }
			this.disabled = false; this.innerHTML = orig;
		} );
	} );

	// ── Test API key ───────────────────────────────────────────────────────────
	document.querySelectorAll( '.sfba-test-key-btn' ).forEach( btn => {
		btn.addEventListener( 'click', async function () {
			const provider = this.dataset.provider;
			const card     = this.closest( '.sfba-provider-card' );
			const orig     = this.innerHTML;
			this.disabled = true; this.innerHTML = '⏳ <?php echo esc_js( __( 'Testing…', 'super-fast-blog-ai' ) ); ?>';
			try {
				const data = await apiPost( '/settings/test-key', { provider } );
				if ( data.success ) {
					providerFeedback( provider, '✓ <?php echo esc_js( __( 'Connection successful.', 'super-fast-blog-ai' ) ); ?>', 'success', card );
				} else {
					providerFeedback( provider, data.message || '<?php echo esc_js( __( 'Connection failed.', 'super-fast-blog-ai' ) ); ?>', 'error', card );
				}
			} catch ( e ) { providerFeedback( provider, '<?php echo esc_js( __( 'Network error.', 'super-fast-blog-ai' ) ); ?>', 'error', card ); }
			this.disabled = false; this.innerHTML = orig;
		} );
	} );

	// ── Ollama: save Base URL + optional Bearer Token ─────────────────────────
	document.getElementById( 'sfba-ollama-save-btn' )?.addEventListener( 'click', async function () {
		const card         = this.closest( '[data-provider]' );
		const base_url     = document.getElementById( 'sfba-ollama-base-url' )?.value.trim() || '';
		const bearer_token = document.getElementById( 'sfba-ollama-token' )?.value.trim()    || '';

		if ( ! base_url ) { notice( '<?php echo esc_js( __( 'Please enter a Base URL.', 'super-fast-blog-ai' ) ); ?>', 'error' ); return; }

		const orig = this.innerHTML;
		this.disabled = true; this.textContent = '<?php echo esc_js( __( 'Saving…', 'super-fast-blog-ai' ) ); ?>';
		try {
			const data = await apiPost( '/settings/ollama', { base_url, bearer_token } );
			if ( data.success ) {
				providerFeedback( 'ollama', '✓ <?php echo esc_js( __( 'Ollama settings saved.', 'super-fast-blog-ai' ) ); ?>', 'success', card );
				document.getElementById( 'sfba-ollama-token' ).value = '';
				setTimeout( () => window.location.reload(), 1200 );
			} else {
				providerFeedback( 'ollama', data.message || '<?php echo esc_js( __( 'Save failed.', 'super-fast-blog-ai' ) ); ?>', 'error', card );
			}
		} catch ( e ) {
			providerFeedback( 'ollama', '<?php echo esc_js( __( 'Network error.', 'super-fast-blog-ai' ) ); ?>', 'error', card );
		}
		this.disabled = false; this.innerHTML = orig;
	} );

	// ── Save / Remove image-service keys (Pixabay, Unsplash) ─────────────────
	function imgFeedback( card, msg, type ) {
		if ( ! card ) return;
		const fb = card.querySelector( '.sfba-img-feedback' );
		if ( ! fb ) return;
		fb.textContent = msg;
		fb.className   = 'sfba-img-feedback sfba-notice--' + type;
		fb.style.cssText = 'display:block;margin-top:8px;font-size:12px;padding:6px 10px;border-radius:6px;background:' + ( type === 'success' ? '#f0fdf4' : '#fef2f2' ) + ';color:' + ( type === 'success' ? '#15803d' : '#b91c1c' ) + ';';
		setTimeout( () => { fb.style.display = 'none'; }, 4000 );
	}
	document.querySelectorAll( '.sfba-save-img-key-btn' ).forEach( btn => {
		btn.addEventListener( 'click', async function () {
			const service = this.dataset.service;
			const card    = this.closest( '.sfba-img-service-card' );
			const input   = card ? card.querySelector( '.sfba-img-key-input' ) : null;
			const api_key = input ? input.value.trim() : '';
			if ( ! api_key ) { notice( 'Please enter an API key.', 'error' ); return; }
			const orig = this.textContent;
			this.disabled = true; this.textContent = '<?php echo esc_js( __( 'Saving…', 'super-fast-blog-ai' ) ); ?>';
			try {
				const data = await apiPost( '/settings/image-key', { service, api_key } );
				if ( data.success ) {
					imgFeedback( card, '✓ <?php echo esc_js( __( 'Key saved.', 'super-fast-blog-ai' ) ); ?>', 'success' );
					if ( input ) input.value = '';
					setTimeout( () => window.location.reload(), 1200 );
				} else {
					imgFeedback( card, data.message || '<?php echo esc_js( __( 'Save failed.', 'super-fast-blog-ai' ) ); ?>', 'error' );
				}
			} catch ( e ) { imgFeedback( card, '<?php echo esc_js( __( 'Network error.', 'super-fast-blog-ai' ) ); ?>', 'error' ); }
			this.disabled = false; this.textContent = orig;
		} );
	} );
	document.querySelectorAll( '.sfba-remove-img-key-btn' ).forEach( btn => {
		btn.addEventListener( 'click', async function () {
			const service = this.dataset.service;
			if ( ! confirm( '<?php echo esc_js( __( 'Remove this image API key?', 'super-fast-blog-ai' ) ); ?>' ) ) return;
			const card = this.closest( '.sfba-img-service-card' );
			const orig = this.textContent;
			this.disabled = true; this.textContent = '<?php echo esc_js( __( 'Removing…', 'super-fast-blog-ai' ) ); ?>';
			try {
				const data = await apiPost( '/settings/image-key', { service, api_key: '' } );
				if ( data.success ) {
					imgFeedback( card, '<?php echo esc_js( __( 'Key removed.', 'super-fast-blog-ai' ) ); ?>', 'success' );
					setTimeout( () => window.location.reload(), 1200 );
				} else {
					imgFeedback( card, data.message || '<?php echo esc_js( __( 'Remove failed.', 'super-fast-blog-ai' ) ); ?>', 'error' );
				}
			} catch ( e ) { imgFeedback( card, '<?php echo esc_js( __( 'Network error.', 'super-fast-blog-ai' ) ); ?>', 'error' ); }
			this.disabled = false; this.textContent = orig;
		} );
	} );

	// ── Save: Provider settings ────────────────────────────────────────────────
	document.getElementById( 'sfba-save-provider-btn' )?.addEventListener( 'click', async function () {
		this.disabled = true; this.textContent = 'Saving…';
		try {
			const data = await apiPost( '/settings', {
				default_provider: document.getElementById( 'sfba-default-provider' )?.value || '',
				default_model:    document.getElementById( 'sfba-default-model' )?.value || '',
				routing_enabled:  document.getElementById( 'sfba-routing-enabled' )?.checked ?? false,
			} );
			if ( data.success ) { notice( 'Provider settings saved.', 'success' ); }
			else { notice( data.message || 'Save failed.', 'error' ); }
		} catch ( e ) { notice( 'Network error.', 'error' ); }
		this.disabled = false; this.textContent = 'Save Provider Settings';
	} );

	// ── Save: Content settings ─────────────────────────────────────────────────
	document.getElementById( 'sfba-save-content-btn' )?.addEventListener( 'click', async function () {
		this.disabled = true; this.textContent = 'Saving…';
		const content = {
			language:      document.getElementById( 'sfba-content-language' )?.value   || 'English',
			writing_style: document.getElementById( 'sfba-content-writing-style' )?.value || 'informative',
			tone:          document.getElementById( 'sfba-content-tone' )?.value        || 'neutral',
			heading_tag:   document.getElementById( 'sfba-content-heading-tag' )?.value || 'h2',
			word_count:    parseInt( document.getElementById( 'sfba-content-word-count' )?.value || '1200', 10 ),
			heading_count: parseInt( document.getElementById( 'sfba-content-heading-count' )?.value || '5', 10 ),
		};
		document.querySelectorAll( '.sfba-content-toggle' ).forEach( el => {
			content[ el.dataset.key ] = el.checked;
		} );
		try {
			const data = await apiPost( '/settings', { content } );
			if ( data.success ) { notice( 'Content settings saved.', 'success' ); }
			else { notice( data.message || 'Save failed.', 'error' ); }
		} catch ( e ) { notice( 'Network error.', 'error' ); }
		this.disabled = false; this.textContent = 'Save Content Settings';
	} );

	// ── Save: SEO settings ─────────────────────────────────────────────────────
	document.getElementById( 'sfba-save-seo-btn' )?.addEventListener( 'click', async function () {
		this.disabled = true; this.textContent = 'Saving…';
		try {
			const data = await apiPost( '/settings', {
				seo: {
					keywords:  document.getElementById( 'sfba-seo-keywords' )?.checked  ?? true,
					meta_desc: document.getElementById( 'sfba-seo-meta-desc' )?.checked ?? true,
				},
			} );
			if ( data.success ) { notice( 'SEO settings saved.', 'success' ); }
			else { notice( data.message || 'Save failed.', 'error' ); }
		} catch ( e ) { notice( 'Network error.', 'error' ); }
		this.disabled = false; this.textContent = 'Save SEO Settings';
	} );

	// ── Save: Image settings ───────────────────────────────────────────────────
	document.getElementById( 'sfba-save-images-btn' )?.addEventListener( 'click', async function () {
		this.disabled = true; this.textContent = 'Saving…';
		try {
			const data = await apiPost( '/settings', {
				images: { source: document.getElementById( 'sfba-images-source' )?.value || 'dalle3' },
			} );
			if ( data.success ) { notice( 'Image settings saved.', 'success' ); }
			else { notice( data.message || 'Save failed.', 'error' ); }
		} catch ( e ) { notice( 'Network error.', 'error' ); }
		this.disabled = false; this.textContent = 'Save Image Settings';
	} );

	// ── Save: Publishing settings ──────────────────────────────────────────────
	document.getElementById( 'sfba-save-publishing-btn' )?.addEventListener( 'click', async function () {
		this.disabled = true; this.textContent = 'Saving…';
		const catEl = document.getElementById( 'sfba-pub-categories' );
		const cats  = catEl?.tagName === 'SELECT'
			? Array.from( catEl.selectedOptions ).map( o => parseInt( o.value, 10 ) ).filter( n => n > 0 )
			: [];
		try {
			const data = await apiPost( '/settings', {
				publishing: {
					categories:   cats,
					email_notify: document.getElementById( 'sfba-pub-email-notify' )?.checked ?? false,
				},
			} );
			if ( data.success ) { notice( 'Publishing settings saved.', 'success' ); }
			else { notice( data.message || 'Save failed.', 'error' ); }
		} catch ( e ) { notice( 'Network error.', 'error' ); }
		this.disabled = false; this.textContent = 'Save Publishing Settings';
	} );

	// ── Save: Schedule settings ────────────────────────────────────────────────
	document.getElementById( 'sfba-save-schedule-btn' )?.addEventListener( 'click', async function () {
		this.disabled = true; this.textContent = 'Saving…';
		const type = document.querySelector( 'input[name="sfba-schedule-type"]:checked' )?.value || 'sameday';
		try {
			const data = await apiPost( '/settings', {
				schedule: {
					type,
					same_day_type:  document.getElementById( 'sfba-schedule-same-day-type' )?.value  || 'immediately',
					hours_delay:    parseInt( document.getElementById( 'sfba-schedule-hours-delay' )?.value   || '0', 10 ),
					minutes_delay:  parseInt( document.getElementById( 'sfba-schedule-minutes-delay' )?.value || '0', 10 ),
					later_days:     parseInt( document.getElementById( 'sfba-schedule-later-days' )?.value    || '1', 10 ),
					later_time:     document.getElementById( 'sfba-schedule-later-time' )?.value  || '09:00',
					recur_days:     parseInt( document.getElementById( 'sfba-schedule-recur-days' )?.value    || '7', 10 ),
					recur_time:     document.getElementById( 'sfba-schedule-recur-time' )?.value  || '09:00',
				},
			} );
			if ( data.success ) { notice( 'Schedule settings saved.', 'success' ); }
			else { notice( data.message || 'Save failed.', 'error' ); }
		} catch ( e ) { notice( 'Network error.', 'error' ); }
		this.disabled = false; this.textContent = 'Save Schedule Settings';
	} );

	// Schedule type toggle
	function updateScheduleVisibility() {
		const type = document.querySelector( 'input[name="sfba-schedule-type"]:checked' )?.value || 'sameday';
		document.getElementById( 'sfba-sched-sameday-opts' ).style.display   = type === 'sameday'   ? 'block' : 'none';
		document.getElementById( 'sfba-sched-later-opts' ).style.display     = type === 'later'     ? 'block' : 'none';
		document.getElementById( 'sfba-sched-recurring-opts' ).style.display = type === 'recurring' ? 'block' : 'none';
	}
	document.querySelectorAll( 'input[name="sfba-schedule-type"]' ).forEach( r => {
		r.addEventListener( 'change', updateScheduleVisibility );
	} );
	updateScheduleVisibility();

	// ── Save: Search Console settings ─────────────────────────────────────────
	document.getElementById( 'sfba-save-sc-btn' )?.addEventListener( 'click', async function () {
		this.disabled = true; this.textContent = 'Saving…';
		try {
			const data = await apiPost( '/settings', {
				search_console: { property: document.getElementById( 'sfba-sc-property' )?.value || '' },
			} );
			if ( data.success ) { notice( 'Search Console settings saved.', 'success' ); }
			else { notice( data.message || 'Save failed.', 'error' ); }
		} catch ( e ) { notice( 'Network error.', 'error' ); }
		this.disabled = false; this.textContent = 'Save Search Console Settings';
	} );

	// ── Save: Advanced settings ────────────────────────────────────────────────
	document.getElementById( 'sfba-save-advanced-btn' )?.addEventListener( 'click', async function () {
		this.disabled = true; this.textContent = 'Saving…';
		try {
			const data = await apiPost( '/settings', {
				debug_mode:     document.getElementById( 'sfba-debug-mode' )?.checked ?? false,
				monthly_budget: parseFloat( document.getElementById( 'sfba-monthly-budget' )?.value || '0' ),
			} );
			if ( data.success ) { notice( 'Advanced settings saved.', 'success' ); }
			else { notice( data.message || 'Save failed.', 'error' ); }
		} catch ( e ) { notice( 'Network error.', 'error' ); }
		this.disabled = false; this.textContent = 'Save Advanced Settings';
	} );
} )();
</script>
