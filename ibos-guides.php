<?php
/**
 * Plugin Name:       IBOS Guides
 * Description:       Tilgængelige guide-apps (PWA) redigeret som almindelige sider i WordPress. Hver guide får sin egen adresse, kan installeres på hjemmeskærmen og virker uden internet.
 * Version:           1.1.1
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            IBOS – Instituttet for Blinde og Svagsynede
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ibos-guides
 *
 * @package IbosGuides
 */

defined( 'ABSPATH' ) || exit;

define( 'IBOS_GUIDES_VERSION', '1.1.1' );
define( 'IBOS_GUIDES_FILE', __FILE__ );
define( 'IBOS_GUIDES_DIR', __DIR__ );
define( 'IBOS_GUIDES_CPT', 'ibos_guide_page' );

require_once IBOS_GUIDES_DIR . '/includes/guide.php';
require_once IBOS_GUIDES_DIR . '/includes/rest.php';
require_once IBOS_GUIDES_DIR . '/includes/post-type.php';
require_once IBOS_GUIDES_DIR . '/includes/router.php';
require_once IBOS_GUIDES_DIR . '/includes/render.php';
require_once IBOS_GUIDES_DIR . '/includes/icons.php';
require_once IBOS_GUIDES_DIR . '/includes/importer.php';
require_once IBOS_GUIDES_DIR . '/includes/admin-tree.php';

// Opgradering fra 1.0.0: dengang kunne en guides adresse komme fra sidens navn (tom adresse),
// og der var ingen markør. Guides, der var aktive apps i 1.0.0 (stod i ruterne), får deres
// adresse og markør gemt eksplicit, så de ikke forsvinder – eller fjernes fra brugernes telefoner.
add_action( 'init', 'ibos_guides_upgrade', 15 );
function ibos_guides_upgrade() {
	if ( version_compare( (string) get_option( 'ibos_guides_db', '1.0.0' ), '1.1.0', '>=' ) ) {
		return;
	}
	foreach ( ibos_guides_routes() as $slug => $id ) {
		if ( '' === (string) get_post_meta( $id, '_ibg_slug', true ) ) {
			update_post_meta( $id, '_ibg_slug', $slug );
		}
		if ( ! metadata_exists( 'post', $id, '_ibg_color' ) ) {
			add_post_meta( $id, '_ibg_color', '#00656c', true );
		}
	}
	ibos_guides_refresh_routes();
	update_option( 'ibos_guides_flush', 1 );
	update_option( 'ibos_guides_db', '1.1.0', true );
}

register_activation_hook( __FILE__, 'ibos_guides_activate' );
function ibos_guides_activate() {
	ibos_guides_register_post_type();
	ibos_guides_upgrade();
	ibos_guides_refresh_routes();
	ibos_guides_register_rewrites();
	flush_rewrite_rules( false );
}

register_deactivation_hook( __FILE__, 'ibos_guides_deactivate' );
function ibos_guides_deactivate() {
	global $wp_rewrite;
	foreach ( ibos_guides_routes() as $slug => $id ) {
		unset( $wp_rewrite->extra_rules_top[ ibos_guides_rule_regex( $slug ) ] );
	}
	foreach ( array_keys( ibos_guides_retired() ) as $slug ) {
		unset( $wp_rewrite->extra_rules_top[ ibos_guides_retired_regex( $slug ) ] );
	}
	flush_rewrite_rules( false );
}
