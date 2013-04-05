Pixelpost Importer plugin for wordpress
================================

Description
-----------

Set up your pixelpost database info, and let it work for a while. It’ll import categories, posts and comments. It’ll left a new table in the database, used by the provided index.php to keep the old link alive, by redirecting them to the new uri.

Imported posts are refered as "image" in wordpress, the image attached to the imported post. A "more" separator is inserted between the image and the post content.

Disclaimer
----------
This script is delivered as-is, with no warantee it works. As usual, please be responsible and do yourself a favor. Prior to launching the importation process, do a backup of your pixelpost set up and your WordPress as well if the latter is not a brand new installation.

How to Install
--------------

 * unzip the importer directory in wp-content/plugins
 * in WP admin interface, go to plugins>Installed, and activate the plugin "Pixelpost"

How to use
----------

 * in WP admin interface, go to Tools>Importer
 * Click on Pixelpost, then set up the pixelpost database settings (in pixelpost.php).
 * Click on "import categories", then click on "import posts". Depending on the number of posts in you pixelpost set up, this may take long (around 30 to 40 min in my case, I have around 850 posts)

Redirection
-----------

Copy the provided index.php in the directory your previous pixelpost installation was. Replace the directory in require_once with yours. It must point to the WordPress installation.

Support
-------
This plugin has been tested with the following version of Wordpress:
 * WordPress 3.5
 * WordPress 3.5.1
 * WordPress 3.6 beta 1

About bug report
----------------

If you find a bug in this importer, please E-mail me ((<URL:mailto:pierre.bodilis+github@gmail.com>)).

License
-------
GPL version 2 or later - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
