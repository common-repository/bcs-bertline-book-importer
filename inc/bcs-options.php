<?php
add_action( 'admin_menu', 'bcs_book_import_add_admin_menu' );
add_action( 'admin_init', 'bcs_book_import_settings_init' );

function bcs_book_import_add_admin_menu(  ) {
    add_management_page( 'Book Import', 'Book Import', 'manage_options', 'bcs-book-import', 'bcs_book_import_options_page' );

    // $args = array(
    //     'default' => '1',
    // );
    // register_setting( 'bcsPlugin1', 'bcs_book_import_blocks_log', $args );
}

function bcs_book_import_settings_init(  ) {
    register_setting( 'bcsPlugin', 'bcs_book_import_settings' );
    register_setting( 'bcsPlugin', 'bcs_book_import_blocks_log' );
    

    add_settings_section(
        'bcs_book_import_bcsPlugin_section',
        __( 'Settings', 'wordpress' ),
        'bcs_book_import_settings_section_callback',
        'bcsPlugin'
    );
    add_settings_section(
        'bcs_book_import_bcsPlugin_codes_section',
        __( 'Stock Status Code Translations', 'wordpress' ),
        'bcs_book_import_settings_codes_section_callback',
        'bcsPlugin'
    );

    //settings
    add_settings_field(
        'bcs_book_import_license_key',
        __( 'License Key', 'wordpress' ),
        'bcs_book_import_license_key_render',
        'bcsPlugin',
        'bcs_book_import_bcsPlugin_section'
    );
    add_settings_field(
        'bcs_book_import_log',
        __( 'Log Imported XML', 'wordpress' ),
        'bcs_book_import_check_field_0_render',
        'bcsPlugin',
        'bcs_book_import_bcsPlugin_section'
    );
    add_settings_field(
        'bcs_book_import_allow_backorders',
        __( 'Backorders', 'wordpress' ),
        'bcs_book_import_check_field_1_render',
        'bcsPlugin',
        'bcs_book_import_bcsPlugin_section'
    );
    add_settings_field(
        'bcs_book_import_stock_only',
        __( 'Manual Import: Update stock only', 'wordpress' ),
        'bcs_book_import_check_field_2_render',
        'bcsPlugin',
        'bcs_book_import_bcsPlugin_section'
    );
    add_settings_field(
        'bcs_book_import_show_image_preview',
        __( 'Peek Inside: ', 'wordpress' ),
        'bcs_book_import_check_field_3_render',
        'bcsPlugin',
        'bcs_book_import_bcsPlugin_section'
    );
    add_settings_field(
        'bcs_book_import_show_short_preview',
        __( ' ', 'wordpress' ),
        'bcs_book_import_check_field_4_render',
        'bcsPlugin',
        'bcs_book_import_bcsPlugin_section'
    );
    add_settings_field(
        'bcs_book_import_show_long_preview',
        __( ' ', 'wordpress' ),
        'bcs_book_import_check_field_5_render',
        'bcsPlugin',
        'bcs_book_import_bcsPlugin_section'
    );

     $args = array(
            'type' => 'string', 
            //'sanitize_callback' => 'sanitize_text_field',
            'default' => NULL,
            );
    register_setting( 'bcs_book_import_bcsPlugin_section', 'my_option_name', $args );

    //translations
    add_settings_field(
        'bcs_book_import_text_field_default',
        __( 'Default', 'wordpress' ),
        'bcs_book_import_text_field_default_render',
        'bcsPlugin',
        'bcs_book_import_bcsPlugin_codes_section'
    );
    add_settings_field(
        'bcs_book_import_text_field_tos',
        __( 'TOS', 'wordpress' ),
        'bcs_book_import_text_field_tos_render',
        'bcsPlugin',
        'bcs_book_import_bcsPlugin_codes_section'
    );
    add_settings_field(
        'bcs_book_import_text_field_op',
        __( 'OP', 'wordpress' ),
        'bcs_book_import_text_field_op_render',
        'bcsPlugin',
        'bcs_book_import_bcsPlugin_codes_section'
    );
    add_settings_field(
        'bcs_book_import_text_field_rpr',
        __( 'RPR', 'wordpress' ),
        'bcs_book_import_text_field_rpr_render',
        'bcsPlugin',
        'bcs_book_import_bcsPlugin_codes_section'
    );
    add_settings_field(
        'bcs_book_import_text_field_nyp',
        __( 'NYP', 'wordpress' ),
        'bcs_book_import_text_field_nyp_render',
        'bcsPlugin',
        'bcs_book_import_bcsPlugin_codes_section'
    );
    add_settings_field(
        'bcs_book_import_text_field_pod',
        __( 'POD', 'wordpress' ),
        'bcs_book_import_text_field_pod_render',
        'bcsPlugin',
        'bcs_book_import_bcsPlugin_codes_section'
    );
}

function bcs_book_import_license_key_render(  ) {
    $options = get_option( 'bcs_book_import_settings' );
    $localkeyoption = get_option( 'bcs_book_import_settings_local_key' );
    ?>
    <input type='text' name='bcs_book_import_settings[bcs_book_import_license_key]' value='<?php if(isset($options['bcs_book_import_license_key'])){ echo $options['bcs_book_import_license_key']; } ?>'>
    <?php
        $localkey = "";
        $licensekey = "";
        
        
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
                
                echo("<br/><p style='color:green;''>License key Active</p>");
                break;
            case "Invalid":
                echo("<br/><p style='color:red;''>License key is Invalid.</p>");
                break;
            case "Expired":
                echo("<br/><p style='color:red;''>License key is Expired</p>");
                break;
            case "Suspended":
                echo("<br/><p style='color:red;''>License key is Suspended</p>");
                break;
            default:
                echo("<br/><p style='color:red;''>Invalid Response</p>");
                break;
        }
}

function bcs_book_import_local_key_render(  ) {
    $option = get_option( 'bcs_book_import_settings_local_key' );
    ?>
    <input type='text' name='bcs_book_import_local_key' value='<?php if(isset($option)){ echo $option; } ?>'>
    <?php
}

function bcs_book_import_check_field_0_render() {

    $options = get_option( 'bcs_book_import_settings' );

    $html = '<style>.tools_page_bcs-book-import .form-table th, .tools_page_bcs-book-import .form-table td{padding:0;}</style>';
    $html .= '<input type="checkbox" id="checkbox_example" name="bcs_book_import_settings[bcs_book_import_log]" value="1"';
    if(get_option( 'bcs_book_import_settings' )){
        if(isset($options['bcs_book_import_log'])){
            $html .= ' checked="checked" ';
        }
    }
    $html .= '/>';
    $html .= '<label for="checkbox_example">Log automatic import xml (only enable for debugging)</label>';

    echo $html;

}

function bcs_book_import_check_field_1_render() {

    $options = get_option( 'bcs_book_import_settings' );

    $html = '<style>.tools_page_bcs-book-import .form-table th, .tools_page_bcs-book-import .form-table td{padding:0;}</style>';
    $html .= '<input type="checkbox" id="checkbox_example1" name="bcs_book_import_settings[bcs_book_import_allow_backorders]" value="1"';
    if(get_option( 'bcs_book_import_settings' )){
        if(isset($options['bcs_book_import_allow_backorders'])){
            $html .= ' checked="checked" ';
        }
    }
    $html .= '/>';
    $html .= '<label for="checkbox_example1">Allow backorders by default?</label>';

    echo $html;

}

function bcs_book_import_check_field_2_render() {

    $options = get_option( 'bcs_book_import_settings' );

    $html = '<style>.tools_page_bcs-book-import .form-table th, .tools_page_bcs-book-import .form-table td{padding:0;}</style>';
    $html .= '<input type="checkbox" id="checkbox_stock" name="bcs_book_import_settings[bcs_book_import_stock_only]" value="1"';
    if(get_option( 'bcs_book_import_settings' )){
        if(isset($options['bcs_book_import_stock_only'])){
            $html .= ' checked="checked" ';
        }
    }
    $html .= '/>';
    $html .= '<label for="checkbox_stock">Manual Import: Only update stock, stock code and price.</label>';

    echo $html;

}

function bcs_book_import_check_field_3_render() {

    $options = get_option( 'bcs_book_import_settings' );

    $html = '<style>.tools_page_bcs-book-import .form-table th, .tools_page_bcs-book-import .form-table td{padding:0;}</style>';
    $html .= '<input type="checkbox" id="checkbox_peek_image" name="bcs_book_import_settings[bcs_book_import_show_image_preview]" value="1"';
    if(get_option( 'bcs_book_import_settings' )){
        if(isset($options['bcs_book_import_show_image_preview'])){
            $html .= ' checked="checked" ';
        }
    }
    $html .= '/>';
    $html .= '<label for="checkbox_peek_image">Add Peek Inside link to product image.</label>';

    echo $html;

}

function bcs_book_import_check_field_4_render() {

    $options = get_option( 'bcs_book_import_settings' );

    $html = '<style>.tools_page_bcs-book-import .form-table th, .tools_page_bcs-book-import .form-table td{padding:0;}</style>';
    $html .= '<input type="checkbox" id="checkbox_peek_short" name="bcs_book_import_settings[bcs_book_import_show_short_preview]" value="1"';
    if(get_option( 'bcs_book_import_settings' )){
        if(isset($options['bcs_book_import_show_short_preview'])){
            $html .= ' checked="checked" ';
        }
    }
    $html .= '/>';
    $html .= '<label for="checkbox_peek_short">Add Peek Inside link to short description.</label>';

    echo $html;

}

function bcs_book_import_check_field_5_render() {

    $options = get_option( 'bcs_book_import_settings' );

    $html = '<style>.tools_page_bcs-book-import .form-table th, .tools_page_bcs-book-import .form-table td{padding:0;}</style>';
    $html .= '<input type="checkbox" id="checkbox_peek_long" name="bcs_book_import_settings[bcs_book_import_show_long_preview]" value="1"';
    if(get_option( 'bcs_book_import_settings' )){
        if(isset($options['bcs_book_import_show_long_preview'])){
            $html .= ' checked="checked" ';
        }
    }
    $html .= '/>';
    $html .= '<label for="checkbox_peek_long">Add Peek Inside link to description.</label>';

    echo $html;

}

function bcs_book_import_backorders_allow_codes_render(  ) {
    $options = get_option( 'bcs_book_import_settings' );
    ?>
    <textarea name='bcs_book_import_settings[bcs_book_import_backorders_allow_codes]' value=''><?php if(isset($options['bcs_book_import_backorders_allow_codes'])){ echo $options['bcs_book_import_backorders_allow_codes']; } ?></textarea>
    <?php
}

function bcs_book_import_backorders_deny_codes_render(  ) {
    $options = get_option( 'bcs_book_import_settings' );
    ?>
    <textarea name='bcs_book_import_settings[bcs_book_import_backorders_deny_codes]' value=''><?php if(isset($options['bcs_book_import_backorders_deny_codes'])){ echo $options['bcs_book_import_backorders_deny_codes']; } ?></textarea>
    <?php
}

function bcs_book_import_text_field_default_render(  ) {
    $options = get_option( 'bcs_book_import_settings' );
    ?>
    <input type='text' name='bcs_book_import_settings[bcs_book_import_text_field_default]' value='<?php if(isset($options['bcs_book_import_text_field_default'])){ echo $options['bcs_book_import_text_field_default']; } ?>'>
    <?php
}
function bcs_book_import_text_field_tos_render(  ) {
    $options = get_option( 'bcs_book_import_settings' );
    ?>
    <input type='text' name='bcs_book_import_settings[bcs_book_import_text_field_tos]' value='<?php if(isset($options['bcs_book_import_text_field_tos'])){ echo $options['bcs_book_import_text_field_tos']; } ?>'>
    <?php
}
function bcs_book_import_text_field_op_render(  ) {
    $options = get_option( 'bcs_book_import_settings' );
    ?>
    <input type='text' name='bcs_book_import_settings[bcs_book_import_text_field_op]' value='<?php if(isset($options['bcs_book_import_text_field_op'])){ echo $options['bcs_book_import_text_field_op']; } ?>'>
    <?php
}
function bcs_book_import_text_field_rpr_render(  ) {
    $options = get_option( 'bcs_book_import_settings' );
    ?>
    <input type='text' name='bcs_book_import_settings[bcs_book_import_text_field_rpr]' value='<?php if(isset($options['bcs_book_import_text_field_rpr'])){ echo $options['bcs_book_import_text_field_rpr']; } ?>'>
    <?php
}
function bcs_book_import_text_field_nyp_render(  ) {
    $options = get_option( 'bcs_book_import_settings' );
    ?>
    <input type='text' name='bcs_book_import_settings[bcs_book_import_text_field_nyp]' value='<?php if(isset($options['bcs_book_import_text_field_nyp'])){ echo $options['bcs_book_import_text_field_nyp']; } ?>'>
    <?php
}
function bcs_book_import_text_field_pod_render(  ) {
    $options = get_option( 'bcs_book_import_settings' );
    ?>
    <input type='text' name='bcs_book_import_settings[bcs_book_import_text_field_pod]' value='<?php if(isset($options['bcs_book_import_text_field_pod'])){ echo $options['bcs_book_import_text_field_pod']; } ?>'>
    <?php
}

function bcs_book_import_settings_section_callback(  ) {
}
function bcs_book_import_settings_codes_section_callback(  ) {
}
function bcs_book_import_options_page(  ) {
    bcs_all_the_products_import_output();
}