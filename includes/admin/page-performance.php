<?php
/**
 * Performance Tracker admin page template.
 *
 * Variables:
 *   $dashboard   array  — top_posts, this_month, comparison_headline, untracked_ai_posts, has_sc_configured
 *   $comparison  array  — ai{}, manual{}, has_data
 *   $api_base    string
 *   $nonce       string
 *   $sc_connected bool
 *
 * @package SuperFastBlogAI
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$ai     = $comparison['ai']     ?? [];
$manual = $comparison['manual'] ?? [];
$month  = $dashboard['this_month'] ?? [ 'impressions' => 0, 'clicks' => 0, 'posts_tracked' => 0 ];
?>
<div class="wrap sfba-admin-wrap">

	<!-- Hero -->
	<div class="sfba-dash-hero" style="margin-bottom:20px;">
		<div class="sfba-dash-hero-brand">
			<div class="sfba-dash-logo-ring">
				<img src="<?php echo esc_url( SFBA_PLUGIN_URL . 'assets/images/menu_icon.svg' ); ?>" alt="" style="width:28px;height:28px;">
			</div>
			<div>
				<h1 style="margin:0 0 2px;"><?php esc_html_e( 'Performance', 'super-fast-blog-ai' ); ?></h1>
				<span class="sfba-dash-ver">v<?php echo esc_html( SFBA_VERSION ); ?></span>
			</div>
		</div>
		<div style="display:flex;gap:8px;flex-wrap:wrap;">
			<button type="button" id="sfba-perf-sync-btn" class="sfba-btn sfba-btn-primary" <?php echo $sc_connected ? '' : 'disabled'; ?>>
				🔄 <?php esc_html_e( 'Sync from Search Console', 'super-fast-blog-ai' ); ?>
			</button>
			<button type="button" id="sfba-perf-seed-btn" class="sfba-btn sfba-btn-secondary">
				🧪 <?php esc_html_e( 'Seed Mock Data', 'super-fast-blog-ai' ); ?>
			</button>
		</div>
	</div>

	<div id="sfba-perf-notice" class="sfba-notice" style="display:none;margin-bottom:12px;"></div>

	<?php if ( ! $sc_connected ) : ?>
	<div class="sfba-info-box--warning" style="margin-bottom:20px;">
		<strong><?php esc_html_e( 'Google Search Console not connected', 'super-fast-blog-ai' ); ?></strong>
		<p><?php esc_html_e( 'Add your OAuth access token and verified site URL in Settings → Search Console to sync real performance data. Use "Seed Mock Data" to preview the UI with sample data.', 'super-fast-blog-ai' ); ?></p>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=sfba-settings' ) ); ?>#search-console"
		   class="sfba-btn sfba-btn-secondary sfba-btn-sm">
			⚙️ <?php esc_html_e( 'Go to Settings', 'super-fast-blog-ai' ); ?>
		</a>
	</div>
	<?php endif; ?>

	<?php if ( ! empty( $dashboard['comparison_headline'] ) ) : ?>
	<div class="sfba-info-box" style="margin-bottom:20px;">
		<p style="font-size:14px;font-weight:600;color:#1e40af;margin:0;">
			📊 <?php echo esc_html( $dashboard['comparison_headline'] ); ?>
		</p>
	</div>
	<?php endif; ?>

	<!-- Stats row -->
	<div class="sfba-dash-stats" style="margin-bottom:20px;">
		<div class="sfba-dash-stat">
			<div class="sfba-dash-stat-icon sfba-dash-stat-icon--blue">👁</div>
			<div>
				<div class="sfba-dash-stat-value"><?php echo esc_html( number_format( (int) $month['impressions'] ) ); ?></div>
				<div class="sfba-dash-stat-label"><?php esc_html_e( 'Impressions (this month)', 'super-fast-blog-ai' ); ?></div>
			</div>
		</div>
		<div class="sfba-dash-stat">
			<div class="sfba-dash-stat-icon sfba-dash-stat-icon--green">🖱</div>
			<div>
				<div class="sfba-dash-stat-value"><?php echo esc_html( number_format( (int) $month['clicks'] ) ); ?></div>
				<div class="sfba-dash-stat-label"><?php esc_html_e( 'Clicks (this month)', 'super-fast-blog-ai' ); ?></div>
			</div>
		</div>
		<div class="sfba-dash-stat">
			<div class="sfba-dash-stat-icon sfba-dash-stat-icon--purple">📄</div>
			<div>
				<div class="sfba-dash-stat-value"><?php echo esc_html( number_format( (int) $month['posts_tracked'] ) ); ?></div>
				<div class="sfba-dash-stat-label"><?php esc_html_e( 'Posts Tracked', 'super-fast-blog-ai' ); ?></div>
			</div>
		</div>
		<?php if ( ( $dashboard['untracked_ai_posts'] ?? 0 ) > 0 ) : ?>
		<div class="sfba-dash-stat">
			<div class="sfba-dash-stat-icon sfba-dash-stat-icon--orange">⚠️</div>
			<div>
				<div class="sfba-dash-stat-value"><?php echo esc_html( $dashboard['untracked_ai_posts'] ); ?></div>
				<div class="sfba-dash-stat-label"><?php esc_html_e( 'AI Posts Not Synced', 'super-fast-blog-ai' ); ?></div>
			</div>
		</div>
		<?php endif; ?>
	</div>

	<!-- AI vs Manual comparison -->
	<?php if ( $comparison['has_data'] ?? false ) : ?>
	<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px;">
		<?php foreach ( [ 'ai' => [ 'label' => '🤖 AI-Generated Posts', 'color' => '#3b82f6' ], 'manual' => [ 'label' => '✍️ Manual Posts', 'color' => '#6b7280' ] ] as $key => $cfg ) :
			$agg = $$key;
		?>
		<div class="sfba-card">
			<h3 style="color:<?php echo esc_attr( $cfg['color'] ); ?>;"><?php echo esc_html( $cfg['label'] ); ?></h3>
			<table class="sfba-info-table">
				<tr><td><?php esc_html_e( 'Posts tracked', 'super-fast-blog-ai' ); ?></td><td><?php echo esc_html( number_format( (int) ( $agg['post_count'] ?? 0 ) ) ); ?></td></tr>
				<tr><td><?php esc_html_e( 'Avg impressions/wk', 'super-fast-blog-ai' ); ?></td><td><?php echo esc_html( number_format( (float) ( $agg['avg_impressions'] ?? 0 ), 1 ) ); ?></td></tr>
				<tr><td><?php esc_html_e( 'Avg clicks/wk', 'super-fast-blog-ai' ); ?></td><td><?php echo esc_html( number_format( (float) ( $agg['avg_clicks'] ?? 0 ), 1 ) ); ?></td></tr>
				<tr><td><?php esc_html_e( 'Avg position', 'super-fast-blog-ai' ); ?></td><td><?php echo esc_html( number_format( (float) ( $agg['avg_position'] ?? 0 ), 1 ) ); ?></td></tr>
			</table>
		</div>
		<?php endforeach; ?>
	</div>
	<?php endif; ?>

	<!-- Top posts -->
	<?php if ( ! empty( $dashboard['top_posts'] ) ) : ?>
	<div class="sfba-card">
		<h3><?php esc_html_e( 'Top AI Posts by Clicks', 'super-fast-blog-ai' ); ?></h3>
		<table class="sfba-cost-table" style="margin-top:12px;">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Post', 'super-fast-blog-ai' ); ?></th>
					<th><?php esc_html_e( 'Impressions', 'super-fast-blog-ai' ); ?></th>
					<th><?php esc_html_e( 'Clicks', 'super-fast-blog-ai' ); ?></th>
					<th><?php esc_html_e( 'Best Position', 'super-fast-blog-ai' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $dashboard['top_posts'] as $tp ) : ?>
			<tr>
				<td>
					<a href="<?php echo esc_url( $tp['post_url'] ); ?>" target="_blank">
						<?php echo esc_html( $tp['post_title'] ); ?>
					</a>
				</td>
				<td><?php echo esc_html( number_format( (int) $tp['impressions'] ) ); ?></td>
				<td><?php echo esc_html( number_format( (int) $tp['clicks'] ) ); ?></td>
				<td><?php echo esc_html( number_format( (float) $tp['best_position'], 1 ) ); ?></td>
			</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php else : ?>
	<div class="sfba-empty-state">
		<div class="sfba-empty-icon">📈</div>
		<h3><?php esc_html_e( 'No performance data yet', 'super-fast-blog-ai' ); ?></h3>
		<p><?php esc_html_e( 'Sync from Google Search Console or seed mock data to see performance charts here.', 'super-fast-blog-ai' ); ?></p>
	</div>
	<?php endif; ?>

</div>

<script>
( function () {
	'use strict';
	const apiBase = <?php echo wp_json_encode( $api_base ); ?>;
	const nonce   = <?php echo wp_json_encode( $nonce ); ?>;

	function notice( msg, type ) {
		const el = document.getElementById( 'sfba-perf-notice' );
		if ( ! el ) return;
		el.textContent = msg; el.className = 'sfba-notice sfba-notice--' + type; el.style.display = 'block';
		setTimeout( () => { el.style.display = 'none'; }, 6000 );
	}
	async function apiPost( path, body ) {
		const r = await fetch( apiBase + path, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce }, body: JSON.stringify( body ) } );
		return r.json();
	}

	document.getElementById( 'sfba-perf-sync-btn' )?.addEventListener( 'click', async function () {
		this.disabled = true; this.textContent = '🔄 Syncing…';
		try {
			const data = await apiPost( '/performance/sync', { weeks: 4 } );
			if ( data.success ) { notice( data.data?.message || 'Sync complete.', 'success' ); setTimeout( () => window.location.reload(), 2000 ); }
			else { notice( data.message || 'Sync failed.', 'error' ); }
		} catch ( e ) { notice( 'Network error.', 'error' ); }
		this.disabled = false; this.textContent = '🔄 Sync from Search Console';
	} );

	document.getElementById( 'sfba-perf-seed-btn' )?.addEventListener( 'click', async function () {
		if ( ! confirm( 'Seed mock performance data? This overwrites existing data for affected posts.' ) ) return;
		this.disabled = true; this.textContent = '🧪 Seeding…';
		try {
			const data = await apiPost( '/performance/seed', { weeks: 8 } );
			if ( data.success ) { notice( data.data?.message || 'Mock data seeded.', 'success' ); setTimeout( () => window.location.reload(), 2000 ); }
			else { notice( data.message || 'Seeding failed.', 'error' ); }
		} catch ( e ) { notice( 'Network error.', 'error' ); }
		this.disabled = false; this.textContent = '🧪 Seed Mock Data';
	} );
} )();
</script>
