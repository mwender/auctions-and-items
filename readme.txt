=== Auctions and Items ===
Contributors: TheWebist
Donate link: https://mwender.com/
Tags: comments, spam
Requires at least: 6.3
Tested up to: 6.7.2
Requires PHP: 8.1
Stable tag: 1.9.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Adds an `Items` CPT along with an `Auction` taxonomy. Together, these provide the means for displaying auction catalogs with WordPress.

== Description ==

This plugin supports the display of large, multi-item auctions. Each Item post features a gallery of high-resolution images allowing site visitors to easily view and assess auction items.

== Changelog ==

= 2.0.0 =
* Auction Importer improvements:
	* Refactoring import to ignore missing columns. If a column is missing when updating an auction item, no processing is performed for that particular column.
	* The importer now treats columns in a case-insensitive fashion.
	* Adding "Item No." as a unique key for auction items.
* Adding "Hammer Price" as Item CPT meta.

= 1.9.0 =
* Adding `LotBiddingURL` option for CSV imports.

= 1.8.7 =
* Adding `data-filter` attr to highlights table to enable filtering.* Loading images from `production` when viewing on `.local`.

= 1.8.6 =
* BUGFIX: Auction Importer was checking for `isHighLight` (with a capital "L") instead of `isHighlight`.

= 1.8.5 =
* Updating importer's docs.

= 1.8.4 =
* Accomodating `CategoryName` or `Categories` as the column heading for "Categories".

= 1.8.3 =
* Updating CSV column name from `CategoryName` to `Categories`.

= 1.8.2 =
* Updating CSS pre-processor to SCSS, moving build process to NPM.
* Building README via `grunt readme`.
* Adding auction viewer overlay logo.

= 1.8.1.1 =
* Checking if `Provenance` and `Condition` fields have content before adding to Item description.

= 1.8.1.0 =
* Removing `StartPrice` from Item Importer.* Updating importer to work with Caspio CSV export.

= 1.8.1 =
* Removing Genesis Theme dependency when retrieving first attachment image for an Auction Item.
* Setting `has_archive` to `true` for Auction Items so that we can build archives for auctions in Elementor.
* Removing email from Auction Highlights note, adding link to Selling page.

= 1.8.0 =
* Adding `wp items unsold` for managing "Unsold" items.

= 1.7.1 =
* Removing legacy Auction Taxonomy Option fields.

= 1.7.0 =
* Adding "Realized" column to Item CPT admin listing.

= 1.6.0 =
* Adding `show_search` and `show_notes` options for `[highlights]` shortcode.

= 1.5.0 =
* Replacing Flare Lightbox with [Featherlight](https://github.com/noelboss/featherlight).

= 1.4.1 =
* Updating `AuctionShortcodes::get_gallery_image()` to use `wp_get_attachment_image_src()` to retrieve the Auction Item's first attachment image source URL.

= 1.4.0 =
* Adding `LiveAuctioneersID` column to Auction Items CSV import.

= 1.3.0 =
* Deleting item image attachments during item delete

= 1.2.0 =
* Adding `Item Tags` custom taxonomy
* Adding Bidsquare lot numbers to auction import

= 1.1.0 =
* Adding Next/Previous navigation to items.
* Table and List view options for Auction archives.
* Enhanced table view for Auction Highlights shortcode.

= 1.0 =
* Initial version ported from code integrated inside a WordPress theme.