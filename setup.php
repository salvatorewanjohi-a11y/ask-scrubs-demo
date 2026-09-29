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

$size_order = [ 'XS', 'S', 'M', 'L', 'XL', '2XL', '3XL', '36', '37', '38', '39', '40', '41', '42', '43', '44' ];
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
	'thrift-scrubs'  => 'Thrift Scrubs',
	'caps-layers'    => 'Caps & Pull-necks',
	'footwear'       => 'Medical Footwear',
	'stethoscopes'   => 'Stethoscopes',
	'equipment'      => 'Medical Equipment',
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
		'colours' => [ 'Eggplant', 'Lilac', 'Teal', 'Pink', 'Mustard', 'Orange', 'Yellow', 'Purple' ], 'sizes' => $SIZES,
		'colour_img' => [ 'Eggplant' => 0, 'Lilac' => 0, 'Teal' => 5, 'Pink' => 0, 'Mustard' => 1, 'Orange' => 2, 'Yellow' => 3, 'Purple' => 4 ],
		'videos' => ask_video_names( 'mint-scrubs', 13 ),
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
		'options' => [ 'label' => 'Style', 'choices' => [ 'Full set (top + trousers)' => 2500, 'Top only' => 1500, 'Trousers only' => 1200 ] ],
		'roles' => [ 'nurses-midwives', 'clinical-students' ], 'spec' => 'NCK Style', 'featured' => true,
		'desc' => 'The white-and-navy nurse uniform in the NCK style, with thin or bold blue stripes: a white tunic with navy trim and pockets, with navy trousers. Buy the full set, the top on its own or the trousers on their own.',
		'fabric' => "Polyester–cotton tunic with navy trim; navy trousers.\n\nWash at 40°C. Wash the white tunic separately from the navy trousers.",
		'upsells' => [ 'nurse-watch', 'nurse-cap', 'pen-torch-battery' ],
		'videos' => ask_video_names( 'nurse-uniform', 2 ),
	],

	// Added from the client's WhatsApp update, 2026-09-29.
	[
		'slug' => 'nurse-uniform-chinese-collar', 'name' => 'Chinese Collar Nurse Uniform', 'cat' => 'nurse-uniforms',
		'colours' => [ 'White & Navy' ], 'sizes' => $SIZES,
		'options' => [ 'label' => 'Style', 'choices' => [ 'Full set (top + trousers)' => 3000, 'Top only' => 1800, 'Trousers only' => 1200 ] ],
		'roles' => [ 'nurses-midwives', 'clinical-students' ], 'spec' => 'Button Front',
		'desc' => 'A smart white nurse tunic with a Chinese (mandarin) collar, button front and navy trim, with navy trousers. Buy the full set, the top on its own or the trousers on their own.',
		'fabric' => "Polyester–cotton tunic with navy trim; navy trousers.\n\nWash at 40°C. Wash the white tunic separately from the navy trousers.",
		'upsells' => [ 'nurse-watch', 'nurse-cap' ],
		'videos' => ask_video_names( 'nurse-uniform-chinese-collar', 2 ),
	],
	[
		'slug' => 'nurse-uniform-buttons', 'name' => 'White Nurse Uniform – With or Without Buttons', 'cat' => 'nurse-uniforms',
		'colours' => [ 'White & Navy' ], 'sizes' => $SIZES,
		'options' => [ 'label' => 'Style', 'choices' => [ 'With buttons' => 3000, 'Without buttons' => 2500 ] ],
		'roles' => [ 'nurses-midwives', 'clinical-students' ], 'spec' => 'Two Styles',
		'desc' => 'Our white nurse uniforms, in stock in all sizes: choose the style with a button front or the pull-on style without buttons.',
		'fabric' => "Polyester–cotton.\n\nWash at 40°C. Wash white items separately.",
		'upsells' => [ 'nurse-watch', 'nurse-cap' ],
	],
	[
		'slug' => 'scrubstar-ultimate', 'name' => 'Scrubstar Ultimate Jogger Scrubs – Black', 'cat' => 'jogger-scrubs', 'price' => 2800,
		'colours' => [ 'Black' ], 'sizes' => [ 'XS', 'L', 'XL' ],
		'roles' => $ALL_ROLES, 'spec' => 'Performance Stretch', 'featured' => true,
		'desc' => 'Scrubstar Ultimate performance scrubs in black: a fitted stretch top with pockets and jogger trousers with cuffed ankles and cargo pockets. Currently available in XS, L and XL.',
		'fabric' => "Performance stretch fabric. Jogger trousers with cuffs and cargo pockets.\n\n$SCRUB_CARE",
		'upsells' => [ 'pullneck', 'infinity-jacket' ],
	],
	[
		'slug' => 'thrift-scrubs', 'name' => 'Thrift (Mtumba) Scrub Sets', 'cat' => 'thrift-scrubs', 'price' => 800,
		'colours' => [ 'Assorted' ],
		'roles' => [ 'clinical-students', 'nurses-midwives', 'doctors-surgeons' ], 'spec' => 'Budget Pick', 'featured' => true,
		'desc' => 'Quality second-hand (mtumba) scrub sets at KSh 800 per set, in plain colours and fun prints. Stock changes every day, so tell us your size and favourite colours on WhatsApp, or come in and pick your own.',
		'fabric' => "Second-hand scrubs, cleaned and sorted by size. Sizes and colours vary.\n\n$SCRUB_CARE",
		'upsells' => [ 'nurse-cap', 'pullneck' ],
		'videos' => ask_video_names( 'thrift-scrubs', 3 ),
	],
	[
		'slug' => 'pullneck', 'name' => 'Pull-neck Underscrub', 'cat' => 'caps-layers', 'price' => 550,
		'colours' => [ 'Bottle Green', 'Navy Blue', 'Cream', 'Black', 'Grey', 'Maroon' ],
		'colour_img' => [ 'Bottle Green' => 0, 'Navy Blue' => 1, 'Cream' => 1, 'Black' => 2, 'Grey' => 3, 'Maroon' => 4 ],
		'roles' => $ALL_ROLES, 'spec' => 'Warm Layer',
		'desc' => 'A soft, stretchy long-sleeve pull-neck (turtleneck) to wear under your scrubs on cold mornings and night shifts.',
		'fabric' => "Soft stretch knit.\n\nMachine wash cold with similar colours. Do not tumble dry.",
		'upsells' => [ 'infinity-jacket', 'scrubstar-ultimate' ],
	],
	[
		'slug' => 'nurse-cap', 'name' => 'Theatre / Nurse Cap', 'cat' => 'caps-layers', 'price' => 950,
		'colours' => [ 'Printed', 'Pink', 'Green', 'Navy Blue', 'Sky Blue' ],
		'colour_img' => [ 'Printed' => 0, 'Pink' => 1, 'Green' => 2, 'Navy Blue' => 3, 'Sky Blue' => 4 ],
		'roles' => $ALL_ROLES, 'spec' => 'Button Sides',
		'desc' => 'Comfortable bouffant-style theatre caps that fit long hair and braids, with buttons at the sides for your mask loops. Plain colours and cheerful prints.',
		'fabric' => "Cotton. Back ties, side buttons for mask loops.\n\nMachine wash warm with similar colours. Air dry.",
		'upsells' => [ 'mint-scrubs', 'cherokee-scrubs' ],
		'videos' => ask_video_names( 'nurse-cap', 7 ),
	],
	[
		'slug' => 'anti-slip-crocs', 'name' => 'Anti-Slip Medical Clogs', 'cat' => 'footwear', 'price' => 2500,
		'colours' => [ 'White', 'Black' ], 'sizes' => [ '36', '37', '38', '39', '40', '41', '42', '43', '44' ],
		'colour_img' => [ 'White' => 0, 'Black' => 2 ],
		'roles' => [ 'doctors-surgeons', 'nurses-midwives', 'dental-lab' ], 'spec' => 'Anti-Slip',
		'desc' => 'Closed medical clogs with an anti-slip sole for wet ward and theatre floors. Easy to wipe clean and comfortable on long shifts.',
		'fabric' => "Moulded, wipe-clean upper with an anti-slip sole.\n\nRinse or wipe with warm soapy water. Keep out of direct heat.",
		'upsells' => [ 'nurse-cap', 'mint-scrubs' ],
	],
	[
		'slug' => 'littmann-classic-3', 'name' => 'Littmann Classic III Stethoscope', 'cat' => 'stethoscopes', 'price' => 5000,
		'roles' => [ 'doctors-surgeons', 'nurses-midwives', 'clinical-students' ], 'spec' => '3M Littmann', 'featured' => true,
		'desc' => 'The 3M Littmann Classic III: a dual-sided stethoscope with tunable diaphragms for excellent sound, the everyday choice of doctors and clinical students.',
		'fabric' => "Dual-head chest piece with tunable diaphragms, supplied boxed with spare parts.\n\nWipe with an alcohol swab. Keep the tubing away from oils and direct heat.",
		'upsells' => [ 'pen-torch-rechargeable', 'bp-machine-manual' ],
		'videos' => ask_video_names( 'littmann-classic-3', 1 ),
	],
	[
		'slug' => 'littmann-classic-2', 'name' => 'Littmann Classic II S.E. Stethoscope', 'cat' => 'stethoscopes', 'price' => 4000,
		'roles' => [ 'doctors-surgeons', 'nurses-midwives', 'clinical-students' ], 'spec' => '3M Littmann',
		'desc' => 'The 3M Littmann Classic II S.E.: a reliable dual-head stethoscope for clinical rotations and ward work.',
		'fabric' => "Dual-head chest piece, supplied boxed.\n\nWipe with an alcohol swab. Keep the tubing away from oils and direct heat.",
		'upsells' => [ 'pen-torch-battery', 'tape-measure' ],
		'videos' => ask_video_names( 'littmann-classic-2', 1 ),
	],
	[
		// No price from the client yet, so it stays a draft until they confirm one.
		'slug' => 'student-stethoscope', 'name' => 'Student Stethoscope', 'cat' => 'stethoscopes', 'price' => '', 'status' => 'draft',
		'roles' => [ 'clinical-students', 'nurses-midwives' ], 'spec' => 'Student Pick',
		'desc' => 'A lightweight dual-head stethoscope for students starting their clinical rotations.',
		'fabric' => "Dual-head chest piece.\n\nWipe with an alcohol swab. Keep the tubing away from oils and direct heat.",
		'upsells' => [ 'pen-torch-battery', 'tape-measure' ],
	],
	[
		'slug' => 'bp-machine-electronic', 'name' => 'Electronic BP Machine', 'cat' => 'equipment', 'price' => 3500,
		'roles' => [ 'nurses-midwives', 'doctors-surgeons', 'clinical-students' ], 'spec' => 'Digital',
		'desc' => 'An automatic upper-arm blood pressure monitor with a large digital display. Easy to use at the clinic or at home.',
		'fabric' => "Automatic upper-arm monitor with cuff.",
		'upsells' => [ 'pulse-oximeter', 'digital-thermometer' ],
	],
	[
		'slug' => 'bp-machine-manual', 'name' => 'Manual BP Machine (Sphygmomanometer)', 'cat' => 'equipment', 'price' => 2500,
		'roles' => [ 'nurses-midwives', 'doctors-surgeons', 'clinical-students' ], 'spec' => 'Aneroid',
		'desc' => 'A manual aneroid sphygmomanometer with an adult cuff, bulb and gauge: the classic kit for taking blood pressure by auscultation.',
		'fabric' => "Aneroid gauge, adult cuff, inflation bulb and carry case.",
		'upsells' => [ 'littmann-classic-3', 'littmann-classic-2' ],
	],
	[
		'slug' => 'pulse-oximeter', 'name' => 'Fingertip Pulse Oximeter', 'cat' => 'equipment',
		'options' => [ 'label' => 'Type', 'choices' => [ 'Adult' => 2000, 'Paediatric' => 2500 ] ],
		'roles' => [ 'nurses-midwives', 'doctors-surgeons', 'clinical-students' ], 'spec' => 'SpO₂ & Pulse',
		'desc' => 'A fingertip pulse oximeter that reads oxygen saturation (SpO₂) and pulse rate in seconds. Choose the adult or the paediatric size.',
		'fabric' => "Fingertip clip with digital display.\n\nWipe with an alcohol swab between patients.",
		'upsells' => [ 'digital-thermometer', 'bp-machine-electronic' ],
	],
	[
		'slug' => 'digital-thermometer', 'name' => 'Digital Thermometer', 'cat' => 'equipment', 'price' => 300,
		'roles' => [ 'nurses-midwives', 'clinical-students' ], 'spec' => 'Fast Reading',
		'desc' => 'A quick-reading digital thermometer for oral, underarm or rectal use.',
		'fabric' => "Digital display, battery included.\n\nClean the tip with an alcohol swab after each use.",
		'upsells' => [ 'pulse-oximeter' ],
	],
	[
		'slug' => 'pen-torch-rechargeable', 'name' => 'Rechargeable Pen Torch', 'cat' => 'equipment', 'price' => 1000,
		'roles' => [ 'clinical-students', 'nurses-midwives', 'doctors-surgeons' ], 'spec' => 'USB Rechargeable',
		'desc' => 'A metal pen torch that charges from any USB port, so there are no batteries to replace. Comes in a metal case.',
		'fabric' => "Metal body, pocket clip, USB charging, metal case.",
		'upsells' => [ 'littmann-classic-3', 'tape-measure' ],
	],
	[
		'slug' => 'pen-torch-battery', 'name' => 'LED Pen Torch (Battery)', 'cat' => 'equipment', 'price' => 650,
		'roles' => [ 'clinical-students', 'nurses-midwives', 'doctors-surgeons' ], 'spec' => 'Batteries Included',
		'desc' => 'A bright LED pen torch for checking pupils and throats, supplied with button batteries.',
		'fabric' => "LED pen torch with pocket clip and button batteries.",
		'upsells' => [ 'littmann-classic-2', 'tape-measure' ],
		'videos' => ask_video_names( 'pen-torch-battery', 1 ),
	],
	[
		'slug' => 'nurse-watch', 'name' => 'Nurse Fob Watch', 'cat' => 'equipment', 'price' => 650,
		'colours' => [ 'Pink', 'Gold' ], 'colour_img' => [ 'Pink' => 0, 'Gold' => 1 ],
		'roles' => [ 'nurses-midwives', 'clinical-students' ], 'spec' => 'Pin-On',
		'desc' => 'A pin-on fob watch with a clear face and second hand for timing pulses and respirations.',
		'fabric' => "Pin-on clip, second hand.",
		'upsells' => [ 'nurse-uniform', 'pen-torch-battery' ],
	],
	[
		'slug' => 'retractable-tape', 'name' => 'Retractable Tape Measure', 'cat' => 'equipment', 'price' => 250,
		'colours' => [ 'Pink', 'White' ], 'colour_img' => [ 'Pink' => 0, 'White' => 2 ],
		'roles' => [ 'nurses-midwives', 'clinical-students' ], 'spec' => 'Pocket Size',
		'desc' => 'A pocket-sized retractable tape measure in a round case, for MUAC, head circumference and wound measurements.',
		'fabric' => "Round plastic case, retractable tape in cm and inches.",
		'upsells' => [ 'tape-measure', 'digital-thermometer' ],
	],
	[
		'slug' => 'tape-measure', 'name' => 'Tape Measure', 'cat' => 'equipment', 'price' => 150,
		'roles' => [ 'nurses-midwives', 'clinical-students' ], 'spec' => 'cm & inches',
		'desc' => 'A soft, flexible 150 cm tape measure marked in centimetres and inches.',
		'fabric' => "Flexible fibre tape, cm and inches.",
		'upsells' => [ 'retractable-tape', 'digital-thermometer' ],
		'videos' => ask_video_names( 'tape-measure', 1 ),
	],
];

// Product videos are <slug>-1.mp4, <slug>-2.mp4… (with a matching .jpg poster) in the videos folder.
function ask_video_names( $slug, $count ) {
	return array_map( fn( $i ) => "$slug-$i", range( 1, $count ) );
}

// Copy the videos into uploads so the site serves them. The Playground demo skips this and
// streams them from the demo repo instead (see the ask_video_base option in build-demo.mjs).
if ( is_dir( '/wordpress/ask-videos' ) ) {
	$dest = wp_upload_dir()['basedir'] . '/ask-videos';
	wp_mkdir_p( $dest );
	foreach ( array_merge( glob( '/wordpress/ask-videos/*.mp4' ) ?: [], glob( '/wordpress/ask-videos/*.jpg' ) ?: [] ) as $file ) {
		copy( $file, $dest . '/' . basename( $file ) );
	}
}

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
	$p->set_status( $d['status'] ?? 'publish' );
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
	if ( ! empty( $d['videos'] ) ) $p->update_meta_data( '_ask_videos', implode( "\n", $d['videos'] ) );
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
