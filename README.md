# Auctions for WordPress Plugin

Adds an `Items` custom post_type along with an `Auction` taxonomy. Together, these structures provide the means for displaying auction catalogs with WordPress.

## Changelog

__Version 1.4.1 - 07/24/2020__

- Updating `AuctionShortcodes::get_gallery_image()` to use `wp_get_attachment_image_src()` to retrieve the Auction Item's first attachment image source URL.

__Version 1.4.0 - 06/11/2020__

- Adding `LiveAuctioneersID` column to Auction Items CSV import.

__Version 1.3.0 - 12/18/2016__

- Deleting item image attachments during item delete

__Version 1.2.0 - 06/27/2016__

- Adding `Item Tags` custom taxonomy
- Adding Bidsquare lot numbers to auction import

__Version 1.1.0 - 12/29/2015__

- Adding Next/Previous navigation to items.
- Table and List view options for Auction archives.
- Enhanced table view for Auction Highlights shortcode.

__Version 1.0__

- Initial version ported from code integrated inside a WordPress theme.