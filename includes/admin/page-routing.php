<?php
/**
 * Model Routing admin page template.
 *
 * Variables: $rules array, $providers array, $content_types array,
 *            $api_base string, $nonce string
 *
 * @package SuperFastBlogAI
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<div class="wrap sfba-admin-wrap sfba-routing-page">

	<!-- ── Hero ──────────────────────────────────────────────────── -->
	<div class="sfba-dash-hero" style="margin-bottom:20px;">
		<div class="sfba-dash-hero-brand">
			<div class="sfba-dash-logo-ring">
				<img src="<?php echo esc_url( SFBA_PLUGIN_URL . 'assets/images/menu_icon.svg' ); ?>" alt="" style="width:28px;height:28px;">
			</div>
			<div>
				<h1 style="margin:0 0 2px;"><?php esc_html_e( 'Model Routing', 'super-fast-blog-ai' ); ?></h1>
				<p style="margin:0;font-size:13px;color:#6b7280;line-height:1.5;">
					<?php esc_html_e( 'Route each content type to the best AI model for the job.', 'super-fast-blog-ai' ); ?>
				</p>
			</div>
		</div>
		<button type="button" id="sfba-seed-defaults-btn" class="sfba-btn sfba-btn-secondary">
			⚡ <?php esc_html_e( 'Load Recommended Defaults', 'super-fast-blog-ai' ); ?>
		</button>
	</div>

	<!-- Global notice bar -->
	<div id="sfba-notice" class="sfba-notice" style="display:none;margin-bottom:12px;"></div>

	<!-- ── Routing rules ─────────────────────────────────────────── -->
	<?php
	$type_icons = [
		'blog'    => ['icon' => '📝', 'color' => '#eff6ff', 'border' => '#bfdbfe'],
		'product' => ['icon' => '🛒', 'color' => '#f0fdf4', 'border' => '#bbf7d0'],
		'social'  => ['icon' => '📢', 'color' => '#fdf4ff', 'border' => '#e9d5ff'],
		'meta'    => ['icon' => '🔍', 'color' => '#fff7ed', 'border' => '#fed7aa'],
		'email'   => ['icon' => '✉️', 'color' => '#fefce8', 'border' => '#fde68a'],
	];
	$provider_colors = [
		'openai'     => '#10a37f',
		'anthropic'  => '#d97706',
		'google'     => '#2563eb',
		'openrouter' => '#7c3aed',
		'deepseek'   => '#0891b2',
		'mistral'    => '#be123c',
		'ollama'     => '#374151',
	];
	?>
	<div class="sfba-rules-wrap">

		<?php if ( empty( $rules ) ) : ?>
		<div style="padding:52px 32px;text-align:center;border:2px dashed #e2e8f0;border-radius:16px;background:#fff;margin-bottom:20px;">

			<!-- Icon -->
			<div style="width:80px;height:80px;background:linear-gradient(135deg,#3b82f6,#8b5cf6);border-radius:22px;display:flex;align-items:center;justify-content:center;font-size:36px;margin:0 auto 20px;box-shadow:0 8px 28px rgba(59,130,246,.22);">🔀</div>

			<!-- Heading + description -->
			<h3 style="margin:0 0 10px;font-size:17px;font-weight:700;color:#0f172a;">
				<?php esc_html_e( 'No routing rules yet', 'super-fast-blog-ai' ); ?>
			</h3>
			<p style="margin:0 auto 26px;font-size:13.5px;color:#64748b;max-width:400px;line-height:1.65;">
				<?php esc_html_e( 'Route each content type to the best AI model for the job — faster, cheaper, and more accurate results.', 'super-fast-blog-ai' ); ?>
			</p>

			<!-- Content-type chips -->
			<div style="display:flex;flex-wrap:wrap;justify-content:center;gap:10px;margin-bottom:30px;">
				<?php foreach ( $type_icons as $type => $ti ) : ?>
				<div style="display:inline-flex;align-items:center;gap:7px;padding:8px 18px;background:#fff;border:1.5px solid <?php echo esc_attr( $ti['border'] ); ?>;border-radius:50px;font-size:12.5px;font-weight:500;color:#374151;box-shadow:0 1px 3px rgba(0,0,0,.05);">
					<?php echo $ti['icon']; ?>
					<?php echo esc_html( $content_types[ $type ] ?? ucfirst( $type ) ); ?>
				</div>
				<?php endforeach; ?>
			</div>

			<!-- CTAs -->
			<div style="display:flex;align-items:center;justify-content:center;gap:14px;flex-wrap:wrap;margin-bottom:24px;">
				<button type="button" id="sfba-seed-defaults-btn-empty" class="sfba-btn sfba-btn-primary sfba-btn-lg">
					⚡ <?php esc_html_e( 'Load Recommended Defaults', 'super-fast-blog-ai' ); ?>
				</button>
				<span style="color:#94a3b8;font-size:13px;"><?php esc_html_e( 'or add rules manually below', 'super-fast-blog-ai' ); ?></span>
			</div>

			<!-- Feature strip -->
			<div style="display:flex;align-items:center;justify-content:center;gap:18px;font-size:12px;color:#94a3b8;flex-wrap:wrap;">
				<span>🤖 <?php esc_html_e( 'Best model per task', 'super-fast-blog-ai' ); ?></span>
				<span style="color:#e2e8f0;">•</span>
				<span>💰 <?php esc_html_e( 'Reduce API cost', 'super-fast-blog-ai' ); ?></span>
				<span style="color:#e2e8f0;">•</span>
				<span>🎯 <?php esc_html_e( 'Better accuracy', 'super-fast-blog-ai' ); ?></span>
			</div>

		</div>
		<?php else : ?>

		<!-- Header row -->
		<div class="sfba-rules-header">
			<div class="sfba-rc sfba-rc--type"><?php esc_html_e( 'Content Type', 'super-fast-blog-ai' ); ?></div>
			<div class="sfba-rc sfba-rc--provider"><?php esc_html_e( 'Provider', 'super-fast-blog-ai' ); ?></div>
			<div class="sfba-rc sfba-rc--model"><?php esc_html_e( 'Model', 'super-fast-blog-ai' ); ?></div>
			<div class="sfba-rc sfba-rc--tokens"><?php esc_html_e( 'Max Tokens', 'super-fast-blog-ai' ); ?></div>
			<div class="sfba-rc sfba-rc--temp"><?php esc_html_e( 'Temp', 'super-fast-blog-ai' ); ?></div>
			<div class="sfba-rc sfba-rc--status"><?php esc_html_e( 'Status', 'super-fast-blog-ai' ); ?></div>
			<div class="sfba-rc sfba-rc--del"></div>
		</div>

		<div class="sfba-rules-body">
		<?php foreach ( $rules as $rule ) :
			$type_label = $content_types[ $rule['content_type'] ] ?? ucfirst( str_replace( '_', ' ', $rule['content_type'] ) );
			$ti = $type_icons[ $rule['content_type'] ]  ?? [ 'icon' => '⚙️', 'color' => '#f9fafb', 'border' => '#e5e7eb' ];
			$pc = $provider_colors[ $rule['provider'] ] ?? '#6b7280';
		?>
		<div class="sfba-rule-row">
			<div class="sfba-rc sfba-rc--type">
				<div class="sfba-rc-type-inner" style="background:<?php echo esc_attr( $ti['color'] ); ?>;border-color:<?php echo esc_attr( $ti['border'] ); ?>;">
					<span class="sfba-rc-icon"><?php echo $ti['icon']; ?></span>
					<span class="sfba-rc-type-label"><?php echo esc_html( $type_label ); ?></span>
				</div>
			</div>
			<div class="sfba-rc sfba-rc--provider">
				<span class="sfba-rc-provider-dot" style="background:<?php echo esc_attr( $pc ); ?>;"></span>
				<span class="sfba-rc-provider-name" style="color:<?php echo esc_attr( $pc ); ?>;">
					<?php echo esc_html( ucfirst( $rule['provider'] ) ); ?>
				</span>
			</div>
			<div class="sfba-rc sfba-rc--model">
				<code class="sfba-rc-model-code"><?php echo esc_html( $rule['model'] ); ?></code>
			</div>
			<div class="sfba-rc sfba-rc--tokens">
				<span class="sfba-rc-num"><?php echo esc_html( number_format( (int) $rule['max_tokens'] ) ); ?></span>
			</div>
			<div class="sfba-rc sfba-rc--temp">
				<span class="sfba-rc-num"><?php echo esc_html( $rule['temperature'] ); ?></span>
			</div>
			<div class="sfba-rc sfba-rc--status">
				<?php if ( $rule['is_active'] ) : ?>
				<span class="sfba-rc-status sfba-rc-status--active">
					<span class="sfba-rc-status-dot"></span>
					<?php esc_html_e( 'Active', 'super-fast-blog-ai' ); ?>
				</span>
				<?php else : ?>
				<span class="sfba-rc-status sfba-rc-status--inactive">
					<span class="sfba-rc-status-dot"></span>
					<?php esc_html_e( 'Inactive', 'super-fast-blog-ai' ); ?>
				</span>
				<?php endif; ?>
			</div>
			<div class="sfba-rc sfba-rc--del">
				<button type="button"
				        class="sfba-rc-del-btn sfba-delete-rule"
				        data-id="<?php echo esc_attr( $rule['id'] ); ?>"
				        title="<?php esc_attr_e( 'Delete rule', 'super-fast-blog-ai' ); ?>">
					<svg width="13" height="13" viewBox="0 0 20 20" fill="none">
						<path d="M6 4V3a1 1 0 011-1h6a1 1 0 011 1v1M3 4h14M8 9v6M12 9v6M5 4l1 13h8l1-13"
						      stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
					</svg>
				</button>
			</div>
		</div>
		<?php endforeach; ?>
		</div>

		<?php endif; ?>
	</div><!-- .sfba-rules-wrap -->

	<!-- ── Add rule form ─────────────────────────────────────────── -->
	<div class="sfba-card">
		<h3 style="margin:0 0 4px;font-size:15px;"><?php esc_html_e( 'Add / Update Rule', 'super-fast-blog-ai' ); ?></h3>
		<p style="margin:0 0 20px;font-size:12px;color:#6b7280;">
			<?php esc_html_e( 'Selecting a content type that already has a rule will update that rule.', 'super-fast-blog-ai' ); ?>
		</p>

		<div class="sfba-routing-form">
			<div class="sfba-routing-form-row">
				<div class="sfba-routing-form-field">
					<label class="sfba-label"><?php esc_html_e( 'Content Type', 'super-fast-blog-ai' ); ?></label>
					<select id="sfba-rule-type" class="sfba-select" style="width:100%;">
						<?php foreach ( $content_types as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="sfba-routing-form-field">
					<label class="sfba-label"><?php esc_html_e( 'Provider', 'super-fast-blog-ai' ); ?></label>
					<select id="sfba-rule-provider" class="sfba-select" style="width:100%;">
						<?php foreach ( $providers as $slug => $p ) : ?>
							<option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $p['name'] ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="sfba-routing-form-field" style="flex:2;">
					<label class="sfba-label"><?php esc_html_e( 'Model ID', 'super-fast-blog-ai' ); ?></label>
					<input type="text" id="sfba-rule-model" class="sfba-input"
					       placeholder="<?php esc_attr_e( 'e.g. claude-sonnet-4-6', 'super-fast-blog-ai' ); ?>">
				</div>
			</div>

			<div class="sfba-routing-form-row">
				<div class="sfba-routing-form-field sfba-routing-field-narrow">
					<label class="sfba-label"><?php esc_html_e( 'Max Tokens', 'super-fast-blog-ai' ); ?></label>
					<input type="number" id="sfba-rule-maxtokens" class="sfba-input"
					       value="4096" min="256" max="32768" step="256">
				</div>
				<div class="sfba-routing-form-field sfba-routing-field-narrow">
					<label class="sfba-label"><?php esc_html_e( 'Temperature', 'super-fast-blog-ai' ); ?></label>
					<input type="number" id="sfba-rule-temperature" class="sfba-input"
					       value="0.7" min="0" max="2" step="0.1">
				</div>
			</div>

			<div style="display:flex;align-items:center;gap:14px;padding-top:4px;">
				<button type="button" id="sfba-save-rule-btn" class="sfba-btn sfba-btn-primary"
				        style="padding:9px 28px;font-size:13px;font-weight:600;min-width:130px;">
					<?php esc_html_e( 'Save Rule', 'super-fast-blog-ai' ); ?>
				</button>
				<div id="sfba-rule-feedback" style="display:none;padding:8px 14px;border-radius:7px;font-size:13px;font-weight:500;"></div>
			</div>
		</div>
	</div>

</div><!-- .sfba-admin-wrap -->

<script>
( function () {
	'use strict';
	const apiBase = <?php echo wp_json_encode( $api_base ); ?>;
	const nonce   = <?php echo wp_json_encode( $nonce ); ?>;

	const providerDefaults = <?php echo wp_json_encode( [
		'openai'     => 'gpt-4o-mini',
		'anthropic'  => 'claude-haiku-4-5-20251001',
		'google'     => 'gemini-2.0-flash',
		'openrouter' => 'openai/gpt-4o-mini',
		'deepseek'   => 'deepseek-chat',
		'mistral'    => 'mistral-small-latest',
		'ollama'     => 'llama3.3',
	] ); ?>;

	const providerSelect = document.getElementById( 'sfba-rule-provider' );
	const modelInput     = document.getElementById( 'sfba-rule-model' );

	function applyProviderDefault() {
		if ( ! providerSelect || ! modelInput ) return;
		const slug = providerSelect.value;
		if ( providerDefaults[ slug ] ) modelInput.value = providerDefaults[ slug ];
	}
	applyProviderDefault();
	providerSelect?.addEventListener( 'change', applyProviderDefault );

	function globalNotice( msg, type ) {
		const el = document.getElementById( 'sfba-notice' );
		if ( ! el ) return;
		el.textContent = msg; el.className = 'sfba-notice sfba-notice--' + type; el.style.display = 'block';
		setTimeout( () => { el.style.display = 'none'; }, 5000 );
	}
	function saveFeedback( msg, ok ) {
		const el = document.getElementById( 'sfba-rule-feedback' );
		if ( ! el ) return;
		el.textContent = msg; el.style.display = 'block';
		el.style.background = ok ? '#dcfce7' : '#fee2e2';
		el.style.color      = ok ? '#166534' : '#991b1b';
		el.style.border     = '1px solid ' + ( ok ? '#bbf7d0' : '#fecaca' );
		if ( ok ) setTimeout( () => { el.style.display = 'none'; }, 3000 );
	}
	async function apiPost( path, body ) {
		const r = await fetch( apiBase + path, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce }, body: JSON.stringify( body ) } );
		return r.json();
	}
	async function apiDelete( path ) {
		const r = await fetch( apiBase + path, { method: 'DELETE', headers: { 'X-WP-Nonce': nonce } } );
		return r.json();
	}

	document.getElementById( 'sfba-save-rule-btn' )?.addEventListener( 'click', async function () {
		const model = document.getElementById( 'sfba-rule-model' )?.value.trim();
		if ( ! model ) { saveFeedback( 'Model ID is required.', false ); document.getElementById( 'sfba-rule-model' )?.focus(); return; }
		const body = {
			content_type: document.getElementById( 'sfba-rule-type' )?.value,
			provider:     document.getElementById( 'sfba-rule-provider' )?.value,
			model,
			max_tokens:  parseInt( document.getElementById( 'sfba-rule-maxtokens' )?.value, 10 ) || 4096,
			temperature: parseFloat( document.getElementById( 'sfba-rule-temperature' )?.value ) || 0.7,
		};
		this.disabled = true; this.textContent = 'Saving…';
		try {
			const data = await apiPost( '/routing/rules', body );
			if ( data.success ) { saveFeedback( '✓ Rule saved. Reloading…', true ); setTimeout( () => window.location.reload(), 1500 ); }
			else { saveFeedback( '✗ ' + ( data.message || 'Failed to save rule.' ), false ); this.disabled = false; this.textContent = 'Save Rule'; }
		} catch ( e ) { saveFeedback( '✗ Network error.', false ); this.disabled = false; this.textContent = 'Save Rule'; }
	} );

	document.querySelectorAll( '.sfba-delete-rule' ).forEach( btn => {
		btn.addEventListener( 'click', async function () {
			if ( ! confirm( 'Delete this routing rule?' ) ) return;
			this.style.opacity = '.4'; this.disabled = true;
			try {
				const data = await apiDelete( '/routing/rules/' + this.dataset.id );
				if ( data.success ) { this.closest( '.sfba-rule-row' )?.remove(); globalNotice( 'Rule deleted.', 'success' ); }
				else { globalNotice( data.message || 'Delete failed.', 'error' ); this.style.opacity = '1'; this.disabled = false; }
			} catch ( e ) { globalNotice( 'Network error.', 'error' ); this.style.opacity = '1'; this.disabled = false; }
		} );
	} );

	// Empty-state seed button delegates to the hero seed button handler.
	document.getElementById( 'sfba-seed-defaults-btn-empty' )?.addEventListener( 'click', function () {
		document.getElementById( 'sfba-seed-defaults-btn' )?.click();
	} );

	document.getElementById( 'sfba-seed-defaults-btn' )?.addEventListener( 'click', async function () {
		if ( ! confirm( 'Add the recommended default routing rules? Existing rules for the same content types will be updated.' ) ) return;
		this.disabled = true; this.textContent = '⚡ Loading…';
		try {
			const data = await apiPost( '/routing/seed-defaults', {} );
			if ( data.success ) { globalNotice( ( data.data?.inserted || 0 ) + ' default rules added. Reloading…', 'success' ); setTimeout( () => window.location.reload(), 1500 ); }
			else { globalNotice( data.message || 'Failed to seed defaults.', 'error' ); this.disabled = false; this.textContent = '⚡ Load Recommended Defaults'; }
		} catch ( e ) { globalNotice( 'Network error.', 'error' ); this.disabled = false; this.textContent = '⚡ Load Recommended Defaults'; }
	} );
} )();
</script>
