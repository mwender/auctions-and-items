<?php
/*
	Plugin Name: Auctions for WordPress
	Plugin URI:
	Description: Adds an `auction` taxonomy with `Item` custom post_types.
	Author: Michael Wender
	Version: 1.0.0
	Author URI: http://michaelwender.com
 */
/*  Copyright 2014  Michael Wender  (email : michael@michaelwender.com)

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

class AuctionsAndItems {
    const VER = '1.0.0';
    private static $instance = null;

    public static function get_instance(){
        if( null == self::$instance )
            self::$instance = new self;

        return self::$instance;
    }

    private function __construct(){

    }

    static function activate(){
        AuctionsAndItems::init_options();
    }

    public function init_options(){
        update_option( 'auctions_and_items_ver', self::VER );
    }
    /**
    * END CLASS SETUP
    */
}

$AuctionsAndItems = AuctionsAndItems::get_instance();
register_activation_hook( __FILE__, array( $AuctionsAndItems, 'activate' ) );

require_once( 'lib/classes/post_type.item.php' );
require_once( 'lib/classes/taxonomy.auction.php' );
require_once( 'lib/classes/auction-importer.php' );
require_once( 'lib/classes/shortcodes.php' );
?>