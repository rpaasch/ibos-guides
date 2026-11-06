<?php
/**
 * App-ikoner: genereres fra guidens valgte billede med GD og gemmes i uploads.
 * Uden valgt billede (eller uden GD) bruges pluginets standardikoner.
 *
 * @package IbosGuides
 */

defined( 'ABSPATH' ) || exit;

/**
 * Sti til en ikonfil.
 *
 * @param WP_Post $guide Rod-side.
 * @param string  $name  icon-192|icon-512|icon-maskable-512|apple-touch-icon|favicon-32.
 * @return string|false
 */
function ibos_guides_icon_file( $guide, $name ) {
	$sizes = array(
		'icon-192'          => array( 192, 0 ),
		'icon-512'          => array( 512, 0 ),
		'icon-maskable-512' => array( 512, 0.1 ), // 10 % margin: motivet skal ligge i den sikre zone.
		'apple-touch-icon'  => array( 180, 0 ),
		'favicon-32'        => array( 32, 0 ),
	);
	if ( ! isset( $sizes[ $name ] ) ) {
		return false;
	}
	$default = IBOS_GUIDES_DIR . '/assets/icons/' . $name . '.png';
	$s       = ibos_guides_settings( $guide );
	$src     = $s['icon_id'] ? get_attached_file( $s['icon_id'] ) : '';
	if ( ! $src || ! is_readable( $src ) || ! function_exists( 'imagecreatefromstring' ) ) {
		return $default;
	}
	$up   = wp_upload_dir();
	$dir  = trailingslashit( $up['basedir'] ) . 'ibos-guides';
	$hash = substr( md5( $s['color'] . filemtime( $src ) . filesize( $src ) ), 0, 8 );
	$out  = $dir . '/' . (int) $guide->ID . '-' . (int) $s['icon_id'] . '-' . $hash . '-' . $name . '.png';
	if ( is_readable( $out ) ) {
		return $out;
	}
	// Tidligere mislykket forsøg på netop denne fil: brug standardikonet uden at prøve igen.
	$fail_key = 'ibg_icon_fail_' . md5( $out );
	if ( get_transient( $fail_key ) ) {
		return $default;
	}
	// Kun almindelige billedformater i rimelig størrelse (forhindrer "decompression bombs").
	$info = @getimagesize( $src ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	if ( ! $info || ! in_array( $info[2], array( IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_GIF, IMAGETYPE_WEBP ), true )
		|| $info[0] < 1 || $info[1] < 1 || $info[0] > 4096 || $info[1] > 4096 || filesize( $src ) > 10 * MB_IN_BYTES ) {
		set_transient( $fail_key, 1, DAY_IN_SECONDS );
		return $default;
	}
	// Kun én generering ad gangen pr. fil.
	$lock = 'ibg_icon_lock_' . md5( $out );
	if ( get_transient( $lock ) ) {
		return $default;
	}
	set_transient( $lock, 1, MINUTE_IN_SECONDS );
	wp_mkdir_p( $dir );
	$img = @imagecreatefromstring( (string) file_get_contents( $src ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	if ( ! $img ) {
		delete_transient( $lock );
		set_transient( $fail_key, 1, DAY_IN_SECONDS );
		return $default;
	}
	list( $size, $pad ) = $sizes[ $name ];
	$canvas = imagecreatetruecolor( $size, $size );
	// Baggrund i temafarven (iOS viser ikke gennemsigtighed).
	list( $r, $g, $b ) = sscanf( $s['color'], '#%02x%02x%02x' );
	imagefill( $canvas, 0, 0, imagecolorallocate( $canvas, $r, $g, $b ) );
	$w     = imagesx( $img );
	$h     = imagesy( $img );
	$inner = (int) round( $size * ( 1 - 2 * $pad ) );
	$scale = min( $inner / $w, $inner / $h );
	$dw    = (int) round( $w * $scale );
	$dh    = (int) round( $h * $scale );
	imagealphablending( $canvas, true );
	imagecopyresampled( $canvas, $img, (int) ( ( $size - $dw ) / 2 ), (int) ( ( $size - $dh ) / 2 ), 0, 0, $dw, $dh, $w, $h );
	// Skriv til en midlertidig fil og omdøb, så ingen kan læse en halvt skrevet PNG.
	$tmp = $out . '.' . wp_generate_password( 6, false ) . '.tmp';
	$ok  = imagepng( $canvas, $tmp ) && @rename( $tmp, $out ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.rename_rename
	imagedestroy( $canvas );
	imagedestroy( $img );
	if ( file_exists( $tmp ) ) {
		@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.unlink_unlink
	}
	delete_transient( $lock );
	if ( ! $ok || ! is_readable( $out ) ) {
		set_transient( $fail_key, 1, HOUR_IN_SECONDS ); // Fx skrivebeskyttet uploads-mappe.
		return $default;
	}
	return $out;
}
