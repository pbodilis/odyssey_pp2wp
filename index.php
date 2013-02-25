<?php

if ( !isset($wp_did_header) ) {

    $wp_did_header = true;

    require_once( dirname(dirname(__FILE__)) . '/wordpress/wp-load.php' );

    wp();
}

$redirection = get_option('ppposts2wpposts');

if (isset($_GET['showimage'])) {
    $ppPostId = intval($_GET['showimage']);
    $wpPostId = isset($redirection[$ppPostId]) ? $redirection[$ppPostId] : null;

    header("Status: 301 Moved Permanently", false, 301);
    header("Location: " . get_permalink($wpPostId));
    exit();
}
var_dump($_GET['showimage']);

echo "porut\n";

