=== Bing404 ===
Contributors: Microsoft
Tags: 404, search, bing, Microsoft
Requires at least: 2.0
Tested up to: 2.9.2

Bing404 transforms your standard "We can't find that" to "Maybe one of these is
what you are looking for".


== Description ==
Using the Bing Search library, this plugin will intercept your standard 404 page
and return a list of urls that may help your user find the content they are
looking for. You can limit the search to just the content on your site, set a
default search in case the computed one doesn't return any results, and set a
host of other options, all designed to provide your users with quality
recomendations.

This plugin should work out-of-the-box with most themes using the included 404
template. You can also define your own to fine tune the look and feel to match
your theme.


== Installation ==

Upload the plugin to your blog, Activate it, then enter your Bing Application
Id (http://www.bing.com/developers/createapp.aspx).

If you want to use the plugin in your theme's 404.php, add the line:

<?php if ( function_exists( 'bing404_search_bing' ) ) { echo bing404_search_bing(); }?>

Where you want the results to show.


== Changelog ==
= 1.0.1 =
* Updated the Msft Library to include missing Exceptions

= 1.0 =
* Initial public release
