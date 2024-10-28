=== BCS BatchLine Book Importer ===
Contributors: bcsstudio
Tags: bertline, batchline, import
Requires at least: 5.6
Tested up to: 6.5.5
Stable tag: 1.6.12
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Import books to Woocommerce from BatchLine's web exporter (BatchLine subscription required) and load the book images from external url.

== Description ==

This plugin will import the xml from BatchLine's web exporter (BatchLine subscription required) and display images from external url.

View [live demo](https://hillsidebooks.bcs-hosting.net/).

Tested with Twenty Twenty-One and Woocommerce Storefront Themes.

Some themes may require tweaks to display the biblio data and images. Please [get in touch](https://bcs-studio.com/) if we can assist with this.

**Features:**

* Import books from BatchLine - manual and automatic uploads
* Automatically display images from external URL

== Installation ==

= Minimum Requirements =

* BatchLine subscription required.

* PHP 7.2 or greater is recommended

= Automatic installation =

Automatic installation is the easiest option -- WordPress will handles the file transfer, and you won’t need to leave your web browser. To do an automatic install of BCS BatchLine Book Importer, log in to your WordPress dashboard, navigate to the Plugins menu, and click “Add New.”

In the search field type "BCS BatchLine Book Importer" then click "Search Plugins". Once you've found the plugin, you can view details about and  install it by click "Install Now" and WordPress will take it from there.

= Manual installation =

1. Unzip the downloaded zip file.
2. Upload the plugin folder into the 'wp-content/plugins/' directory of your WordPress site.
3. Activate 'BCS BatchLine Book Importer' from Plugins page

== Frequently Asked Questions ==

= Is the plugin compatible with all themes and plugins? =

Compatibility with all themes and plugins is impossible, because there are too many. Some themes and plugins change how images are loaded.

= Biblio data is not showing on product page =

Some themes change the way attributes are displayed on the product page. Please try the official [Woocommerce Store Front theme](https://woocommerce.com/storefront/). If you would like assistance displaying this on your own theme please [get in touch](https://bcs-studio.com/).

= Book images are not loading =

Please check for conflicts with other plugins/theme. Please try the official [Woocommerce Store Front theme](https://woocommerce.com/storefront/). If you would like assistance displaying images on your own theme please [get in touch](https://bcs-studio.com/).

= Create or manage API keys =

- Go to: <strong>WooCommerce > Settings > Advanced > REST API</strong>.<br/>
- Select <strong>Add Key</strong>. You are taken to the <strong>Key Details</strong> screen.<br/>
- Add a <strong>Description</strong>.<br/>
- Select the <strong>User</strong> you would like to generate a key for in the dropdown.<br/>
- Select a level of access for this API key — <strong>Read/Write</strong> access.<br/>
- Select <strong>Generate API Key</strong>, and WooCommerce creates API keys for that user.<br/>
- Now that keys have been generated, you should see your <strong>Consumer Key</strong> and <strong>Consumer Secret</strong> keys.

= License Keys =

- Go to: <a href="https://servicedesk.bcs.solutions/index.php?rp=/store/wordpress-plugins">https://servicedesk.bcs.solutions/index.php?rp=/store/wordpress-plugins</a> to purchase a new license key.<br/>

= How do I manage sale prices on Woocommerce =

If a sale price is set on Woocommerce you should add the product to the Exlude list on BatchLine to prevent the price being changed on the next import.

= Enable Basic Authentication on Apache Server =

Step 1: .htaccess file add

RewriteEngine On
RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

Step 2: go to WHM Panel and go to:

Home » Service Configuration » Apache Configuration » Include Editor » Pre VirtualHost Include » All Version 

Add this line

SetEnvIf Authorization "(.*)" HTTP_AUTHORIZATION=$1

and Restart Apache.

== Screenshots ==

1. Upload screen
2. Import screen

== Changelog ==
= 1.6.12 =
* Fix warning when import log empty
* Fix fatal error when displaying log entries

= 1.6.11 =
* Fix bug

= 1.6.10 =
* Add import log

= 1.6.9 =
* Fix version number

= 1.6.8 =
* Bug fixes

= 1.6.7 =
* Bug fixes

= 1.6.6 =
* Bufix - stop JellyBook requests if no SKU

= 1.6.5 =
* Bufix

= 1.6.4 =
* Backorders notify by default if option checked

= 1.6.3 =
* Bug fixes

= 1.6.2 =
* Bug fixes

= 1.6.1 =
* Readme changes

= 1.6 =
* Add Peek Inside functionality

= 1.5.10 =
* Update stock only: Only update stock, stock code and price. New option on settings page.
* Don't schedule future dates: all products uploaded are now published, even with future date set, if published date is after today’s date show a Pre-order message on product page.
* New Full: new full import will delete all products before uploading new products.


= 1.5.9 =
* Rename functions

= 1.5.8 =
* Security fix

= 1.5.7 =
* Bug fix

= 1.5.6 =
* Bug fix

= 1.5.5 =
* Bug fix

= 1.5.4 =
* Help page changes

= 1.5.3 =
* Readme changes

= 1.5.2 =
* Readme changes

= 1.5.1 =
* Add automatic uploads

= 1.4.2 =
* Load Storefront theme pagination images from biblio data

= 1.4.1 =
* Updated readme.txt

= 1.4 =
* Ajax import

= 1.3 =
* Updated readme.txt

= 1.2 =
* Updated readme.txt

= 1.1 =
* Allow xml upload

== Upgrade Notice ==

= 1.1 =
Upgrade to allow XML upload