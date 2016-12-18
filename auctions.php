<?php
/*
	Plugin Name: Auctions for WordPress
	Plugin URI:
	Description: Adds an `auction` taxonomy with `Item` custom post_types.
	Author: Michael Wender
	Version: 1.3.0
	Author URI: http://michaelwender.com
 */
/*  Copyright 2015  Michael Wender  (email : michael@michaelwender.com)

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
    const VER = '1.3.0';
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

    /**
     * Enqueues scripts.
     *
     * @since 1.x.x
     *
     * @return void
     */
    public function enqueue_scripts(){
        wp_enqueue_style( 'flare-lightbox', plugin_dir_url( __FILE__ ) . 'lib/js/flare/jquery.pixelentity.flare.min.css' );
        wp_enqueue_script( 'flare-lightbox', plugin_dir_url( __FILE__ ) . 'lib/js/flare/jquery.pixelentity.flare.min.js', array( 'jquery' ) );
        wp_enqueue_script( 'flare-init', plugin_dir_url( __FILE__ ) . 'lib/js/flare/flare-init.js', array( 'flare-lightbox' ) );

        if( is_tax( 'auction' ) ){
            wp_enqueue_script( 'datatables-user' );

            global $wp_query;
            $value    = get_query_var($wp_query->query_vars['taxonomy']);
            $current_term = get_term_by('slug',$value,$wp_query->query_vars['taxonomy']);

            $show_realized = get_metadata( 'auction', $current_term->term_id, 'show_realized', true );

            wp_localize_script( 'datatables-user', 'wpvars', array( 'ajax_url' => admin_url( 'admin-ajax.php' ), 'auction' => $current_term->term_id, 'show_realized' => $show_realized ) );
            wp_enqueue_style( 'datatables' );
            wp_enqueue_style( 'dashicons' );
        }
    }

    /**
     * Registers scripts.
     *
     * @since 1.x.x
     *
     * @return void
     */
    public function register_scripts(){
        $styles = array(
            0 => array(
                'handle' => 'footable',
                'src' => 'footable/css/footable.core.min.css',
                'type' => 'style',
            ),
        );
        foreach( $styles as $args ){
            $this->_register_bower_script( $args );
        }

        $scripts = array(
            1 => array(
                'handle' => 'footable',
                'src' => 'footable/js/footable.js',
                'deps' => array( 'jquery' ),
            ),
            2 => array(
                'handle' => 'footable-sort',
                'src' => 'footable/js/footable.sort.js',
                'deps' => array( 'jquery', 'footable' ),
            ),
            3 => array(
                'handle' => 'footable-filter',
                'src' => 'footable/js/footable.filter.js',
                'deps' => array( 'jquery', 'footable' ),
            ),
            4 => array(
                'handle' => 'footable-striping',
                'src' => 'footable/js/footable.striping.js',
                'deps' => array( 'jquery', 'footable' ),
            ),
        );
        foreach( $scripts as $args ){
            $this->_register_bower_script( $args );
        }

        wp_register_script( 'footable-user', plugin_dir_url( __FILE__ ) . 'lib/js/footable.js' , array( 'jquery', 'footable' ), filemtime( plugin_dir_path( __FILE__ ) . 'lib/js/footable.js' ) );
        wp_register_script( 'datatables', plugin_dir_url( __FILE__ ) . 'lib/js/datatables/datatables.min.js', null, filemtime( plugin_dir_path( __FILE__ ) . 'lib/js/datatables/datatables.min.js' ) );
        wp_register_style( 'datatables', plugin_dir_url( __FILE__ ) . 'lib/js/datatables/datatables.min.css', null, filemtime( plugin_dir_path( __FILE__ ) . 'lib/js/datatables/datatables.min.css' ) );
        wp_register_script( 'datatables-user', plugin_dir_url( __FILE__ ) . 'lib/js/datatables.js' , array( 'jquery', 'datatables' ), filemtime( plugin_dir_path( __FILE__ ) . 'lib/js/datatables.js' ) );
    }

    /**
     * Registers bower script compenents via `wp_register_script`
     *
     * @access enqueue_scripts()
     * @since 1.x.x
     *
     * @param array $args
     *          @type string $handle Name for the script.
     *          @type string $src File path to the script.
     *          @type array $deps Array of handles of all registered scripts required by this script.
     *          @type bool $in_footer Should this script be placed in the footer.
     * }
     * @return void
     */
    private function _register_bower_script( $args ){
        $defaults = array(
            'handle' => null,
            'src' => null,
            'deps' => null,
            'in_footer' => null,
            'media' => 'screen',
            'type' => 'script',
        );

        $args = wp_parse_args( $args, $defaults );

        if( ! stristr( $args['src'], 'bower_components/' ) )
            $args['src'] = 'bower_components/' . $args['src'];

        $src_url = plugin_dir_url( __FILE__ ) . $args['src'];
        $ver = filemtime( plugin_dir_path( __FILE__ ) . $args['src'] );

        if( 'script' == $args['type'] ){
            wp_register_script( $args['handle'], $src_url, $args['deps'], $ver, $args['in_footer'] );
        } elseif ( 'style' == $args['type'] ){
            wp_register_style( $args['handle'], $src_url, $args['deps'], $ver, $args['media'] );
        }

    }
}

$AuctionsAndItems = AuctionsAndItems::get_instance();
register_activation_hook( __FILE__, array( $AuctionsAndItems, 'activate' ) );

add_action( 'wp_enqueue_scripts', array( $AuctionsAndItems, 'register_scripts' ), 9 );
add_action( 'wp_enqueue_scripts', array( $AuctionsAndItems, 'enqueue_scripts' ), 10 );

require_once( 'lib/classes/post_type.item.php' );
require_once( 'lib/classes/taxonomy.auction.php' );

// Tags for Auction Items
require_once( 'lib/classes/taxonomy.item-tags.php' );
$ItemTagTaxonomy = ItemTagTaxonomy::get_instance();

require_once( 'lib/classes/auction-importer.php' );
require_once( 'lib/classes/shortcodes.php' );
?>