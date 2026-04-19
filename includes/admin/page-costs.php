<?php
/**
 * Cost Tracker admin page template.
 *
 * Variables:
 *   $summary  array  — total_cost_usd, total_generations, this_month_cost, by_provider, by_feature
 *   $budget   array  — pct, status, monthly_budget
 *   $api_base string
 *   $nonce    string
 *
 * @package SuperFastBlogAI
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$status_colors = [
	'ok'       => 'green',
	'warning'  => 'yellow',
	'critical' => 'orange',
	'over'     => 'red',
];
$budget_color = $status_colors[ $budget['status'] ?? 'ok' ] ?? 'green';
?>
<div class="wrap sfba-admin-wrap" style="max-width:1060px;">

	<!-- Hero -->
	<div class="sfba-dash-hero" style="margin-bottom:20px;">
		<div class="sfba-dash-hero-brand">
			<div class="sfba-dash-logo-ring">
				<img src="<?php echo esc_url( SFBA_PLUGIN_URL . 'assets/images/menu_icon.svg' ); ?>" alt="" style="width:28px;height:28px;">
			</div>
			<div>
				<h1 style="margin:0 0 2px;"><?php esc_html_e( 'Cost Tracker', 'super-fast-blog-ai' ); ?></h1>
				<span class="sfba-dash-ver">v<?php echo esc_html( SFBA_VERSION ); ?></span>
			</div>
		</div>
		<div style="display:flex;gap:8px;">
			<button type="button" id="sfba-cost-prune-btn" class="sfba-btn sfba-btn-secondary sfba-btn-sm">
				🧹 <?php esc_html_e( 'Prune Old Logs', 'super-fast-blog-ai' ); ?>
			</button>
		</div>
	</div>

	<div id="sfba-cost-notice" class="sfba-notice" style="display:none;margin-bottom:12px;"></div>

	<!-- Summary cards -->
	<div class="sfba-dash-stats" style="margin-bottom:20px;">
		<div class="sfba-dash-stat">
			<div class="sfba-dash-stat-icon sfba-dash-stat-icon--blue">🤖</div>
			<div>
				<div class="sfba-dash-stat-value"><?php echo esc_html( number_format( (int) ( $summary['total_generations'] ?? 0 ) ) ); ?></div>
				<div class="sfba-dash-stat-label"><?php esc_html_e( 'Total Generations', 'super-fast-blog-ai' ); ?></div>
			</div>
		</div>
		<div class="sfba-dash-stat">
			<div class="sfba-dash-stat-icon sfba-dash-stat-icon--green">💵</div>
			<div>
				<div class="sfba-dash-stat-value">$<?php echo esc_html( number_format( (float) ( $summary['this_month_cost'] ?? 0 ), 4 ) ); ?></div>
				<div class="sfba-dash-stat-label"><?php esc_html_e( 'Cost This Month', 'super-fast-blog-ai' ); ?></div>
			</div>
		</div>
		<div class="sfba-dash-stat">
			<div class="sfba-dash-stat-icon sfba-dash-stat-icon--purple">📊</div>
			<div>
				<div class="sfba-dash-stat-value">$<?php echo esc_html( number_format( (float) ( $summary['total_cost_usd'] ?? 0 ), 4 ) ); ?></div>
				<div class="sfba-dash-stat-label"><?php esc_html_e( 'Total Spend (All Time)', 'super-fast-blog-ai' ); ?></div>
			</div>
		</div>
		<?php if ( ( $budget['budget_usd'] ?? 0 ) > 0 ) : ?>
		<div class="sfba-dash-stat">
			<div class="sfba-dash-stat-icon sfba-dash-stat-icon--<?php echo in_array( $budget['status'] ?? '', [ 'critical', 'over' ] ) ? 'orange' : 'green'; ?>">🎯</div>
			<div>
				<div class="sfba-dash-stat-value"><?php echo esc_html( number_format( (float) ( $budget['percent_used'] ?? 0 ), 0 ) ); ?>%</div>
				<div class="sfba-dash-stat-label"><?php esc_html_e( 'Monthly Budget Used', 'super-fast-blog-ai' ); ?></div>
			</div>
		</div>
		<?php endif; ?>
	</div>

	<!-- Budget bar -->
	<?php if ( ( $budget['budget_usd'] ?? 0 ) > 0 ) : ?>
	<div class="sfba-card" style="margin-bottom:16px;">
		<h3><?php esc_html_e( 'Monthly Budget', 'super-fast-blog-ai' ); ?></h3>
		<div class="sfba-budget-bar-wrap">
			<div class="sfba-budget-labels">
				<span>$<?php echo esc_html( number_format( (float) ( $summary['this_month_cost'] ?? 0 ), 4 ) ); ?> <?php esc_html_e( 'used', 'super-fast-blog-ai' ); ?></span>
				<span><?php esc_html_e( 'Budget:', 'super-fast-blog-ai' ); ?> $<?php echo esc_html( number_format( (float) ( $budget['budget_usd'] ?? 0 ), 2 ) ); ?></span>
			</div>
			<div class="sfba-budget-bar">
				<div class="sfba-budget-bar-fill sfba-badge-<?php echo esc_attr( $budget_color ); ?>"
				     style="width:<?php echo esc_attr( min( 100, (float) ( $budget['percent_used'] ?? 0 ) ) ); ?>%;"></div>
			</div>
		</div>
		<?php if ( in_array( $budget['status'] ?? '', [ 'critical', 'over' ] ) ) : ?>
		<p class="sfba-budget-message">
			⚠️ <?php esc_html_e( 'You are close to or over your monthly budget.', 'super-fast-blog-ai' ); ?>
		</p>
		<?php endif; ?>
	</div>
	<?php endif; ?>

	<!-- Breakdown by provider -->
	<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
		<div class="sfba-card">
			<h3><?php esc_html_e( 'By Provider', 'super-fast-blog-ai' ); ?></h3>
			<div style="max-height:300px;overflow-y:auto;border:1px solid #e5e7eb;border-radius:8px;">
				<table class="sfba-cost-table" style="margin:0;border:none;border-radius:0;">
					<thead>
						<tr>
							<th style="position:sticky;top:0;background:#f9fafb;z-index:1;"><?php esc_html_e( 'Provider', 'super-fast-blog-ai' ); ?></th>
							<th style="position:sticky;top:0;background:#f9fafb;z-index:1;"><?php esc_html_e( 'Calls', 'super-fast-blog-ai' ); ?></th>
							<th style="position:sticky;top:0;background:#f9fafb;z-index:1;"><?php esc_html_e( 'Cost', 'super-fast-blog-ai' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $summary['by_provider'] ?? [] as $row ) : ?>
					<tr>
						<td><?php echo esc_html( ucfirst( $row['provider'] ) ); ?></td>
						<td><?php echo esc_html( number_format( (int) $row['count'] ) ); ?></td>
						<td>$<?php echo esc_html( number_format( (float) $row['cost'], 4 ) ); ?></td>
					</tr>
					<?php endforeach; ?>
					<?php if ( empty( $summary['by_provider'] ?? [] ) ) : ?>
					<tr><td colspan="3" class="sfba-table-loading"><?php esc_html_e( 'No data yet.', 'super-fast-blog-ai' ); ?></td></tr>
					<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>

		<div class="sfba-card">
			<h3><?php esc_html_e( 'By Feature', 'super-fast-blog-ai' ); ?></h3>
			<div style="max-height:300px;overflow-y:auto;border:1px solid #e5e7eb;border-radius:8px;">
				<table class="sfba-cost-table" style="margin:0;border:none;border-radius:0;">
					<thead>
						<tr>
							<th style="position:sticky;top:0;background:#f9fafb;z-index:1;"><?php esc_html_e( 'Feature', 'super-fast-blog-ai' ); ?></th>
							<th style="position:sticky;top:0;background:#f9fafb;z-index:1;"><?php esc_html_e( 'Calls', 'super-fast-blog-ai' ); ?></th>
							<th style="position:sticky;top:0;background:#f9fafb;z-index:1;"><?php esc_html_e( 'Cost', 'super-fast-blog-ai' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $summary['by_feature'] ?? [] as $row ) : ?>
					<tr>
						<td><?php echo esc_html( str_replace( '_', ' ', ucwords( $row['feature'], '_' ) ) ); ?></td>
						<td><?php echo esc_html( number_format( (int) $row['count'] ) ); ?></td>
						<td>$<?php echo esc_html( number_format( (float) $row['cost'], 4 ) ); ?></td>
					</tr>
					<?php endforeach; ?>
					<?php if ( empty( $summary['by_feature'] ?? [] ) ) : ?>
					<tr><td colspan="3" class="sfba-table-loading"><?php esc_html_e( 'No data yet.', 'super-fast-blog-ai' ); ?></td></tr>
					<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>
	</div>

	<!-- Recent generations log -->
	<div class="sfba-card">
		<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
			<h3 style="margin:0;"><?php esc_html_e( 'Recent Generations', 'super-fast-blog-ai' ); ?></h3>
			<div style="display:flex;gap:8px;align-items:center;">
				<label class="sfba-label" style="margin:0;"><?php esc_html_e( 'From', 'super-fast-blog-ai' ); ?></label>
				<input type="date" id="sfba-cost-from" class="sfba-input-sm">
				<label class="sfba-label" style="margin:0;"><?php esc_html_e( 'To', 'super-fast-blog-ai' ); ?></label>
				<input type="date" id="sfba-cost-to" class="sfba-input-sm">
				<button type="button" id="sfba-cost-filter-btn" class="sfba-btn sfba-btn-secondary sfba-btn-sm">
					<?php esc_html_e( 'Filter', 'super-fast-blog-ai' ); ?>
				</button>
			</div>
		</div>
		<div style="max-height:400px;overflow-y:auto;border:1px solid #e5e7eb;border-radius:8px;">
			<table class="sfba-cost-table" id="sfba-cost-log-table" style="margin:0;border:none;border-radius:0;">
				<thead>
					<tr>
						<th style="position:sticky;top:0;background:#f9fafb;z-index:1;"><?php esc_html_e( 'Time', 'super-fast-blog-ai' ); ?></th>
						<th style="position:sticky;top:0;background:#f9fafb;z-index:1;"><?php esc_html_e( 'Provider', 'super-fast-blog-ai' ); ?></th>
						<th style="position:sticky;top:0;background:#f9fafb;z-index:1;"><?php esc_html_e( 'Model', 'super-fast-blog-ai' ); ?></th>
						<th style="position:sticky;top:0;background:#f9fafb;z-index:1;"><?php esc_html_e( 'Feature', 'super-fast-blog-ai' ); ?></th>
						<th style="position:sticky;top:0;background:#f9fafb;z-index:1;"><?php esc_html_e( 'Tokens', 'super-fast-blog-ai' ); ?></th>
						<th style="position:sticky;top:0;background:#f9fafb;z-index:1;"><?php esc_html_e( 'Cost', 'super-fast-blog-ai' ); ?></th>
					</tr>
				</thead>
				<tbody id="sfba-cost-log-body">
					<tr><td colspan="6" class="sfba-table-loading"><?php esc_html_e( 'Loading…', 'super-fast-blog-ai' ); ?></td></tr>
				</tbody>
			</table>
		</div>
	</div>

</div>

<script>
( function () {
	'use strict';
	const apiBase = <?php echo wp_json_encode( $api_base ); ?>;
	const nonce   = <?php echo wp_json_encode( $nonce ); ?>;

	function notice( msg, type ) {
		const el = document.getElementById( 'sfba-cost-notice' );
		if ( ! el ) return;
		el.textContent = msg; el.className = 'sfba-notice sfba-notice--' + type; el.style.display = 'block';
		setTimeout( () => { el.style.display = 'none'; }, 5000 );
	}
	async function apiGet( path ) {
		const r = await fetch( apiBase + path, { headers: { 'X-WP-Nonce': nonce } } );
		return r.json();
	}
	async function apiPost( path, body ) {
		const r = await fetch( apiBase + path, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce }, body: JSON.stringify( body ) } );
		return r.json();
	}

	function loadLog( from, to ) {
		const tbody = document.getElementById( 'sfba-cost-log-body' );
		if ( ! tbody ) return;
		tbody.innerHTML = '<tr><td colspan="6" class="sfba-table-loading">Loading…</td></tr>';
		let url = '/costs/log?per_page=25';
		if ( from ) url += '&from=' + encodeURIComponent( from );
		if ( to )   url += '&to='   + encodeURIComponent( to );
		apiGet( url ).then( data => {
			if ( ! data.success || ! data.data?.rows?.length ) {
				tbody.innerHTML = '<tr><td colspan="6" class="sfba-table-loading">No records in this period.</td></tr>';
				return;
			}
			tbody.innerHTML = data.data.rows.map( r => `<tr>
				<td style="white-space:nowrap;font-size:11px;">${ escHtml( r.created_at || '' ) }</td>
				<td>${ escHtml( r.provider || '' ) }</td>
				<td><code style="font-size:11px;">${ escHtml( r.model || '' ) }</code></td>
				<td>${ escHtml( ( r.feature || '' ).replace( /_/g, ' ' ) ) }</td>
				<td>${ parseInt( r.prompt_tokens || 0 ) + parseInt( r.completion_tokens || 0 ) }</td>
				<td>$${ parseFloat( r.cost_usd || 0 ).toFixed( 6 ) }</td>
			</tr>` ).join( '' );
		} ).catch( () => {
			tbody.innerHTML = '<tr><td colspan="6" class="sfba-table-loading">Failed to load log.</td></tr>';
		} );
	}

	function escHtml( s ) { return String( s ).replace( /&/g,'&amp;' ).replace( /</g,'&lt;' ).replace( />/g,'&gt;' ); }

	// Load log on page ready
	loadLog( '', '' );

	document.getElementById( 'sfba-cost-filter-btn' )?.addEventListener( 'click', function () {
		const from = document.getElementById( 'sfba-cost-from' )?.value || '';
		const to   = document.getElementById( 'sfba-cost-to' )?.value   || '';
		loadLog( from, to );
	} );

	document.getElementById( 'sfba-cost-prune-btn' )?.addEventListener( 'click', async function () {
		if ( ! confirm( 'Prune generation logs older than 90 days? This cannot be undone.' ) ) return;
		this.disabled = true; this.textContent = '🧹 Pruning…';
		try {
			const data = await apiPost( '/costs/prune', {} );
			if ( data.success ) { notice( 'Pruned ' + ( data.data?.pruned || 0 ) + ' rows.', 'success' ); loadLog( '', '' ); }
			else { notice( data.message || 'Prune failed.', 'error' ); }
		} catch ( e ) { notice( 'Network error.', 'error' ); }
		this.disabled = false; this.textContent = '🧹 Prune Old Logs';
	} );
} )();
</script>
