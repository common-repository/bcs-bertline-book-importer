<?php 


add_filter( 'woocommerce_placeholder_img', 'bcs_replace_woocommerce_thumbnail_image' );
function bcs_replace_woocommerce_thumbnail_image( $size ) {
    global $post;
    $src = get_post_meta( $post->ID, 'thumbnail_image', true );
		if(!has_post_thumbnail() && !$src)
		{
				 $src = wc_placeholder_img_src();
		}
    return '<img src="' . $src . '" />';
  }

add_filter( 'woocommerce_single_product_image_thumbnail_html', 'bcs_replace_woocommerce_single_image' );
function bcs_replace_woocommerce_single_image( $size ) {
    global $post, $product;
		
	$sku = $product->get_sku();

	if(has_post_thumbnail($post->ID)){
		$src = get_the_post_thumbnail_url($post->ID);
	} else {
		$src = get_post_meta( $post->ID, 'main_image', true );
	}

	if(!has_post_thumbnail() && !$src)
	{
			 $src = wc_placeholder_img_src();
	}
	$options = get_option( 'bcs_book_import_settings' );
	// if(isset($options['bcs_book_import_show_image_preview']) && $options['bcs_book_import_show_image_preview'] == true){
	// 	return '<a class="jb-modal-link" href="https://www.jellybooks.com/cloud_reader/excerpts/alternatives-to-valium_9781788854948-ex/54w4Q"><img class="peek_inside" src="' . $src . '" /></a>';
	// }else{
		return '<img src="' . $src . '" />';
	// }
}

add_action('woocommerce_before_main_content', 'bcs_remove_shop_hooks');
function bcs_remove_shop_hooks(){
	remove_action( 'woocommerce_after_single_product_summary', 'storefront_single_product_pagination', 30 );
}

add_action( 'woocommerce_after_single_product_summary', 'bcs_storefront_single_product_pagination', 30 );
function bcs_storefront_single_product_pagination() {
	if ( class_exists( 'Storefront_Product_Pagination' ) || true !== get_theme_mod( 'storefront_product_pagination' ) ) {
		return;
	}

	// Show only products in the same category?
	$in_same_term   = apply_filters( 'storefront_single_product_pagination_same_category', true );
	$excluded_terms = apply_filters( 'storefront_single_product_pagination_excluded_terms', '' );
	$taxonomy       = apply_filters( 'storefront_single_product_pagination_taxonomy', 'product_cat' );

	$previous_product = storefront_get_previous_product( $in_same_term, $excluded_terms, $taxonomy );
	$next_product     = storefront_get_next_product( $in_same_term, $excluded_terms, $taxonomy );

	if ( ! $previous_product && ! $next_product ) {
		return;
	}

	?>
	<nav class="storefront-product-pagination" aria-label="<?php esc_attr_e( 'More products', 'storefront' ); ?>">
		<?php if ( $previous_product ) : ?>
			<a href="<?php echo esc_url( $previous_product->get_permalink() ); ?>" rel="prev">
				<?php $src = get_post_meta( $previous_product->get_id(), 'thumbnail_image', true ); ?>
                <img src="<?php echo $src; ?>" class="card-image"  />
				<?php //echo wp_kses_post( $previous_product->get_image() ); ?>
				<span class="storefront-product-pagination__title"><?php echo wp_kses_post( $previous_product->get_name() ); ?></span>
			</a>
		<?php endif; ?>

		<?php if ( $next_product ) : ?>
			<a href="<?php echo esc_url( $next_product->get_permalink() ); ?>" rel="next">
				<?php $srcnext = get_post_meta( $next_product->get_id(), 'thumbnail_image', true ); ?>
                <img src="<?php echo $srcnext; ?>" class="card-image"  />
				<?php //echo wp_kses_post( $next_product->get_image() ); ?>
				<span class="storefront-product-pagination__title"><?php echo wp_kses_post( $next_product->get_name() ); ?></span>
			</a>
		<?php endif; ?>
	</nav><!-- .storefront-product-pagination -->
	<?php
}

// Add product new column in administration
add_filter( 'manage_edit-product_columns', 'bcs_woo_product_id_column', 20 );
function bcs_woo_product_id_column( $columns ) {

    // $columns['id'] = esc_html__( 'ID', 'woocommerce' );
    // return $columns;
    $newcolumns = array(
		"cb"       		=> "<input type  = \"checkbox\" />",
		"id"    => esc_html__('ID', 'woocommerce'),
	);

	$columns = array_merge($newcolumns, $columns);
	
	return $columns;

}
add_action( 'manage_product_posts_custom_column', 'bcs_woo_product_id_column_data', 2 );
function bcs_woo_product_id_column_data( $column ) {
    global $post;

    if ( $column == 'id' ) {
        $product = wc_get_product($post->ID);
        $product_id = $post->ID;
        print $product_id;
    }
}
function bcs_my_set_sortable_columns( $columns )
{
    $columns['id'] = 'id';
    return $columns;
}
add_filter( 'manage_edit-product_sortable_columns', 'bcs_my_set_sortable_columns' );
function bcs_my_sort_custom_column_query( $query )
{
    $orderby = $query->get( 'orderby' );

    if ( 'id' == $orderby ) {

        $query->set( 'orderby', 'ID' );
    }
}
add_action( 'pre_get_posts', 'bcs_my_sort_custom_column_query' );
add_action('admin_head', 'bcs_my_column_width');

function bcs_my_column_width() {
    echo '<style type="text/css">';
    echo 'table.wp-list-table .column-id { width: 46px; text-align: left!important;padding: 5px;}';
    echo '.wp-list-table tr:not(.inline-edit-row):not(.no-items) td.column-id::before {display:none !important;}';
    echo '</style>';
}


/* Sort products in wp_list_table by column in ascending or descending order. */
function bcs_custom_product_order( $query ){

    global $typenow;

    if( is_admin() && $query->is_main_query() && $typenow == 'product' ){

        /* Post Column: e.g. title */
        if($query->get('orderby') == ''){
            $query->set('orderby', 'id');
        }
        /* Post Order: ASC / DESC */
        if($query->get('order') == ''){
            $query->set('order', 'DESC');
        }

    }
}
add_action( 'parse_query', 'bcs_custom_product_order' );


//set stock message from plugin code translations
function bcs_change_backorder_message( $text, $product ){
	$options = get_option( 'bcs_book_import_settings' );
	$stock_code = get_post_meta( $product->get_id(), 'stock_code', true );

	if($product->get_stock_quantity() <= 0) {
		if($product->backorders_allowed()) {
			if(isset($options['bcs_book_import_text_field_default'])){
				$text = __($options['bcs_book_import_text_field_default']);
			}
			if($stock_code ==='TOS'){
		    	if(isset($options['bcs_book_import_text_field_tos'])){
					$text = __($options['bcs_book_import_text_field_tos']);
				}
			}
			
			if($stock_code === 'RPR'){
				if(isset($options['bcs_book_import_text_field_rpr'])){
					$text = $options['bcs_book_import_text_field_rpr'];
				}
			}
			if($stock_code === 'NYP'){
				if(isset($options['bcs_book_import_text_field_nyp'])){
					$text = $options['bcs_book_import_text_field_nyp'];
				}
			}
			if($stock_code === 'POD'){
				if(isset($options['bcs_book_import_text_field_pod'])){
					$text = __($options['bcs_book_import_text_field_pod']);
				}
			}
		} else {
			if($stock_code === 'OP'){
				if(isset($options['bcs_book_import_text_field_op'])){
					$text = $options['bcs_book_import_text_field_op'];
				}
			}
		}
	} else {
		$text = __('In stock');
	}
	$pub_date = get_the_date( 'U', $product->get_id() );
	$todays_date = date("U");
	if($pub_date > $todays_date){
		$text = ('Pre-order');
	}

    return $text;
}
add_filter( 'woocommerce_get_availability_text', 'bcs_change_backorder_message', 10, 2 );

?>