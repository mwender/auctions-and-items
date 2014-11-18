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

    public function wp_enqueue_scripts(){
        wp_enqueue_style( 'flare-lightbox', plugin_dir_url( __FILE__ ) . 'lib/js/flare/jquery.pixelentity.flare.min.css' );
        wp_enqueue_script( 'flare-lightbox', plugin_dir_url( __FILE__ ) . 'lib/js/flare/jquery.pixelentity.flare.min.js', array( 'jquery' ) );
        wp_enqueue_script( 'flare-init', plugin_dir_url( __FILE__ ) . 'lib/js/flare/flare-init.js', array( 'flare-lightbox' ) );
    }
}

$AuctionsAndItems = AuctionsAndItems::get_instance();
register_activation_hook( __FILE__, array( $AuctionsAndItems, 'activate' ) );

add_action( 'wp_enqueue_scripts', array( $AuctionsAndItems, 'wp_enqueue_scripts' ) );

require_once( 'lib/classes/post_type.item.php' );
require_once( 'lib/classes/taxonomy.auction.php' );
require_once( 'lib/classes/auction-importer.php' );
require_once( 'lib/classes/shortcodes.php' );
?>