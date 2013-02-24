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

if ( ! defined( 'WP_LOAD_IMPORTERS' ) )
	return;

/** Display verbose errors */
define( 'IMPORT_DEBUG', false );

// Load Importer API
require_once ABSPATH . 'wp-admin/includes/import.php';

if ( ! class_exists( 'WP_Importer' ) ) {
	$class_wp_importer = ABSPATH . 'wp-admin/includes/class-wp-importer.php';
	if ( file_exists( $class_wp_importer ) )
		require $class_wp_importer;
}

/**
    Add These Functions to make our lives easier
**/
if(!function_exists('get_catbynicename')) {
    function get_catbyname($category_name) 
    {
        global $wpdb;
        
        $cat_id -= 0;   // force numeric
        $name = $wpdb->get_var('SELECT cat_ID FROM '.$wpdb->categories.' WHERE cat_name="'.$category_name.'"');
        
        return $name;
    }
}

if(!function_exists('get_comment_count')) {
    function get_comment_count($postId)
    {
        global $wpdb;
        return $wpdb->get_var('SELECT count(*) FROM '.$wpdb->comments.' WHERE comment_post_ID = '.$postId);
    }
}

if(!function_exists('link_exists')) {
    function link_exists($linkname)
    {
        global $wpdb;
        return $wpdb->get_var('SELECT link_id FROM '.$wpdb->links.' WHERE link_name = "'.$wpdb->escape($linkname).'"');
    }
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
   
    
    function header() 
    {
        echo '<div class="wrap">';
        echo '<h2>'.__('Import Pixelpost').'</h2>';
    }

    function footer() 
    {
        echo '</div>';
    }

    function getPixelpostDefaultSettings()
    {
        return array(
            'dbuser'      => array('label' => __('Pixelpost Database User:'),     'type' => 'text',     'value' => ''),
            'dbpass'      => array('label' => __('Pixelpost Database Password:'), 'type' => 'password', 'value' => ''),
            'dbname'      => array('label' => __('Pixelpost Database Name:'),     'type' => 'text',     'value' => ''),
            'dbhost'      => array('label' => __('Pixelpost Database Host:'),     'type' => 'text',     'value' => 'localhost'),
            'dbpre'       => array('label' => __('Pixelpost Table Prefix:'),      'type' => 'text',     'value' => 'pixelpost_'),
            'ppurl'       => array('label' => __('Pixelpost original url:'),      'type' => 'text',     'value' => 'http://127.0.0.1/pixelpost/'),

            'finaldomain' => array('label' => __('Final Domain Name for Wordpress Install:'), 'type' => 'text',     'value' => 'www.mydomainname.com'),
        );
    }
    function getPixelpostSettings()
    {
        return get_option(self::PPIMPORTER_PIXELPOST_OPTIONS, $this->getPixelpostDefaultSettings());
    }

    function greet() 
    {
        if (isset($_POST[self::PPIMPORTER_PIXELPOST_RESET])) {
            delete_option(self::PPIMPORTER_PIXELPOST_OPTIONS);
        }
        $settings = $this->getPixelpostSettings();
        if (isset($_POST[self::PPIMPORTER_PIXELPOST_SUBMIT])) {
            $doUpdate = false;
            foreach ($settings as $name => &$setting) {
                if (isset($_POST[$name])) {
                    $setting['value'] = $_POST[$name];
                }
            }
            update_option(self::PPIMPORTER_PIXELPOST_OPTIONS, $settings);
        }

        echo '<p>'.__('Howdy! This importer allows you to extract posts from a Pixelpost install 1.4.2 into your blog.').'</p>';
        echo '<p>'.__('Your Pixelpost configuration settings are as follows:').'</p>';

        echo '<form action="admin.php?import=pixelpost&amp;step=1" method="post">';
        echo '  <table class="form-table">';
        echo '    <tbody>';
        foreach ($settings as $name => &$setting) {
            echo '      <tr valign="top">';
            echo '        <th scope="row"><label for="' . $name . '" name="' . $name . '" style="width: 300px; display: inline-block;">' . $setting['label'] . '</label></th>';
            echo '        <td>';
            echo '          <input id="' . $name . '" name="' . $name . '" type="' . $setting['type'] . '" value="' . $setting['value'] . '"  size="60" />';
            echo '        </td>';
            echo '      </tr>';
        }
        echo '    </tbody>';
        echo '  </table>';
        echo '  <p>';
        echo '    <input type="submit" name="' . self::PPIMPORTER_PIXELPOST_SUBMIT . '"  class="button button-primary" value="' . __('update settings') . '" />';
        echo '  </p>';
        echo '  <p>';
        echo '    <input type="submit" name="' . self::PPIMPORTER_PIXELPOST_RESET . '"  class="button button-primary" value="' . __('reset settings') . '" />';
        echo '  </p>';
        echo '</form>';
    }
    
    function getPPDB()
    {
        if (isset($this->ppdb)) return $this->ppdb;
        
        $settings = $this->getPixelpostSettings();
        $this->ppdb = new wpdb(
            $settings['dbuser']['value'],
            $settings['dbpass']['value'],
            $settings['dbname']['value'],
            $settings['dbhost']['value']
        );
        $this->ppdb->show_errors();
        set_magic_quotes_runtime(0);

        $this->prefix = $settings['dbpre']['value'];
        $this->ppurl  = $settings['ppurl']['value'];
        return $this->ppdb;
    }

    function get_pp_config()
    {
        $ppdb = $this->getPPDB();
        return $ppdb->get_results("SELECT * FROM {$this->prefix}config", ARRAY_A);
    }
    
    function get_pp_cats()
    {
        $ppdb = $this->getPPDB();
        return $ppdb->get_results("SELECT id, name FROM {$this->prefix}categories", ARRAY_A);
    }
    
    function get_pp_postcat($postId)
    {
        $ppdb = $this->getPPDB();
        return $ppdb->get_results("SELECT cat.name 
                                    FROM {$this->prefix}pixelpost p
                                        INNER JOIN {$this->prefix}categories cat ON cat.id = p.category
                                    WHERE p.id = $postId",
                                    ARRAY_A);
    }
    
    function get_pp_postcats($postId)
    {
        $ppdb = $this->getPPDB();
        $res = $ppdb->get_results("SELECT ca.cat_id
                                   FROM {$this->prefix}catassoc ca
                                    INNER JOIN {$this->prefix}categories c ON c.id = ca.cat_id
                                   WHERE ca.image_id = $postId", 
                                 ARRAY_A);
        $ret = array();
        foreach ($res as $r) {
             $ret[] = intval($r['cat_id']);
        }
        return $ret;
    }
    
    function get_pp_posts()
    {
        $ppdb = $this->getPPDB();
        return $ppdb->get_results("SELECT 
                                        id,
                                        datetime,
                                        headline,
                                        body,
                                        image
                                    FROM {$this->prefix}pixelpost
                                    WHERE id=552
                                    ",
                                    ARRAY_A);
    }
    
    function get_pp_post_comment_count($postId)
    {
        $ppdb = $this->getPPDB();
        $ret = $ppdb->get_results("SELECT count(id) as 'comments_count' FROM {$this->prefix}comments WHERE parent_id = '$postId'", ARRAY_A);
        if (is_array($ret)) {
            return $ret[0]['comments_count'];
        } else {
            return 0;
        }
    }
    
    function get_pp_comments()
    {
        $ppdb = $this->getPPDB();
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
    
    function buildCategoryTree($categories)
    {
        global $wpdb;
        $tree = array();
        foreach ($categories as $category) {
            $path = explode('/', $category['name']);
            $last = end($path);
            reset($path);
            $t = &$tree;
            foreach ($path as $p) {
                if (!isset($t[$p])) {
                    $name = $wpdb->escape($p);
                    $leaf = ($p == $last);
                    $t[$p] = array(
                        'id'       => $leaf ? $category['id'] : null,
                        'nicename' => str_replace(' ', '-', strtolower($name)),
                        'leaf'     => $leaf,
                        'sub'      => array(),
                    );
                } else {
                    $t[$p]['leaf'] &= ($p == $last);
                }
                $t = &$t[$p]['sub'];
            }
        }
        return $tree;
    }

    function insertCategoryTree($ppCatTree, $parentWpCatId = null)
    {
        $ppcat2wpcat = array();
        foreach ($ppCatTree as $name => $ppCat) {
            $params = array(
                'category_nicename' => $ppCat['nicename'],
                'cat_name'          => $name
            );
            if ($cinfo = category_exists($name)) {
                $params['cat_ID'] = $cinfo;
            }
            if (!is_null($parentWpCatId)) {
                $params['category_parent'] = $parentWpCatId;
            }

            $wpCatId = wp_insert_category($params);

            if (!is_null($ppCat['id'])) {
                $ppcat2wpcat[$ppCat['id']] = $wpCatId;
            }
            if (!$ppCat['leaf']) {
                $ppcat2wpcat = $this->insertCategoryTree($ppCat['sub'], $wpCatId) + $ppcat2wpcat;
            }
        }
        return $ppcat2wpcat;
    }
    
    function cat2wp($categories) 
    {
        // General Housekeeping
        global $wpdb;
        $count          = 0;
        $txpcat2wpcat   = array();

        if (is_array($categories)) {
            $catTree = $this->buildCategoryTree($categories);
            $ppcat2wpcat = $this->insertCategoryTree($catTree);

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
    
    function posts2wp($ppPosts)
    {
        global $wpdb;
        
        $ppposts2wpposts        = array();
        $ppcat2wpcat = get_option('ppcat2wpcat', $ppcat2wpcat);

        if (!is_array($ppPosts)) {
            return false;
        }

        // Let's assume the logged in user in the author of all imported posts
        $authorid = get_current_user_id();

        echo '<p>' . __('Importing Posts...') . '<br /><br /></p>';
        set_time_limit(0);
$i =0;
        foreach($ppPosts as $ppPost) {
if (++$i>5) break;
//            $cc = $this->get_pp_post_comment_count($ppPost->id);

            // retrieve this post categories ID
            $ppCategories = $this->get_pp_postcats($ppPost['id']);
            $wpCategories = array();
            foreach ($ppCategories as $ppCategory) {
                $wpCategories[] = $ppcat2wpcat[$ppCategory];
            }

            // let's insert the new post
            $wpPostParams = array(
                'comment_status' => 'open',
                'ping_status'    => 'open',
                'post_author'    => $authorid,
                'post_date'      => $ppPost['datetime'],
                'post_modified'  => $ppPost['datetime'],
                'post_content'   => '',
                'post_status'    => 'publish',
                'post_title'     => utf8_decode($ppPost['headline']),
                'post_category'  => $wpCategories,
            );
            $wpPostId = wp_insert_post($wpPostParams, true);

            // upload the post image (! may be troublesome on certain platforms!)
            $ppImageUrl = $this->ppurl . '/images/' . $ppPost['image'];
            $tmp = download_url($ppImageUrl);
            $ret = media_sideload_image($ppImageUrl, $wpPostId, $ppPost['headline']);

            // update the newly inserted post with a link and a post to the image
            // TODO: make image size configurable
            // first, get the url/img to the attached image
            $args = array(
                'numberposts'    => 1,
                'order'          => 'ASC',
                'post_mime_type' => 'image',
                'post_parent'    => $wpPostId,
                'post_type'      => 'attachment'
            );
            $attachment = current(get_children($args, ARRAY_A));
            $img = wp_get_attachment_image($attachment['ID'], 'full');
            $url = '<a href ="' . wp_get_attachment_url($attachment['ID']) . '">' . $img . '</a>';
            // Update the post into the database
            $wpPostParams['ID'] = $wpPostId;
            $wpPostParams['post_content'] = $url . PHP_EOL . PHP_EOL . utf8_decode($ppPost['body']);
            wp_update_post($wpPostParams);

            set_post_format($wpPostId, 'image');

            // keep a reference to this post
            $ppposts2wpposts[$ppPost['id']] = $wpPostId;
        }
        set_time_limit(30);

        // Store ID translation for later use
        update_option('ppposts2wpposts', $ppposts2wpposts);
        
        echo '<p>'.sprintf(__('Done! <strong>%1$s</strong> posts imported.'), $count).'<br /><br /></p>';
        return true;    
    }
    
    function comments2wp($comments='')
    {
        // General Housekeeping
        global $wpdb;
        $count          = 0;
        $ppcm2wpcm      = array();
        $postarr        = get_option('ppposts2wpposts');
        
        // Magic Mojo
        if(is_array($comments))
        {
            echo '<p>'.__('Importing Comments...').'<br /><br /></p>';
            foreach($comments as $comment)
            {
                $count++;
                extract($comment);
                
                // WordPressify Data
                $comment_post_ID    = $postarr[$parent_id];
                $web                = $url;
                
                
                if($cinfo = comment_exists($name, $datetime))
                {
                    // Update comments
                    $ret_id = wp_update_comment(array(
                            'comment_ID'            => $cinfo,
                            'comment_post_ID'       => $comment_post_ID,
                            'comment_author'        => $name,
                            'comment_author_email'  => $email,
                            'comment_author_url'    => $web,
                            'comment_date'          => $datetime,
                            'comment_content'       => $message,
                            'comment_approved'      => '1')
                            );
                }
                else 
                {
                    // Insert comments
                    $ret_id = wp_insert_comment(array(
                            'comment_post_ID'       => $comment_post_ID,
                            'comment_author'        => $name,
                            'comment_author_email'  => $email,
                            'comment_author_url'    => $web,
                            'comment_author_IP'     => $ip,
                            'comment_date'          => $datetime,
                            'comment_content'       => $message,
                            'comment_approved'      => '1')
                            );
                }
                $pppcm2wpcm[$id] = $ret_id;
            }
            // Store Comment ID translation for future use
            add_option('pppcm2wpcm', $pppcm2wpcm);          
            
            // Associate newly formed categories with posts
            get_comment_count($ret_id);
            
            
            echo '<p>'.sprintf(__('Done! <strong>%1$s</strong> comments imported.'), $count).'<br /><br /></p>';
            return true;
        }
        echo __('No Comments to Import!');
        return false;
    }
    
    function import_categories() 
    {   
        // Category Import  
        $cats = $this->get_pp_cats();
        $this->cat2wp($cats);
        add_option('pp_cats', $cats);
    }
    
    function import_posts()
    {
        // Post Import
        $posts = $this->get_pp_posts();
        echo sprintf(__('Retrieved %d posts from Pixelpost, importing...'), count($posts));
        $this->posts2wp($posts);
    }
    
    function import_comments()
    {
        // Comment Import
        $comments = $this->get_pp_comments();
        $this->comments2wp($comments);
    }
    
    function cleanup_ppimport()
    {
        delete_option('tpre');
        delete_option('pp_cats');
        delete_option('ppcat2wpcat');
        delete_option('ppposts2wpposts');
        delete_option('ppcm2wpcm');
        delete_option('ppuser');
        delete_option('pppass');
        delete_option('ppname');
        delete_option('pphost');
    }
    
    function dispatch() 
    {
        $this->header();

        if (isset($_POST[self::PPIMPORTER_PIXELPOST_SUBMIT]) ||
            isset($_POST[self::PPIMPORTER_PIXELPOST_RESET])  ||
            empty($_GET['step'])) {
            $step = 0;
        } else {
            $step = intval($_GET['step']);
        }

        switch ($step) {
            default:
            case 0 : $this->greet();             break;
            case 1 : $this->import_categories(); break;
            case 2 : $this->import_posts();      break;
            case 3 : $this->import_comments();   break;
            case 4 : $this->cleanup_ppimport();  break;
        }
        
        $step2Str = array(
            0 => __('Import Categories'),
            1 => __('Import Posts'),
            2 => __('Import Comments'),
            3 => __('Finish'),
        );

        if (isset($step2Str[$step])) {
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
	    array( $GLOBALS['pp_import'], 'dispatch')
    );
}
add_action( 'admin_init', 'pixelpost_importer_init' );
