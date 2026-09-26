<?php
/**
 * Builds the ASK Scrubs store inside WordPress Playground.
 * Run by the blueprint after WooCommerce and the theme are installed.
 * Product photos are read from /wordpress/ask-images.
 */
require_once '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

// Fast demo build: the photos are already web-sized, so skip making thumbnails of each one.
// (Real hosting can regenerate thumbnails later.)
if ( defined( 'ASK_FAST' ) && ASK_FAST ) {
	add_filter( 'intermediate_image_sizes_advanced', '__return_empty_array' );
	add_filter( 'big_image_size_threshold', '__return_false' );
	add_filter( 'woocommerce_background_image_regeneration', '__return_false' );
	add_filter( 'woocommerce_resize_images', '__return_false' );
}

// Store settings.
foreach ( [
	'blogname'                               => 'ASK Scrubs',
	'blogdescription'                        => 'For Medical Apparels – scrubs, lab coats and nurse uniforms, Nairobi CBD',
	'timezone_string'                        => 'Africa/Nairobi',
	'woocommerce_currency'                   => 'KES',
	'woocommerce_currency_pos'               => 'left_space',
	'woocommerce_price_num_decimals'         => '0',
	'woocommerce_price_thousand_sep'         => ',',
	'woocommerce_default_country'            => 'KE:KE30',
	'woocommerce_store_address'              => 'Imenti House, Limoda Exhibition, Shop 4DW Basement, Tom Mboya Street',
	'woocommerce_store_city'                 => 'Nairobi',
	'woocommerce_manage_stock'               => 'yes',
	'woocommerce_notify_low_stock_amount'    => '3',
	'woocommerce_notify_no_stock_amount'     => '0',
	'woocommerce_allowed_countries'          => 'specific',
	'woocommerce_specific_allowed_countries' => [ 'KE' ],
	'woocommerce_ship_to_countries'          => '',
	'woocommerce_enable_reviews'             => 'yes',
	'woocommerce_review_rating_verification_label' => 'yes',
	'woocommerce_onboarding_profile'         => [ 'skipped' => true ],
	'woocommerce_task_list_hidden'           => 'yes',
	'woocommerce_coming_soon'                => 'no',
	'woocommerce_checkout_phone_field'       => 'required',
	'woocommerce_enable_coupons'             => 'yes',
] as $k => $v ) {
	update_option( $k, $v );
}

// Remove sample content.
foreach ( get_posts( [ 'post_type' => [ 'post', 'page' ], 'name' => 'hello-world', 'numberposts' => 1 ] ) as $p ) wp_delete_post( $p->ID, true );
$sample = get_page_by_path( 'sample-page' );
if ( $sample ) wp_delete_post( $sample->ID, true );

// Colour and size attributes (global, so the shop can filter by them).
$attr_ids = [];
foreach ( [ 'colour' => 'Colour', 'size' => 'Size' ] as $slug => $label ) {
	$id = wc_attribute_taxonomy_id_by_name( $slug );
	if ( ! $id ) {
		$id = wc_create_attribute( [ 'name' => $label, 'slug' => $slug, 'type' => 'select', 'order_by' => 'menu_order', 'has_archives' => true ] );
	}
	$attr_ids[ $slug ] = $id;
	register_taxonomy( "pa_$slug", 'product', [ 'hierarchical' => false, 'rewrite' => [ 'slug' => $slug ], 'public' => true, 'query_var' => true ] );
}
delete_transient( 'wc_attribute_taxonomies' );

$size_order = [ 'XS', 'S', 'M', 'L', 'XL', '2XL', '3XL' ];
foreach ( $size_order as $i => $s ) {
	$t = term_exists( $s, 'pa_size' ) ?: wp_insert_term( $s, 'pa_size', [ 'slug' => strtolower( $s ) ] );
	update_term_meta( (int) $t['term_id'], 'order', $i );
}

function ask_term_ids( $tax, array $names ) {
	$ids = [];
	foreach ( $names as $n ) {
		$t = term_exists( $n, $tax ) ?: wp_insert_term( $n, $tax );
		$ids[] = (int) $t['term_id'];
	}
	return $ids;
}

function ask_attribute( $tax_slug, $attr_id, array $names, $position ) {
	$a = new WC_Product_Attribute();
	$a->set_id( $attr_id );
	$a->set_name( "pa_$tax_slug" );
	$a->set_options( ask_term_ids( "pa_$tax_slug", $names ) );
	$a->set_position( $position );
	$a->set_visible( true );
	$a->set_variation( false );
	return $a;
}

// Categories.
$cat = [];
foreach ( [
	'scrubs'         => 'Scrub Sets',
	'jogger-scrubs'  => 'Jogger Scrubs',
	'scrub-jackets'  => 'Scrub Jackets',
	'lab-coats'      => 'Lab Coats',
	'nurse-uniforms' => 'Nurse Uniforms',
] as $slug => $name ) {
	$t = term_exists( $slug, 'product_cat' ) ?: wp_insert_term( $name, 'product_cat', [ 'slug' => $slug ] );
	$cat[ $slug ] = (int) $t['term_id'];
}

// "Shop by role" tags.
$role = [];
foreach ( [
	'doctors-surgeons'  => 'Doctors & Surgeons',
	'nurses-midwives'   => 'Nurses & Midwives',
	'clinical-students' => 'Clinical Students',
	'dental-lab'        => 'Dental & Lab Staff',
] as $slug => $name ) {
	$t = term_exists( $slug, 'product_tag' ) ?: wp_insert_term( $name, 'product_tag', [ 'slug' => $slug ] );
	$role[ $slug ] = (int) $t['term_id'];
}
$ALL_ROLES = array_keys( $role );

// Delivery.
$zone = new WC_Shipping_Zone();
$zone->set_zone_name( 'Nairobi' );
$zone->add_location( 'KE:KE30', 'state' );
$zone->save();
$id = $zone->add_shipping_method( 'flat_rate' );
update_option( "woocommerce_flat_rate_{$id}_settings", [ 'title' => 'Express Same-Day Nairobi Dispatch', 'cost' => '300', 'tax_status' => 'none' ] );
$id = $zone->add_shipping_method( 'free_shipping' );
update_option( "woocommerce_free_shipping_{$id}_settings", [ 'title' => 'Free Nairobi Express Delivery', 'requires' => 'min_amount', 'min_amount' => '6000' ] );

$rest = new WC_Shipping_Zone();
$rest->set_zone_name( 'Rest of Kenya' );
$rest->add_location( 'KE', 'country' );
$rest->save();
$id = $rest->add_shipping_method( 'flat_rate' );
update_option( "woocommerce_flat_rate_{$id}_settings", [ 'title' => 'Upcountry Courier (1–3 days)', 'cost' => '500', 'tax_status' => 'none' ] );

update_option( 'woocommerce_pickup_location_settings', [ 'enabled' => 'yes', 'title' => 'In-Store Fitting & Pickup (free)', 'tax_status' => 'none', 'cost' => '' ] );
update_option( 'pickup_location_pickup_locations', [ [
	'name'    => 'ASK Scrubs – Imenti House, Shop 4DW Basement',
	'address' => [ 'address_1' => 'Imenti House, Limoda Exhibition, Shop 4DW Basement, Tom Mboya Street (opposite Equity Bank)', 'city' => 'Nairobi CBD', 'state' => 'KE30', 'postcode' => '', 'country' => 'KE' ],
	'details' => 'Mon–Sat 9:00 AM – 6:30 PM. The entrance is next to Sports Collection. We will call you when your order is ready, and you can try it on before you leave.',
	'enabled' => true,
] ] );

// Payment: pay on delivery or at pickup until M-Pesa is connected.
update_option( 'woocommerce_cod_settings', [
	'enabled'            => 'yes',
	'title'              => 'Pay on delivery or pickup (M-Pesa or cash)',
	'description'        => 'Pay by M-Pesa or cash when your order arrives, or when you collect it from the shop.',
	'instructions'       => 'We will call you to confirm your order and delivery time.',
	'enable_for_methods' => [],
	'enable_for_virtual' => 'yes',
] );

// Newsletter welcome coupon.
$c = new WC_Coupon();
$c->set_code( 'MEDCLUB300' );
$c->set_discount_type( 'fixed_cart' );
$c->set_amount( 300 );
$c->set_minimum_amount( 1500 );
$c->set_individual_use( true );
$c->set_usage_limit_per_user( 1 );
$c->set_description( 'Med-Club newsletter welcome offer' );
$c->save();

// Products, from the shop's WhatsApp catalogue (wa.me/c/254110433305).
$SIZES      = [ 'XS', 'S', 'M', 'L', 'XL', '2XL' ];
$SCRUB_CARE = "Wash inside out at 30–40°C with similar colours. No bleach. Hang to dry and warm iron if needed; iron embroidery from the reverse side.";

// colour_img: which photo shows each colour (0 = main photo, 1 = second photo…).
$products = [
	[
		'slug' => 'infinity-scrubs', 'name' => 'Infinity Stretch Scrub Set', 'cat' => 'scrubs', 'price' => 3500,
		'colours' => [ 'Ceil Blue', 'Wine', 'Teal', 'Seafoam', 'Black', 'Plum', 'Royal Blue', 'Navy Blue', 'Pewter' ], 'sizes' => $SIZES,
		'colour_img' => [ 'Ceil Blue' => 0, 'Wine' => 1, 'Teal' => 2, 'Seafoam' => 3, 'Black' => 4, 'Plum' => 5, 'Royal Blue' => 6, 'Navy Blue' => 7, 'Pewter' => 8 ],
		'roles' => $ALL_ROLES, 'spec' => '4-Way Stretch', 'featured' => true,
		'desc' => 'Our best-loved scrubs. Infinity sets are made from a soft, stretchy fabric that moves with you through a long shift and keeps its shape wash after wash: a fitted V-neck top with pockets, and trousers with a drawstring waist. WhatsApp us for any colour not listed.',
		'fabric' => "Stretch polyester–spandex blend. V-neck top with pockets, drawstring trousers. Sizes XS–2XL.\n\n$SCRUB_CARE",
		'upsells' => [ 'infinity-jacket', 'lab-coat' ],
	],
	[
		'slug' => 'landau-proflex-joggers', 'name' => 'Landau ProFlex Jogger Scrubs', 'cat' => 'jogger-scrubs', 'price' => 2200,
		'colours' => [ 'Royal Blue', 'Pewter', 'Black', 'Navy Blue', 'Ceil Blue', 'Hunter Green', 'Red' ], 'sizes' => $SIZES,
		'colour_img' => [ 'Royal Blue' => 0, 'Pewter' => 1, 'Black' => 2, 'Navy Blue' => 4, 'Ceil Blue' => 6, 'Hunter Green' => 7, 'Red' => 8 ],
		'roles' => $ALL_ROLES, 'spec' => 'Jogger Fit', 'featured' => true,
		'desc' => 'Landau ProFlex jogger scrubs with a modern tapered leg, cargo pockets and the signature lime trim. Stretchy and light, with a comfortable elastic waist.',
		'fabric' => "Stretch ProFlex fabric. Jogger cuffs, cargo pockets, elastic and drawstring waist. Sizes XS–2XL.\n\n$SCRUB_CARE",
		'upsells' => [ 'infinity-jacket', 'landau-flex-scrubs' ],
	],
	[
		'slug' => 'landau-flex-scrubs', 'name' => 'Landau Flex Scrub Set', 'cat' => 'scrubs', 'price' => 2200,
		'colours' => [ 'Royal Blue', 'Red', 'Pewter' ], 'sizes' => $SIZES,
		'colour_img' => [ 'Royal Blue' => 0, 'Red' => 1, 'Pewter' => 3 ],
		'roles' => $ALL_ROLES, 'spec' => 'Flex Fabric', 'featured' => true,
		'desc' => 'A classic Landau Flex scrub set: a V-neck top and straight-leg trousers in a light, flexible fabric. Available in different colours and sizes XS–2XL.',
		'fabric' => "Landau Flex fabric. V-neck top, straight-leg trousers. Sizes XS–2XL.\n\n$SCRUB_CARE",
		'upsells' => [ 'lab-coat', 'infinity-jacket' ],
	],
	[
		'slug' => 'cherokee-scrubs', 'name' => 'Cherokee Cotton Scrub Set', 'cat' => 'scrubs', 'price' => 1800,
		'colours' => [ 'Royal Blue', 'Surgical Green', 'Ceil Blue', 'Black', 'Navy Blue', 'Purple', 'Wine', 'Teal' ], 'sizes' => $SIZES,
		'colour_img' => [ 'Royal Blue' => 0, 'Surgical Green' => 1, 'Ceil Blue' => 2, 'Black' => 3, 'Navy Blue' => 4, 'Purple' => 5, 'Wine' => 6 ],
		'roles' => $ALL_ROLES, 'spec' => '100% Cotton', 'featured' => true,
		'desc' => 'Cherokee scrub sets made purely from cotton: breathable, cool and easy to wash every day. Unisex V-neck top and drawstring trousers. Our most affordable scrubs, and a favourite with students.',
		'fabric' => "100% cotton. Unisex V-neck top, drawstring trousers. Sizes XS–2XL.\n\n$SCRUB_CARE",
		'upsells' => [ 'lab-coat', 'infinity-jacket' ],
	],
	[
		'slug' => 'mint-scrubs', 'name' => 'Mint Fabric Scrub Set', 'cat' => 'scrubs', 'price' => 2500,
		'colours' => [ 'Eggplant', 'Lilac', 'Teal', 'Pink', 'Mustard', 'Orange' ], 'sizes' => $SIZES,
		'colour_img' => [ 'Eggplant' => 0, 'Lilac' => 0, 'Teal' => 0, 'Pink' => 0, 'Mustard' => 1, 'Orange' => 1 ],
		'roles' => [ 'nurses-midwives', 'dental-lab', 'clinical-students' ], 'spec' => 'Mint Fabric', 'featured' => true,
		'desc' => 'Scrub sets made purely from our smooth "mint" fabric, in bright colours that stand out on the ward. Fitted top with pockets and matching trousers.',
		'fabric' => "Mint fabric: smooth, lightweight and quick-drying. Sizes XS–2XL.\n\n$SCRUB_CARE",
		'upsells' => [ 'infinity-jacket' ],
	],
	[
		'slug' => 'infinity-jacket', 'name' => 'Infinity Scrub Jacket', 'cat' => 'scrub-jackets', 'price' => 2000,
		'colours' => [ 'Royal Blue', 'Teal', 'Navy Blue', 'Olive', 'Black' ], 'sizes' => $SIZES,
		'colour_img' => [ 'Royal Blue' => 4, 'Teal' => 2, 'Navy Blue' => 3, 'Olive' => 5, 'Black' => 7 ],
		'roles' => $ALL_ROLES, 'spec' => 'Stretch Knit', 'featured' => true,
		'desc' => 'The Infinity warm-up jacket: a soft stretch knit with a full front zip and a stand collar, worn over your scrubs on cold mornings and night shifts. Unisex fit.',
		'fabric' => "Stretch knit. Full front zip, stand collar, front pockets. Sizes XS–2XL.\n\nMachine wash cold, inside out. Do not tumble dry. Iron embroidery from the reverse side.",
		'upsells' => [ 'infinity-scrubs', 'landau-proflex-joggers' ],
	],
	[
		'slug' => 'lab-coat', 'name' => 'Mint White Lab Coat', 'cat' => 'lab-coats', 'price' => 1700,
		'colours' => [ 'White' ], 'sizes' => $SIZES,
		'roles' => [ 'doctors-surgeons', 'clinical-students', 'dental-lab' ], 'spec' => 'Mint Fabric', 'featured' => true,
		'desc' => 'A crisp, knee-length white lab coat in smooth mint fabric, with a notched collar, button front and pockets. For ward rounds, the lab and your white-coat ceremony. Add your name and title embroidery.',
		'fabric' => "Mint white fabric. Button front, notched collar, pockets.\n\nWash at 60°C to keep it white. Iron on medium.",
		'upsells' => [ 'cherokee-scrubs', 'infinity-scrubs' ],
	],
	[
		'slug' => 'nurse-uniform', 'name' => 'NCK Nurse Uniform – White & Navy', 'cat' => 'nurse-uniforms',
		'colours' => [ 'White & Navy' ], 'sizes' => $SIZES,
		'options' => [ 'label' => 'Style', 'choices' => [ 'Full set (top + trousers)' => 2500, 'Top only' => 1500 ] ],
		'roles' => [ 'nurses-midwives', 'clinical-students' ], 'spec' => 'NCK Style', 'featured' => true,
		'desc' => 'The white-and-navy nurse uniform in the NCK style: a white tunic with navy trim and pockets, with navy trousers. Buy the full set or the top on its own.',
		'fabric' => "Polyester–cotton tunic with navy trim; navy trousers.\n\nWash at 40°C. Wash the white tunic separately from the navy trousers.",
		'upsells' => [ 'lab-coat', 'infinity-jacket' ],
	],
];

function ask_attach_images( $pid, $slug, $name ) {
	$files = glob( "/wordpress/ask-images/$slug*.webp" ) ?: [];
	$files = array_values( array_filter( $files, fn( $f ) => preg_match( '#/' . preg_quote( $slug, '#' ) . '(-\d+)?\.webp$#', $f ) ) );
	// slug.webp is the main photo, then slug-2.webp, slug-3.webp…
	$num = fn( $f ) => preg_match( '#-(\d+)\.webp$#', substr( $f, strlen( $slug ) ), $m ) ? (int) $m[1] : 1;
	usort( $files, fn( $a, $b ) => $num( basename( $a ) ) <=> $num( basename( $b ) ) );
	$ids = [];
	foreach ( $files as $i => $file ) {
		$base = basename( $file );
		$tmp  = wp_tempnam( $base );
		copy( $file, $tmp );
		$att = media_handle_sideload( [ 'name' => $base, 'tmp_name' => $tmp ], $pid, $name . ( $i ? ' – photo ' . ( $i + 1 ) : '' ) );
		if ( ! is_wp_error( $att ) ) $ids[] = $att;
	}
	return $ids;
}

$by_slug = [];
foreach ( $products as $order => $d ) {
	$is_var = ! empty( $d['options'] );
	$p = $is_var ? new WC_Product_Variable() : new WC_Product_Simple();
	$p->set_name( $d['name'] );
	$p->set_slug( $d['slug'] );
	$p->set_status( 'publish' );
	$p->set_menu_order( $order );
	$p->set_description( $d['desc'] );
	$p->set_short_description( $d['desc'] );
	$p->set_category_ids( [ $cat[ $d['cat'] ] ] );
	$p->set_tag_ids( array_map( fn( $r ) => $role[ $r ], $d['roles'] ) );
	$p->set_featured( ! empty( $d['featured'] ) );
	$p->set_manage_stock( true );
	$p->set_stock_quantity( 10 );
	if ( ! $is_var ) {
		$p->set_regular_price( $d['price'] );
		if ( ! empty( $d['sale'] ) ) $p->set_sale_price( $d['sale'] );
	}

	$attrs = [];
	$pos = 0;
	if ( ! empty( $d['colours'] ) ) $attrs[] = ask_attribute( 'colour', $attr_ids['colour'], $d['colours'], $pos++ );
	if ( ! empty( $d['sizes'] ) ) $attrs[] = ask_attribute( 'size', $attr_ids['size'], $d['sizes'], $pos++ );
	if ( $is_var ) {
		$a = new WC_Product_Attribute();
		$a->set_name( $d['options']['label'] );
		$a->set_options( array_keys( $d['options']['choices'] ) );
		$a->set_position( $pos++ );
		$a->set_visible( true );
		$a->set_variation( true );
		$attrs[] = $a;
	}
	$p->set_attributes( $attrs );
	$p->update_meta_data( '_ask_spec', $d['spec'] );
	$p->update_meta_data( '_ask_fabric', $d['fabric'] );
	if ( ! empty( $d['colour_img'] ) ) $p->update_meta_data( '_ask_colour_images', $d['colour_img'] );
	$pid = $p->save();

	if ( $is_var ) {
		$key = sanitize_title( $d['options']['label'] );
		$first = true;
		foreach ( $d['options']['choices'] as $label => $price ) {
			$v = new WC_Product_Variation();
			$v->set_parent_id( $pid );
			$v->set_attributes( [ $key => $label ] );
			$v->set_regular_price( $price );
			$v->set_manage_stock( false ); // Uses the parent product's stock.
			$v->set_status( 'publish' );
			$v->save();
			if ( $first ) {
				$p->set_default_attributes( [ $key => $label ] );
				$first = false;
			}
		}
		$p->save();
		WC_Product_Variable::sync( $pid );
	}

	$imgs = ask_attach_images( $pid, $d['slug'], $d['name'] );
	if ( $imgs ) {
		set_post_thumbnail( $pid, array_shift( $imgs ) );
		if ( $imgs ) {
			$p = wc_get_product( $pid );
			$p->set_gallery_image_ids( $imgs );
			$p->save();
		}
	}
	$by_slug[ $d['slug'] ] = $pid;
}

// "Complete the set" suggestions.
foreach ( $products as $d ) {
	if ( empty( $d['upsells'] ) ) continue;
	$p = wc_get_product( $by_slug[ $d['slug'] ] );
	$p->set_upsell_ids( array_values( array_filter( array_map( fn( $s ) => $by_slug[ $s ] ?? 0, $d['upsells'] ) ) ) );
	$p->save();
}

// Pages.
function ask_page( $slug, $title, $content ) {
	$existing = get_page_by_path( $slug );
	if ( $existing ) return $existing->ID;
	return wp_insert_post( [ 'post_type' => 'page', 'post_status' => 'publish', 'post_name' => $slug, 'post_title' => $title, 'post_content' => $content ] );
}
ask_page( 'wishlist', 'Your wishlist', "<!-- wp:shortcode -->\n[ask_wishlist]\n<!-- /wp:shortcode -->" );
ask_page( 'size-guide', 'Size guide', "<!-- wp:shortcode -->\n[ask_size_guide]\n<!-- /wp:shortcode -->" );

update_option( 'permalink_structure', '/%postname%/' );
flush_rewrite_rules();

// Skip WooCommerce's first-run redirect and setup checklist so the admin opens on the store itself.
delete_transient( '_wc_activation_redirect' );
update_option( 'woocommerce_task_list_hidden_lists', [ 'setup', 'extended' ] );
update_option( 'woocommerce_task_list_complete', 'yes' );
update_option( 'woocommerce_show_marketplace_suggestions', 'no' );
update_option( 'woocommerce_admin_install_timestamp', time() - WEEK_IN_SECONDS );
