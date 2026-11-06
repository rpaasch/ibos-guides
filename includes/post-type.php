<?php
/**
 * Post type, redigering og administration.
 *
 * @package IbosGuides
 */

defined( 'ABSPATH' ) || exit;

add_action( 'init', 'ibos_guides_register_post_type' );
function ibos_guides_register_post_type() {
	register_post_type(
		IBOS_GUIDES_CPT,
		array(
			'labels'              => array(
				'name'               => __( 'Guides', 'ibos-guides' ),
				'singular_name'      => __( 'Guide-side', 'ibos-guides' ),
				'menu_name'          => __( 'Guides', 'ibos-guides' ),
				'all_items'          => __( 'Alle guides og sider', 'ibos-guides' ),
				'add_new'            => __( 'Tilføj ny', 'ibos-guides' ),
				'add_new_item'       => __( 'Tilføj ny guide eller side', 'ibos-guides' ),
				'edit_item'          => __( 'Redigér', 'ibos-guides' ),
				'new_item'           => __( 'Ny side', 'ibos-guides' ),
				'view_item'          => __( 'Vis i appen', 'ibos-guides' ),
				'search_items'       => __( 'Søg i guides', 'ibos-guides' ),
				'not_found'          => __( 'Ingen sider fundet', 'ibos-guides' ),
				'parent_item_colon'  => __( 'Forælder:', 'ibos-guides' ),
			),
			'description'         => __( 'Sider i tilgængelige guide-apps.', 'ibos-guides' ),
			'public'              => false,
			'publicly_queryable'  => false,
			'exclude_from_search' => true,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_nav_menus'   => false,
			'show_in_rest'        => true,
			'hierarchical'        => true,
			'menu_position'       => 21,
			'menu_icon'           => 'dashicons-smartphone',
			'supports'            => array( 'title', 'editor', 'excerpt', 'page-attributes', 'revisions' ),
			'rewrite'             => false,
			'query_var'           => false,
			'capability_type'     => 'page',
			'map_meta_cap'        => true,
		)
	);
}

/* ---------------------------------------------------------------------------
 * REST: kun for brugere, der må redigere guides.
 *
 * Blok-editoren bruger REST, men offentligheden skal kun se indhold via appen, som selv
 * frasorterer kladder, private og adgangskodebeskyttede sider og sider under dem. Uden dette
 * kunne fx udgivne undersider af en kladde-guide læses anonymt via /wp-json/wp/v2/ibos_guide_page.
 * ------------------------------------------------------------------------- */

add_filter( 'rest_pre_dispatch', 'ibos_guides_rest_guard', 10, 3 );
function ibos_guides_rest_guard( $result, $server, $request ) {
	$route = (string) $request->get_route();
	if ( 0 === strpos( $route, '/wp/v2/' . IBOS_GUIDES_CPT ) && ! current_user_can( 'edit_pages' ) ) {
		return new WP_Error( 'rest_forbidden', __( 'Guide-sider er kun tilgængelige for redaktører via REST.', 'ibos-guides' ), array( 'status' => rest_authorization_required_code() ) );
	}
	return $result;
}

/* ---------------------------------------------------------------------------
 * Visningslink: peg på appen i stedet for en almindelig WP-side.
 * ------------------------------------------------------------------------- */

add_filter( 'post_type_link', 'ibos_guides_post_link', 10, 2 );
function ibos_guides_post_link( $link, $post ) {
	if ( IBOS_GUIDES_CPT !== $post->post_type ) {
		return $link;
	}
	$root = ibos_guides_root( $post );
	if ( ! $root || '' === ibos_guides_settings( $root )['slug'] ) {
		return $link;
	}
	return ibos_guides_base_url( $root ) . ibos_guides_path( $post->ID, $root->ID );
}

/* ---------------------------------------------------------------------------
 * Metabokse
 * ------------------------------------------------------------------------- */

add_action( 'add_meta_boxes_' . IBOS_GUIDES_CPT, 'ibos_guides_meta_boxes' );
function ibos_guides_meta_boxes( $post ) {
	add_meta_box( 'ibg-help', __( 'Sådan virker guides', 'ibos-guides' ), 'ibos_guides_box_help', null, 'side', 'high' );
	add_meta_box( 'ibg-check', __( 'Tilgængelighedstjek', 'ibos-guides' ), 'ibos_guides_box_check', null, 'side', 'default' );
	add_meta_box( 'ibg-page', __( 'Visning', 'ibos-guides' ), 'ibos_guides_box_page', null, 'side', 'default' );
	if ( ! $post->post_parent ) {
		add_meta_box( 'ibg-settings', __( 'App-indstillinger (kun for guidens øverste side)', 'ibos-guides' ), 'ibos_guides_box_settings', null, 'normal', 'high' );
	}
}

function ibos_guides_box_help( $post ) {
	echo '<p>' . esc_html__( 'En side uden forælder er en guide (en app). Dens tekst bliver appens forside.', 'ibos-guides' ) . '</p>';
	echo '<p>' . esc_html__( 'Sider med guiden som forælder er kapitler. Sider under et kapitel er indholdssider.', 'ibos-guides' ) . '</p>';
	echo '<p>' . esc_html__( 'Vælg forælder og rækkefølge under "Sideattributter". Lavt tal vises først.', 'ibos-guides' ) . '</p>';
	if ( 'publish' === $post->post_status ) {
		echo '<p><a href="' . esc_url( get_permalink( $post ) ) . '">' . esc_html__( 'Vis siden i appen', 'ibos-guides' ) . '</a></p>';
	}
}

function ibos_guides_box_check( $post ) {
	$w = ibos_guides_check( $post );
	if ( ! $w ) {
		echo '<p>' . esc_html__( 'Ingen problemer fundet ved sidste gem.', 'ibos-guides' ) . '</p>';
		return;
	}
	echo '<ul style="list-style:disc;padding-left:1.2em">';
	foreach ( $w as $msg ) {
		echo '<li>' . esc_html( $msg ) . '</li>';
	}
	echo '</ul><p><em>' . esc_html__( 'Tjekket opdateres, når siden gemmes og genindlæses.', 'ibos-guides' ) . '</em></p>';
}

function ibos_guides_box_page( $post ) {
	wp_nonce_field( 'ibg_save_' . $post->ID, 'ibg_nonce' );
	$hidden    = (bool) get_post_meta( $post->ID, '_ibg_hidden', true );
	$menu_meta = get_post_meta( $post->ID, '_ibg_child_menu', true );
	?>
	<p>
		<label><input type="checkbox" name="ibg_hidden" value="1" <?php checked( $hidden ); ?>>
		<?php esc_html_e( 'Skjul i menu, forrige/næste og søgning', 'ibos-guides' ); ?></label>
	</p>
	<p class="description"><?php esc_html_e( 'Bemærk: en skjult side kan stadig åbnes, hvis man kender adressen. Brug "Kladde" eller "Privat", hvis indholdet ikke må være offentligt.', 'ibos-guides' ); ?></p>
	<p>
		<label for="ibg-child-menu"><?php esc_html_e( 'Visning af undersider', 'ibos-guides' ); ?></label><br>
		<select name="ibg_child_menu" id="ibg-child-menu">
			<option value="" <?php selected( $menu_meta, '' ); ?>><?php esc_html_e( 'Som guidens standard', 'ibos-guides' ); ?></option>
			<option value="tiles" <?php selected( $menu_meta, 'tiles' ); ?>><?php esc_html_e( 'Felter', 'ibos-guides' ); ?></option>
			<option value="list" <?php selected( $menu_meta, 'list' ); ?>><?php esc_html_e( 'Liste', 'ibos-guides' ); ?></option>
		</select>
	</p>
	<p class="description"><?php esc_html_e( 'Uddraget (excerpt) vises som kort beskrivelse under linket i menuen.', 'ibos-guides' ); ?></p>
	<?php
}

function ibos_guides_box_settings( $post ) {
	$s        = ibos_guides_settings( $post );
	// Konflikter afvises ved gem (ibos_guides_slug_problem). En side, der oprettes senere på
	// samme adresse, opdages først her – derfor advares der stadig.
	$conflict = $s['slug'] ? get_page_by_path( $s['slug'], OBJECT, array( 'page', 'post' ) ) : null;
	if ( '' === $s['slug'] ) {
		$s['slug'] = ibos_guides_clean_slug( $post->post_name ? $post->post_name : sanitize_title( $post->post_title ) );
	}
	$contrast = ibos_guides_contrast( $s['color'], '#ffffff' );
	wp_enqueue_media();
	?>
	<?php $is_guide = metadata_exists( 'post', $post->ID, '_ibg_color' ) || 'auto-draft' === $post->post_status; ?>
	<p>
		<label><input type="checkbox" name="ibg_is_guide" value="1" <?php checked( $is_guide ); ?>>
		<strong><?php esc_html_e( 'Dette er en guide (en app med egen adresse)', 'ibos-guides' ); ?></strong></label>
	</p>
	<p class="description"><?php esc_html_e( 'Fjern fluebenet, hvis siden ikke skal være en offentlig app – fx et kapitel, der har mistet sin guide.', 'ibos-guides' ); ?></p>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="ibg-slug"><?php esc_html_e( 'Adresse', 'ibos-guides' ); ?></label></th>
			<td>
				<code><?php echo esc_html( home_url( '/' ) ); ?></code>
				<input type="text" id="ibg-slug" name="ibg_slug" class="regular-text" value="<?php echo esc_attr( $s['slug'] ); ?>" aria-describedby="ibg-slug-help">
				<code>/</code>
				<p class="description" id="ibg-slug-help"><?php esc_html_e( 'Små bogstaver, tal og bindestreg. Ændres adressen, skal brugere installere appen igen.', 'ibos-guides' ); ?></p>
				<?php $slug_error = get_post_meta( $post->ID, '_ibg_slug_error', true ); ?>
				<?php if ( $slug_error ) : ?>
					<p class="notice notice-error inline" style="padding:.5em"><?php echo esc_html( $slug_error ); ?></p>
				<?php endif; ?>
				<?php if ( $conflict ) : ?>
					<p class="notice notice-warning inline" style="padding:.5em"><?php esc_html_e( 'Der findes en almindelig side med samme adresse. Appen vises i stedet for den.', 'ibos-guides' ); ?></p>
				<?php endif; ?>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="ibg-short"><?php esc_html_e( 'Kort navn', 'ibos-guides' ); ?></label></th>
			<td><input type="text" id="ibg-short" name="ibg_short_name" maxlength="20" value="<?php echo esc_attr( $s['short_name'] ); ?>" aria-describedby="ibg-short-help">
				<p class="description" id="ibg-short-help"><?php esc_html_e( 'Navnet under ikonet på hjemmeskærmen (højst ca. 12 tegn).', 'ibos-guides' ); ?></p></td>
		</tr>
		<tr>
			<th scope="row"><label for="ibg-color"><?php esc_html_e( 'Temafarve', 'ibos-guides' ); ?></label></th>
			<td><input type="color" id="ibg-color" name="ibg_color" value="<?php echo esc_attr( $s['color'] ); ?>" aria-describedby="ibg-color-help">
				<p class="description" id="ibg-color-help">
				<?php
				/* translators: %s: kontrastforhold */
				echo esc_html( sprintf( __( 'Bruges til topbjælken med hvid tekst. Kontrast mod hvid: %s:1 (mindst 4,5:1 kræves).', 'ibos-guides' ), number_format_i18n( $contrast, 1 ) ) );
				?>
				</p>
				<?php if ( $contrast < 4.5 ) : ?>
					<p class="notice notice-error inline" style="padding:.5em"><?php esc_html_e( 'Farven er for lys til hvid tekst. Vælg en mørkere farve.', 'ibos-guides' ); ?></p>
				<?php endif; ?>
			</td>
		</tr>
		<?php
		foreach ( array(
			'icon_id' => array( __( 'App-ikon', 'ibos-guides' ), __( 'Kvadratisk billede, mindst 512×512 px. Hold motivet inden for de midterste 80 %.', 'ibos-guides' ) ),
			'logo_id' => array( __( 'Logo (valgfrit)', 'ibos-guides' ), __( 'Vises i topbjælken. Bør være hvidt eller lyst. Alt-teksten hentes fra mediebiblioteket.', 'ibos-guides' ) ),
		) as $key => $info ) :
			$img = $s[ $key ] ? wp_get_attachment_image_url( $s[ $key ], 'thumbnail' ) : '';
			?>
		<tr>
			<th scope="row"><?php echo esc_html( $info[0] ); ?></th>
			<td>
				<div class="ibg-media" data-target="ibg-<?php echo esc_attr( $key ); ?>">
					<img src="<?php echo esc_url( $img ); ?>" alt="" style="max-width:96px;max-height:96px;display:<?php echo $img ? 'block' : 'none'; ?>;background:<?php echo esc_attr( $s['color'] ); ?>">
					<input type="hidden" id="ibg-<?php echo esc_attr( $key ); ?>" name="ibg_<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $s[ $key ] ); ?>">
					<button type="button" class="button ibg-media-choose"><?php esc_html_e( 'Vælg billede', 'ibos-guides' ); ?></button>
					<button type="button" class="button-link ibg-media-remove"><?php esc_html_e( 'Fjern', 'ibos-guides' ); ?></button>
				</div>
				<p class="description"><?php echo esc_html( $info[1] ); ?></p>
			</td>
		</tr>
		<?php endforeach; ?>
		<tr>
			<th scope="row"><label for="ibg-publisher"><?php esc_html_e( 'Udgiver', 'ibos-guides' ); ?></label></th>
			<td><input type="text" id="ibg-publisher" name="ibg_publisher" class="large-text" value="<?php echo esc_attr( $s['publisher'] ); ?>"></td>
		</tr>
		<tr>
			<th scope="row"><label for="ibg-colophon"><?php esc_html_e( 'Kolofon', 'ibos-guides' ); ?></label></th>
			<td><input type="text" id="ibg-colophon" name="ibg_colophon" class="large-text" value="<?php echo esc_attr( $s['colophon'] ); ?>"></td>
		</tr>
		<tr>
			<th scope="row"><label for="ibg-menu"><?php esc_html_e( 'Menuvisning', 'ibos-guides' ); ?></label></th>
			<td><select id="ibg-menu" name="ibg_menu">
				<option value="tiles" <?php selected( $s['menu'], 'tiles' ); ?>><?php esc_html_e( 'Felter', 'ibos-guides' ); ?></option>
				<option value="list" <?php selected( $s['menu'], 'list' ); ?>><?php esc_html_e( 'Liste', 'ibos-guides' ); ?></option>
			</select></td>
		</tr>
	</table>
	<p class="description"><?php esc_html_e( 'Guidens beskrivelse (uddrag) bruges i appens manifest og på forsiden i søgemaskiner.', 'ibos-guides' ); ?></p>
	<?php
}

add_action( 'admin_enqueue_scripts', 'ibos_guides_admin_assets' );
function ibos_guides_admin_assets( $hook ) {
	$screen = get_current_screen();
	if ( ! $screen || IBOS_GUIDES_CPT !== $screen->post_type ) {
		return;
	}
	wp_enqueue_script( 'ibos-guides-admin', plugins_url( 'assets/admin.js', IBOS_GUIDES_FILE ), array( 'jquery' ), IBOS_GUIDES_VERSION, true );
}

/* ---------------------------------------------------------------------------
 * Gem
 * ------------------------------------------------------------------------- */

/**
 * Er adressen problematisk? Returnerer en fejltekst eller ''.
 *
 * En guide-adresse overskygger alt, WordPress ellers ville vise på samme sti. Derfor afvises
 * WordPress' egne stier og adresser, som allerede bruges af en side, et indlæg eller en anden guide.
 *
 * @param string $slug    Renset adresse.
 * @param int    $post_id Guiden, der gemmes.
 * @return string
 */
function ibos_guides_slug_problem( $slug, $post_id ) {
	if ( '' === $slug ) {
		return '';
	}
	$first    = strtok( $slug, '/' );
	$reserved = array( 'wp-admin', 'wp-content', 'wp-includes', 'wp-json', 'wp-login', 'wp-signup', 'wp-activate', 'wp-sitemap', 'xmlrpc', 'feed', 'comments', 'category', 'tag', 'author', 'search', 'page', 'embed', 'attachment', 'trackback', 'type', 'date', 'admin', 'login', 'logout', 'dashboard', 'register', 'signup', 'activate', 'sitemap', 'robots-txt', 'favicon-ico' );
	foreach ( get_taxonomies( array( 'public' => true ), 'objects' ) as $tax ) {
		if ( ! empty( $tax->rewrite['slug'] ) ) {
			$reserved[] = $tax->rewrite['slug'];
		}
	}
	foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $pt ) {
		if ( ! empty( $pt->rewrite['slug'] ) ) {
			$reserved[] = $pt->rewrite['slug'];
		}
	}
	if ( in_array( $first, $reserved, true ) ) {
		return __( 'Adressen bruges af WordPress selv og blev ikke gemt. Vælg en anden.', 'ibos-guides' );
	}
	if ( get_page_by_path( $slug, OBJECT, array( 'page', 'post' ) ) || get_page_by_path( $first, OBJECT, array( 'page', 'post' ) ) ) {
		return __( 'Der findes allerede en side eller et indlæg på adressen. Den blev ikke gemt, så sitets indhold ikke bliver skjult. Vælg en anden adresse.', 'ibos-guides' );
	}
	// Andre guides (også kladder): samme adresse, eller den ene adresse ligger under den anden.
	$others = get_posts(
		array(
			'post_type'      => IBOS_GUIDES_CPT,
			'post_parent'    => 0,
			'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
			'posts_per_page' => -1,
			'post__not_in'   => array( (int) $post_id ),
			'fields'         => 'ids',
		)
	);
	foreach ( $others as $oid ) {
		$o = ibos_guides_clean_slug( get_post_meta( $oid, '_ibg_slug', true ) );
		if ( '' !== $o && ( $o === $slug || 0 === strpos( $slug . '/', $o . '/' ) || 0 === strpos( $o . '/', $slug . '/' ) ) ) {
			return __( 'En anden guide bruger allerede adressen (eller en adresse, der overlapper). Vælg en anden.', 'ibos-guides' );
		}
	}
	return '';
}

add_action( 'save_post_' . IBOS_GUIDES_CPT, 'ibos_guides_save', 10, 2 );
function ibos_guides_save( $post_id, $post ) {
	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
		return;
	}
	ibos_guides_bump( $post_id );

	if ( isset( $_POST['ibg_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ibg_nonce'] ) ), 'ibg_save_' . $post_id ) && current_user_can( 'edit_post', $post_id ) ) {
		update_post_meta( $post_id, '_ibg_hidden', empty( $_POST['ibg_hidden'] ) ? '' : '1' );
		$cm = isset( $_POST['ibg_child_menu'] ) ? sanitize_key( wp_unslash( $_POST['ibg_child_menu'] ) ) : '';
		update_post_meta( $post_id, '_ibg_child_menu', in_array( $cm, array( 'tiles', 'list' ), true ) ? $cm : '' );

		if ( ! $post->post_parent && isset( $_POST['ibg_slug'] ) && empty( $_POST['ibg_is_guide'] ) ) {
			// Ikke (længere) en guide: fjern markøren og adressen, så siden ikke serveres som app.
			delete_post_meta( $post_id, '_ibg_color' );
			delete_post_meta( $post_id, '_ibg_slug' );
			delete_post_meta( $post_id, '_ibg_slug_error' );
		} elseif ( ! $post->post_parent && isset( $_POST['ibg_slug'] ) ) {
			$slug = ibos_guides_clean_slug( sanitize_text_field( wp_unslash( $_POST['ibg_slug'] ) ) );
			if ( '' === $slug ) {
				$slug = ibos_guides_clean_slug( $post->post_name ? $post->post_name : sanitize_title( $post->post_title ) );
			}
			$error = ibos_guides_slug_problem( $slug, $post_id );
			if ( $error ) {
				// Behold den hidtidige adresse, og vis fejlen i metaboksen.
				update_post_meta( $post_id, '_ibg_slug_error', $error );
			} else {
				delete_post_meta( $post_id, '_ibg_slug_error' );
				update_post_meta( $post_id, '_ibg_slug', $slug );
			}
			update_post_meta( $post_id, '_ibg_short_name', sanitize_text_field( wp_unslash( isset( $_POST['ibg_short_name'] ) ? $_POST['ibg_short_name'] : '' ) ) );
			$color = sanitize_hex_color( wp_unslash( isset( $_POST['ibg_color'] ) ? $_POST['ibg_color'] : '' ) );
			update_post_meta( $post_id, '_ibg_color', $color ? $color : '#00656c' );
			// Ikon og logo skal være billeder, som brugeren selv må se.
			foreach ( array( 'icon_id', 'logo_id' ) as $key ) {
				$att = absint( isset( $_POST[ 'ibg_' . $key ] ) ? $_POST[ 'ibg_' . $key ] : 0 );
				if ( $att && ( ! wp_attachment_is_image( $att ) || ! current_user_can( 'read_post', $att ) ) ) {
					$att = 0;
				}
				update_post_meta( $post_id, '_ibg_' . $key, $att );
			}
			update_post_meta( $post_id, '_ibg_publisher', sanitize_text_field( wp_unslash( isset( $_POST['ibg_publisher'] ) ? $_POST['ibg_publisher'] : '' ) ) );
			update_post_meta( $post_id, '_ibg_colophon', sanitize_text_field( wp_unslash( isset( $_POST['ibg_colophon'] ) ? $_POST['ibg_colophon'] : '' ) ) );
			$menu = sanitize_key( wp_unslash( isset( $_POST['ibg_menu'] ) ? $_POST['ibg_menu'] : 'tiles' ) );
			update_post_meta( $post_id, '_ibg_menu', 'list' === $menu ? 'list' : 'tiles' );
		}
	}
	if ( ibos_guides_refresh_routes() ) {
		update_option( 'ibos_guides_flush', 1 );
	}
}

// Flytning til en anden forælder: også den gamle guide skal have ny version (og ryddet cache),
// ellers ligger sidens indhold i den gamle guides søgeindeks, til cachen udløber.
add_action( 'pre_post_update', 'ibos_guides_before_update', 10, 2 );
function ibos_guides_before_update( $post_id, $data ) {
	$old = get_post( $post_id );
	if ( $old && IBOS_GUIDES_CPT === $old->post_type && isset( $data['post_parent'] ) && (int) $data['post_parent'] !== (int) $old->post_parent ) {
		$root = ibos_guides_root( $old );
		if ( $root ) {
			ibos_guides_bump( $root->ID );
			delete_transient( 'ibg_collect_' . $root->ID );
		}
	}
}

// Papirkurv: når en guide eller et kapitel lægges i papirkurven, følger undersiderne med
// (og gendannes sammen med den). Ellers bliver de forældreløse, når papirkurven tømmes.
add_action( 'wp_trash_post', 'ibos_guides_trash_children' );
function ibos_guides_trash_children( $post_id ) {
	$post = get_post( $post_id );
	if ( ! $post || IBOS_GUIDES_CPT !== $post->post_type ) {
		return;
	}
	$kids = get_posts( array( 'post_type' => IBOS_GUIDES_CPT, 'post_parent' => $post_id, 'post_status' => array( 'publish', 'draft', 'pending', 'private', 'future' ), 'posts_per_page' => -1, 'fields' => 'ids' ) );
	foreach ( $kids as $kid ) {
		update_post_meta( $kid, '_ibg_trashed_with', (int) $post_id );
		wp_trash_post( $kid );
	}
}
add_action( 'untrashed_post', 'ibos_guides_untrash_children' );
function ibos_guides_untrash_children( $post_id ) {
	$kids = get_posts( array( 'post_type' => IBOS_GUIDES_CPT, 'post_status' => 'trash', 'posts_per_page' => -1, 'fields' => 'ids', 'meta_key' => '_ibg_trashed_with', 'meta_value' => (int) $post_id ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
	foreach ( $kids as $kid ) {
		delete_post_meta( $kid, '_ibg_trashed_with' );
		wp_untrash_post( $kid );
	}
}
// Gendannede sider skal have deres tidligere status (ikke "kladde", som WP ellers sætter).
add_filter( 'wp_untrash_post_status', 'ibos_guides_untrash_status', 10, 3 );
function ibos_guides_untrash_status( $new_status, $post_id, $previous_status ) {
	return IBOS_GUIDES_CPT === get_post_type( $post_id ) ? $previous_status : $new_status;
}
// Permanent sletning af en guide eller et kapitel: slet også undersiderne, så de ikke bliver
// forældreløse sider på øverste niveau.
add_action( 'before_delete_post', 'ibos_guides_delete_children', 10, 2 );
function ibos_guides_delete_children( $post_id, $post = null ) {
	$post = $post ? $post : get_post( $post_id );
	if ( ! $post || IBOS_GUIDES_CPT !== $post->post_type ) {
		return;
	}
	$kids = get_posts( array( 'post_type' => IBOS_GUIDES_CPT, 'post_parent' => $post_id, 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids' ) );
	foreach ( $kids as $kid ) {
		wp_delete_post( $kid, true );
	}
}

// Ændringer i status, sletning og billeders alt-tekst skal også give en ny version.
add_action( 'transition_post_status', 'ibos_guides_on_status', 10, 3 );
function ibos_guides_on_status( $new, $old, $post ) {
	if ( IBOS_GUIDES_CPT === $post->post_type && $new !== $old ) {
		ibos_guides_bump( $post->ID );
		if ( ibos_guides_refresh_routes() ) {
			update_option( 'ibos_guides_flush', 1 );
		}
	}
}
add_action( 'deleted_post', 'ibos_guides_on_delete', 10, 2 );
function ibos_guides_on_delete( $id, $post ) {
	if ( $post && IBOS_GUIDES_CPT === $post->post_type ) {
		ibos_guides_bump();
		if ( ibos_guides_refresh_routes() ) {
			update_option( 'ibos_guides_flush', 1 );
		}
	}
}
add_action( 'edit_attachment', 'ibos_guides_bump_all' );
function ibos_guides_bump_all() {
	ibos_guides_bump();
}

/* ---------------------------------------------------------------------------
 * Listevisning
 * ------------------------------------------------------------------------- */

add_filter( 'manage_' . IBOS_GUIDES_CPT . '_posts_columns', 'ibos_guides_columns' );
function ibos_guides_columns( $cols ) {
	$new = array();
	foreach ( $cols as $k => $v ) {
		$new[ $k ] = $v;
		if ( 'title' === $k ) {
			$new['ibg_guide'] = __( 'Guide', 'ibos-guides' );
			$new['ibg_order'] = __( 'Rækkefølge', 'ibos-guides' );
			$new['ibg_check'] = __( 'Tjek', 'ibos-guides' );
		}
	}
	return $new;
}

add_action( 'manage_' . IBOS_GUIDES_CPT . '_posts_custom_column', 'ibos_guides_column', 10, 2 );
function ibos_guides_column( $col, $post_id ) {
	$post = get_post( $post_id );
	if ( 'ibg_guide' === $col ) {
		$root = ibos_guides_root( $post );
		if ( $root && (int) $root->ID === (int) $post_id && '' === ibos_guides_settings( $root )['slug'] ) {
			echo '<strong>' . esc_html__( 'App', 'ibos-guides' ) . '</strong><br><span style="color:#b32d2e">' . esc_html__( 'Ingen godkendt adresse – se App-indstillinger', 'ibos-guides' ) . '</span>';
		} elseif ( $root && (int) $root->ID === (int) $post_id ) {
			$url = ibos_guides_base_url( $root );
			echo '<strong>' . esc_html__( 'App', 'ibos-guides' ) . '</strong><br><a href="' . esc_url( $url ) . '">' . esc_html( wp_parse_url( $url, PHP_URL_PATH ) ) . '</a>';
		} elseif ( $root ) {
			echo esc_html( $root->post_title );
		}
	} elseif ( 'ibg_order' === $col ) {
		echo (int) $post->menu_order;
	} elseif ( 'ibg_check' === $col ) {
		$n = count( ibos_guides_check( $post ) );
		echo $n
			? '<span style="color:#b32d2e">' . esc_html( sprintf( /* translators: %d: antal */ _n( '%d advarsel', '%d advarsler', $n, 'ibos-guides' ), $n ) ) . '</span>'
			: esc_html__( 'OK', 'ibos-guides' );
	}
}

// Vis træet i rækkefølge.
add_action( 'pre_get_posts', 'ibos_guides_admin_order' );
function ibos_guides_admin_order( $q ) {
	if ( is_admin() && $q->is_main_query() && IBOS_GUIDES_CPT === $q->get( 'post_type' ) && ! $q->get( 'orderby' ) ) {
		$q->set( 'orderby', array( 'menu_order' => 'ASC', 'title' => 'ASC' ) );
	}
}

// Hjælpetekst øverst på listen.
add_action( 'admin_notices', 'ibos_guides_list_notice' );
function ibos_guides_list_notice() {
	$screen = get_current_screen();
	if ( ! $screen || 'edit-' . IBOS_GUIDES_CPT !== $screen->id ) {
		return;
	}
	echo '<div class="notice notice-info"><p>' . esc_html__( 'Hver guide er en side på øverste niveau. Kapitler og sider ligger under den. Brug "Hurtig redigering" til at ændre rækkefølge og forælder.', 'ibos-guides' ) . ' <a href="' . esc_url( admin_url( 'edit.php?post_type=' . IBOS_GUIDES_CPT . '&page=ibos-guides-import' ) ) . '">' . esc_html__( 'Importér O&M-teknik eller Ledsageteknik', 'ibos-guides' ) . '</a></p></div>';
}
