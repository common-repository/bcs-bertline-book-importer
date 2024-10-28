<?php 

/**
 * This is our callback function that embeds our resource in a WP_REST_Response
 */

add_action( 'rest_api_init', function () {
	register_rest_route( 'wc/v3', 'bcsbertlinebookimport', array(
		'methods' => 'POST',
		'permission_callback' => function () { return current_user_can( 'edit_others_posts' ); },
		'callback' => 'bcs_run_import',
	));
});

add_filter('upload_mimes', 'bcs_custom_upload_xml');
function bcs_custom_upload_xml($mimes) {
    $mimes = array_merge($mimes, array('xml' => 'application/xml'));
    return $mimes;
}

function bcs_get_product_by_sku( $isbn ) {
    global $wpdb;
    $product_id = $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key='_sku' AND meta_value='%s' LIMIT 1", $isbn ) );
    if ( $product_id ) return $product_id;
    return null;
}

function bcs_get_data($url){
  $request = new WP_Http;
  $result = $request->request( $url );
  $data = $result['body'];
  return $data;
}

function bcs_create_attributes(){
	$add_attributes = array(
		array('publisher', 'Publisher', true),
		array('cover', 'Cover', false),
		array('book_author', 'Author', true),
		array('pages', 'Pages', false),
		array('imprint', 'Imprint', true),
		array('language', 'Language', false),
		array('edition', 'Edition', false),
		array('dewey', 'Dewey', false),
		array('readership', 'Readership', false),
	);
	foreach($add_attributes as $add_attribute ){
		bcs_create_global_attribute($add_attribute[1],$add_attribute[0],$add_attribute[2]);
	}
}

function bcs_create_global_attribute($name, $slug, $archive){

    $taxonomy_name = wc_attribute_taxonomy_name( $slug );
    if (taxonomy_exists($taxonomy_name)){
        return wc_attribute_taxonomy_id_by_name($slug);
    }
    $attribute_id = wc_create_attribute( array(
        'name'         => $name,
        'slug'         => $slug,
        'type'         => 'select',
        'order_by'     => 'menu_order',
        'has_archives' => $archive,
    ) );
    //Register it as a wordpress taxonomy for just this session. Later on this will be loaded from the woocommerce taxonomy table.
    register_taxonomy(
        $taxonomy_name,
        apply_filters( 'woocommerce_taxonomy_objects_' . $taxonomy_name, array( 'product' ) ),
        apply_filters( 'woocommerce_taxonomy_args_' . $taxonomy_name, array(
            'labels'       => array(
                'name' => $name,
            ),
            'hierarchical' => true,
            'show_ui'      => false,
            'query_var'    => true,
            'rewrite'      => false,
        ) )
    );
    //Clear caches
    delete_transient( 'wc_attribute_taxonomies' );
}

// The functions which is going to do the job
function bcs_run_import($data){
	$localkey = "";
	$licensekey = "";
    $options = get_option( 'bcs_book_import_settings' );
	$localkeyoption = get_option( 'bcs_book_import_settings_local_key' );
    if(isset($options['bcs_book_import_license_key'])){
        $licensekey = __($options['bcs_book_import_license_key']);
    }
	$results = bcs_check_license($licensekey, $localkeyoption);
	// Interpret response
	switch ($results['status']) {
	    case "Active":
	        // get new local key and save it somewhere
	        if(isset($results['localkey'])){
		        $localkeydata = $results['localkey'];
		        update_option('bcs_book_import_settings_local_key', $localkeydata);
		       }

	        bcs_process_import($data);
	        break;
	    case "Invalid":
	        $return = array(
			    'message'  => 'License key '.$licensekey.' is Invalid.'
			);
			wp_send_json($return, 500);
	        break;
	    case "Expired":
	        $return = array(
			    'message'  => 'License key is Expired.'
			);
			wp_send_json($return, 500);
	        break;
	    case "Suspended":
	        $return = array(
			    'message'  => 'License key is Suspended.'
			);
			wp_send_json($return, 500);
	        break;
	    default:
	        $return = array(
			    'message'  => 'Invalid Response.'
			);
			wp_send_json($return, 500);
	        break;
	}
}
function &bcs_delete_all_products(){
	$args = array(
	  'post_type' => 'product',
	  'post_status' => 'any',
	  'posts_per_page' => -1 );
	$the_query = new WP_Query( $args );
	$product_count = $the_query->found_posts;
	//echo $product_count;

	global $wpdb;
	$db_prefix = $wpdb->prefix;
	$wpdb->query("DELETE FROM ".$db_prefix."terms WHERE term_id IN (SELECT term_id FROM ".$db_prefix."term_taxonomy WHERE taxonomy LIKE 'pa_%')");
	$wpdb->query("DELETE FROM ".$db_prefix."term_taxonomy WHERE taxonomy LIKE 'pa_%'");
	$wpdb->query("DELETE FROM ".$db_prefix."term_relationships WHERE term_taxonomy_id not IN (SELECT term_taxonomy_id FROM ".$db_prefix."term_taxonomy)");
	$wpdb->query("DELETE FROM ".$db_prefix."term_relationships WHERE object_id IN (SELECT ID FROM ".$db_prefix."posts WHERE post_type IN ('product','product_variation'))");
	$wpdb->query("DELETE FROM ".$db_prefix."postmeta WHERE post_id IN (SELECT ID FROM ".$db_prefix."posts WHERE post_type IN ('product','product_variation'))");
	$wpdb->query("DELETE FROM ".$db_prefix."posts WHERE post_type IN ('product','product_variation')");
	$wpdb->query("DELETE pm FROM ".$db_prefix."postmeta pm LEFT JOIN ".$db_prefix."posts wp ON wp.ID = pm.post_id WHERE wp.ID IS NULL");

	$args = array(
	  'post_type' => 'product',
	  'post_status' => 'any',
	  'posts_per_page' => -1 );
	$the_query_1 = new WP_Query( $args );
	$product_count_after_delete = $the_query_1->found_posts;
	//echo $product_count_after_delete;

	$products_deleted = $product_count - $product_count_after_delete;
	$total_products_deleted = abs($product_count - $products_deleted);
	//$total_products_deleted = $product_count;

	return $total_products_deleted;
}
function bcs_process_import($data){
	$newfull = $data['newfull'];
	$block = $data['block'];
	$total_blocks = $data['totalblocks'];
	$update_only = $data['stockOnly'];
	$uid = $data['UID'];
	$datasize = $data['datasize'];

	$dt = date("d/m/Y H:i:s");

	$stock_only = false;
	if($update_only == true){
	    $stock_only = true;
	    //echo 'stock only';
	}

	if($newfull == true && $stock_only == false && $block == 0){
		$total_deleted =& bcs_delete_all_products();
	}else{
		$total_deleted = 0;
	}

	wp_enqueue_media();
    wp_enqueue_script('jquery');
    wp_enqueue_script('media-upload');
	
	set_time_limit(0);

	$total_items = 0;
  	$no_imported = 0;
 
	$body = $data->get_body();
 	$body_decoded = urldecode($body);

 	//$body_strlen = strlen($body);
 	//$body_strlen_mb = mb_strlen($body);
 	//$body_strlen_utf8 = mb_strlen($body, "UTF-8");
 	$body_decoded_strlen = strlen($body_decoded);

 	$body_size = $data->get_header('content-length');

 	$success = false;

 	if(get_option( 'bcs_book_import_settings' )){
 		$options = get_option( 'bcs_book_import_settings' );
 		if(isset($options['bcs_book_import_log'])){
		    if($options['bcs_book_import_log']){
		    	$uploads  = wp_upload_dir( null, false );
				$logs_dir = $uploads['basedir'] . '/bertlineimport-logs';

				if ( ! is_dir( $logs_dir ) ) {
				    mkdir( $logs_dir, 0755, true );
				}
				$log  = "XML: ".$body_decoded.PHP_EOL.
				        "-------------------------".PHP_EOL;
				file_put_contents($logs_dir.'/log_'.date("j.n.Y").'.log', $log, FILE_APPEND);
		    }
		}
	}

  	libxml_use_internal_errors(true);

	$xml = simplexml_load_string($body_decoded);
	$xml_part = explode("\n", $body_decoded);

	if (!$xml) {
	    $errors = libxml_get_errors();
	    $xml_errors = array();
	    foreach ($errors as $error) {
	    	$xml_errors[] = $error->message;
	    }
		$return = array(
		    'message'  => 'XML Error. Block not imported.',
			'xml errors' => $xml_errors
		);
		wp_send_json($return, 500);
	    libxml_clear_errors();
	} else {
		
		$permalinks = get_option( 'woocommerce_permalinks' );
		$error_messages = array();
		
		bcs_create_attributes();

		$deny_codes = array('OP');
		$allow_codes = array('TOS', 'RPR', 'NYP', 'POD');

		
		foreach($xml->book as $item){
			$_backorders = 'no';
			
			$total_items++;
			$isbn = (string)$item->isbn;
			if($isbn == ''){
				$error_messages[] = 'Item skipped: no isbn';
				continue;
			}
			
			include 'bcs-get-values.php';

			if(get_option( 'bcs_book_import_settings' )){
		 		$options = get_option( 'bcs_book_import_settings' );
			    if(isset($options['bcs_book_import_allow_backorders']) && $options['bcs_book_import_allow_backorders'] == true){
			    	if(in_array($stock_code, $deny_codes)){
						$_backorders = 'no';
					} else {
					//echo $stock_code;
					//if(in_array($stock_code, $allow_codes)){
						$_backorders = 'notify';
					}
			    }
			}

			// Let's start with creating the post itself
			if($stock_only){
				$post_data = array(
					//'post_title' 	=> '',
				);
			} else {
				$post_data = array(
					'post_title' 	=> $post_title,
					'post_content' 	=> $content,
					'post_status' 	=> 'publish',
					'post_type' 	=> 'product', // Or "page" or some custom post type
					'post_date'     =>   $pub_date,
					'post_excerpt'	=> $excerpt
				);
			}

			// check if product already exists
			if(bcs_get_product_by_sku($isbn)){
				//update existing post
				$idpost = bcs_get_product_by_sku($isbn);
				$post_data['ID'] = $idpost;
				$post_id = wp_update_post( $post_data, true );
				if(!is_wp_error($post_id)){
					$no_imported++;
					$success = true;
				}else{
					$error_messages[] = $isbn.' 1: '.$post_title.' 1: '.$post_id->get_error_message();
					continue;
				}
			} else {
				//add new post
				if(!$stock_only){
					//add new post
					$post_id = wp_insert_post($post_data, true);
					if(!is_wp_error($post_id)){
						$no_imported++;
						$success = true;
					}else{
						$error_messages[] = $isbn.' 1: '.$post_title.' : '.$post_id->get_error_message();
						continue;
					}
				}else{
					$error_messages[] = $isbn.' 1: '.$post_title.' : ISBN not found. Can not update stock only.';
					continue;
				}
			}

			if(!$stock_only){
				wp_set_object_terms( $post_id, 'simple', 'product_type' );
				wp_set_object_terms( $post_id, $uc_categories, 'product_cat' );
				wp_set_object_terms( $post_id, $tags, 'product_tag');
			}
		
			$product = wc_get_product( $post_id );
			$woosaleprice = $product->get_sale_price();
			$currentprice = $price;

			if( !$woosaleprice == '' && $product->is_on_sale() ) {
				$currentprice = $woosaleprice;
			}
			
			if(!$stock_only){
				$attributes = array(
					'pa_book_author' => $author,
					'pa_publisher' => $publisher,
					'pa_imprint' => $imprint,
					'pa_cover' => $cover,
					'pa_pages' => $pages,
					'pa_language' => $language,
					'pa_edition' => $edition,
					'pa_dewey' => $dewey,
					'pa_readership' => $readership,
				);
				$i = 0;
				$product_attributes = array();
				
				foreach($attributes as $key => $value){
					wp_set_object_terms( $post_id, $value, $key, false);
					
					$product_attributes[sanitize_title($key)] = array (
							'name' => wc_clean($key), // set attribute name
							'value' => $value, // set attribute value
							'position' => $i,
							'is_visible' => 1,
							'is_variation' => 0,
							'is_taxonomy' => 1
						);
					$i++;
				}

				update_post_meta($post_id, '_product_attributes', $product_attributes);
				update_post_meta($post_id, 'pa_book_author', $author);
			}

			



			if(!$stock_only){
				
				$postOptions = array(
					'_sku' => $isbn,
					'_regular_price' => $price,
					'_sale_price' => $woosaleprice,
					'_price' => $currentprice,
					'_length' => $length,
					'_width' => $width,
					'_height' => $height,
					'_weight' => $weight,
					'_visibility' => 'visible',
					'_manage_stock' => 'yes',
					'imported' => true,
					'stock_code' => $stock_code,
					'main_image' => $thumbnail,
					'thumbnail_image' => $thumbnailsm,
					'_yoast_wpseo_title'	=> $post_title,
					'_yoast_wpseo_metadesc'	=> $content,
					'_backorders' => $_backorders,
				);
			} else {
				$postOptions = array(
					'_regular_price' => $price,
					'_sale_price' => $woosaleprice,
					'_price' => $currentprice,
					'stock_code' => $stock_code,
					'_backorders' => $_backorders,
				);
			}

			// Loop through the post options
			foreach($postOptions as $key=>$value){
				// Add the post options
				update_post_meta($post_id,$key,$value);
			}
			wc_update_product_stock($post_id, $stock, 'set');

			// $publish_post = array( 'ID' => $post_id, 'post_status' => 'publish' );
			// 	wp_update_post($publish_post);
			// }
			if(isset($options['bcs_book_import_allow_backorders']) && $options['bcs_book_import_allow_backorders'] == true){
				wp_publish_post($post_id);
			}
		}
		//bcs_add_log_entry($dt);
		//$body_strlen = strlen($body);
 		//$body_decoded_strlen = strlen($body_decoded);
		if($success){
			if (empty($error_messages)) {
				bcs_add_log_entry($uid, $dt, $success, $block, $total_blocks, $total_items, $no_imported, $total_deleted, $datasize, $body_decoded_strlen);
				if($newfull == true && $stock_only == false && $block == 0){
					$return = array(
					    'message'  => 'Imported '.$no_imported.' from block of '.$total_items.'. '.$total_deleted.' products deleted.'
					);
				}else{
					$return = array(
					    'message'  => 'Imported '.$no_imported.' from block of '.$total_items
					);
				}
			}else{
				bcs_add_log_entry($uid, $dt, $success, $block, $total_blocks, $total_items, $no_imported, $total_deleted, $datasize, $body_decoded_strlen);
				if($newfull == true && $stock_only == false && $block == 0){
					
					$return = array(
					    'message'  => 'Imported '.$no_imported.' from block of '.$total_items.'. '.$total_deleted.' products deleted.',
						'errors' => $error_messages
					);
				}else{
					$return = array(
					    'message'  => 'Imported '.$no_imported.' from block of '.$total_items,
						'errors' => $error_messages
					);
				}
			}
			wp_send_json($return, 200);
		} else {
			bcs_add_log_entry($uid, $dt, $success, $block, $total_blocks, $total_items, $no_imported, $total_deleted, $datasize, $body_decoded_strlen);
			if($newfull == true && $stock_only == false && $block == 0){
				$return = array(
				    'message'  => 'Nothing imported. Check errors below.',
				    'errors' => $error_messages
				);
			}else{
				$return = array(
				    'message'  => 'Nothing imported. Check errors below.'.'. '.$total_deleted.' products deleted.',
				    'errors' => $error_messages
				);
			}
			wp_send_json($return, 200);
		}
	}
	
}

// function bcs_add_log_entry($uid, $dt, $success, $block, $total_blocks, $total_items, $no_imported, $total_deleted, $datasize, $body_decoded_strlen){
// 	//delete_option('bcs_book_import_blocks_log');
// 	$current_log = get_option('bcs_book_import_blocks_log', array());
// 	//$current_log = array_reverse($current_log);
// 	//$new_log_entry = array('UID' => $uid,'dt' => $dt, 'success' => $success, 'block' => $block, 'total_blocks' => $total_blocks, 'total_items' => $total_items, 'no_imported' => $no_imported, 'body_size' => $body_size, 'datasize' => $datasize);
// 	$new_log_entry = array($uid, $dt, $success, $block, $total_blocks, $total_items, $no_imported, $total_deleted, $datasize, $body_decoded_strlen);
// 	if(is_array($current_log)){
// 	    array_unshift($current_log, $new_log_entry);
// 	    $current_log = array_slice($current_log, 0, 50);
// 	    update_option('bcs_book_import_blocks_log', $current_log);
// 	}else{
// 	    update_option('bcs_book_import_blocks_log', $new_log_entry);
// 	}
// }


function bcs_add_log_entry($uid, $dt, $success, $block, $total_blocks, $total_items, $no_imported, $total_deleted, $datasize, $body_decoded_strlen) {
    // Fetch the current log
    $current_log = get_option('bcs_book_import_blocks_log', array());

    // Ensure current log is an array of arrays
    if (!is_array($current_log)) {
        $current_log = array();
    } else {
        // Filter out any non-array elements
        $current_log = array_filter($current_log, 'is_array');
    }

    // Create the new log entry
    $new_log_entry = array($uid, $dt, $success, $block, $total_blocks, $total_items, $no_imported, $total_deleted, $datasize, $body_decoded_strlen);

    // Add the new log entry to the beginning of the log
    array_unshift($current_log, $new_log_entry);

    // Limit the log to the most recent 50 entries
    $current_log = array_slice($current_log, 0, 50);

    // Update the log option
    update_option('bcs_book_import_blocks_log', $current_log);
}

function bcs_create_import_table() {
	global $wpdb;

	$table_name = $wpdb->prefix . 'bcs_temp_book_import';
	
	$charset_collate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE $table_name (
		id mediumint(9) NOT NULL AUTO_INCREMENT,
		isbn bigint(20),
		title varchar(555),
	    author varchar(255),
	    excerpt text(5555),
	    price varchar(255),
	    sale_price varchar(255),
	    weight varchar(255),
	    thumbnail varchar(255),
	    thumbnailsm varchar(255),
	    stock bigint(20),
	    stock_code varchar(255),
		publisher varchar(255),
	    cover varchar(255),
	    pages varchar(255),
	    imprint varchar(255),
	    language varchar(255),
	    edition varchar(255),
	    subject varchar(555),
	    length varchar(255),
	    width varchar(255),
	    height varchar(255),
	    category varchar(555),
	    pub_date varchar(255),
	    content text(6555),
	    dewey varchar(555),
	    readership varchar(555),
		PRIMARY KEY  (id)
	) $charset_collate;";

	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $sql );
}

function bcs_all_the_products_import_output(){
	global $wpdb;
    wp_enqueue_media();
    wp_enqueue_script('jquery');
    wp_enqueue_script('media-upload');
    wp_enqueue_script('jquery-timing','https://creativecouple.github.io/jquery-timing/jquery-timing.min.js',array('jquery'));
    wp_enqueue_script('jquery-ui-progressbar');
    wp_enqueue_style('jquery-ui-smoothness-style','https://ajax.googleapis.com/ajax/libs/jqueryui/1.10.3/themes/smoothness/jquery-ui.min.css');
    $import_counter = 0; ?>
    <div class="wrap">
    <?php if(isset($_POST['filename'])) {
    	bcs_create_import_table();
    	$temp = utf8_encode(bcs_get_data($_POST['filename']));
  		libxml_use_internal_errors(true);
		$xml = simplexml_load_string($temp);
		$xml_error = explode("\n", $temp);
		$xml_error_messages = array();
		if (!$xml) {
		    $errors = libxml_get_errors();
		    foreach ($errors as $error) {
		        $xml_error_messages[] = $error->message;
		    }
		    libxml_clear_errors();
		} else {
			
	  		$error_messages = array();
	 		$product_array = [];
			$table_name = $wpdb->prefix . 'bcs_temp_book_import';
			$sql_query = $wpdb->query("TRUNCATE TABLE $table_name");
			if($sql_query == 1){
				$insert_errors = [];
		 		foreach($xml->book as $item){
					$isbn = (string)$item->isbn;
					if($isbn == ''){
						$error_messages[] = 'Item skipped: no isbn';
						continue;
					}
					
					include 'bcs-get-values.php';
			
					$wpdb->insert( 
						$table_name, 
						array( 
							'isbn' => $isbn, 
							'title' => $post_title, 
							'author' => $author, 
							'excerpt' => $excerpt, 
							'price' => $price, 
							'sale_price' => $saleprice, 
							'weight' => $weight, 
							'thumbnail' => $thumbnail, 
							'thumbnailsm' => $thumbnailsm, 
							'stock' => $stock, 
							'stock_code' => $stock_code,
							'publisher' => $publisher, 
							'cover' => $cover, 
							'pages' => $pages, 
							'imprint' => $imprint, 
							'language' => $language, 
							'edition' => $edition, 
							'subject' => $subject, 
							'length' => $length,
							'width' => $width,
							'height' => $height, 
							'category' => $category,
							'pub_date' => $pub_date,
							'content' => $content,
							'dewey' => $dewey,
							'readership' => $readership
						) 
					);
				
					 $import_counter++;
				}
			}
		}


	$permalinks = get_option( 'woocommerce_permalinks' );
	
	bcs_create_attributes(); ?>

	<h2>BCS BatchLine Book Importer</h2>
    <h4>Import Books</h4>
    <?php if(empty($xml_error_messages)){ ?>
		<br/>
	
    	<span class="importpreview" id="importpreview"><?php echo $import_counter; ?> Books for import.</span>
    
	    <p>Click the button to start the import. Don't close the window until import is finished.</p>
	    <div id="progressbar"></div>
	    <p>
	        <a class="button goimport" href="#" id="import_start">Start import</a>  
	    </p>
	    <span class="resultlabel">Result: </span><br />
	    <span class="result" data-errors="0" data-imported="0">
	        Books imported: <span class="updated">0</span><br />
	        Errors: <span class="error">0</span>
	    </span><br />
	    <div id="ajax_responses"></div><br/>
	    <script>
	        var progressbar;
	        var aktval = 0;
	        jQuery(document).ready(function($){
	            progressbar = $('#progressbar').progressbar({
	                max: <?php echo $import_counter; ?>
	            });
	        });
	    </script>
	<?php } else { ?>
		<p><strong>XML Error. Please check error messages:</strong></p>
		<?php //print_r($xml_error_messages); 
		foreach ($xml_error_messages as $xml_error_message) {
			echo $xml_error_message.'<br/>';
		} 
	}
    
    } else { ?>
	    <style type="text/css"></style>
		<h2>BCS BatchLine Book Importer</h2>

		<?php 
		// $bcs_book_import_log = get_option('bcs_book_import_blocks_log'); 
		// echo '<pre>';
		// print_r($bcs_book_import_log); 
		// echo '</pre>'; 
		?>
		
		<h4>Automatic Uploads</h4>
		<p>Automatic uploads require Wordpress v5.6 or greater.</p>
		
		<h4>Manual Uploads</h4>
		<p>XML export files can be manually uploaded and imported below.</p>
				
	    <h3>Import Books</h3>

	    <?php $localkey = "";
		$licensekey = "";
	    $options = get_option( 'bcs_book_import_settings' );
		$localkeyoption = get_option( 'bcs_book_import_settings_local_key' );
	    if(isset($options['bcs_book_import_license_key'])){
	        $licensekey = __($options['bcs_book_import_license_key']);
	    }
	    $results = bcs_check_license($licensekey, $localkeyoption);
		// Interpret response
		switch ($results['status']) {
		    case "Active":
		        // get new local key and save it somewhere
		        if(isset($results['localkey'])){
			        $localkeydata = $results['localkey'];
			        update_option('bcs_book_import_settings_local_key', $localkeydata);
			       }
		        ?>
		        <form name="import_form" method="post" action="<?php echo str_replace( '%7E', '~', $_SERVER['REQUEST_URI']); ?>">
				    <label for="filename">XML-File</label>
				    <input id="upload_txt" type="text" name="filename" value="" />
				    <input id="upload_button" type="button" class="button" value="Upload XML file" />
				    <input type="submit" class="button" value="Prepare import" />
				</form>
		<?php
		        break;
		    case "Invalid":
		        echo("<p style='color:red;''>License key is Invalid. Please enter a valid license key to use the importer.</p>");
		        break;
		    case "Expired":
		        echo("<p style='color:red;''>License key is Expired. Please enter a valid license key to use the importer.</p>");
		        break;
		    case "Suspended":
		        echo("<p style='color:red;''>License key is Suspended. Please enter a valid license key to use the importer.</p>");
		        break;
		    default:
		        echo("<p style='color:red;''>Invalid Response</p>");
		        break;
		} ?>

		<form action='options.php' method='post'>
	        <?php
	        settings_fields( 'bcsPlugin' );
	        do_settings_sections( 'bcsPlugin' );
	        do_settings_sections( 'bcsPlugin_codes' );
	        submit_button();
	        ?>
	    </form>
					
		<h3>Help</h3>
		<p><strong>License keys</strong><br/>
			- Go to: <a href="https://servicedesk.bcs.solutions/index.php?rp=/store/wordpress-plugins">https://servicedesk.bcs.solutions/index.php?rp=/store/wordpress-plugins</a> to purchase a new license key.<br/>
			- Enter license key above and click Save.
		</p>
		<p><strong>Create or manage API keys</strong><br/>
			- Go to: <a href="<?php echo home_url(); ?>/wp-admin/admin.php?page=wc-settings&tab=advanced&section=keys"><strong>WooCommerce > Settings > Advanced > REST API</strong></a>.<br/>
			- Select <strong>Add Key</strong>. You are taken to the <strong>Key Details</strong> screen.<br/>
			- Add a <strong>Description</strong>.<br/>
			- Select the <strong>User</strong> you would like to generate a key for in the dropdown.<br/>
			- Select a level of access for this API key — <strong>Read/Write</strong> access.<br/>
			- Select <strong>Generate API Key</strong>, and WooCommerce creates API keys for that user.<br/>
			- Now that keys have been generated, you should see your <strong>Consumer Key</strong> and <strong>Consumer Secret</strong> keys.<br/>
			- The <strong>Consumer Key</strong> and <strong>Consumer Secret</strong> may now be entered in the web exporter app.</p>
		<p><strong>URLs</strong> (copy and paste to web exporter app)<br/>
			Upload URL: <strong><?php echo home_url(); ?>/wp-json/wc/v3/bcsbertlinebookimport</strong><br/>
			Order Sync URL: <strong><?php echo home_url(); ?>/wp-json/wc/v3/orders</strong></p>
		<p><strong>Where is the bibliodata?</strong><br/>
		Biblio data is saved as product attributes. Product attributes are displayed in the Additional Infomation tab on the product page.</p>
		<p><strong>Biblio data is not showing on product page </strong><br/>
		Some themes change the way attributes are displayed on the product page. Please try the official <a href="https://woocommerce.com/storefront/" rel="nofollow ugc">Woocommerce Store Front theme</a>. If you would like assistance displaying this on your own theme please <a href="https://bcs-studio.com/" rel="nofollow ugc">get in touch</a>.</p>
		<p><strong>Images aren't imported.</strong><br/>
		Images are not imported. Instead they are loaded from Batch servers.</p>
		<p><strong>Images are not displaying.</strong><br/>
		Please check for conflicts with other plugins/theme. Please try the official <a href="https://woocommerce.com/storefront/" rel="nofollow ugc">Woocommerce Store Front theme</a>. If you would like assistance displaying images on your own theme please <a href="https://bcs-studio.com/" rel="nofollow ugc">get in touch</a>.</p>
		<p><strong>How do I get an export?</strong><br/>
		Exports are made through the BatchLine Web Exporter. Contact Batch if you require the exporter addon.</p>
		<p><strong>Peek Inside</strong><br/>
		The options for Peek Inside above add the links to the default woocommerce areas. If your theme has changed these and the options above do not work you can try adding a shortcode instead. Either add the shortcode "[bcs-peek-inside]" to the content area or add "echo do_shortcode('[bcs-peek-inside]');" to your theme files. You can also add you own styling to the Peek Inside label/links.</p>

		<h3>Debugging</h3>
		<p>($uid, $dt, $success, $block, $total_blocks, $total_items, $no_imported, $total_deleted, $datasize, $body_decoded_strlen)</p>
		<?php $php_version = phpversion();
			$wordpress_version = get_bloginfo('version');

			$plugin_dir = WP_PLUGIN_DIR . '/woocommerce/woocommerce.php';
			$plugin_data = get_plugin_data($plugin_dir);
			$woocommerce_version = $plugin_data['Version'];

			$bcs_plugin_dir = WP_PLUGIN_DIR . '/bcs-bertline-book-importer/bcs-bertline-book-importer.php';
			$bcs_plugin_data = get_plugin_data($bcs_plugin_dir);
			$bcs_plugin_version = $bcs_plugin_data['Version'];

			$domain = $_SERVER['SERVER_NAME'];

			$bcs_book_import_log = get_option('bcs_book_import_blocks_log');
			// echo '<pre>';
			// print_r($bcs_book_import_log);
			// echo '</pre>';
			//var_dump(implode(",", $bcs_book_import_log)); ?>
		<textarea rows="20" style="width:100%;" readonly="readonly"><?php echo "PHP: ".$php_version."\n";
			echo "Wordpress: ".$wordpress_version."\n";
			echo "WooCommerce: ".$woocommerce_version."\n";
			echo "BCS BatchLine Book Importer: ".$bcs_plugin_version."\n";
			echo "License Key: ".$licensekey."\n";
			echo "Domain: ".$domain."\n";
			echo "\n";
			if ($bcs_book_import_log) {
			echo "Log:"."\n";
			
				foreach($bcs_book_import_log as $bcs_book_import_log_entry){
					//echo '<p>';
					//echo "\n";
					echo implode(",", $bcs_book_import_log_entry)."\n";
					//echo '</p>';
					//echo "\n";
					//echo '<br/>';
				} 
			} ?>
		</textarea>

	<?php } ?>
	<script>
        jQuery(document).ready(function($) {
            $('.goimport').click(function(event){
                event.preventDefault();
                $('#import_start').addClass('disabled');
                var mycounter = 0;
                var i = 1;
                var x = 1;
                var max_count = <?php echo $import_counter; ?>;
                function go() {
                    mycounter++;
                    postdata = new Object();
                    postdata['action'] = 'import_all_the_products_action';
					postdata['import_counter'] = i;

                        $.post(ajaxurl, postdata, function(response){
                            if(response.message == 'SUCCESS'){
                                $('span.result').data('imported',parseInt($('span.result').data('imported'))+1);
                                $('span.result .updated').html(parseInt($('span.result .updated').html())+1);
                            }else if(response.message == 'ERROR'){
                                $('span.result').data('errors',parseInt($('span.result').data('errors'))+1);
                                $('span.result .error').html(parseInt($('span.result .error').html())+1);
                                $('#ajax_responses').append(response.error_message+'<br/>');
                            }
                            aktval++;
                            progressbar.progressbar( "value", aktval );
                        },"json");
                        i = i+1;
                    
                  if (x++ < <?php echo $import_counter; ?>) {
		                setTimeout(go, 600);
		            } else {
		            	// $('#import_start').removeClass('disabled');
		            }
		            }
		           go();

		           return false;
            });
            var _custom_media = true,
            _orig_send_attachment = wp.media.editor.send.attachment;
            $('#upload_button').click(function(e) {
                var send_attachment_bkp = wp.media.editor.send.attachment;
                var button = $(this);
                _custom_media = true;
                wp.media.editor.send.attachment = function(props, attachment){
                    if ( _custom_media ) {
                        $("#upload_txt").val(attachment.url);
                    } else {
                        return _orig_send_attachment.apply( this, [props, attachment] );
                    };
                };
                wp.media.editor.open(button);
                return false;
            });
        });
    </script>
	</div>
<?php }

add_action( 'wp_ajax_import_all_the_products_action', 'bcs_import_products_ajax_action' );
function bcs_import_products_ajax_action() {
	global $wpdb;
	$row_id = $_POST['import_counter'];
	$myrow = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM wp_bcs_temp_book_import WHERE id = %d", $row_id ) );
	$import_id = $myrow->id;
	$isbn = $myrow->isbn;
	$post_title = $myrow->title;
	$author = $myrow->author;
	$excerpt = $myrow->excerpt;
	$price = $myrow->price;
	$saleprice = $myrow->sale_price;
	$weight = $myrow->weight;
	$thumbnail = $myrow->thumbnail;
	$thumbnailsm = $myrow->thumbnailsm;
	$stock = $myrow->stock;
	$stock_code = $myrow->stock_code;
	$publisher = $myrow->publisher;
	$cover = $myrow->cover;
	$pages = $myrow->pages;
	$imprint = $myrow->imprint;
	$language = $myrow->language;
	$edition = $myrow->edition;
	$subject = $myrow->subject;
	$length = $myrow->length;
	$width = $myrow->width;
	$height = $myrow->height;
	$category = $myrow->category;
	$pub_date = $myrow->pub_date;
	$content = $myrow->content;
	$dewey = $myrow->dewey;
	$readership = $myrow->readership;	

	if($post_title == '' && $content == '' && $longdesc == ''){
		echo json_encode(array('message' => 'ERROR','isbn' => $isbn,'error_message' => $isbn.' : Content, title, and excerpt are empty.'));
        die();
	}

	$stock_only = false;
	if(get_option( 'bcs_book_import_settings' )){
		$options = get_option( 'bcs_book_import_settings' );
			if(isset($options['bcs_book_import_stock_only']) && $options['bcs_book_import_stock_only'] == true){
				$stock_only = true;
			}
	}

	$tags = explode('|', $subject);
	$uc_categories = ucwords(strtolower($category));
	
	$success = false;
	// Let's start with creating the post itself
	if($stock_only){
		$post_data = array(
			'post_title' 	=> $post_title,
		);
	} else {
		$post_data = array(
			'post_title' 	=> $post_title,
			'post_content' 	=> $content,
			'post_status' 	=> 'publish',
			'post_type' 	=> 'product', // Or "page" or some custom post type
			'post_date'     =>   $pub_date,
			'post_excerpt'	=> $excerpt
		);
	};

	// check if product already exists
	if(bcs_get_product_by_sku($isbn)){
		//update existing post
		$idpost = bcs_get_product_by_sku($isbn);
		$post_data['ID'] = $idpost;
		$post_id = wp_update_post( $post_data, true );
		if(!is_wp_error($post_id)){
			$no_imported++;
			$success = true;
		}else{
			$error_messages[] = $isbn.' : '.$post_title.' : '.$post_id->get_error_message();
		}
	} else {
		//add new post
		if(!$stock_only){
			//add new post
			$post_id = wp_insert_post($post_data, true);
			if(!is_wp_error($post_id)){
				$no_imported++;
				$success = true;
			}else{
				$error_messages[] = $isbn.' : '.$post_title.' : '.$post_id->get_error_message();
			}
		}else{
			$error_messages[] = $isbn.' : '.$post_title.' : ISBN not found. Can not update stock only.';
			die();
		}

	}

	if(!$stock_only){
		wp_set_object_terms( $post_id, 'simple', 'product_type' );
		wp_set_object_terms( $post_id, $uc_categories, 'product_cat' );
		wp_set_object_terms( $post_id, $tags, 'product_tag');
	}

	$product = wc_get_product( $post_id );

	$product = wc_get_product( $post_id );
	$woosaleprice = $product->get_sale_price();
	$currentprice = $price;

	if( !$woosaleprice == '' && $product->is_on_sale() ) {
		$currentprice = $woosaleprice;
	}
	
	if(!$stock_only){
		$attributes = array(
			'pa_book_author' => $author,
			'pa_publisher' => $publisher,
			'pa_imprint' => $imprint,
			'pa_cover' => $cover,
			'pa_pages' => $pages,
			'pa_language' => $language,
			'pa_edition' => $edition,
			'pa_dewey' => $dewey,
			'pa_readership' => $readership,
		);
		$i = 0;
		$product_attributes = array();
		
		foreach($attributes as $key => $value){
			wp_set_object_terms( $post_id, $value, $key, false);
			
			$product_attributes[sanitize_title($key)] = array (
				'name' => wc_clean($key), // set attribute name
				'value' => $value, // set attribute value
				'position' => $i,
				'is_visible' => 1,
				'is_variation' => 0,
				'is_taxonomy' => 1
			);
			$i++;
		}

		update_post_meta($post_id, '_product_attributes', $product_attributes);
		update_post_meta($post_id, 'pa_book_author', $author);
	}

	$_backorders = 'no';
	$deny_codes = array('OP');
	$allow_codes = array('TOS', 'RPR', 'NYP', 'POD');

	if(get_option( 'bcs_book_import_settings' )){
 		$options = get_option( 'bcs_book_import_settings' );
	    if(isset($options['bcs_book_import_allow_backorders']) && $options['bcs_book_import_allow_backorders'] == true){
	    	$_backorders = 'notify';
	    }
	}

	if(!$stock_only){
		if(in_array($stock_code, $deny_codes)){
			$_backorders = 'no';
		} else {
		//if(in_array($stock_code, $allow_codes)){
			$_backorders = 'notify';
		}

		$postOptions = array(
			'_sku' => $isbn,
			'_regular_price' => $price,
			'_sale_price' => $woosaleprice,
			'_price' => $currentprice,
			'_length' => $length,
			'_width' => $width,
			'_height' => $height,
			'_weight' => $weight,
			'_visibility' => 'visible',
			'_manage_stock' => 'yes',
			'imported' => true,
			'stock_code' => $stock_code,
			'main_image' => $thumbnail,
			'thumbnail_image' => $thumbnailsm,
			'_yoast_wpseo_title'	=> $post_title,
			'_yoast_wpseo_metadesc'	=> $content,
			'_backorders' => $_backorders,
		);
	} else {
		$postOptions = array(
					'_regular_price' => $price,
						'_sale_price' => $woosaleprice,
						'_price' => $currentprice,
					'stock_code' => $stock_code,
			'_backorders' => $_backorders,
				);
	}

	// Loop through the post options
	foreach($postOptions as $key=>$value){
		// Add the post options
		update_post_meta($post_id,$key,$value);
	}

	wc_update_product_stock($post_id, $stock, 'set');

	if(isset($options['bcs_book_import_allow_backorders']) && $options['bcs_book_import_allow_backorders'] == true){
		wp_publish_post($post_id);
	}

    if($success){
        echo json_encode(array('message' => 'SUCCESS','data' => $isbn));
        die();
    } else {
       echo json_encode(array('message' => 'ERROR','isbn' => $isbn,'error_message' => $error_messages));
        die();
     }
}