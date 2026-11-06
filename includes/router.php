<?php
/**
 * Rewrite-regler og servering af apps.
 *
 * @package IbosGuides
 */

defined( 'ABSPATH' ) || exit;

/**
 * Regex til en guides rewrite-regel.
 *
 * @param string $slug Adresse.
 * @return string
 */
function ibos_guides_rule_regex( $slug ) {
	return '^' . preg_quote( $slug, '#' ) . '(?:/(.*))?/?$';
}

add_action( 'init', 'ibos_guides_register_rewrites', 20 );
function ibos_guides_register_rewrites() {
	foreach ( ibos_guides_routes() as $slug => $id ) {
		add_rewrite_rule( ibos_guides_rule_regex( $slug ), 'index.php?ibos_guide=' . (int) $id . '&ibos_guide_path=$matches[1]', 'top' );
	}
}

add_action( 'init', 'ibos_guides_maybe_flush', 99 );
function ibos_guides_maybe_flush() {
	if ( get_option( 'ibos_guides_flush' ) ) {
		delete_option( 'ibos_guides_flush' );
		flush_rewrite_rules( false );
	}
}

add_filter( 'query_vars', 'ibos_guides_query_vars' );
function ibos_guides_query_vars( $vars ) {
	$vars[] = 'ibos_guide';
	$vars[] = 'ibos_guide_path';
	return $vars;
}

add_action( 'parse_request', 'ibos_guides_parse_request', 1 );
function ibos_guides_parse_request( $wp ) {
	if ( empty( $wp->query_vars['ibos_guide'] ) ) {
		return;
	}
	$guide = get_post( (int) $wp->query_vars['ibos_guide'] );
	$path  = isset( $wp->query_vars['ibos_guide_path'] ) ? (string) $wp->query_vars['ibos_guide_path'] : '';
	if ( ! ibos_guides_is_guide( $guide ) || ! ibos_guides_request_in_base( $guide ) || ! ibos_guides_dispatch( $guide, $path ) ) {
		unset( $wp->query_vars['ibos_guide'], $wp->query_vars['ibos_guide_path'] );
		$wp->query_vars['error'] = '404';
	}
}

/**
 * Kommer forespørgslen faktisk fra guidens egen adresse?
 *
 * Query-variablerne er offentlige, så uden dette tjek kunne fx "/?ibos_guide=12&ibos_guide_path=sw.js"
 * servere appens service worker fra sitets rod, hvor den ville kunne styre hele sitet.
 *
 * @param WP_Post $guide Rod-side.
 * @return bool
 */
function ibos_guides_request_in_base( $guide ) {
	$base = (string) wp_parse_url( ibos_guides_base_url( $guide ), PHP_URL_PATH );
	$req  = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- kun sammenlignet.
	$req  = rawurldecode( $req );
	return '' !== $base && ( 0 === strpos( $req, $base ) || rtrim( $base, '/' ) === $req );
}

/**
 * Sender standard-headers for app-svar.
 *
 * @param string $type  Content-Type.
 * @param string $cache Cache-Control.
 */
function ibos_guides_headers( $type, $cache = 'no-cache' ) {
	if ( ! defined( 'DONOTCACHEPAGE' ) ) {
		define( 'DONOTCACHEPAGE', true ); // Cache-plugins må ikke gemme app-svar.
	}
	while ( ob_get_level() > 0 ) {
		ob_end_clean();
	}
	// HTML til en logget ind bruger må ikke gemmes i fælles caches (CDN/proxy).
	if ( 0 === strpos( $type, 'text/html' ) && is_user_logged_in() ) {
		$cache = 'private, ' . $cache;
	}
	status_header( 200 );
	header( 'Content-Type: ' . $type );
	header( 'Cache-Control: ' . $cache );
	header( 'X-Content-Type-Options: nosniff' );
	header( 'X-Frame-Options: SAMEORIGIN' );
	header( "Content-Security-Policy: frame-ancestors 'self'" );
	header( 'Referrer-Policy: strict-origin-when-cross-origin' );
}

/**
 * Behandler en forespørgsel under en guide. Returnerer false ved 404.
 *
 * @param WP_Post $guide Rod-side.
 * @param string  $path  Sti efter guidens adresse.
 * @return bool
 */
function ibos_guides_dispatch( $guide, $path ) {
	$path = ltrim( str_replace( '\\', '/', rawurldecode( $path ) ), '/' );
	if ( false !== strpos( $path, "\0" ) || preg_match( '#(^|/)\.#', $path ) ) {
		return false;
	}
	$s = ibos_guides_settings( $guide );

	switch ( $path ) {
		case 'sw.js':
			ibos_guides_headers( 'text/javascript; charset=utf-8', 'no-cache, no-store, must-revalidate' );
			header( 'Service-Worker-Allowed: ' . wp_parse_url( ibos_guides_base_url( $guide ), PHP_URL_PATH ) );
			echo ibos_guides_service_worker( $guide ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- genereret JavaScript.
			exit;
		case 'manifest.webmanifest':
			ibos_guides_headers( 'application/manifest+json; charset=utf-8' );
			echo wp_json_encode( ibos_guides_manifest( $guide ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
			exit;
		case 'search-index.json':
			ibos_guides_headers( 'application/json; charset=utf-8' );
			echo wp_json_encode( ibos_guides_search_index( $guide ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
			exit;
	}
	if ( preg_match( '#^icons/(icon-192|icon-512|icon-maskable-512|apple-touch-icon|favicon-32)\.png$#', $path, $m ) ) {
		$file = ibos_guides_icon_file( $guide, $m[1] );
		if ( ! $file ) {
			return false;
		}
		ibos_guides_headers( 'image/png', 'public, max-age=604800' );
		header( 'Content-Length: ' . filesize( $file ) );
		readfile( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		exit;
	}

	// Sider skal have afsluttende skråstreg, så relative stier og service worker-cache passer.
	$request_path = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- kun sammenlignet.
	$base         = ibos_guides_base_url( $guide );

	$special = array( 'soeg' => 'search', 'installer' => 'install' );
	$first   = trim( $path, '/' );
	if ( isset( $special[ $first ] ) && ! ibos_guides_resolve( $guide->ID, $first ) ) {
		if ( '/' !== substr( $request_path, -1 ) ) {
			wp_safe_redirect( $base . $first . '/', 301 );
			exit;
		}
		ibos_guides_headers( 'text/html; charset=utf-8' );
		echo ibos_guides_render_special( $guide, $special[ $first ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapet i skabelonen.
		exit;
	}

	$id = ibos_guides_resolve( $guide->ID, $path );
	if ( ! $id ) {
		return false;
	}
	if ( '/' !== substr( $request_path, -1 ) ) {
		wp_safe_redirect( $base . ibos_guides_path( $id, $guide->ID ), 301 );
		exit;
	}
	ibos_guides_headers( 'text/html; charset=utf-8' );
	echo ibos_guides_render_page( $guide, $id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapet i skabelonen.
	exit;
}
