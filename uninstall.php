<?php
/**
 * Rydder pluginets indstillinger op. Guidernes sider og billeder bevares,
 * så indhold ikke går tabt ved en fejl – slet dem manuelt, hvis de ikke skal bruges.
 *
 * @package IbosGuides
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;
foreach ( array( 'ibos_guides_routes', 'ibos_guides_rev', 'ibos_guides_flush' ) as $opt ) {
	delete_option( $opt );
}
// Importfremdrift og låse.
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'ibos\\_guides\\_import\\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
// Genererede ikoner (kan genskabes).
$up  = wp_upload_dir();
$dir = trailingslashit( $up['basedir'] ) . 'ibos-guides';
foreach ( (array) glob( $dir . '/*.png' ) as $file ) {
	@unlink( $file ); // phpcs:ignore
}
@rmdir( $dir ); // phpcs:ignore
