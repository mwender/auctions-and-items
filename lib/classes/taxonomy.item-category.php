<?php
class ItemCategoryTaxonomy extends AuctionsAndItems{

    private static $instance = null;

    public static function get_instance() {
        if( null == self::$instance ) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    private function __construct() {
        add_action( 'init', array( $this, 'init_callback' ), 11 );

        // AJAX calls for Auction DataTables display
        // add_action( 'wp_ajax_query_item_tags', array( $this, 'query_items_callback' ) );
        // add_action( 'wp_ajax_nopriv_query_item_tags', array( $this, 'query_items_callback' ) );
    }

    /**
    * END CLASS SETUP
    */

    /**
     * Hooked to WordPress `init` action.
     *
     * Performs the following:
     *
     *  - Registers `auction` custom taxonomy.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function init_callback(){
        global $wpdb;
        /**
         * Create `Item Category` taxonomy
         *
         * @uses  Inserts new taxonomy object into the list
         * @uses  Adds query vars
         *
         * @param string  Name of taxonomy object
         * @param array|string  Name of the object type for the taxonomy object.
         * @param array|string  Taxonomy arguments
         * @return null|WP_Error WP_Error if errors, otherwise null.
         */

        $labels = array(
            'name'                  => 'Item Categories',
            'singular_name'         => 'Item Category',
            'search_items'          => 'Item Categories',
            'popular_items'         => 'Popular Item Categories',
            'all_items'             => 'All Item Categories',
            'parent_item'           => 'Parent Item Category',
            'parent_item_colon'     => 'Parent Item Category',
            'edit_item'             => 'Edit Item Category',
            'update_item'           => 'Update Item Category',
            'add_new_item'          => 'Add New Item Category',
            'new_item_name'         => 'New Item Category Name',
            'add_or_remove_items'   => 'Add or remove Item Categories',
            'choose_from_most_used' => 'Choose from most used Item Categories',
            'menu_name'             => 'Item Category',
        );

        $args = array(
            'labels'            => $labels,
            'public'            => true,
            'show_in_nav_menus' => true,
            'show_admin_column' => false,
            'hierarchical'      => true,
            'show_tagcloud'     => true,
            'show_ui'           => true,
            'query_var'         => true,
            'rewrite'           => true,
            'query_var'         => true,
            'capabilities'      => array(),
        );

        register_taxonomy( 'item_category', ['item'], $args );

    }
}
?>