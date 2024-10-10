=== Web Librarian ===
Contributors: RobertPHeller, psykro
Donate link: http://www.deepsoft.com/home/products/web-librarian/
Tags: widget,shortcode,library,circulation
Requires at least: 3.2.1
Tested up to: 6.6.2
Stable tag: 3.5.8.4
License: GPL2

A WordPress plugin that implements a basic library collection and
circulation system.

== Description ==

This WordPress plugin started as a portable, cross-platform system that
the Wendell Free Library could use as a transition system from its current
paper card based circulation system to the system that will eventually
be rolled out by the regional library system.  It has 'morphed' to a
web-based successor to Deepwoods Software's Home Librarian 3 system.

This plugin implements a simple and basic, web-based, library catalog
and circulation system.  There are short codes that can be added to
pages of a WordPress site to search and display items in the library
collection.  And there are back-end (admin) pages that implement
management of the collection, management of patrons (users) of the
library, as well as implementing the functionality of a  circulation
desk. 

== Installation ==

The plugin can be installed by uploading the Zip file to the plugin
installer or unpacking the zip file under the plugins directory.

There are some options that can be set, but these options are not needed
for basic operation.  There are three short codes that can be added to
pages or posts for front end searching and display of your library
collection and there are a number of back-end (dashboard) pages for all
of the management of your library.

Please   read  the  [PDF  User   Manual][usermanual]   ([also   available   in
Italian][usermanualIT])  for complete documentation on using this plugin. This
is a fairly  non-trivial  plugin, and there is not a simple  quick-start guide
for the  impatient.  The user  manual  files are  1548805  bytes  long for the
Italian  version  and  1548666  for the  Englist  version  and [MD5  sums  are
available][usermanualmd5sums].  If you are having  trouble  opening  the PDFs,
please  check the size and the MD5Sum.  If the size is right and the MD5Sum is
right, try another PDF viewer.

[usermanual]: http://plugins.svn.wordpress.org/weblibrarian/assets/user_manual/user_manual.pdf "User Manual (English)"
[usermanualIT]: http://plugins.svn.wordpress.org/weblibrarian/assets/user_manual/user_manual_IT.pdf "User Manual (Italian)"
[usermanualmd5sums]: http://plugins.svn.wordpress.org/weblibrarian/assets/user_manual/md5sum.txt

== Frequently Asked Questions ==

Support is handled either with [Deepwoods Software's Bugzilla][bugreport]
(submit any bugs and feature requests to the Bugzilla) or though the
[Deepwoods Software's Support page][support] (use this for  comments or
for general questions).

= Where are the admin menus? =

You've successfully installed the Web Librarian, but none of the admin
menus (Patrons, Collection, Circulation Types, Circulation Desk, or
Circulation Stats) show up. Why is this? This is because you are
probably logged in as the web site administrator (your user role is
Administrator).  You need to create at least a user with a user role of
Librarian and then log in as this user.  Optionally, you can also
create users with roles of Senior Aid or Volunteer, who have lesser
privileges -- these latter users make sense if you are a large enough
library that has additional staff ("Senior Aid") or uses additional
workers ("Volunteer") who man the circulation desk(s).  It is important
to read the subsection titled "User Role Setup" in the "Installation and
basic setup" section *carefully* and to be sure you understand it fully.

= Which stylesheet (CSS) selectors can I use to modify the appearance of the front end? =

This is described in the appendix of the user manual.

= I am having a problem with the bulk upload. =

Here are some tips relating to problems with bulk uploading a collection:

If you export a CSV file from Excel (and probably  other 'modern'  spreadsheet
programs as well), you should check the resulting CSV file with a *plain text*
editor.  Things  to  look  for  include  extra  commas  at the  ends of  lines
('phantom'  or empty  columns)  and strange  characters,  partitularly  in the
headings and in barcodes (if you using your own barcodes).

Other  issues  involving  issues  with  newline  characters.  Most of the time
WordPress will be running on a LAMP server (a Linux  machine).  Linux uses the
linefeed character (ASCII 10, Ctrl-j) as newline character.  MS-Windows uses a
two  character  sequence  for  newlines:  carriage  return  (ASCII 13, Ctrl-m)
followed  by a  linefeed  (ASCII  10,  Ctrl-j).  MacOSX  uses just a  carriage
return (ASCII 13, Ctrl-m).  Web browsers are *supposed* to normalize uploaded
plain text files, but  sometimes  this does not happen.  It might be necessary
to fix things in advance of uploading.

Finally, try your upload in *small* chunks, at least until you get the process
worked out. Remember  that most  WordPress  (PHP) installs have a limit on the
maximum size of uploaded files, so a really large database is going to have be
uploaded in chunks anyway.

= How do I check an item out? =

In order to check an item out, you need to load a patron record into the 
circulation desk page.  You can use the "Find Patron" button to search the 
patrons by name and then  select the proper  result  from the list of results 
in the drop down and then click the "Lookup Patron" to load that patron's
record.  You can then enter the item's barcode in the item barcode field and
click the "Checkout" button.  It is possible to pre-load the item barcode
field before looking up the patron.

= Search does not work - sends me back to my home page =

This is caused by a conflict with  permalinks  and form handling.  Answered in
the support forum on the WordPress site:

https://wordpress.org/support/topic/search-doesnt-work-7

  I'd suggest this solution:
  
  Change your site's permalink settings: on the dashboard select 
  Settings->permalinks, then select something other than the 'default'.
    
= I cannot open the user manual, it appears to be corrupt =

Please check the size of the PDF (1548805 for  user_manual_IT.pdf  and 1548666
for user_manual.pdf) and also check the [MD5  sum][usermanualmd5sums]  to make
sure you properly downloaded the file.  The correct download urls are:

User Manual (English):
http://plugins.svn.wordpress.org/weblibrarian/assets/user_manual/user_manual.pdf

User Manual (Italian):
http://plugins.svn.wordpress.org/weblibrarian/assets/user_manual/user_manual_IT.pdf

[usermanualmd5sums]: http://plugins.svn.wordpress.org/weblibrarian/assets/user_manual/md5sum.txt

= Something does not work. What should I do? =

Submit a bug at [Deepwoods Software's Bugzilla][bugreport].

= I have another question that is not listed here. What should I do? =

Submit one on [Deepwoods Software's Support page][support]. You can
also submit a documentation bug at [Deepwoods Software's Bugzilla][bugreport]
as well.

[bugreport]: http://bugzilla.deepsoft.com/enter_bug.cgi?product=Web%20Librarian "Deepwoods Software Bugzilla"
[support]: http://www.deepsoft.com/support/ "Deepwoods Software's Support page"


== Screenshots ==

No Screenshots yet.

== Changelog ==

= 3.5.8.4 =

Sanitize and Escape everything.

= 3.5.8.3 =

Minor fix to Export code (better checking).

= 3.5.8.2 =

Fix security issue:
https://patchstack.com/database/report-preview/b626e7c0-9a3e-48d5-a5ad-5cbf33d16b2e?pin=tyLtfTu1yGOgOrDo

= 3.5.8.1 =

Updated .pot file.

= 3.5.8 =

Minor bug fixes.

= 3.5.7 =

Fixed collection sorting.

= 3.5.6 =

Fixed missing ; in short_codes.php.

= 3.5.5 =

Fix CVE-2019-1010034.

= 3.5.4 =

Add loading of base jQuery, in case the theme does not load it.

= 3.5.3 = 

Perform length check and truncation in WEBLIB_ItemInCollection::upload_csv().

= 3.5.2 =

Removed extra dollar sign in database_code.php.

= 3.5.1 =

Small bug -- fix small edit error in WEBLIB_AdminPages constructor.

= 3.5 =

Changed the  localization  slug from  web-librarian to weblibrarian to conform
with https://make.wordpress.org/meta/handbook/documentation/translations/

= 3.4.8.7 =

Fix yet another XSS problem in front end short codes.

= 3.4.8.6 =

Fix additional XSS problems in front end short codes.

= 3.4.8.5 =

Fix XSS problem in front end short codes.

= 3.4.8.4 = 

Fix pubdate error in short codes.

= 3.4.8.3 =

Corrected ordering of fields in long format short code.

= 3.4.8.2 =

* Make date display and entry in forms consistent.

= 3.4.8.1 =

* Remove USA specific State check (required two letter state).

= 3.4.8 =

* Relaxed the State, Zip, and Telephone fields of patrons to allow for non-USA users.

= 3.4.7 =

* Fix small error handling code in WEBLIB_Patrons_Admin.php.

= 3.4.6 =

* Changed to use PHP5 constructors in Widget classes (WP_Widget).

= 3.4.5 =

* Changed split() in includes/WEBLIB_Patrons_Admin.php to explode().

= 3.4.4 =

* Added Czech language files.

= 3.4.3 =

* Added Brazilian Portugese language module.

= 3.4.2 =

* Minor  bugfix for Patron Short Codes:  removed  unused  functions  and removed
  references to undefined  members (leftovers from copying ListTable code from
  Admin land).

= 3.4.1 =

* Added check for patron insertion falure.

= 3.4 =

* Added short codes to put patron edit  functions  on the front side:  editing
patron contact info along with hold and circs  handling.  This allows use with
plugins  like  WooCommerce  that  redirect  away  from the  "normal"  WP Admin
Dashboard.

= 3.3 =

* Secure data export code: convert to use admin-post.php.
* Secure AWS code: convert to use admin-post.php and admin-ajax.php
* Move AJAX code to proper WP coding (using admin_ajax.php).

= 3.2.10.14 =
* Add 'view_admin_dashboard'  cap. to Librarian, Senior Aid, and Volunteer, to allow usage with WooCommerce.

= 3.2.10.13 =
* Fix minor error (wrong scope for WEBLIB_Circulation_Admin::single_row_columns()).

= 3.2.10.12 =
* Add auto truncation of collection fields to prevent database errors.

= 3.2.10.11 =
*  Fix minor error (wrong scope for WEBLIB_Circulation_Admin::get_column_info()).

= 3.2.10.10 =
* Fix minor error (unset variable).

= 3.2.10.9 =
* Fix errors in cirlist code (fun with changed WP_List api...).

= 3.2.10.8 =
* Moved URL defines to the constructor. And updated tested to version.

= 3.2.10.7 =
* Fix incrstring -- MySQL uses case folded compares for unique string keys!

= 3.2.10.6 =
* Add wildcards to username search in add patron id.

= 3.2.10.5 =
* Fix small bug in Add Patron ID page: wrong page id in search form.

= 3.2.10.4 =
* Minor stylesheet fix (#overdue => span.overdue).

= 3.2.10.3 =
* Minor documentation update.

= 3.2.10.1 =
* Handle Excel extra column stupidity.

= 3.2.10 =
* Fix problems with generated barcodes during bulk uploads.

= 3.2.9.9 =
* Include medium and large image in AWS item loopup and make them  available for insertion

= 3.2.9.8 =
* Fix bug in autobarcode generator code.  (Stupid SQL 'order by'!).

= 3.2.9.7 =
* Updated AWS Locale endpoints.

= 3.2.9.6 =
* Fix missing name attribute in short code.

= 3.2.9.5 =
* Remove two small short tags.

= 3.2.9.4 =
* Comment out ALL debug code (silly IIS).

= 3.2.9.3 =
* Fix minor bug in patron admin code (wrong page name).

= 3.2.9.2 =
* Workaround for missing localization function (nl_langinfo()).
* Fix missing localization function call (missing _'s).

= 3.2.9.1 =
* Fix typo in the readme.txt file.

= 3.2.9 = 
* Contextual help translated to Italian (completed).

= 3.2.8.3 =
* Update when styles are enqueued.
* Contextual help translated to Italian (in progress).

= 3.2.8.2 =
* Updated readme: Fixed Changelog section (too many ='s!).
                  Added link for user manual (in English and Italian).
* Updated user manual to include style sheet information for front side
  styling.

= 3.2.8.1 =
* Added hook to allow for localized contextual help.
* Fixed minor localization bug.

= 3.2.8 =
* Move user manual to assets.
* Small fix to options page: allow for blank AWS options.

= 3.2.7.7 =
* Front side update: minor short code updates.

= 3.2.7.6 =
* Front side update: short codes and front style sheet updates.

= 3.2.7.5 =
* Localization updates. Minor database update.

= 3.2.7.4 =
* Localization updates, including localized date validation.

= 3.2.7.3 =
* Localization updates.

= 3.2.7.2 =
* Added missing style definition for weblib-item-table.

= 3.2.7.1 =
* Changed default for publication date to 1900-01-01 to deal with possible 
  MySQL/PHP error on activation (out of range default for publication date).

= 3.2.7 =
* Fixed up the jQuery UI, smoothed out the rough edges (eg got all of the 
  proper stylesheet and image support). Additional (minor) localization 
  updates. 

= 3.2.6.1 =
* Way too much fun with resizable iframes and jQuery: put the Amazon search
  thingy in an iframe and put the iframe into a resizable (via jQuery) div.
  sort of works, but still a little funky.
*  Fixed  various  minor  typos:  broken  tags,   spelling   errors,   missing
   localizations.

= 3.2.6 =
* Changed AWS insert buttons to be a small icon instead of "bulky" text buttons
* Updated localization, added Italian translation.

= 3.2.5.3 =
* Add insert / add buttons to Amazon item loopup. (Experimental!)

= 3.2.5.2 =
* Remove roles on deactivate.
* Make title the default on Amazon searches.

= 3.2.5.1 =
* Move loading of Localization files to the correct place

= 3.2.5 =
* Added missing contextual help page.
* Fixed silly typo error in the collection bulk delete code.

= 3.2.4 =
* Added code to collection and patron delete functions to clear out orphaned
  holds and checkouts.
* Added Collection Database Maintenance page, containing a button to clear out
  orphaned holds and checkouts.

= 3.2.3 =
* Updated the support/donation links (added localization).
* Added an 'About' page.

= 3.2.2 =
* Added donation buttons and links.
* Updated localization.

= 3.2.1 =
* Minor bug fix with Call Number column.

= 3.2 =
* Added Call Number column to collection database.
* Updated localization.

= 3.1 =
* Minor documentation update for the contextual help for the options page.

= 3.0 =
* Major code rewrite.  All of the WP_List_Tables redone properly and 
  separated into separate source files.  Adding the per_page screen options 
  properly.
  Added bulletproofing to the collection import code: barcodes are now checked
  and fixed as they are added -- no more 'broken' databases!

= 2.6.3.2 =
* Various security fixes.

= 2.6.3.1 =
* Include debugging code.

= 2.6.3 =
* Fixed minor bug with telephone number validation when adding patrons.

= 2.6.2 =
* Fixed database creation to deal with MS-Windows / MySQL weirdness 
  (no default allowed for BLOB/TEXT -- error under MS-Windows, warning 
   under Linux).

= 2.6.1 =
* Add localization to the JavaScript code

= 2.6 =
* Add localization to the PHP code

= 2.3 =
* Fixed a SQL syntax error.
* Added 'upload_files' capability to Librarian and SeniorAid roles (allows 
  them to upload images for items in the collection).
* Fixed the scoping of variables in the JavaScript code.

= 2.2.2 =
* Added something to the FAQ section.

= 2.2.1 =
* Fixed a problem with the search form short code.

= 2.2 =
* Fixed a problem with short PHP tags.

= 2.1 =
* Updated to include the AssociateTag parameter required by Amazon.

= 2.0 =
* Initial public release.

== Upgrade Notice ==

= 3.2 =
Added Call number column to collection database.  Updated localization.

= 2.3 =
Various updates, see the Change log for details.

= 2.1 =
Updated to include the AssociateTag parameter required by Amazon.

= 2.0 =
Initial public release.



