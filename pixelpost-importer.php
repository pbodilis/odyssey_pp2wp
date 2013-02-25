<?php
/*
Plugin Name: Pixelpost Importer
Plugin URI: http://wordpress.org/extend/plugins/wordpress-importer/
Description: Import posts, pages, comments, custom fields, categories, tags and more from a Pixelpost export file.
Author: Pierre Bodilis
Author URI: http://rataki.eu/
Version: 0.1
Text Domain: pixelpost-importer
License: GPL version 2 or later - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
*/


// add_action('admin_print_scripts', 'my_action_javascript');
function js_getPPPosts() {
?>
<div id="pp2wp_post_importation_log">Starting...</div>
<p>
    <input id="pp2wp_post_importation_stop"   type="submit" name="stop importing"   value="stop importing"   class="button button-primary"/>
    <input id="pp2wp_post_importation_resume" type="submit" name="resume importing" value="resume importing" class="button button-primary"/>
</p>

<script type="text/javascript" >
var pp2wp_current = 0;
var pp2wp_total = 0;
var pp2wp = {
    stop: false,
    doMigration: function() {
        if (pp2wp.stop) return;
        var pp_post_id = pp2wp.pp_post_ids[pp2wp_current];
        if (typeof(pp_post_id) == 'undefined') {
            jQuery('#pp2wp_post_importation_log').html('Pixelpost post with ID "' + pp_post_id + '" (index "' + pp2wp_current + '") aborted!');
            return;
        }
        var ajaxArgs = {
            action:     'pp2wp_import_pp_post_to_wp',
            pp_post_id: pp_post_id
        }
        jQuery.ajax({
            url:      ajaxurl,
            dataType: 'json',
            data:     ajaxArgs
        }).done(function(r) {
            if (r) {
                pp2wp_current++;
                jQuery('#pp2wp_post_importation_log').html('Pixelpost post with ID "' + pp_post_id +
                    '" (index "' + pp2wp_current + '/' + pp2wp_total + '")  successfully imported');
                setTimeout('pp2wp.doMigration()', 50);
            } else {
                jQuery('#pp2wp_post_importation_log').html('Pixelpost post with ID "' + pp_post_id +
                    '" (index "' + pp2wp_current + '/' + pp2wp_total +'")  could not be imported');
            }
        });
    }
};


jQuery(document).on('click', '#pp2wp_post_importation_stop', function(e) {
    pp2wp.stop = true;
    e.preventDefault();
    return false;
});
jQuery(document).on('click', '#pp2wp_post_importation_resume', function(e) {
    pp2wp.stop = false;
    pp2wp.doMigration();
    e.preventDefault();
    return false;
});

jQuery(document).ready(function($) {
    var ajaxArgs = {
        action:   'pp2wp_get_pp_post_ids'
    }
    jQuery.ajax({
        url:      ajaxurl,
        dataType: 'json',
        data:     ajaxArgs,
    }).done(function(pp) {
        pp2wp.pp_post_ids = pp;
        pp2wp_total = pp.length;
        pp2wp.doMigration();
    });
});
</script>
<?php
}

function pp2wp_get_pp_post_ids_callback() {
    echo json_encode($GLOBALS['pp_import']->get_pp_post_ids());
    die();
}
add_action('wp_ajax_pp2wp_get_pp_post_ids', 'pp2wp_get_pp_post_ids_callback');

function  pp2wp_import_pp_post_to_wp_callback() {
    $pp_post_id = $_REQUEST['pp_post_id'];
    $GLOBALS['pp_import']->pp_post2wp_post($pp_post_id);
    echo json_encode(true);
    die();
}
add_action('wp_ajax_pp2wp_import_pp_post_to_wp', 'pp2wp_import_pp_post_to_wp_callback');


// let's comment this, as we need to ajaxify the thing
// if ( ! defined('WP_LOAD_IMPORTERS'))
//     return;

/** Display verbose errors */
define('IMPORT_DEBUG', true);

// Load Importer API
require_once ABSPATH . 'wp-admin/includes/import.php';
if ( ! class_exists( 'WP_Importer')) {
    $class_wp_importer = ABSPATH . 'wp-admin/includes/class-wp-importer.php';
    if (file_exists( $class_wp_importer))
        require $class_wp_importer;
}

/**
 * Pixelpost Importer class
 *
 * @package PixelPost
 * @subpackage Importer
 */
if ( class_exists( 'WP_Importer' ) ) {
class PP_Import extends WP_Importer {
    const PPIMPORTER_PIXELPOST_OPTIONS = 'odyssey_pp2wp_pixelpost_importer_settings';
    const PPIMPORTER_PIXELPOST_SUBMIT  = 'odyssey_pp2wp_pixelpost_importer_submit';
    const PPIMPORTER_PIXELPOST_RESET   = 'odyssey_pp2wp_pixelpost_importer_reset';
   
    
    function header() {
        echo '<div class="wrap">';
        echo '<div id="icon-tools" class="icon32"><br></div>' . PHP_EOL;
        echo '<h2>'.__('Import Pixelpost').'</h2>';
    }

    function footer() {
        echo '</div>';
    }

    function get_pixelpost_default_settings() {
        $uploads = wp_upload_dir();
        $tmp_dir = $uploads['basedir'] . '/odyssey_pp2wp/';
        @mkdir($tmp_dir);
        return array(
            'dbuser'        => '',
            'dbpass'        => '',
            'dbname'        => '',
            'dbhost'        => 'localhost',
            'dbpre'         => 'pixelpost_',
            'ppurl'         => 'http://127.0.0.1/pixelpost/',
            'tmp_directory' => $tmp_dir,
            'image_size'    => 'full',
        );
    }
    function get_pixelpost_settings() {
        return get_option(self::PPIMPORTER_PIXELPOST_OPTIONS, $this->get_pixelpost_default_settings());
    }

    static function setting2Type($s) {
        return ($s == 'dbpass') ? 'password' : 'text';
    }
    static function setting2Label($s) {
         $s2l = array(
            'dbuser'        => __('Pixelpost Database User:'),
            'dbpass'        => __('Pixelpost Database Password:'),
            'dbname'        => __('Pixelpost Database Name:'),
            'dbhost'        => __('Pixelpost Database Host:'),
            'dbpre'         => __('Pixelpost Table Prefix:'),
            'ppurl'         => __('Pixelpost original url:'),
            'tmp_directory' => __('Directory for temp files:'),
            'image_size'    => __('Imported image size in post (thumbnail, medium, large or full):'),
        );
        return $s2l[$s];
    }

    function greet() {
        if ( isset( $_POST[self::PPIMPORTER_PIXELPOST_RESET] ) ) {
            delete_option(self::PPIMPORTER_PIXELPOST_OPTIONS);
        }
        $settings = $this->get_pixelpost_settings();
        if ( isset( $_POST[ self::PPIMPORTER_PIXELPOST_SUBMIT ] ) ) {
            unset( $_POST[ self::PPIMPORTER_PIXELPOST_SUBMIT ] );
            foreach ( $_POST as $name => $setting ) {
                $settings[$name] = $setting;
            }
            update_option(self::PPIMPORTER_PIXELPOST_OPTIONS, $settings);
        }

        echo '<p>' . __( 'This importer allows you to extract posts from a Pixelpost install into wordpress.' ) . '</p>';
        echo '<p>' . __( 'Please note that this improter has been developped for pixelpost 1.7.1 and wordpress 3.5.1. It may not work very well with other versions.' ) . '</p>';
        echo '<p>' . __( 'Your Pixelpost configuration settings are as follows:' ) . '</p>';

        echo '<form action="admin.php?import=pixelpost&amp;step=1" method="post">';
        echo '  <table class="form-table">';
        echo '    <tbody>';
        foreach ($settings as $name => &$setting) {
            echo '      <tr valign="top">';
            echo '        <th scope="row">';
            echo '          <label for="' . $name . '" name="' . $name . '" style="width: 300px; display: inline-block;">';
            echo             self::setting2Label($name);
            echo '          </label>';
            echo '        </th>';
            echo '        <td>';
            echo '          <input id="' . $name . '" name="' . $name . '" type="' . self::setting2Type($name) . '" value="' . $setting . '"  size="60" />';
            echo '        </td>';
            echo '      </tr>';
        }
        echo '    </tbody>';
        echo '  </table>';
        echo '  <p>';
        echo '    <input type="submit" name="' . self::PPIMPORTER_PIXELPOST_SUBMIT . '"  class="button button-primary" value="' . __( 'update settings' ) . '" />';
        echo '    <input type="submit" name="' . self::PPIMPORTER_PIXELPOST_RESET  . '"  class="button button-primary" value="' . __( 'reset settings' )  . '" />';
        echo '  </p>';
        echo '</form>';
    }
    
    function init() {
        $settings = $this->get_pixelpost_settings();
        $this->ppdb = new wpdb(
            $settings['dbuser'],
            $settings['dbpass'],
            $settings['dbname'],
            $settings['dbhost']
        );
        $this->ppdb->show_errors();
        set_magic_quotes_runtime(0);

        $this->prefix   = $settings['dbpre'];
        $this->ppurl    = $settings['ppurl'];
        $this->tmp_dir  = $settings['tmp_directory'];
        $this->img_size = $settings['image_size'];
    }

    function get_pp_db() {
        if ( ! isset($this->ppdb)) $this->init();
        return $this->ppdb;
    }

    function get_pp_config() {
        $ppdb = $this->get_pp_db();
        return $ppdb->get_results("SELECT * FROM {$this->prefix}config", ARRAY_A);
    }
    
    function get_pp_cats() {
        $ppdb = $this->get_pp_db();
        return $ppdb->get_results("SELECT id, name FROM {$this->prefix}categories", ARRAY_A);
    }
    
    function get_pp_postcat( $post_id ) {
        $ppdb = $this->get_pp_db();
        return $ppdb->get_results("SELECT cat.name 
                                    FROM {$this->prefix}pixelpost p
                                        INNER JOIN {$this->prefix}categories cat ON cat.id = p.category
                                    WHERE p.id = $post_id",
                                    ARRAY_A);
    }
    
    function get_pp_postcats( $post_id ) {
        $ppdb = $this->get_pp_db();
        $res = $ppdb->get_results("SELECT ca.cat_id
                                   FROM {$this->prefix}catassoc ca
                                    INNER JOIN {$this->prefix}categories c ON c.id = ca.cat_id
                                   WHERE ca.image_id = $post_id",
                                 ARRAY_A);
        $ret = array();
        foreach ($res as $r) {
             $ret[] = intval($r['cat_id']);
        }
        return $ret;
    }
    
    function get_pp_post_ids() {
        $ppdb = $this->get_pp_db();
        $pp_ids = $ppdb->get_results("SELECT id FROM {$this->prefix}pixelpost", ARRAY_A);
        $ret = array();
        foreach($pp_ids as $pp_id) {
            $ret[] = $pp_id['id'];
        }
        return $ret;
    }

    function get_pp_post_count() {
        $ppdb = $this->get_pp_db();
        $ret = $ppdb->get_results("SELECT count(id) as 'post_count' FROM {$this->prefix}pixelpost", ARRAY_A);
        if (is_array($ret)) {
            return $ret[0]['post_count'];
        } else {
            return 0;
        }
    }

    function get_pp_post_by_post_id($post_id) {
        $ppdb = $this->get_pp_db();
        $ret = $ppdb->get_results("SELECT
                                        id,
                                        datetime,
                                        headline,
                                        body,
                                        image
                                    FROM {$this->prefix}pixelpost
                                    WHERE id = '$post_id'
                                    ",
//                                     WHERE id=16
//                                     WHERE id=17
                                    ARRAY_A);
        if (is_array($ret)) {
            return $ret[0];
        } else {
            return 0;
        }
    }

    function get_pp_posts() {
        $ppdb = $this->get_pp_db();
        return $ppdb->get_results("SELECT
                                        id,
                                        datetime,
                                        headline,
                                        body,
                                        image
                                    FROM {$this->prefix}pixelpost
                                    ",
//                                     WHERE id=16
//                                     WHERE id=17
                                    ARRAY_A);
    }
    
    function get_pp_post_comment_count( $post_id ) {
        $ppdb = $this->get_pp_db();
        $ret = $ppdb->get_results("SELECT count(id) as 'comments_count' FROM {$this->prefix}comments WHERE parent_id = '$post_id'", ARRAY_A);
        if (is_array($ret)) {
            return $ret[0]['comments_count'];
        } else {
            return 0;
        }
    }
    
    function get_pp_comment_by_post_id( $post_id ) {
        $ppdb = $this->get_pp_db();
        return $ppdb->get_results("SELECT
                                        id,
                                        parent_id,
                                        datetime,
                                        ip,
                                        message,
                                        name,
                                        url,
                                        email
                                    FROM {$this->prefix}comments
                                    WHERE parent_id = '$post_id'
                                      AND publish = 'yes'
                                    ORDER BY datetime ASC",
                                    ARRAY_A);
    }

    function get_pp_comments() {
        $ppdb = $this->get_pp_db();
        return $ppdb->get_results("SELECT
                                        id,
                                        parent_id,
                                        datetime,
                                        ip,
                                        message,
                                        name,
                                        url,
                                        email
                                    FROM {$this->prefix}comments",
                                    ARRAY_A);
    }
    
    function build_category_tree( $categories ) {
        global $wpdb;
        $tree = array();
        foreach ( $categories as $category ) {
            $path = explode('/', $category['name']);
            $last = end($path);
            reset($path);
            $t = &$tree;
            foreach ( $path as $p ) {
                if ( ! isset( $t[ $p ] )) {
                    $name = $wpdb->escape($p);
                    $leaf = ($p == $last);
                    $t[$p] = array(
                        'id'       => $leaf ? $category['id'] : null,
                        'nicename' => str_replace(' ', '-', strtolower($name)),
                        'leaf'     => $leaf,
                        'sub'      => array(),
                    );
                } else {
                    $t[ $p ]['leaf'] &= ($p == $last);
                }
                $t = &$t[ $p ]['sub'];
            }
        }
        return $tree;
    }

    function insert_category_tree($ppCatTree, $parent_wp_cat_id = null) {
        $ppcat2wpcat = array();
        foreach ($ppCatTree as $name => $ppCat) {
            $params = array(
                'category_nicename' => $ppCat['nicename'],
                'cat_name'          => $name
            );
            if ($cinfo = category_exists($name)) {
                $params['cat_ID'] = $cinfo;
            }
            if ( ! is_null($parent_wp_cat_id)) {
                $params['category_parent'] = $parent_wp_cat_id;
            }

            $wp_cat_id = wp_insert_category($params);

            if ( ! is_null($ppCat['id'])) {
                $ppcat2wpcat[ $ppCat['id'] ] = $wp_cat_id;
            }
            if ( ! $ppCat['leaf']) {
                $ppcat2wpcat = $this->insert_category_tree($ppCat['sub'], $wp_cat_id) + $ppcat2wpcat;
            }
        }
        return $ppcat2wpcat;
    }
    
    function cat2wp($categories) {
        // General Housekeeping
        global $wpdb;
        $count          = 0;
        $txpcat2wpcat   = array();

        if (is_array($categories)) {
            $catTree = $this->build_category_tree($categories);
            $ppcat2wpcat = $this->insert_category_tree($catTree);

            // $tmpPpCats = $this->get_pp_cats();
            // $ppCat = array();
            // foreach ($tmpPpCats as $c) {
            // 	$ppCat[$c['id']] = $c['name'];
            // }
            // foreach ($ppcat2wpcat as $ppcatid => $wpcatid) {
            //     echo '"' . $ppCat[$ppcatid] . '" => "' . get_cat_name($wpcatid) . '"' . PHP_EOL;
            // }
            
            // Store category translation for future use
            update_option('ppcat2wpcat', $ppcat2wpcat);
            echo '<p>'.sprintf(__('Done! <strong>%1$s</strong> categories imported.'), count($ppcat2wpcat)).'<br /><br /></p>';
            return true;
        }
        echo __('No Categories to Import!');
        return false;
    }
    
    function pp_post2wp_post($pp_post_id) {
        global $wpdb;
        
//         $ppposts2wpposts = array();
        $ppcat2wpcat = get_option('ppcat2wpcat');

        // Let's assume the logged in user in the author of all imported posts
        $authorid = get_current_user_id();

        set_time_limit(0);
//         foreach($pp_posts as $pp_post) {
            $pp_post = $this->get_pp_post_by_post_id($pp_post_id);

            // retrieve this post categories ID
            $pp_categories = $this->get_pp_postcats($pp_post['id']);
            $wp_categories = array();
            foreach ($pp_categories as $pp_category) {
                $wp_categories[] = $ppcat2wpcat[$pp_category];
            }

            // let's insert the new post
            $wp_post_params = array(
                'comment_status' => 'open',
                'ping_status'    => 'open',
                'post_author'    => $authorid,
                'post_date'      => $pp_post['datetime'],
                'post_modified'  => $pp_post['datetime'],
                'post_content'   => '',
                'post_status'    => 'publish',
                'post_title'     => utf8_decode($pp_post['headline']),
                'post_category'  => $wp_categories,
            );
            $wp_post_id = wp_insert_post($wp_post_params, true);

            // download the post image ( !  may be troublesome on certain platforms!)
            $pp_image_url      = str_replace(' ', '%20', $this->ppurl . '/images/' . $pp_post['image']);
            $pp_image_tmp_file = $this->tmp_dir . '/' . $pp_post['image'];
            
            $response = wp_remote_get($pp_image_url, array('timeout' => 300, 'stream' => true, 'filename' => $pp_image_tmp_file));
            if (is_wp_error($response)) {
                var_dump($response);
                unlink($pp_image_tmp_file);
                return $response;
            }
            
            // Set variables for storage & fix file filename for query strings
            $file_array = array(
                'name'     => basename($pp_image_tmp_file),
                'tmp_name' => $pp_image_tmp_file,
            );

            // do the validation and storage stuff, note that the tmp file is moved, no need for unlink
            $wp_post_img_id = media_handle_sideload($file_array, $wp_post_id, $wp_post_params['post_title']);

            // update the newly inserted post with a link and a post to the image
            $img = wp_get_attachment_image($wp_post_img_id, $this->img_size);
            $url = '<a href ="' . wp_get_attachment_url($wp_post_img_id) . '">' . $img . '</a>';
            // Update the post into the database
            $wp_post_params['ID'] = $wp_post_id;
            $wp_post_params['post_content'] = $url . PHP_EOL . PHP_EOL . utf8_decode($pp_post['body']);
            wp_update_post($wp_post_params);

            // set post format to image
            set_post_format($wp_post_id, 'image');
            
            // get the comments bound to this post
            $pp_comments = $this->get_pp_comment_by_post_id($pp_post['id']);
            foreach ($pp_comments as $pp_comment) {
                $wpCommentParams = array(
                    'comment_post_ID'      => $wp_post_id,
                    'comment_author'       => utf8_decode($pp_comment['name']),
                    'comment_author_email' => $pp_comment['email'],
                    'comment_author_url'   => $pp_comment['url'],
                    'comment_content'      => html_entity_decode(utf8_decode($pp_comment['message'])),
                    'user_id'              => $authorid,
                    'comment_author_IP'    => $pp_comment['ip'],
                    'comment_agent'        => 'Import from PP',
                    'comment_date'         => $pp_comment['datetime'],
                    'comment_approved'     => true,
                );
                wp_insert_comment($wpCommentParams);
            }

            // keep a reference to this post
            $pp_posts2wp_posts[$pp_post['id']] = $wp_post_id;
//         }
        set_time_limit(30);

        // Store ID translation for later use
        update_option('odyssey_pp_posts2wp_posts_' . $pp_post_id, $wp_post_id);

        return true;    
    }
        
    function import_categories() {
        // Category Import
        $cats = $this->get_pp_cats();
        $this->cat2wp($cats);
        add_option('pp_cats', $cats);
    }
    
    function import_posts() {
        // Post Import
//         $posts = $this->get_pp_posts();
        echo '<p>' . sprintf(__('Retrieved %d posts from Pixelpost, importing...'), $this->get_pp_post_count()) . '</p>';
        js_getPPPosts();
//         $this->posts2wp($posts);

    }
    
    function cleanup_ppimport() {
        delete_option('pp_cats');
        delete_option('ppcat2wpcat');
        delete_option('ppposts2wpposts');
        delete_option('ppcm2wpcm');
    }
    
    function dispatch() {
        $this->header();

        if (isset ( $_POST[ self::PPIMPORTER_PIXELPOST_SUBMIT ] ) ||
            isset ( $_POST[ self::PPIMPORTER_PIXELPOST_RESET ] )  ||
            empty ( $_GET['step'] ) ) {
            $step = 0;
        } else {
            $step = intval ( $_GET ['step']);
        }

        switch ( $step ) {
            default:
            case 0 : $this->greet();             break;
            case 1 : $this->import_categories(); break;
            case 2 : $this->import_posts();      break;
            case 4 : $this->cleanup_ppimport();  break;
        }
        
        $step2Str = array(
            0 => __('Import Categories'),
            1 => __('Import Posts'),
            2 => __('Finish'),
        );

        if ( isset ( $step2Str[ $step ] ) ) {
            echo '<form action="admin.php?import=pixelpost&amp;step=' . ($step + 1) . '" method="post">';
            echo '  <input type="submit" name="submit" value="' . $step2Str[$step] . '" class="button button-primary" />';
            echo '</form>';
        }

        $this->footer();
    }
    
    
}

} // class_exists( 'WP_Importer' )

function pixelpost_importer_init() {
    //load_plugin_textdomain( 'pixelpost-importer', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    /**
     * WordPress Importer object for registering the import callback
     * @global WP_Import $wp_import
     */
    $GLOBALS['pp_import'] = new PP_Import();
    register_importer(
        'pixelpost',
        'PixelPost',
        __('Import <strong>posts, pages, comments, custom fields, categories, and tags</strong> pixelpost installation.', 'pixelpost-importer'),
        array($GLOBALS['pp_import'], 'dispatch')
    );
}
add_action('admin_init', 'pixelpost_importer_init');
