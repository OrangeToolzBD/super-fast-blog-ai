<?php
/**
 * Generate Content admin page — merged title generator + article generator.
 *
 * Variables:
 *   $results   array  — DB rows from slf_generated_title
 *   $api_base  string
 *   $nonce     string
 *
 * @package SuperFastBlogAI
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$s = $this->core->settings;
?>
<div class="wrap sfba-admin-wrap" style="max-width:1060px;">

	<!-- Hero -->
	<div class="sfba-dash-hero" style="margin-bottom:24px;">
		<div class="sfba-dash-hero-brand">
			<div class="sfba-dash-logo-ring">
				<img src="<?php echo esc_url( SFBA_PLUGIN_URL . 'assets/images/menu_icon.svg' ); ?>" alt="" style="width:28px;height:28px;">
			</div>
			<div>
				<h1 style="margin:0 0 2px;"><?php esc_html_e( 'Generate Content', 'super-fast-blog-ai' ); ?></h1>
				<p class="sfba-dash-hero-sub"><?php esc_html_e( 'Generate blog titles and full AI-written articles in seconds.', 'super-fast-blog-ai' ); ?></p>
			</div>
		</div>
		<span class="sfba-dash-ver">v<?php echo esc_html( SFBA_VERSION ); ?></span>
	</div>

	<!-- Global notice -->
	<div id="sfba-gen-notice" class="sfba-notice" style="display:none;margin-bottom:16px;"></div>

	<!-- Tab bar -->
	<style>
	.sfba-gen-tabs { display:flex;background:#f1f5f9;border-radius:12px;padding:4px;margin-bottom:24px;gap:3px;border:1px solid #e2e8f0; }
	.sfba-gen-tab  { flex:1;display:flex;align-items:center;justify-content:center;gap:8px;padding:11px 20px;border:none;border-radius:9px;font-size:13px;font-weight:600;cursor:pointer;transition:all .18s;background:transparent;color:#64748b;line-height:1.2; }
	.sfba-gen-tab:hover { color:#334155;background:rgba(255,255,255,.65); }
	.sfba-gen-tab.sfba-tab-active { background:#fff;color:#1e40af;box-shadow:0 1px 5px rgba(0,0,0,.13),0 0 0 1px rgba(0,0,0,.05); }
	</style>
	<div class="sfba-gen-tabs">
		<button type="button" class="sfba-tab-btn sfba-gen-tab sfba-tab-active" data-tab="titles">
			🔤 <?php esc_html_e( 'Generate Titles', 'super-fast-blog-ai' ); ?>
		</button>
		<button type="button" class="sfba-tab-btn sfba-gen-tab" data-tab="article">
			📝 <?php esc_html_e( 'Generate Article', 'super-fast-blog-ai' ); ?>
		</button>
	</div>

	<!-- ===================================================================
	     TAB 1 — Generate Titles
	     =================================================================== -->
	<div id="sfba-tab-titles" class="sfba-tab-panel">

		<!-- Generate form -->
		<div class="sfba-card" style="margin-bottom:20px;">

			<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
				<div>
					<h3 style="margin:0 0 3px;font-size:15px;">🔤 <?php esc_html_e( 'Title Generator', 'super-fast-blog-ai' ); ?></h3>
					<p class="sfba-muted"><?php esc_html_e( 'Generate multiple catchy title options from a topic.', 'super-fast-blog-ai' ); ?></p>
				</div>
			</div>

			<!-- Topic -->
			<div class="sfba-field-group" style="margin-bottom:16px;">
				<label class="sfba-label" for="sfba-blog-title"><?php esc_html_e( 'Blog Topic / Prompt', 'super-fast-blog-ai' ); ?></label>
				<input type="text" id="sfba-blog-title" class="sfba-input"
				       style="font-size:14px;padding:10px 14px;"
				       placeholder="<?php esc_attr_e( 'Enter Blog Topic / Prompt', 'super-fast-blog-ai' ); ?>">
			</div>

			<!-- Options row -->
			<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:20px;">
				<div class="sfba-field-group">
					<label class="sfba-label" for="sfba-wvariation"><?php esc_html_e( 'Variations', 'super-fast-blog-ai' ); ?></label>
					<select id="sfba-wvariation" class="sfba-input">
						<?php foreach ( [ 5, 10, 15, 20 ] as $n ) : ?>
						<option value="<?php echo esc_attr( $n ); ?>"><?php echo esc_html( $n ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="sfba-field-group">
					<label class="sfba-label" for="sfba-tlanguage"><?php esc_html_e( 'Language', 'super-fast-blog-ai' ); ?></label>
					<select id="sfba-tlanguage" class="sfba-input">
						<?php foreach ( [ 'English', 'Bangla', 'Spanish', 'French', 'German', 'Italian', 'Portuguese', 'Dutch', 'Polish', 'Japanese', 'Chinese' ] as $lang ) : ?>
						<option value="<?php echo esc_attr( $lang ); ?>"><?php echo esc_html( $lang ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="sfba-field-group">
					<label class="sfba-label" for="sfba-twstyle"><?php esc_html_e( 'Writing Style', 'super-fast-blog-ai' ); ?></label>
					<select id="sfba-twstyle" class="sfba-input">
						<?php foreach ( [ 'Informative', 'Conversational', 'Professional', 'Creative', 'Academic' ] as $st ) : ?>
						<option value="<?php echo esc_attr( strtolower( $st ) ); ?>"><?php echo esc_html( $st ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="sfba-field-group">
					<label class="sfba-label" for="sfba-writone"><?php esc_html_e( 'Tone', 'super-fast-blog-ai' ); ?></label>
					<select id="sfba-writone" class="sfba-input">
						<?php foreach ( [ 'Neutral', 'Friendly', 'Formal', 'Casual', 'Authoritative', 'Empathetic' ] as $t ) : ?>
						<option value="<?php echo esc_attr( strtolower( $t ) ); ?>"><?php echo esc_html( $t ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>

			<!-- Action -->
			<div class="sfba-action-bar" style="padding-top:0;border:none;margin-bottom:0;">
				<button type="button" id="sfba-generate-titles-btn" class="sfba-btn sfba-btn-primary sfba-btn-lg">
					✨ <?php esc_html_e( 'Generate Titles', 'super-fast-blog-ai' ); ?>
				</button>
				<div id="sfba-titles-spinner" style="display:none;align-items:center;gap:8px;">
					<div class="sfba-spinner"></div>
					<span class="sfba-muted"><?php esc_html_e( 'Generating…', 'super-fast-blog-ai' ); ?></span>
				</div>
			</div>

			<!-- Inline results -->
			<div id="sfba-titles-result" style="display:none;margin-top:20px;border-top:1px solid var(--sfba-grey-100);padding-top:18px;">
				<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
					<h4 style="margin:0;font-size:13px;font-weight:600;color:var(--sfba-grey-600);">
						<?php esc_html_e( 'GENERATED TITLES', 'super-fast-blog-ai' ); ?>
					</h4>
					<span id="sfba-titles-count" class="sfba-badge sfba-badge-blue"></span>
				</div>
				<div id="sfba-titles-list" style="display:flex;flex-direction:column;gap:8px;"></div>
			</div>
		</div>

		<!-- Saved generations -->
		<?php if ( ! empty( $results ) ) : ?>
		<div class="sfba-card">
			<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
				<h3 style="margin:0;font-size:15px;">💾 <?php esc_html_e( 'Saved Generations', 'super-fast-blog-ai' ); ?></h3>
				<span class="sfba-badge sfba-badge-gray"><?php echo esc_html( count( $results ) ); ?> <?php esc_html_e( 'groups', 'super-fast-blog-ai' ); ?></span>
			</div>
			<div style="display:flex;flex-direction:column;gap:12px;">
			<?php foreach ( $results as $group ) :
				$titles = [];
				foreach ( explode( "\n", (string) $group->titles ) as $line ) {
					$parts = explode( '||', $line, 2 );
					if ( count( $parts ) === 2 ) {
						$titles[] = [ 'id' => (int) $parts[0], 'title' => $parts[1] ];
					}
				}
			?>
			<div class="sfba-gen-group" style="border:1px solid var(--sfba-grey-200);border-radius:10px;overflow:hidden;">
				<div style="display:flex;align-items:center;justify-content:space-between;padding:11px 16px;background:var(--sfba-grey-50);border-bottom:1px solid var(--sfba-grey-200);">
					<div style="display:flex;align-items:center;gap:10px;">
						<span style="font-size:13px;font-weight:600;color:var(--sfba-grey-900);"><?php echo esc_html( $group->promt_title ); ?></span>
						<span class="sfba-badge sfba-badge-gray"><?php echo esc_html( count( $titles ) ); ?></span>
						<span class="sfba-muted" style="font-size:12px;"><?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $group->ctime ) ) ); ?></span>
					</div>
					<button type="button" class="sfba-btn sfba-btn-danger sfba-btn-sm sfba-bulk-delete-btn"
					        data-protid="<?php echo esc_attr( $group->protid ); ?>">
						🗑 <?php esc_html_e( 'Delete', 'super-fast-blog-ai' ); ?>
					</button>
				</div>
				<div style="padding:10px 14px;display:flex;flex-direction:column;gap:6px;">
				<?php foreach ( $titles as $t ) : ?>
				<div class="sfba-title-result-item sfba-title-row" data-id="<?php echo esc_attr( $t['id'] ); ?>">
					<span class="sfba-title-result-text"><?php echo esc_html( $t['title'] ); ?></span>
					<button type="button" class="sfba-btn sfba-btn-secondary sfba-btn-sm sfba-copy-title-btn"
					        data-title="<?php echo esc_attr( $t['title'] ); ?>">
						📋 <?php esc_html_e( 'Copy', 'super-fast-blog-ai' ); ?>
					</button>
					<button type="button" class="sfba-btn sfba-btn-secondary sfba-btn-sm sfba-use-for-article-btn"
					        data-title="<?php echo esc_attr( $t['title'] ); ?>">
						📝 <?php esc_html_e( 'Write Article', 'super-fast-blog-ai' ); ?>
					</button>
					<button type="button" class="sfba-btn sfba-btn-danger sfba-btn-sm sfba-delete-title-btn"
					        data-id="<?php echo esc_attr( $t['id'] ); ?>" title="<?php esc_attr_e( 'Delete', 'super-fast-blog-ai' ); ?>">
						🗑
					</button>
				</div>
				<?php endforeach; ?>
				</div>
			</div>
			<?php endforeach; ?>
			</div>
		</div>
		<?php else : ?>
		<div id="sfba-titles-empty-state" style="margin-top:0;padding:52px 28px;text-align:center;border:2px dashed #e2e8f0;border-radius:16px;background:linear-gradient(160deg,#f8fafc 0%,#f1f5f9 100%);">
			<div style="width:80px;height:80px;background:linear-gradient(135deg,#3b82f6,#8b5cf6);border-radius:22px;display:flex;align-items:center;justify-content:center;font-size:36px;margin:0 auto 20px;box-shadow:0 8px 28px rgba(59,130,246,.22);">🔤</div>
			<h3 style="margin:0 0 10px;font-size:17px;font-weight:700;color:#0f172a;"><?php esc_html_e( 'No saved titles yet', 'super-fast-blog-ai' ); ?></h3>
			<p style="margin:0 auto;font-size:13.5px;color:#64748b;max-width:340px;line-height:1.65;">
				<?php esc_html_e( 'Enter a blog topic above and click', 'super-fast-blog-ai' ); ?>
				<span style="color:#3b82f6;font-weight:600;">&nbsp;"<?php esc_html_e( 'Generate Titles', 'super-fast-blog-ai' ); ?>"&nbsp;</span>
				<?php esc_html_e( 'to get AI-powered title ideas in seconds.', 'super-fast-blog-ai' ); ?>
			</p>
			<div style="margin-top:22px;display:flex;align-items:center;justify-content:center;gap:18px;font-size:12px;color:#94a3b8;flex-wrap:wrap;">
				<span>✨ <?php esc_html_e( 'AI-powered', 'super-fast-blog-ai' ); ?></span>
				<span style="color:#e2e8f0;">•</span>
				<span>⚡ <?php esc_html_e( 'Instant results', 'super-fast-blog-ai' ); ?></span>
				<span style="color:#e2e8f0;">•</span>
				<span>💾 <?php esc_html_e( 'Auto-saved', 'super-fast-blog-ai' ); ?></span>
			</div>
		</div>
		<?php endif; ?>

	</div><!-- /tab-titles -->

	<!-- ===================================================================
	     TAB 2 — Generate Article
	     =================================================================== -->
	<style>
	/* ── Optional section toggle pills ── */
	.sfba-opt-toggle { cursor:pointer; display:inline-block; }
	.sfba-opt-toggle-face {
		display:inline-flex; align-items:center; gap:8px;
		padding:10px 20px; border:2px solid #e5e7eb; border-radius:10px;
		font-size:13px; font-weight:500; color:#374151; background:#fff;
		transition:all .15s ease; user-select:none; line-height:1.3;
	}
	.sfba-opt-toggle-face:hover { border-color:#93c5fd; background:#eff6ff; color:#1d4ed8; }
	.sfba-opt-tick {
		display:none; align-items:center; justify-content:center;
		width:18px; height:18px; background:#2563eb; border-radius:50%;
		color:#fff; font-size:10px; font-weight:800; flex-shrink:0;
	}
	.sfba-opt-toggle input:checked + .sfba-opt-toggle-face { background:#eff6ff; border-color:#3b82f6; color:#1d4ed8; font-weight:600; }
	.sfba-opt-toggle input:checked + .sfba-opt-toggle-face .sfba-opt-tick { display:inline-flex; }
	/* ── Progress steps ── */
	.sfba-pstep {
		flex:1; display:flex; flex-direction:column; align-items:center; gap:5px;
		font-size:11px; color:#9ca3af; text-align:center; position:relative;
	}
	.sfba-pstep:not(:last-child)::after {
		content:''; position:absolute; top:12px; left:calc(50% + 14px);
		right:calc(-50% + 14px); height:2px; background:#e5e7eb; z-index:0;
		transition:background .4s;
	}
	.sfba-pstep.done:not(:last-child)::after { background:#22c55e; }
	.sfba-pstep-dot {
		width:26px; height:26px; border-radius:50%; background:#e5e7eb;
		display:flex; align-items:center; justify-content:center; font-size:12px;
		transition:all .3s ease; position:relative; z-index:1; flex-shrink:0;
	}
	.sfba-pstep.active .sfba-pstep-dot { background:#3b82f6; box-shadow:0 0 0 4px #bfdbfe; }
	.sfba-pstep.done   .sfba-pstep-dot { background:#22c55e; }
	.sfba-pstep.active { color:#1d4ed8; font-weight:600; }
	.sfba-pstep.done   { color:#16a34a; }
	/* ── Article preview ── */
	#sfba-art-preview-content h1,
	#sfba-art-preview-content h2 { font-size:1.25em; font-weight:700; margin:1.3em 0 .5em; color:#111827; border-bottom:1px solid #f3f4f6; padding-bottom:.3em; }
	#sfba-art-preview-content h3 { font-size:1.08em; font-weight:600; margin:1.1em 0 .4em; color:#1f2937; }
	#sfba-art-preview-content h4 { font-size:1em; font-weight:600; margin:1em 0 .3em; color:#374151; }
	#sfba-art-preview-content p  { margin:0 0 .9em; line-height:1.75; }
	#sfba-art-preview-content ul,
	#sfba-art-preview-content ol { margin:0 0 .9em 1.6em; }
	#sfba-art-preview-content li { margin-bottom:.35em; }
	#sfba-art-preview-content strong { font-weight:700; }
	#sfba-art-preview-content table { width:100%; border-collapse:collapse; margin:.9em 0; font-size:13px; }
	#sfba-art-preview-content th,
	#sfba-art-preview-content td { border:1px solid #e5e7eb; padding:8px 14px; }
	#sfba-art-preview-content th { background:#f9fafb; font-weight:600; }
	</style>

	<div id="sfba-tab-article" class="sfba-tab-panel" style="display:none;">

		<!-- ── Main form card ── -->
		<div class="sfba-card" style="margin-bottom:16px;">

			<!-- Card header -->
			<div style="display:flex;align-items:center;gap:14px;margin-bottom:26px;padding-bottom:20px;border-bottom:1px solid var(--sfba-grey-100);">
				<div style="width:44px;height:44px;background:linear-gradient(135deg,#3b82f6,#8b5cf6);border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0;">📝</div>
				<div>
					<h3 style="margin:0 0 3px;font-size:16px;font-weight:700;color:#111827;"><?php esc_html_e( 'Article Generator', 'super-fast-blog-ai' ); ?></h3>
					<p class="sfba-muted" style="margin:0;"><?php esc_html_e( 'Generate a full SEO-optimised blog post and save it as a WordPress draft.', 'super-fast-blog-ai' ); ?></p>
				</div>
			</div>

			<!-- Post Title -->
			<div class="sfba-field-group" style="margin-bottom:20px;">
				<label class="sfba-label" for="sfba-art-title">
					<?php esc_html_e( 'Post Title', 'super-fast-blog-ai' ); ?>
					<span style="color:#ef4444;margin-left:3px;">*</span>
				</label>
				<input type="text" id="sfba-art-title" class="sfba-input"
				       style="font-size:15px;padding:12px 16px;font-weight:500;"
				       placeholder="<?php esc_attr_e( 'Enter title', 'super-fast-blog-ai' ); ?>">
			</div>

			<!-- Row 1: Content Type | Language | Writing Style -->
			<div class="sfba-form-grid sfba-form-grid-3" style="margin-bottom:16px;">
				<div class="sfba-field-group">
					<label class="sfba-label" for="sfba-art-content-type"><?php esc_html_e( 'Content Type', 'super-fast-blog-ai' ); ?></label>
					<select id="sfba-art-content-type" class="sfba-input">
						<?php
						$saved_ctype = $s->get( 'content.content_type', 'blog' );
						foreach ( SFBA_Model_Router::CONTENT_TYPES as $ct_slug => $ct_label ) :
						?>
						<option value="<?php echo esc_attr( $ct_slug ); ?>" <?php selected( $saved_ctype, $ct_slug ); ?>><?php echo esc_html( $ct_label ); ?></option>
						<?php endforeach; ?>
					</select>
					<span class="sfba-field-hint"><?php esc_html_e( 'Uses the matching Model Routing rule.', 'super-fast-blog-ai' ); ?></span>
				</div>
				<div class="sfba-field-group">
					<label class="sfba-label" for="sfba-art-language"><?php esc_html_e( 'Language', 'super-fast-blog-ai' ); ?></label>
					<select id="sfba-art-language" class="sfba-input">
						<?php
						$saved_lang = $s->get( 'content.language', 'English' );
						foreach ( [ 'English', 'Bangla', 'Spanish', 'French', 'German', 'Italian', 'Portuguese', 'Dutch', 'Polish', 'Japanese', 'Chinese' ] as $lang ) :
						?>
						<option value="<?php echo esc_attr( $lang ); ?>" <?php selected( $saved_lang, $lang ); ?>><?php echo esc_html( $lang ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="sfba-field-group">
					<label class="sfba-label" for="sfba-art-style"><?php esc_html_e( 'Writing Style', 'super-fast-blog-ai' ); ?></label>
					<select id="sfba-art-style" class="sfba-input">
						<?php
						$saved_style = $s->get( 'content.writing_style', 'informative' );
						foreach ( [ 'informative', 'conversational', 'professional', 'creative', 'academic' ] as $st ) :
						?>
						<option value="<?php echo esc_attr( $st ); ?>" <?php selected( $saved_style, $st ); ?>><?php echo esc_html( ucfirst( $st ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>

			<!-- Row 2: Tone | Word Count | No. Headings | Heading Tag -->
			<div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:14px;margin-bottom:20px;">
				<div class="sfba-field-group">
					<label class="sfba-label" for="sfba-art-tone"><?php esc_html_e( 'Tone', 'super-fast-blog-ai' ); ?></label>
					<select id="sfba-art-tone" class="sfba-input">
						<?php
						$saved_tone = $s->get( 'content.tone', 'neutral' );
						foreach ( [ 'neutral', 'friendly', 'formal', 'casual', 'authoritative', 'empathetic' ] as $t ) :
						?>
						<option value="<?php echo esc_attr( $t ); ?>" <?php selected( $saved_tone, $t ); ?>><?php echo esc_html( ucfirst( $t ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="sfba-field-group">
					<label class="sfba-label" for="sfba-art-word-count"><?php esc_html_e( 'Word Count', 'super-fast-blog-ai' ); ?></label>
					<input type="number" id="sfba-art-word-count" class="sfba-input"
					       value="<?php echo esc_attr( $s->get( 'content.word_count', 1200 ) ); ?>"
					       min="300" max="8000" step="100">
				</div>
				<div class="sfba-field-group">
					<label class="sfba-label" for="sfba-art-heading-count"><?php esc_html_e( 'No. of Headings', 'super-fast-blog-ai' ); ?></label>
					<input type="number" id="sfba-art-heading-count" class="sfba-input"
					       value="<?php echo esc_attr( $s->get( 'content.heading_count', 5 ) ); ?>"
					       min="1" max="20">
				</div>
				<div class="sfba-field-group">
					<label class="sfba-label" for="sfba-art-heading-tag"><?php esc_html_e( 'Heading Tag', 'super-fast-blog-ai' ); ?></label>
					<select id="sfba-art-heading-tag" class="sfba-input">
						<?php foreach ( [ 'h2', 'h3', 'h4' ] as $tag ) : ?>
						<option value="<?php echo esc_attr( $tag ); ?>" <?php selected( $s->get( 'content.heading_tag', 'h2' ), $tag ); ?>><?php echo esc_html( strtoupper( $tag ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>

			<!-- Row 3: Focus Keywords -->
			<div class="sfba-field-group" style="margin-bottom:16px;">
				<label class="sfba-label" for="sfba-art-keywords"><?php esc_html_e( 'Focus Keywords', 'super-fast-blog-ai' ); ?></label>
				<input type="text" id="sfba-art-keywords" class="sfba-input"
				       placeholder="<?php esc_attr_e( 'e.g. WordPress SEO, best plugins, 2025', 'super-fast-blog-ai' ); ?>">
				<span class="sfba-field-hint"><?php esc_html_e( 'Comma-separated. First keyword is primary and placed most often.', 'super-fast-blog-ai' ); ?></span>
			</div>

			<!-- Row 4: Meta Description (textarea) -->
			<div class="sfba-field-group" style="margin-bottom:24px;">
				<label class="sfba-label" for="sfba-art-meta">
					<?php esc_html_e( 'Meta Description', 'super-fast-blog-ai' ); ?>
					<span class="sfba-muted" style="font-weight:400;margin-left:6px;"><?php esc_html_e( '(optional)', 'super-fast-blog-ai' ); ?></span>
				</label>
				<textarea id="sfba-art-meta" class="sfba-textarea" rows="3"
				          placeholder="<?php esc_attr_e( 'Leave blank to auto-generate. Max 160 characters recommended for best SEO results.', 'super-fast-blog-ai' ); ?>"></textarea>
				<span class="sfba-field-hint"><?php esc_html_e( 'Written to Yoast / Rank Math if the plugin is active.', 'super-fast-blog-ai' ); ?></span>
			</div>

			<hr class="sfba-section-sep">

			<!-- Optional Sections — tick toggle pills -->
			<div style="margin-bottom:24px;">
				<p class="sfba-label" style="margin-bottom:12px;"><?php esc_html_e( 'Optional Sections', 'super-fast-blog-ai' ); ?></p>
				<div style="display:flex;gap:10px;flex-wrap:wrap;">
					<?php
					$opt_items = [
						'sfba-art-faq'       => [ 'key' => 'content.faq',       'icon' => '❓', 'label' => __( 'FAQ Section',        'super-fast-blog-ai' ) ],
						'sfba-art-toc'       => [ 'key' => 'content.toc',       'icon' => '📋', 'label' => __( 'Table of Contents',  'super-fast-blog-ai' ) ],
						'sfba-art-pros-cons' => [ 'key' => 'content.pros_cons', 'icon' => '⚖️', 'label' => __( 'Pros &amp; Cons',   'super-fast-blog-ai' ) ],
					];
					foreach ( $opt_items as $opt_id => $opt ) :
					?>
					<label class="sfba-opt-toggle">
						<input type="checkbox" id="<?php echo esc_attr( $opt_id ); ?>"
						       style="position:absolute;opacity:0;pointer-events:none;"
						       <?php checked( $s->get( $opt['key'], false ) ); ?>>
						<span class="sfba-opt-toggle-face">
							<span class="sfba-opt-tick">✓</span>
							<span><?php echo esc_html( $opt['icon'] ); ?> <?php echo esc_html( $opt['label'] ); ?></span>
						</span>
					</label>
					<?php endforeach; ?>
				</div>
			</div>

			<!-- Action bar -->
			<div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
				<button type="button" id="sfba-art-outline-btn" class="sfba-btn sfba-btn-secondary sfba-btn-lg">
					🗂 <?php esc_html_e( 'Preview Outline', 'super-fast-blog-ai' ); ?>
				</button>
				<button type="button" id="sfba-art-generate-btn" class="sfba-btn sfba-btn-primary sfba-btn-lg"
				        style="background:linear-gradient(135deg,#2563eb,#7c3aed);border-color:transparent;">
					🚀 <?php esc_html_e( 'Generate &amp; Save Draft', 'super-fast-blog-ai' ); ?>
				</button>
			</div>

			<!-- Progress indicator (hidden until generating) -->
			<div id="sfba-art-progress" style="display:none;margin-top:22px;padding:20px 22px;background:#f0f7ff;border:1px solid #bfdbfe;border-radius:12px;">
				<div style="display:flex;margin-bottom:16px;">
					<?php
					$psteps = [
						1 => [ 'icon' => '📋', 'label' => __( 'Preparing', 'super-fast-blog-ai' ) ],
						2 => [ 'icon' => '🤖', 'label' => __( 'Sending',   'super-fast-blog-ai' ) ],
						3 => [ 'icon' => '✍️',  'label' => __( 'Writing',   'super-fast-blog-ai' ) ],
						4 => [ 'icon' => '💾', 'label' => __( 'Saving',    'super-fast-blog-ai' ) ],
					];
					foreach ( $psteps as $n => $ps ) :
					?>
					<div class="sfba-pstep" id="sfba-pstep-<?php echo esc_attr( $n ); ?>">
						<div class="sfba-pstep-dot"><?php echo esc_html( $ps['icon'] ); ?></div>
						<span style="margin-top:3px;"><?php echo esc_html( $ps['label'] ); ?></span>
					</div>
					<?php endforeach; ?>
				</div>
				<div style="height:6px;background:#dbeafe;border-radius:3px;overflow:hidden;margin-bottom:10px;">
					<div id="sfba-art-progress-fill"
					     style="height:100%;width:0%;background:linear-gradient(90deg,#3b82f6,#8b5cf6);border-radius:3px;transition:width .8s ease;"></div>
				</div>
				<p id="sfba-art-progress-msg" style="margin:0;font-size:13px;color:#2563eb;font-weight:500;min-height:1.4em;"></p>
			</div>

		</div><!-- /main form card -->

		<!-- Outline preview card -->
		<div id="sfba-art-outline-box" class="sfba-card" style="display:none;margin-bottom:16px;">
			<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
				<h4 style="margin:0;font-size:14px;font-weight:600;">🗂 <?php esc_html_e( 'Outline Preview', 'super-fast-blog-ai' ); ?></h4>
				<button type="button" id="sfba-art-outline-btn-2" class="sfba-btn sfba-btn-secondary sfba-btn-sm">
					↻ <?php esc_html_e( 'Regenerate', 'super-fast-blog-ai' ); ?>
				</button>
			</div>
			<pre id="sfba-art-outline-content" class="sfba-outline-pre"></pre>
		</div>

		<!-- Article preview panel -->
		<div id="sfba-art-preview-box" style="display:none;margin-bottom:16px;">
			<div class="sfba-card" style="padding:0;overflow:hidden;border:2px solid #bbf7d0;">

				<!-- Success header bar -->
				<div style="display:flex;align-items:center;justify-content:space-between;padding:14px 20px;background:linear-gradient(135deg,#f0fdf4,#dcfce7);border-bottom:1px solid #bbf7d0;flex-wrap:wrap;gap:10px;">
					<div style="display:flex;align-items:center;gap:10px;">
						<div style="width:32px;height:32px;background:#16a34a;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;font-size:15px;font-weight:700;flex-shrink:0;">✓</div>
						<div>
							<strong style="font-size:14px;color:#14532d;display:block;"><?php esc_html_e( 'Article Generated Successfully!', 'super-fast-blog-ai' ); ?></strong>
							<span id="sfba-art-preview-meta" style="font-size:12px;color:#166534;"></span>
						</div>
					</div>
					<div style="display:flex;gap:8px;flex-wrap:wrap;">
						<button type="button" id="sfba-art-copy-html-btn" class="sfba-btn sfba-btn-secondary sfba-btn-sm">
							📋 <?php esc_html_e( 'Copy HTML', 'super-fast-blog-ai' ); ?>
						</button>
						<a id="sfba-art-edit-link" href="#" target="_blank" class="sfba-btn sfba-btn-primary sfba-btn-sm">
							✏️ <?php esc_html_e( 'Open in Editor', 'super-fast-blog-ai' ); ?>
						</a>
						<button type="button" id="sfba-art-reset-btn" class="sfba-btn sfba-btn-secondary sfba-btn-sm">
							🔄 <?php esc_html_e( 'New Article', 'super-fast-blog-ai' ); ?>
						</button>
					</div>
				</div>

				<!-- Rendered article HTML -->
				<div id="sfba-art-preview-content"
				     style="padding:28px 32px;max-height:580px;overflow-y:auto;font-size:14px;line-height:1.8;color:#1f2937;">
				</div>

			</div>
		</div>

	</div><!-- /tab-article -->

</div><!-- .wrap -->

<script>
( function () {
	'use strict';
	const apiBase = <?php echo wp_json_encode( $api_base ); ?>;
	const nonce   = <?php echo wp_json_encode( $nonce ); ?>;

	// ── Helpers ────────────────────────────────────────────────────────────────
	function notice( msg, type ) {
		const el = document.getElementById( 'sfba-gen-notice' );
		if ( ! el ) return;
		el.textContent = msg;
		el.className = 'sfba-notice sfba-notice--' + type;
		el.style.display = 'block';
		el.scrollIntoView( { behavior: 'smooth', block: 'nearest' } );
		setTimeout( () => { el.style.display = 'none'; }, 6000 );
	}
	function escHtml( s ) {
		return String( s ).replace( /&/g,'&amp;' ).replace( /</g,'&lt;' ).replace( />/g,'&gt;' );
	}
	async function apiPost( path, body ) {
		const r = await fetch( apiBase + path, {
			method: 'POST',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
			body: JSON.stringify( body ),
		} );
		return r.json();
	}
	async function apiDelete( path ) {
		const r = await fetch( apiBase + path, { method: 'DELETE', headers: { 'X-WP-Nonce': nonce } } );
		return r.json();
	}
	function spinner( id, show, label ) {
		const el = document.getElementById( id );
		if ( ! el ) return;
		el.style.display = show ? 'inline-flex' : 'none';
		if ( label ) {
			const lbl = document.getElementById( id + '-label' );
			if ( lbl ) lbl.textContent = label;
		}
	}

	// ── Tab switching ──────────────────────────────────────────────────────────
	const panels = { titles: 'sfba-tab-titles', article: 'sfba-tab-article' };
	function switchTab( tab ) {
		document.querySelectorAll( '.sfba-tab-btn' ).forEach( b => b.classList.remove( 'sfba-tab-active' ) );
		Object.values( panels ).forEach( id => { const p = document.getElementById( id ); if(p) p.style.display='none'; } );
		const btn = document.querySelector( `.sfba-tab-btn[data-tab="${tab}"]` );
		if ( btn ) btn.classList.add( 'sfba-tab-active' );
		const panel = document.getElementById( panels[ tab ] );
		if ( panel ) panel.style.display = 'block';
	}
	document.querySelectorAll( '.sfba-tab-btn' ).forEach( btn => {
		btn.addEventListener( 'click', () => switchTab( btn.dataset.tab ) );
	} );

	// ── Pre-fill from URL params (coming from Content Calendar) ───────────────
	( function () {
		const params  = new URLSearchParams( window.location.search );
		const tab     = params.get( 'tab' );
		const title   = params.get( 'title' );
		const keyword = params.get( 'keyword' );
		if ( tab === 'article' ) switchTab( 'article' );
		if ( title ) {
			const el = document.getElementById( 'sfba-art-title' );
			if ( el ) el.value = title;
		}
		if ( keyword ) {
			const el = document.getElementById( 'sfba-art-keywords' );
			if ( el ) el.value = keyword;
		}
	} )();

	// ── Generate Titles ────────────────────────────────────────────────────────
	document.getElementById( 'sfba-generate-titles-btn' )?.addEventListener( 'click', async function () {
		const topic = document.getElementById( 'sfba-blog-title' )?.value.trim();
		if ( ! topic ) { notice( 'Please enter a blog topic.', 'error' ); return; }
		this.disabled = true;
		this.innerHTML = '<div class="sfba-spinner" style="width:14px;height:14px;border-width:2px;"></div> Generating…';
		spinner( 'sfba-titles-spinner', true );
		try {
			const data = await apiPost( '/title/generate', {
				blog_title: topic,
				wvariation: document.getElementById( 'sfba-wvariation' )?.value  || '5',
				tlanguage:  document.getElementById( 'sfba-tlanguage' )?.value || 'English',
				twstyle:    document.getElementById( 'sfba-twstyle' )?.value     || 'informative',
				writone:    document.getElementById( 'sfba-writone' )?.value      || 'neutral',
			} );
			spinner( 'sfba-titles-spinner', false );
			if ( data.success && Array.isArray( data.data ) && data.data.length ) {
				const list  = document.getElementById( 'sfba-titles-list' );
				const count = document.getElementById( 'sfba-titles-count' );
				if ( count ) count.textContent = data.data.length + ' titles';

				const now     = new Date();
				const dateStr = now.toLocaleDateString( undefined, { year: 'numeric', month: 'short', day: 'numeric' } );

				list.innerHTML = `
<div class="sfba-gen-group" style="border:1px solid var(--sfba-grey-200);border-radius:10px;overflow:hidden;">
	<div style="display:flex;align-items:center;justify-content:space-between;padding:11px 16px;background:var(--sfba-grey-50);border-bottom:1px solid var(--sfba-grey-200);">
		<div style="display:flex;align-items:center;gap:10px;">
			<span style="font-size:13px;font-weight:600;color:var(--sfba-grey-900);">${ escHtml( topic ) }</span>
			<span class="sfba-badge sfba-badge-gray">${ data.data.length }</span>
			<span class="sfba-muted" style="font-size:12px;">${ escHtml( dateStr ) }</span>
		</div>
	</div>
	<div style="padding:10px 14px;display:flex;flex-direction:column;gap:6px;">
		${ data.data.map( t => `
		<div class="sfba-title-result-item">
			<span class="sfba-title-result-text">${ escHtml( t ) }</span>
			<button type="button" class="sfba-btn sfba-btn-secondary sfba-btn-sm sfba-copy-title-btn"
			        data-title="${ escHtml( t ) }">
				📋 Copy
			</button>
			<button type="button" class="sfba-btn sfba-btn-secondary sfba-btn-sm sfba-use-for-article-btn"
			        data-title="${ escHtml( t ) }">
				📝 Write Article
			</button>
		</div>
		` ).join( '' ) }
	</div>
</div>`;

				document.getElementById( 'sfba-titles-result' ).style.display = 'block';
				// Hide the empty state once we have results
				const emptyState = document.getElementById( 'sfba-titles-empty-state' );
				if ( emptyState ) emptyState.style.display = 'none';
				wireUseForArticleBtns( list );
				wireCopyTitleBtns( list );
			} else {
				notice( data.message || 'Generation failed. Check your API key in Settings.', 'error' );
			}
		} catch ( e ) {
			spinner( 'sfba-titles-spinner', false );
			notice( 'Network error. Please try again.', 'error' );
		}
		this.disabled = false;
		this.innerHTML = '✨ <?php echo esc_js( __( 'Generate Titles', 'super-fast-blog-ai' ) ); ?>';
	} );

	// ── Wire "Write Article" buttons ───────────────────────────────────────────
	function wireUseForArticleBtns( root ) {
		( root || document ).querySelectorAll( '.sfba-use-for-article-btn' ).forEach( btn => {
			btn.addEventListener( 'click', function () {
				const titleInput = document.getElementById( 'sfba-art-title' );
				if ( titleInput ) titleInput.value = this.dataset.title;
				switchTab( 'article' );
				setTimeout( () => titleInput?.focus(), 100 );
			} );
		} );
	}
	wireUseForArticleBtns( null );

	// ── Wire "Copy" title buttons ──────────────────────────────────────────────
	function wireCopyTitleBtns( root ) {
		( root || document ).querySelectorAll( '.sfba-copy-title-btn' ).forEach( btn => {
			btn.addEventListener( 'click', function () {
				const title = this.dataset.title || '';
				const orig  = this.innerHTML;
				const self  = this;
				const markCopied = () => {
					self.innerHTML = '✓ Copied!';
					setTimeout( () => { self.innerHTML = orig; }, 2000 );
				};
				if ( navigator.clipboard ) {
					navigator.clipboard.writeText( title ).then( markCopied ).catch( () => {
						notice( '<?php echo esc_js( __( 'Copy failed — please copy manually.', 'super-fast-blog-ai' ) ); ?>', 'error' );
					} );
				} else {
					try {
						const ta = document.createElement( 'textarea' );
						ta.value = title;
						ta.style.cssText = 'position:fixed;opacity:0;top:0;left:0;';
						document.body.appendChild( ta );
						ta.focus(); ta.select();
						document.execCommand( 'copy' );
						document.body.removeChild( ta );
						markCopied();
					} catch ( e ) {
						notice( '<?php echo esc_js( __( 'Copy failed — please copy manually.', 'super-fast-blog-ai' ) ); ?>', 'error' );
					}
				}
			} );
		} );
	}
	wireCopyTitleBtns( null );

	// ── Delete single title ────────────────────────────────────────────────────
	document.querySelectorAll( '.sfba-delete-title-btn' ).forEach( btn => {
		btn.addEventListener( 'click', async function () {
			if ( ! confirm( '<?php echo esc_js( __( 'Delete this title?', 'super-fast-blog-ai' ) ); ?>' ) ) return;
			this.disabled = true;
			try {
				const data = await apiDelete( '/title/' + this.dataset.id );
				if ( data.success ) { this.closest( '.sfba-title-result-item, .sfba-title-row' )?.remove(); notice( 'Title deleted.', 'success' ); }
				else { notice( data.message || 'Delete failed.', 'error' ); this.disabled = false; }
			} catch ( e ) { notice( 'Network error.', 'error' ); this.disabled = false; }
		} );
	} );

	// ── Bulk delete group ──────────────────────────────────────────────────────
	document.querySelectorAll( '.sfba-bulk-delete-btn' ).forEach( btn => {
		btn.addEventListener( 'click', async function () {
			if ( ! confirm( '<?php echo esc_js( __( 'Delete this entire group? This cannot be undone.', 'super-fast-blog-ai' ) ); ?>' ) ) return;
			this.disabled = true; this.textContent = '🗑 Deleting…';
			try {
				const data = await apiPost( '/title/bulk-delete', { protids: [ parseInt( this.dataset.protid, 10 ) ] } );
				if ( data.success ) { this.closest( '.sfba-gen-group' )?.remove(); notice( 'Group deleted.', 'success' ); }
				else { notice( data.message || 'Delete failed.', 'error' ); this.disabled = false; this.textContent = '🗑 Delete'; }
			} catch ( e ) { notice( 'Network error.', 'error' ); this.disabled = false; this.textContent = '🗑 Delete'; }
		} );
	} );

	// ── Article payload ────────────────────────────────────────────────────────
	function articlePayload() {
		return {
			title:         document.getElementById( 'sfba-art-title' )?.value.trim()           || '',
			content_type:  document.getElementById( 'sfba-art-content-type' )?.value           || 'blog',
			keywords:      document.getElementById( 'sfba-art-keywords' )?.value.trim()        || '',
			meta_desc:     document.getElementById( 'sfba-art-meta' )?.value.trim()            || '',
			language:      document.getElementById( 'sfba-art-language' )?.value               || 'English',
			writing_style: document.getElementById( 'sfba-art-style' )?.value                 || 'informative',
			tone:          document.getElementById( 'sfba-art-tone' )?.value                   || 'neutral',
			word_count:    parseInt( document.getElementById( 'sfba-art-word-count' )?.value    || '1200', 10 ),
			heading_count: parseInt( document.getElementById( 'sfba-art-heading-count' )?.value || '5',    10 ),
			heading_tag:   document.getElementById( 'sfba-art-heading-tag' )?.value            || 'h2',
			faq:           !! document.getElementById( 'sfba-art-faq' )?.checked,
			toc:           !! document.getElementById( 'sfba-art-toc' )?.checked,
			pros_cons:     !! document.getElementById( 'sfba-art-pros-cons' )?.checked,
		};
	}

	// ── Progress management ────────────────────────────────────────────────────
	let _progTimers = [];
	function progressStart() {
		const box  = document.getElementById( 'sfba-art-progress' );
		const fill = document.getElementById( 'sfba-art-progress-fill' );
		const msg  = document.getElementById( 'sfba-art-progress-msg' );
		if ( box ) box.style.display = 'block';
		_progTimers.forEach( clearTimeout );
		_progTimers = [];
		// Reset all steps
		for ( let i = 1; i <= 4; i++ ) {
			const el = document.getElementById( 'sfba-pstep-' + i );
			if ( el ) el.classList.remove( 'active', 'done' );
		}
		if ( fill ) fill.style.width = '0%';
		const sequence = [
			{ delay:    0, pct:  8, step: 1, text: '<?php echo esc_js( __( '📋 Preparing your prompt…', 'super-fast-blog-ai' ) ); ?>' },
			{ delay:  900, pct: 25, step: 2, text: '<?php echo esc_js( __( '🤖 Sending to AI model…', 'super-fast-blog-ai' ) ); ?>' },
			{ delay: 4000, pct: 58, step: 3, text: '<?php echo esc_js( __( '✍️ Writing your article — this can take 20–60 seconds…', 'super-fast-blog-ai' ) ); ?>' },
			{ delay:22000, pct: 82, step: 3, text: '<?php echo esc_js( __( '✍️ Still writing — almost there…', 'super-fast-blog-ai' ) ); ?>' },
		];
		sequence.forEach( s => {
			const t = setTimeout( () => {
				if ( fill ) fill.style.width = s.pct + '%';
				if ( msg  ) msg.textContent  = s.text;
				progressActivateStep( s.step );
			}, s.delay );
			_progTimers.push( t );
		} );
	}
	function progressActivateStep( active ) {
		for ( let i = 1; i <= 4; i++ ) {
			const el = document.getElementById( 'sfba-pstep-' + i );
			if ( ! el ) continue;
			el.classList.remove( 'active', 'done' );
			if ( i < active )  el.classList.add( 'done' );
			if ( i === active ) el.classList.add( 'active' );
		}
	}
	function progressFinish( ok ) {
		_progTimers.forEach( clearTimeout );
		_progTimers = [];
		const fill = document.getElementById( 'sfba-art-progress-fill' );
		const msg  = document.getElementById( 'sfba-art-progress-msg' );
		const box  = document.getElementById( 'sfba-art-progress' );
		if ( fill ) fill.style.width = '100%';
		if ( ok ) {
			progressActivateStep( 4 );
			if ( msg ) msg.textContent = '<?php echo esc_js( __( '💾 Saving draft…', 'super-fast-blog-ai' ) ); ?>';
			setTimeout( () => {
				for ( let i = 1; i <= 4; i++ ) {
					const el = document.getElementById( 'sfba-pstep-' + i );
					if ( el ) { el.classList.remove( 'active' ); el.classList.add( 'done' ); }
				}
				if ( msg ) msg.textContent = '<?php echo esc_js( __( '✅ Done! Article saved as draft.', 'super-fast-blog-ai' ) ); ?>';
				setTimeout( () => { if ( box ) box.style.display = 'none'; }, 1400 );
			}, 600 );
		} else {
			if ( msg ) { msg.style.color = '#dc2626'; msg.textContent = '<?php echo esc_js( __( '❌ Generation failed.', 'super-fast-blog-ai' ) ); ?>'; }
		}
	}

	// ── Preview Outline ────────────────────────────────────────────────────────
	async function doOutline() {
		const payload = articlePayload();
		if ( ! payload.title ) { notice( '<?php echo esc_js( __( 'Please enter a post title.', 'super-fast-blog-ai' ) ); ?>', 'error' ); return; }
		const btns  = [ document.getElementById( 'sfba-art-outline-btn' ), document.getElementById( 'sfba-art-outline-btn-2' ) ];
		const saved = btns.map( b => b?.innerHTML || '' );
		btns.forEach( b => { if ( b ) { b.disabled = true; b.textContent = '⏳ <?php echo esc_js( __( 'Generating…', 'super-fast-blog-ai' ) ); ?>'; } } );
		try {
			const data = await apiPost( '/generate/outline', { title: payload.title, keywords: payload.keywords } );
			if ( data.success ) {
				document.getElementById( 'sfba-art-outline-content' ).textContent = data.data?.outline || '';
				const box = document.getElementById( 'sfba-art-outline-box' );
				box.style.display = 'block';
				box.scrollIntoView( { behavior: 'smooth', block: 'nearest' } );
			} else { notice( data.message || '<?php echo esc_js( __( 'Outline failed.', 'super-fast-blog-ai' ) ); ?>', 'error' ); }
		} catch ( e ) { notice( '<?php echo esc_js( __( 'Network error.', 'super-fast-blog-ai' ) ); ?>', 'error' ); }
		btns.forEach( ( b, i ) => { if ( b ) { b.disabled = false; b.innerHTML = saved[ i ]; } } );
	}
	document.getElementById( 'sfba-art-outline-btn'   )?.addEventListener( 'click', doOutline );
	document.getElementById( 'sfba-art-outline-btn-2' )?.addEventListener( 'click', doOutline );

	// ── Generate Full Article ──────────────────────────────────────────────────
	document.getElementById( 'sfba-art-generate-btn' )?.addEventListener( 'click', async function () {
		const payload = articlePayload();
		if ( ! payload.title ) { notice( '<?php echo esc_js( __( 'Please enter a post title first.', 'super-fast-blog-ai' ) ); ?>', 'error' ); return; }

		this.disabled = true;
		this.innerHTML = '<div class="sfba-spinner" style="width:14px;height:14px;border-width:2px;margin-right:6px;"></div> <?php echo esc_js( __( 'Generating…', 'super-fast-blog-ai' ) ); ?>';

		// Hide previous results
		document.getElementById( 'sfba-art-preview-box' ).style.display   = 'none';
		document.getElementById( 'sfba-art-outline-box' ).style.display   = 'none';

		progressStart();

		try {
			const data = await apiPost( '/generate/article', payload );

			progressFinish( data.success );

			if ( data.success && data.data?.post_id ) {
				const d = data.data;

				// Build meta line
				const meta = [];
				if ( d.model    ) meta.push( '🤖 ' + d.model );
				if ( d.cost_usd ) meta.push( '💰 $' + parseFloat( d.cost_usd ).toFixed( 6 ) );
				const wc = d.content ? d.content.replace( /<[^>]*>/g, '' ).trim().split( /\s+/ ).filter( Boolean ).length : 0;
				if ( wc ) meta.push( '📄 ~' + wc.toLocaleString() + ' <?php echo esc_js( __( 'words', 'super-fast-blog-ai' ) ); ?>' );

				document.getElementById( 'sfba-art-preview-meta' ).textContent = meta.join( '  ·  ' );
				document.getElementById( 'sfba-art-edit-link'    ).href = d.edit_url || ( '/wp-admin/post.php?post=' + d.post_id + '&action=edit' );

				// Render article HTML
				const previewEl = document.getElementById( 'sfba-art-preview-content' );
				if ( previewEl ) {
					previewEl.innerHTML  = d.content || '<p><?php echo esc_js( __( 'Content was saved but cannot be previewed here.', 'super-fast-blog-ai' ) ); ?></p>';
					previewEl._rawHtml   = d.content || '';
				}

				const previewBox = document.getElementById( 'sfba-art-preview-box' );
				previewBox.style.display = 'block';
				setTimeout( () => previewBox.scrollIntoView( { behavior: 'smooth', block: 'start' } ), 300 );

				notice( '<?php echo esc_js( __( '✅ Article saved as draft!', 'super-fast-blog-ai' ) ); ?>', 'success' );
			} else {
				notice( data.message || '<?php echo esc_js( __( 'Generation failed. Check your provider settings.', 'super-fast-blog-ai' ) ); ?>', 'error' );
			}
		} catch ( e ) {
			progressFinish( false );
			notice( '<?php echo esc_js( __( 'Network error. Please try again.', 'super-fast-blog-ai' ) ); ?>', 'error' );
		}

		this.disabled = false;
		this.innerHTML = '🚀 <?php echo esc_js( __( 'Generate & Save Draft', 'super-fast-blog-ai' ) ); ?>';
	} );

	// ── Copy HTML ──────────────────────────────────────────────────────────────
	document.getElementById( 'sfba-art-copy-html-btn' )?.addEventListener( 'click', function () {
		const previewEl = document.getElementById( 'sfba-art-preview-content' );
		const html = previewEl?._rawHtml || previewEl?.innerHTML || '';
		if ( ! html.trim() ) { notice( '<?php echo esc_js( __( 'Nothing to copy.', 'super-fast-blog-ai' ) ); ?>', 'error' ); return; }
		const orig = this.innerHTML;
		try {
			if ( navigator.clipboard ) {
				navigator.clipboard.writeText( html ).then( () => {
					this.innerHTML = '✓ <?php echo esc_js( __( 'Copied!', 'super-fast-blog-ai' ) ); ?>';
					setTimeout( () => { this.innerHTML = orig; }, 5000 );
				} );
			} else {
				const ta = document.createElement( 'textarea' );
				ta.value = html;
				ta.style.cssText = 'position:fixed;opacity:0;top:0;left:0;';
				document.body.appendChild( ta );
				ta.focus(); ta.select();
				document.execCommand( 'copy' );
				document.body.removeChild( ta );
				this.innerHTML = '✓ <?php echo esc_js( __( 'Copied!', 'super-fast-blog-ai' ) ); ?>';
				setTimeout( () => { this.innerHTML = orig; }, 5000 );
			}
		} catch ( e ) {
			notice( '<?php echo esc_js( __( 'Copy failed — please copy manually.', 'super-fast-blog-ai' ) ); ?>', 'error' );
		}
	} );

	// ── New Article (reset) ────────────────────────────────────────────────────
	document.getElementById( 'sfba-art-reset-btn' )?.addEventListener( 'click', function () {
		document.getElementById( 'sfba-art-preview-box'  ).style.display = 'none';
		document.getElementById( 'sfba-art-progress'     ).style.display = 'none';
		document.getElementById( 'sfba-art-outline-box'  ).style.display = 'none';
		const titleEl = document.getElementById( 'sfba-art-title' );
		if ( titleEl ) { titleEl.value = ''; titleEl.focus(); }
		window.scrollTo( { top: 0, behavior: 'smooth' } );
	} );
} )();
</script>
