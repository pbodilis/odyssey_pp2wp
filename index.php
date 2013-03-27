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

if( ! isset($wp_did_header) ) {
    $wp_did_header = true;
    require_once( dirname(dirname(__FILE__)) . '/wordpress/wp-load.php' );
    wp();
}


$link = home_url('/');

if( isset( $_GET['showimage']) && class_exists('PP_Importer') ) {
    $pp_post_id = intval( $_GET['showimage'] );
    $pp_importer = new PP_Importer();
    $wp_post_id = $pp_importer->get_pp2wp_wp_post_id($pp_post_id);
    $link = get_permalink( $wp_post_id );
} else if( isset( $_GET['x'] ) ) {
    switch($_GET['x']) {
        case 'rss':
            $link = get_bloginfo('rss2_url');
            break;
        case 'browse': // todo one dayœ
            break;
    }
}

header( "Status: 301 Moved Permanently", false, 301 );
header( "Location: " . $link );
exit();

