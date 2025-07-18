<?php
/*
	Plugin Name: Auctions for WordPress
	Plugin URI:
	Description: Adds an `auction` taxonomy with `Item` custom post_types.
	Author: Michael Wender
	Version: 3.0.0
	Author URI: https://mwender.com
 */
/*  Copyright 2015-25  Michael Wender  (email : mwender@wenmarkdigital.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

// Define plugin constants
$css_dir = ( stristr( site_url(), '.local' ) || SCRIPT_DEBUG )? 'css' : 'dist' ;
define( 'AAI_CSS_DIR', $css_dir );
define( 'AAI_DEV_ENV', stristr( site_url(), '.local' ) );
define( 'AAI_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'AAI_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Load Composer files
// 04/07/2025 (10:28) - checking if Composer autoloader has already been loaded:
if( ! class_exists( 'LightnCandy\\LightnCandy' ) )
    require 'vendor/autoload.php';

// Load main class
require_once( 'lib/classes/auctions-and-items.php' );
$AuctionsAndItems = AuctionsAndItems::get_instance();
register_activation_hook( __FILE__, array( $AuctionsAndItems, 'activate' ) );

/**
 * Adds custom metadata links to the Auctions plugin row in the Plugins screen.
 *
 * Displays a "local" badge when running the local development version, and
 * appends links to the changelog and the package name.
 *
 * @since 2.1.3
 *
 * @param string[] $links An array of the plugin's metadata links.
 * @param string   $file  Path to the plugin file relative to the plugins directory.
 * @return string[] Modified array of metadata links.
 */
add_filter( 'plugin_row_meta', function( $links, $file ) {
  
  if ( strpos( $file, 'auctions.php' ) !== false ) {
    
    // Check if we're running the local dev version:
    $plugin_dir = plugin_dir_path( __FILE__ );
    
    if ( strpos( $plugin_dir, 'localdev' ) !== false ) {
      array_unshift( $links, '<span style="padding:2px 8px; background:#0073aa; color:#fff; border-radius:10px; font-size:11px;">local</span> ' );;
    }

    $links[] = '<a href="https://github.com/mwender/auctions-and-items?tab=readme-ov-file#changelog" target="_blank">Changelog</a>';
    $links[] = '<code>mwender/auctions-and-items</code>';
  }

  return $links;

}, 10, 2 );

/**
 * Add a custom hook 'aai_empty_trash', this hook runs when
 * an `Empty Trash` button is clicked.
 */
add_action( 'load-edit.php', function()
{
    add_action( 'before_delete_post', function ( $post_id )
    {
        if (
            'trash' === get_post_status( $post_id )
            && filter_input( INPUT_GET, 'delete_all' )
            && 1 === did_action( 'before_delete_post ' )
        )
            do_action( 'aai_empty_trash', $post_id );
    } );
} );

/**
 * Enhanced logging.
 *
 * @param      string  $message  The log message
 */
if( ! function_exists( 'uber_log' ) ){
    function uber_log( $message = null ){
      static $counter = 1;

      $bt = debug_backtrace();
      $caller = array_shift( $bt );

      if( 1 == $counter )
        error_log( "\n\n" . str_repeat('-', 25 ) . ' STARTING DEBUG [' . date('h:i:sa', current_time('timestamp') ) . '] ' . str_repeat('-', 25 ) . "\n\n" );
      error_log( "\n" . $counter . '. ' . basename( $caller['file'] ) . '::' . $caller['line'] . "\n" . $message . "\n---\n" );
      $counter++;
    }
}

require_once( 'lib/classes/post_type.item.php' );
require_once( 'lib/classes/taxonomy.auction.php' );

// Categories for Auction Items
require_once( 'lib/classes/taxonomy.item-category.php' );

// Tags for Auction Items
require_once( 'lib/classes/taxonomy.item-tags.php' );

require_once( 'lib/classes/auction-importer.php' );
require_once( 'lib/classes/shortcodes.php' );

// Setup background process for deleting items
require_once( 'lib/classes/background-delete-item-process.php' );
$GLOBALS['BackgroundDeleteItemProcess'] = new AAI_Delete_Item_Process(); // We must set this as an explicit global in order for it to be available inside WPCLI

// Misc function files
require_once( 'lib/fns/enqueues.php' );
require_once( 'lib/fns/handlebars.php' );
require_once( 'lib/fns/wpcli.php' );
require_once( 'lib/fns/utilities.php' );
require_once( 'lib/fns/shortcodes.php' );
?>