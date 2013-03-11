<?php
/*
This redirection tool is to be used once the pixelpost importer for wordpress has been used.
It uses options left in wp DB to do the magic.
Author: Pierre Bodilis
Author URI: http://rataki.eu/
Version: 0.1
Text Domain: pixelpost-importer
License: GPL version 2 or later - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
*/

if ( !isset($wp_did_header) ) {
    $wp_did_header = true;
    require_once( dirname(dirname(__FILE__)) . '/wordpress/wp-load.php' );
    wp();
}


$wp_post_id = null;

if ( isset( $_GET['showimage'] ) ) {
    $pp_post_id = intval( $_GET['showimage'] );
    $pp_importer = new PP_Import();
    $wp_post_id = $pp_importer->get_pp2wp_wp_post_id($pp_post_id);
}

header( "Status: 301 Moved Permanently", false, 301);
header( "Location: " . get_permalink( $wp_post_id ) );
exit();

