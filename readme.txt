=== Export Posts ===
Contributors: jboydston, dkukral, Droyal
Donate link: http://joeboydston.com/export-posts
Tags: export, text, xml, quark, indesign
Requires at least: 2.9.1
Tested up to: 3.0.1
Stable Tag: 1.5.2

Plugin for WordPress that exports text files for print publication.

== Description ==

Used to select posts from a Wordpress site, and export them as text files.

== License ==
Copyright 2010 - 2012 Joe Boydston, Don Kukral

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

== Installation ==

Copy "export-posts" folder to your WordPress Plugins Directory. Activate plugin via WordPress settings.

== Other Notes ==

Requires PHP zip and iconv modules.

== Screenshots ==

None.

== Changelog ==

= 1.5.2 = 
Limiting post list to only published posts

= 1.5.1 =
style fix

= 1.5 = 
Fixed caching problem with tags - call to clean_post_cache()

= 1.3.3 =
Typo fix

= 1.3.2 =
Added new XML export options (comment count, e-section quote, short link)

= 1.3.1 =
Added total inches to selected stories

= 1.3 =
Added export-posts-date meta field to exported posts

= 1.2.1 =
Changed word count to inch count on export-posts page
Integrated with print-tags plugin

= 1.2 =
Added license and copyright information
Added code to integrate with print-tags plugin
Added word count and column inches to bottom of post (requires column-inches plugin) 

= 1.1.1 = 
Fixed ampersands in XML export
Added number of posts to display in settings page and export page

= 1.0.1 =
Code cleanup
New post selection interface
Export formats

= 1.0 = 
Initial version

== Frequently Asked Questions ==

= Why am I getting errors about wp-content/uploads directory? =

Make sure the uploads directory exists in your wp-content directory and the user the web server runs as has write permissions.


== Upgrade Notice ==

None.
