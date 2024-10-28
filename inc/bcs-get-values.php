<?php 

	$post_title = (string)$item->title;
	$author = (string)$item->author;
	$excerpt = (string)$item->content;
	$price = (string)$item->price;
	$saleprice = (string)$item->saleprice;
	$weight = (string)$item->weight;
	$thumbnail = (string)$item->thumbnailL;
	$thumbnailsm = (string)$item->thumbnailM;
	$stock = (string)$item->stock;
	$stock_code = (string)$item->stock_code;
	$publisher = (string)$item->publisher;
	$cover = (string)$item->cover;
	$pages = (string)$item->pages;
	$imprint = (string)$item->imprint;
	$language = (string)$item->lang;
	$edition = (string)$item->edition;
	$subject = (string)$item->subject;
	$dimensions = (string)$item->dimensions;
	$category = (string)$item->multicat;
	$pub_date = (string)$item->pub_date;
	$content = (string)$item->longdesc;
	$dewey = (string)$item->dewey;
	$readership = (string)$item->readership;
	
	if(in_array($author, array(', ', ',, ', ','))){
		$author = '';
	}
	
	$weightunit = get_option('woocommerce_weight_unit');
	$dimensionunit = get_option('woocommerce_dimension_unit');
	
	if(!$weight == '' && !empty($weight)){
		//change weight from grams
		if($weightunit == 'kg'){
			$weight = $weight / 1000;
		}
		if($weightunit == 'lbs'){
			$weight = $weight / 454;
		}
		if($weightunit == 'oz'){
			$weight = $weight / 28.35;
		}
	}
	
	if(!$dimensions == '' && !empty($dimensions)){
		list($length, $width, $height) = array_pad(explode('x', $dimensions, 3), 3, null);
	} else {
		$width = "";
		$length = "";
		$height = "";
	}	
	
	if(!$dimensions == '' && !empty($dimensions)){
		//change dimensions from mm
		if($dimensionunit == 'cm'){
			$width = $width / 10;
			$length = $length / 10;
			$height = $height / 10;
		}
		if($dimensionunit == 'in'){
			$width = $width / 25.4;
			$length = $length / 25.4;
			$height = $height / 25.4;
		}
		if($dimensionunit == 'yd'){
			$width = $width / 914;
			$length = $length / 914;
			$height = $height / 914;
		}
	}

	$tags = explode('|', $subject);		
	$uc_categories = ucwords(strtolower($category));

?>