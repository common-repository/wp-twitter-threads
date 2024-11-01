=== WP Twitter Threads ===
Contributors: xuxu.fr
Donate link: https://www.paypal.com/paypalme/kzukzu
Tags: plugin, twitter, thread
Requires at least: 4.8
Tested up to: 5.8
Requires PHP: 5.6
Stable tag: 1.1.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Turn Twitter Threads into WordPress Post

== Description ==

*   Create Twitter API key & secret at https://developer.twitter.com.
*   Put them in WPTT admin page and validate.
*   Copy the URL of the first tweet of your Twitter Thread.
*   Paste this one in the dedicated field of the WordPress Post Edit page.
*   Submit.

That's all.

You've just import all the tweets of the thread as a post.

Page dedicated to this plugin : https://www.wp-tw-threads.com

You can contact me :

*   My blog: https://xuxu.fr/contact
*   My website : https://www.xuan-nguyen.fr
*   My Twitter account:  https://twitter.com/xuxu

== Installation ==

1. Extract and upload the directory `wp-twitter-threads` to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.

== Upgrade Notice ==

Nothing to do right now.

== Frequently Asked Questions ==

= Why the plugin does not retrieve my thread ? =

Twitter API Prevent us to get more than the 3200 last tweets in one request. If you wanna fetch too old threads, it will not be possible in one click.
You can try to fetch each tweet of the thread one by one.

== Screenshots ==

1. get your Twitter API Consumer Key and Secret
2. set them in the WP TW Threads Settings
3. copy the URL of the first Tweet of the Thread
4. paste it in the field of the meta dedicated in your post edit page
5. that's all!
6. your post published
7. if you got hashtags, in your tweets, post tags will be created
8. images, and Retweet are embed
9. video (and gif) are embed too
10. it works with Classical Editor

== Changelog ==

= 1.1.0 =
* add Gutenberg Block to add a Twitter Thread
* remove metabox in Gutenberg mode to add a Twitter Thread
* add rewrite rules management when permalink is in simple mode
* css & js minified
* webpack config for development & deployment
* minor fixes

= 1.0 =
* first Release.
