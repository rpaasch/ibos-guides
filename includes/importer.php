<?php
/**
 * Import af eksisterende guides (import/*.json) med billeder fra GitHub.
 * Køres i små skridt via AJAX, så serverens tidsgrænse ikke rammes.
 *
 * @package IbosGuides
 */

defined( 'ABSPATH' ) || exit;

/**
 * Tilgængelige import-pakker.
 *
 * @return array<string,array> nøgle => data.
 */
function ibos_guides_import_packages() {
	$out = array();
	foreach ( (array) glob( IBOS_GUIDES_DIR . '/import/*.json' ) as $file ) {
		$data = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		// Kun gyldige pakker: billeder hentes fra en fastlåst commit på GitHub og tjekkes mod SHA-256.
		if ( is_array( $data ) && isset( $data['key'], $data['guide'], $data['pages'], $data['images'], $data['hashes'], $data['raw_base'] )
			&& is_string( $data['key'] ) && preg_match( '/^[a-z0-9-]+$/', $data['key'] )
			&& is_array( $data['pages'] ) && is_array( $data['images'] ) && is_array( $data['hashes'] )
			&& is_string( $data['raw_base'] ) && preg_match( '#^https://raw\.githubusercontent\.com/[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+/[0-9a-f]{40}/$#', $data['raw_base'] ) ) {
			$out[ $data['key'] ] = $data;
		}
	}
	return $out;
}

/**
 * Eksisterende guide fra en tidligere import.
 *
 * @param string $key Nøgle.
 * @return WP_Post|null
 */
function ibos_guides_imported( $key ) {
	$p = get_posts(
		array(
			'post_type'   => IBOS_GUIDES_CPT,
			'post_parent' => 0,
			'post_status' => array( 'publish', 'draft', 'pending', 'private' ),
			'meta_key'    => '_ibg_import_key', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'  => $key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			'numberposts' => 1,
		)
	);
	return $p ? $p[0] : null;
}

add_action( 'admin_menu', 'ibos_guides_import_menu' );
function ibos_guides_import_menu() {
	add_submenu_page( 'ibos-guides', __( 'Importér guide', 'ibos-guides' ), __( 'Importér', 'ibos-guides' ), 'publish_pages', 'ibos-guides-import', 'ibos_guides_import_page' );
}

function ibos_guides_import_page() {
	if ( ! current_user_can( 'publish_pages' ) || ! current_user_can( 'upload_files' ) ) {
		wp_die( esc_html__( 'Du har ikke adgang til denne side.', 'ibos-guides' ) );
	}
	$packages = ibos_guides_import_packages();
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Importér guide', 'ibos-guides' ); ?></h1>
		<p><?php esc_html_e( 'Opretter guiden med alle kapitler og sider. Billederne hentes fra GitHub og lægges i mediebiblioteket med deres alt-tekster. Det kan tage et par minutter – lad siden være åben imens. Bemærk: når importen er færdig, er guiden udgivet og kan ses af alle. Importér gerne på et testsite først, eller sæt guiden til Kladde bagefter.', 'ibos-guides' ); ?></p>
		<table class="widefat striped" style="max-width:56rem">
			<thead><tr>
				<th scope="col"><?php esc_html_e( 'Guide', 'ibos-guides' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Indhold', 'ibos-guides' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Status', 'ibos-guides' ); ?></th>
			</tr></thead>
			<tbody>
			<?php foreach ( $packages as $key => $pkg ) : $existing = ibos_guides_imported( $key ); ?>
				<tr>
					<th scope="row"><strong><?php echo esc_html( $pkg['guide']['title'] ); ?></strong></th>
					<td>
					<?php
					/* translators: 1: sider, 2: billeder */
					echo esc_html( sprintf( __( '%1$d sider, %2$d billeder', 'ibos-guides' ), count( $pkg['pages'] ), count( $pkg['images'] ) ) );
					?>
					</td>
					<td>
						<?php if ( $existing && 'publish' === $existing->post_status ) : ?>
							<?php esc_html_e( 'Importeret.', 'ibos-guides' ); ?>
							<a style="text-decoration:underline" href="<?php echo esc_url( get_permalink( $existing ) ); ?>"><?php esc_html_e( 'Åbn appen', 'ibos-guides' ); ?></a>
							<p class="description"><?php esc_html_e( 'Vil du importere igen, så læg den eksisterende guide i papirkurven først (Guides → Alle guides og sider). Papirkurven behøver ikke at blive tømt.', 'ibos-guides' ); ?></p>
						<?php else : ?>
							<?php if ( ! $existing ) : ?>
								<p>
									<label for="ibg-slug-<?php echo esc_attr( $key ); ?>"><?php esc_html_e( 'Adresse', 'ibos-guides' ); ?></label><br>
									<code><?php echo esc_html( home_url( '/' ) ); ?></code><input type="text" class="regular-text ibg-import-slug" id="ibg-slug-<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $pkg['guide']['slug'] ); ?>" aria-describedby="ibg-slug-help-<?php echo esc_attr( $key ); ?>" style="width:14em"><code>/</code>
								</p>
								<p class="description" id="ibg-slug-help-<?php echo esc_attr( $key ); ?>"><?php esc_html_e( 'Vælg selv adressen, fx "guides/om-teknik" eller "o-og-m". Små bogstaver, tal, bindestreg og skråstreg. Adresser, som sitet allerede bruger, afvises. Tip: åbn adressen i browseren først – viser den noget, så vælg en anden. Kan ændres senere under App-indstillinger.', 'ibos-guides' ); ?></p>
							<?php endif; ?>
							<button type="button" class="button button-primary ibg-import" data-key="<?php echo esc_attr( $key ); ?>">
								<?php
								/* translators: %s: guidens navn */
								echo esc_html( sprintf( __( 'Importér %s', 'ibos-guides' ), $pkg['guide']['title'] ) );
								?>
							</button>
							<?php if ( $existing ) : ?>
								<p class="description"><?php esc_html_e( 'En tidligere import blev ikke færdig – den fortsætter, hvor den slap.', 'ibos-guides' ); ?></p>
							<?php endif; ?>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<div id="ibg-import-progress" hidden style="max-width:56rem;margin-top:1.5em">
			<label for="ibg-progress"><?php esc_html_e( 'Fremdrift', 'ibos-guides' ); ?></label>
			<progress id="ibg-progress" max="100" value="0" style="width:100%;height:1.5em"></progress>
			<p id="ibg-import-status" role="status" aria-live="polite"></p>
		</div>
	</div>
	<script>
	( function () {
		var nonce = <?php echo wp_json_encode( wp_create_nonce( 'ibos_guides_import' ) ); ?>;
		var box = document.getElementById( 'ibg-import-progress' );
		var bar = document.getElementById( 'ibg-progress' );
		var status = document.getElementById( 'ibg-import-status' );
		function step( key, btn ) {
			var body = new FormData();
			body.append( 'action', 'ibos_guides_import' );
			body.append( 'key', key );
			var slugField = document.getElementById( 'ibg-slug-' + key );
			if ( slugField ) { body.append( 'slug', slugField.value ); }
			body.append( '_ajax_nonce', nonce );
			fetch( ajaxurl, { method: 'POST', body: body, credentials: 'same-origin' } )
				.then( function ( r ) { return r.json(); } )
				.then( function ( r ) {
					if ( ! r.success ) { throw new Error( r.data && r.data.message ? r.data.message : 'Ukendt fejl' ); }
					bar.value = r.data.progress;
					status.textContent = r.data.message;
					if ( r.data.done ) {
						var a = document.createElement( 'a' );
						a.href = r.data.url; a.style.textDecoration = "underline"; a.textContent = <?php echo wp_json_encode( __( 'Åbn appen', 'ibos-guides' ) ); ?>;
						status.appendChild( document.createTextNode( ' ' ) ); status.appendChild( a );
						btn.disabled = false;
					} else { step( key, btn ); }
				} )
				.catch( function ( e ) {
					status.textContent = <?php echo wp_json_encode( __( 'Importen stoppede: ', 'ibos-guides' ) ); ?> + e.message + ' ' + <?php echo wp_json_encode( __( 'Ret problemet (fx adressen ovenfor), og tryk på knappen igen. Det, der allerede er importeret, bliver ikke importeret igen.', 'ibos-guides' ) ); ?>;
					btn.disabled = false;
				} );
		}
		document.querySelectorAll( '.ibg-import' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				btn.disabled = true;
				box.hidden = false;
				status.textContent = <?php echo wp_json_encode( __( 'Importen er startet …', 'ibos-guides' ) ); ?>;
				step( btn.getAttribute( 'data-key' ), btn );
			} );
		} );
	} )();
	</script>
	<?php
}

add_action( 'wp_ajax_ibos_guides_import', 'ibos_guides_import_ajax' );
function ibos_guides_import_ajax() {
	check_ajax_referer( 'ibos_guides_import' );
	if ( ! current_user_can( 'publish_pages' ) || ! current_user_can( 'upload_files' ) ) {
		wp_send_json_error( array( 'message' => __( 'Ingen adgang.', 'ibos-guides' ) ), 403 );
	}
	$key      = isset( $_POST['key'] ) ? sanitize_key( wp_unslash( $_POST['key'] ) ) : '';
	$packages = ibos_guides_import_packages();
	if ( ! isset( $packages[ $key ] ) ) {
		wp_send_json_error( array( 'message' => __( 'Ukendt guide.', 'ibos-guides' ) ) );
	}
	$existing = ibos_guides_imported( $key );
	if ( $existing && false === get_option( 'ibos_guides_import_' . $key ) ) {
		wp_send_json_error( array( 'message' => __( 'Guiden er allerede importeret.', 'ibos-guides' ) ) );
	}
	// Lås: kun ét importskridt ad gangen pr. guide (fx hvis siden er åben i to faner).
	$lock = 'ibos_guides_import_lock_' . $key;
	if ( ! add_option( $lock, time(), '', false ) ) {
		if ( time() - (int) get_option( $lock ) < 5 * MINUTE_IN_SECONDS ) {
			wp_send_json_error( array( 'message' => __( 'Importen kører allerede i et andet vindue.', 'ibos-guides' ) ) );
		}
		update_option( $lock, time(), false ); // Forældet lås efter et nedbrud.
	}
	$pkg = $packages[ $key ];
	// Selvvalgt adresse: tjekkes, før der oprettes noget (kun ved første skridt).
	if ( ! $existing ) {
		$slug = ibos_guides_clean_slug( sanitize_text_field( ibos_guides_post_str( 'slug' ) ) );
		if ( '' !== $slug ) {
			$problem = ibos_guides_slug_problem( $slug, 0 );
			if ( $problem ) {
				delete_option( $lock );
				wp_send_json_error( array( 'message' => $problem ) );
			}
			$pkg['guide']['slug'] = $slug;
		}
	}
	try {
		$result = ibos_guides_import_step( $pkg );
		delete_option( $lock );
		wp_send_json_success( $result );
	} catch ( Throwable $e ) {
		delete_option( $lock );
		wp_send_json_error( array( 'message' => $e->getMessage() ) );
	}
}

/**
 * Ét skridt af importen.
 *
 * @param array $pkg Import-pakke.
 * @return array progress, message, done, url.
 * @throws Exception Ved fejl.
 */
function ibos_guides_import_step( $pkg ) {
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$opt   = 'ibos_guides_import_' . $pkg['key'];
	$state = get_option( $opt );
	$root  = ibos_guides_imported( $pkg['key'] );
	if ( ! is_array( $state ) || ! $root ) {
		$state = array( 'map' => array(), 'images' => array(), 'phase' => 'pages' );
	}
	$total_imgs = max( 1, count( $pkg['images'] ) );

	// 1. Opret guide og sider (som kladder).
	if ( 'pages' === $state['phase'] ) {
		$g = $pkg['guide'];
		if ( ! $root ) {
			$root_id = wp_insert_post(
				array(
					'post_type'    => IBOS_GUIDES_CPT,
					'post_status'  => 'draft',
					'post_title'   => wp_slash( $g['title'] ),
					'post_name'    => sanitize_title( basename( $g['slug'] ) ),
					'post_excerpt' => wp_slash( $g['excerpt'] ),
					'post_content' => wp_slash( $g['content'] ),
					'meta_input'   => array(
						'_ibg_import_key' => $pkg['key'],
						'_ibg_slug'       => $g['slug'],
						'_ibg_short_name' => $g['short_name'],
						'_ibg_color'      => $g['color'],
						'_ibg_publisher'  => $g['publisher'],
						'_ibg_colophon'   => $g['colophon'],
						'_ibg_menu'       => $g['menu'],
					),
				),
				true
			);
			if ( is_wp_error( $root_id ) ) {
				throw new Exception( $root_id->get_error_message() );
			}
			// Samme adressetjek som ved manuelt gem: en eksisterende side må ikke blive skjult.
			$problem = ibos_guides_slug_problem( $g['slug'], $root_id );
			if ( $problem ) {
				update_post_meta( $root_id, '_ibg_slug', '' );
				update_post_meta( $root_id, '_ibg_slug_error', $problem );
			}
			$root = get_post( $root_id );
		}
		$state['map']['root'] = (int) $root->ID;
		update_option( $opt, $state, false );
		foreach ( $pkg['pages'] as $p ) {
			if ( isset( $state['map'][ $p['key'] ] ) ) {
				continue;
			}
			if ( ! isset( $state['map'][ $p['parent'] ] ) ) {
				/* translators: %s: side */
				throw new Exception( sprintf( __( 'Pakken er ugyldig: siden "%s" har en ukendt forælder.', 'ibos-guides' ), $p['title'] ) );
			}
			$id = wp_insert_post(
				array(
					'post_type'    => IBOS_GUIDES_CPT,
					'post_status'  => 'draft',
					'post_title'   => wp_slash( $p['title'] ),
					'post_name'    => $p['slug'],
					'post_parent'  => (int) $state['map'][ $p['parent'] ],
					'menu_order'   => (int) $p['order'],
					'post_excerpt' => wp_slash( $p['excerpt'] ),
					'post_content' => wp_slash( $p['content'] ),
					'meta_input'   => array_filter(
						array(
							'_ibg_menu_title' => $p['menu_title'],
							'_ibg_hidden'     => $p['hidden'] ? '1' : '',
						)
					),
				),
				true
			);
			if ( is_wp_error( $id ) ) {
				throw new Exception( $id->get_error_message() );
			}
			$state['map'][ $p['key'] ] = (int) $id;
			update_option( $opt, $state, false ); // Gem fremdrift efter hver side (sikker genoptagelse).
		}
		// App-ikon.
		if ( ! empty( $g['icon_png'] ) && ! get_post_meta( $root->ID, '_ibg_icon_id', true ) ) {
			$up = wp_upload_bits( $pkg['key'] . '-ikon.png', null, base64_decode( $g['icon_png'] ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
			if ( empty( $up['error'] ) ) {
				$att = wp_insert_attachment( array( 'post_mime_type' => 'image/png', 'post_title' => $g['title'] . ' – ikon', 'post_status' => 'inherit' ), $up['file'], $root->ID );
				wp_update_attachment_metadata( $att, wp_generate_attachment_metadata( $att, $up['file'] ) );
				update_post_meta( $att, '_wp_attachment_image_alt', '' );
				update_post_meta( $root->ID, '_ibg_icon_id', $att );
			}
		}
		$state['phase'] = 'images';
		update_option( $opt, $state, false );
		return array(
			'progress' => 5,
			'done'     => false,
			/* translators: %d: antal sider */
			'message'  => sprintf( __( '%d sider oprettet. Henter billeder …', 'ibos-guides' ), count( $pkg['pages'] ) ),
		);
	}

	// 2. Hent billeder, nogle få ad gangen.
	if ( 'images' === $state['phase'] ) {
		$alts = ibos_guides_import_alts( $pkg );
		$n    = 0;
		foreach ( $pkg['images'] as $path ) {
			if ( isset( $state['images'][ $path ] ) ) {
				continue;
			}
			if ( $n >= 3 ) {
				break;
			}
			$n++;
			if ( ! is_string( $path ) || ! preg_match( '#^content/[A-Za-z0-9_./-]+\.(jpe?g|png|webp|gif)$#i', $path ) || false !== strpos( $path, '..' ) || empty( $pkg['hashes'][ $path ] ) ) {
				/* translators: %s: fil */
				throw new Exception( sprintf( __( 'Ugyldig billedsti i pakken: %s', 'ibos-guides' ), is_string( $path ) ? $path : '?' ) );
			}
			$tmp = wp_tempnam( basename( $path ) );
			$res = wp_safe_remote_get(
				$pkg['raw_base'] . str_replace( '%2F', '/', rawurlencode( $path ) ),
				array(
					'timeout'             => 60,
					'stream'              => true,
					'filename'            => $tmp,
					'limit_response_size' => 25 * MB_IN_BYTES,
				)
			);
			if ( ! is_wp_error( $res ) && 404 === (int) wp_remote_retrieve_response_code( $res ) ) {
				// Billedet findes ikke (længere): spring over, så importen ikke sidder fast.
				@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.unlink_unlink
				$state['images'][ $path ] = 0;
				$state['skipped'][]       = $path;
				update_option( $opt, $state, false );
				continue;
			}
			if ( is_wp_error( $res ) || 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
				@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.unlink_unlink
				/* translators: 1: fil, 2: fejl */
				throw new Exception( sprintf( __( 'Kunne ikke hente %1$s (%2$s).', 'ibos-guides' ), $path, is_wp_error( $res ) ? $res->get_error_message() : wp_remote_retrieve_response_code( $res ) ) );
			}
			// Forsyningskæde: billedet skal være præcis det, der blev eksporteret.
			if ( ! hash_equals( strtolower( (string) $pkg['hashes'][ $path ] ), (string) hash_file( 'sha256', $tmp ) ) ) {
				@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.unlink_unlink
				/* translators: %s: fil */
				throw new Exception( sprintf( __( 'Billedet %s matcher ikke den forventede kontrolsum og blev afvist.', 'ibos-guides' ), $path ) );
			}
			$att = media_handle_sideload( array( 'name' => basename( $path ), 'tmp_name' => $tmp ), (int) $state['map']['root'] );
			if ( is_wp_error( $att ) ) {
				@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.unlink_unlink
				throw new Exception( $att->get_error_message() );
			}
			update_post_meta( $att, '_wp_attachment_image_alt', isset( $alts[ $path ] ) ? $alts[ $path ] : '' );
			$state['images'][ $path ] = (int) $att;
			update_option( $opt, $state, false ); // Gem efter hvert billede (ingen dubletter ved genoptagelse).
		}
		$done_imgs = count( $state['images'] );
		if ( $done_imgs >= count( $pkg['images'] ) ) {
			$state['phase'] = 'finish';
		}
		update_option( $opt, $state, false );
		return array(
			'progress' => 5 + (int) floor( 90 * $done_imgs / $total_imgs ),
			'done'     => false,
			/* translators: 1: hentet, 2: i alt */
			'message'  => sprintf( __( 'Billeder: %1$d af %2$d hentet …', 'ibos-guides' ), $done_imgs, count( $pkg['images'] ) ),
		);
	}

	// 3. Indsæt billedblokke og udgiv.
	$all = array_merge( array( 'root' => (int) $state['map']['root'] ), $state['map'] );
	foreach ( $all as $id ) {
		$post    = get_post( $id );
		$content = preg_replace_callback(
			'/<!-- ibg:image (\{.*?\}) \/-->/s',
			function ( $m ) use ( $state ) {
				$d = json_decode( $m[1], true );
				return ! empty( $state['images'][ $d['src'] ] ) ? ibos_guides_image_block( $state['images'][ $d['src'] ], $d ) : '';
			},
			$post->post_content
		);
		wp_update_post(
			array(
				'ID'           => $id,
				'post_content' => wp_slash( $content ),
				'post_status'  => 'publish',
			)
		);
	}
	$skipped = isset( $state['skipped'] ) ? count( $state['skipped'] ) : 0;
	delete_option( $opt );
	ibos_guides_refresh_routes();
	update_option( 'ibos_guides_flush', 1 );
	ibos_guides_bump( $state['map']['root'] );
	if ( '' === ibos_guides_settings( $state['map']['root'] )['slug'] ) {
		return array(
			'progress' => 100,
			'done'     => true,
			'url'      => get_edit_post_link( $state['map']['root'], 'raw' ),
			/* translators: %s: guidens navn */
			'message'  => sprintf( __( '%s er importeret, men adressen var optaget. Vælg en anden adresse i App-indstillinger og gem.', 'ibos-guides' ), $pkg['guide']['title'] ),
		);
	}
	return array(
		'progress' => 100,
		'done'     => true,
		'url'      => ibos_guides_base_url( $state['map']['root'] ),
		/* translators: %s: guidens navn */
		'message'  => sprintf( __( '%s er importeret og udgivet.', 'ibos-guides' ), $pkg['guide']['title'] )
			/* translators: %d: antal */
			. ( $skipped ? ' ' . sprintf( _n( '%d billede kunne ikke hentes og er udeladt.', '%d billeder kunne ikke hentes og er udeladt.', $skipped, 'ibos-guides' ), $skipped ) : '' ),
	);
}

/**
 * Første alt-tekst pr. billede (bruges i mediebiblioteket).
 *
 * @param array $pkg Import-pakke.
 * @return array<string,string>
 */
function ibos_guides_import_alts( $pkg ) {
	$alts = array();
	$all  = array_merge( array( $pkg['guide']['content'] ), wp_list_pluck( $pkg['pages'], 'content' ) );
	foreach ( $all as $c ) {
		if ( preg_match_all( '/<!-- ibg:image (\{.*?\}) \/-->/s', $c, $m ) ) {
			foreach ( $m[1] as $json ) {
				$d = json_decode( $json, true );
				if ( $d && ! isset( $alts[ $d['src'] ] ) ) {
					$alts[ $d['src'] ] = (string) $d['alt'];
				}
			}
		}
	}
	return $alts;
}

/**
 * Gutenberg-markup for en billedblok.
 *
 * @param int   $att Vedhæftnings-id.
 * @param array $d   alt, caption, decorative.
 * @return string
 */
function ibos_guides_image_block( $att, $d ) {
	$src   = wp_get_attachment_image_src( $att, 'large' );
	$attrs = array( 'id' => (int) $att, 'sizeSlug' => 'large', 'linkDestination' => 'none' );
	$class = 'wp-block-image size-large';
	if ( ! empty( $d['decorative'] ) ) {
		$attrs['className'] = 'dekorativ';
		$class             .= ' dekorativ';
	}
	$caption = '' !== (string) $d['caption'] ? '<figcaption class="wp-element-caption">' . esc_html( $d['caption'] ) . '</figcaption>' : '';
	return '<!-- wp:image ' . wp_json_encode( $attrs, JSON_UNESCAPED_SLASHES ) . " -->\n"
		. '<figure class="' . esc_attr( $class ) . '"><img src="' . esc_url( $src ? $src[0] : wp_get_attachment_url( $att ) ) . '" alt="' . esc_attr( $d['alt'] ) . '" class="wp-image-' . (int) $att . '"/>' . $caption . "</figure>\n"
		. '<!-- /wp:image -->';
}
