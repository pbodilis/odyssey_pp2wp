=== Pixelpost Importer plugin for WordPress ===
Contributors: Pierre Bodilis
Donate link: 
Tags: pixelpost, importer
Requires at least: 3.3
Tested up to: 3.5.1
Stable tag: 
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Import your PixelPost database in WordPress.

== Description ==

Set up your pixelpost database info, and let it work for a while. It’ll import categories, posts and comments. It’ll left a new table in the database, used by the provided index.php to keep the old link alive, by redirecting them to the new uri.

Imported posts are imported as posts with an "image" format in wordpress, the image attached to the imported post. A "more" separator is inserted between the image and the post content.

How to use:
1. in WP admin interface, go to Tools>Importer
1. Click on Pixelpost, then set up the pixelpost database settings (in pixelpost.php).
1. Click on "import categories", then click on "import posts". Depending on the number of posts in you pixelpost set up, this may take long (around 30 to 40 min in my case, I have around 850 posts)

== Installation ==

1. Upload `pp2wp_importer` to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress

== Frequently asked questions ==

= What exactly are imported ? =

Categories, Posts, and Comments. Tags are not supported in this version.

== Screenshots ==

1. Set up the pixelpost database information.

== Changelog ==



== Upgrade notice ==



== Redirection ==

This plugin provides are redirection script that matches old the PixelPost links to their counterparts in WordPress.