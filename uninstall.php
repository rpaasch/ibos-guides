<?php
/**
 * Rydder pluginets indstillinger op. Guidernes sider og billeder bevares,
 * så indhold ikke går tabt ved en fejl – slet dem manuelt, hvis de ikke skal bruges.
 *
 * @package IbosGuides
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;
wp_unschedule_hook( 'ibos_guides_rebuild' );
// "ibos_guides_retired" bevares bevidst: geninstalleres pluginet, kan telefoner, der stadig har
// en gammel app, så stadig få besked om at fjerne den.
foreach ( array( 'ibos_guides_routes', 'ibos_guides_rev', 'ibos_guides_flush' ) as $opt ) {
	delete_option( $opt );
}
// Cachede søgeindekser, ikon-låse/-fejlmarkører og importfremdrift/-låse.
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_%ibg\\_%' OR option_name LIKE '\\_transient\\_timeout\\_%ibg\\_%' OR option_name LIKE 'ibg\\_collect\\_lock\\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'ibos\\_guides\\_import\\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
// Genererede ikoner (kan genskabes).
$up  = wp_upload_dir();
$dir = trailingslashit( $up['basedir'] ) . 'ibos-guides';
foreach ( (array) glob( $dir . '/*.png' ) as $file ) {
	@unlink( $file ); // phpcs:ignore
}
@rmdir( $dir ); // phpcs:ignore
