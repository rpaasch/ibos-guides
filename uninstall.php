<?php
/**
 * Rydder pluginets indstillinger op. Guidernes sider og billeder bevares,
 * så indhold ikke går tabt ved en fejl – slet dem manuelt, hvis de ikke skal bruges.
 *
 * @package IbosGuides
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

foreach ( array( 'ibos_guides_routes', 'ibos_guides_rev', 'ibos_guides_flush' ) as $opt ) {
	delete_option( $opt );
}
