<?php

function bcs_enqueue_excerpt_style() {
    wp_register_style( 'excerpt_css', plugin_dir_url( __DIR__ ) . 'assets/excerpts-1.0-beta.css', false, '1.0.0' );
    wp_enqueue_style( 'excerpt_css' );
}
add_action( 'wp_enqueue_scripts', 'bcs_enqueue_excerpt_style' ); 

$options = get_option( 'bcs_book_import_settings' );
if((isset($options['bcs_book_import_show_image_preview']) && $options['bcs_book_import_show_image_preview'] == true) || (isset($options['bcs_book_import_show_short_preview']) && $options['bcs_book_import_show_short_preview'] == true) || (isset($options['bcs_book_import_show_long_preview']) && $options['bcs_book_import_show_long_preview'] == true)){
	//add_action( 'woocommerce_after_single_product_summary', 'add_script_after_addtocart_button_func', 2);
	add_action( 'woocommerce_before_single_product_summary' , 'add_custom_text_before_product_title', 3 );
}
 
function add_custom_text_before_product_title() {
   global $post, $product;
	
	$sku = $product->get_sku();
	if(!$sku == '' ){
		$excerpt_url = wp_cache_get( $sku.'_excerpt_url' );

		if ( false === $excerpt_url ) {

			$ch = curl_init();

			$jelly_call = "https://www.jellybooks.com/discovery/api/excerpts/".$sku."?jb_discovery_api_key=08eb70e0f4f6b0b31a88e5084d07820d";
			curl_setopt($ch, CURLOPT_URL, $jelly_call);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");

			$headers = array();
			$headers[] = "Accept: application/json";
			$headers[] = "Content-Type: application/json";
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

			$result = curl_exec($ch);
			if (curl_errno($ch)) {
			    //$your_msg .= 'Error:' . curl_error($ch);
			}
			curl_close ($ch);

			$jelly_result = json_decode($result, true);

			$jelly_url = $jelly_result['excerpt']['url'];

			if(!$jelly_url == ''){
				wp_cache_set( $sku.'_excerpt_url', $jelly_url );
			}
		}
		add_action( 'woocommerce_after_single_product_summary', 'add_script_after_addtocart_button_func', 2);
	}
}


function add_script_after_addtocart_button_func() {

    $your_msg = '<script src="'.plugin_dir_url( __DIR__ ).'assets/excerpts-1.0-beta.js"></script>';
	$your_msg .= '<script>
		excerpts.init({
			label: {
	            selector: ".peek_inside", // data-jb-peek
	            text: " Peek Inside", // data-jb-peek-text
	            placement: {
	                x: "right",
	                y: "top"
	            } // data-jb-peek-placement
	        },
            modal: {
                selector: ".jb-modal-link" // data-jb-modal
            }
        });
        // var label = document.querySelector(".jb-peek-label-text");
        // var icon = document.createElement("i");
        // icon.className = "fa fa-eye";
        // label.prepend(icon);
	</script>';
	echo $your_msg;

}

function filter_woocommerce_short_description( $post_excerpt )   {
	if ( is_product() ) {
		global $post, $product;
		$sku = $product->get_sku();
		$excerpt_url = wp_cache_get( $sku.'_excerpt_url' );
		//echo $excerpt_url;
		if ( false !== $excerpt_url ) {
			$your_msg .= '<br/>';
			$your_msg .='<a class="jb-modal-link jb-short-desc-link" style="border:1px solid; padding:8px;" href="'.$excerpt_url.'">Peek Inside</a>';
			return $post_excerpt.'<br>'.$your_msg; 
		}else{
			return $post_excerpt;
		}

	}
};

if(isset($options['bcs_book_import_show_short_preview']) && $options['bcs_book_import_show_short_preview'] == true){
	add_filter( 'woocommerce_short_description','filter_woocommerce_short_description',10, 1 );
}


function bcs_replace_woocommerce_single_image2( $size ) {
    global $post, $product;
		
	$sku = $product->get_sku();

	$excerpt_url = wp_cache_get( $sku.'_excerpt_url' );

	if(has_post_thumbnail($post->ID)){
		$src = get_the_post_thumbnail_url($post->ID);
	} else {
		$src = get_post_meta( $post->ID, 'main_image', true );
	}

	if(!has_post_thumbnail() && !$src)
	{
			 $src = wc_placeholder_img_src();
	}
	if(false !== $excerpt_url){
		return '<a class="jb-modal-link jb-image-link" href="'.$excerpt_url.'"><img class="peek_inside" src="' . $src . '" /></a>';
	}else{
		return '<img src="' . $src . '" />';
	}
	
}

if(isset($options['bcs_book_import_show_image_preview']) && $options['bcs_book_import_show_image_preview'] == true){
	add_filter( 'woocommerce_single_product_image_thumbnail_html', 'bcs_replace_woocommerce_single_image2' );
}


//add_filter( 'the_content', 'customizing_woocommerce_description' );
function customizing_woocommerce_description( $content ) {

    // Only for single product pages (woocommerce)
    if ( is_product() ) {
    	global $post, $product;
		
		$sku = $product->get_sku();

		$excerpt_url = wp_cache_get( $sku.'_excerpt_url' );

		if(false !== $excerpt_url){
			$your_msg ='<p><a class="jb-modal-link jb-desc-link" href="'.$excerpt_url.'">Read a sample here</a></p>';		
        	$content .= $your_msg;
        }
    }
    return $content;
}

if(isset($options['bcs_book_import_show_long_preview']) && $options['bcs_book_import_show_long_preview'] == true){
	add_filter( 'the_content', 'customizing_woocommerce_description' );
}

remove_action( 'woocommerce_before_shop_loop_item_title', 'woocommerce_template_loop_product_thumbnail', 10);
add_action( 'woocommerce_before_shop_loop_item_title', 'woocommerce_template_loop_product_thumbnail', 10);

/**
* WooCommerce Loop Product Thumbs
**/
if ( ! function_exists( 'woocommerce_template_loop_product_thumbnail' ) ) {
    function woocommerce_template_loop_product_thumbnail() {
        echo "<div class='wc-img-wrapper'>";
        echo woocommerce_get_product_thumbnail();
        echo "</div>";
    }
}


function bcs_peek_inside_shortcode($atts) {
	global $post, $product;
		
	$sku = $product->get_sku();

	$excerpt_url = wp_cache_get( $sku.'_excerpt_url' );

	if(false !== $excerpt_url){
		$your_msg ='<p><a class="jb-modal-link" href="'.$excerpt_url.'">Peek Inside</a></p>';		
    }
	 
    return $your_msg;
}
add_shortcode('bcs-peek-inside', 'bcs_peek_inside_shortcode');
