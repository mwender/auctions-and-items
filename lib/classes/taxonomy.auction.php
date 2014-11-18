<?php
class AuctionTaxonomy extends AuctionsAndItems{

    private static $instance = null;

    public static function get_instance() {
        if( null == self::$instance ) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    private function __construct() {
    }

    /**
    * END CLASS SETUP
    */

    /**
     * Hooked to `admin_enqueue_scripts`
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function admin_enqueue_scripts( $hook ){

        switch( $hook ){
            case 'edit-tags.php':
                wp_enqueue_script( 'datepicker-js', plugin_dir_url( __FILE__ ) . '../js/admin.js', array( 'jquery', 'jquery-ui-datepicker' ) );
                wp_enqueue_style( 'datepicker', plugin_dir_url( __FILE__ ) . '../css/jquery-ui-1.7.2.custom.css' );
            break;
        }

    }

    /**
     * Hooked to `admin_init`
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function admin_init_callback(){
        add_action( 'auction_edit_form', array( $this, 'taxonomy_archive_options_for_auction' ) , 10, 2 );
    }

    /**
     * Hooked to `delete_auction`. Runs when an auction is deleted.
     *
     * @since 1.0.0
     *
     * @param int $term_id ID of the term.
     * @param int $tt_id Term Taxonomy ID.
     * @return void
     */
    public function delete_auction_callback( $term_id, $tt_id ){
        if ( !$term_id )
            return;

        delete_metadata( $_REQUEST['taxonomy'], $term_id, 'meta' );
        delete_metadata( $_REQUEST['taxonomy'], $term_id, 'date' );
    }

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

        // Register `Auctions` and `Gallery` custom taxonomies
        register_taxonomy(
            'auction',
            'item',
            array(
                'label' => 'Auctions',
                'labels' => array( 'singular_name' => 'Auction', 'search_items' => 'Search Auctions', 'popular_items' => 'Popular Auctions', 'all_items' => 'All Auctions', 'parent_item' => 'Parent Auction', 'parent_item_colon' => 'Parent Auction:', 'edit_item' => 'Edit Auction', 'update_item' => 'Update Auction', 'add_new_item' => 'Add New Auction', 'new_item_name' => 'New Auction Name' ),
                'hierarchical' => true,
                'query_var' => true
            ) );
        $wpdb->auctionmeta = $wpdb->prefix.'auctionmeta'; // What am I doing here?

    }

    /**
     * Modify the number of posts that appear on taxonomy auction archives
     *
     *
     *
     * @see Function/method/class relied on
     * @link URL short description.
     * @global type $varname short description.
     *
     * @since 1.0.0
     *
     * @param type $var Description.
     * @param type $var Optional. Description.
     * @return type Description. (@return void if a non-returning function)
     */
     public function pre_get_posts( $query ){
        if( ! $query->is_main_query() )
            return;

        if( ! is_admin() && is_tax( 'auction' ) ){
            $query->set( 'posts_per_page', 20 );
            $query->set( 'orderby', 'meta_value' );
            $query->set( 'meta_key', '_lotnum' );
            $query->set( 'meta_type', 'NUMERIC' );
            $query->set( 'order', 'ASC' );
            return;
        }
     }

    /**
     * Saving additional meta fields for auctions
     *
     * @since 1.0.0
     *
     * @param int $term_id Term ID.
     * @param int $tt_id Term Taxonomy ID.
     * @return void
     */
    public function save_auction_callback( $term_id, $tt_id ){
        if ( defined( 'DOING_AJAX' ) && DOING_AJAX )
            return;

        if ( ! $term_id )
            return;

        if ( ! wp_verify_nonce( $_POST['auction_nonce'], basename( __FILE__ ) ) )
            return $term_id;

        if ( isset( $_POST['meta'] ) && !empty( $_POST['meta'] ) )
            update_metadata( $_POST['taxonomy'], $term_id, 'meta', $_POST['meta'] );

        if ( isset( $_POST['date'] ) && !empty( $_POST['date'] ) )
            update_metadata( $_POST['taxonomy'], $term_id, 'date', $_POST['date'] );
    }

    /**
     * Adds additional options to `auctions` custom taxonomy
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function taxonomy_archive_options_for_auction( $tag, $taxonomy ){
        $meta = get_metadata( $tag->taxonomy, $tag->term_id, 'meta', true );
        $date = get_metadata( $tag->taxonomy, $tag->term_id, 'date', true );
    ?>
        <input type="hidden" name="auction_nonce" value="<?php echo wp_create_nonce( basename( __FILE__ ) )?>" />
        <h3><?php echo __( 'Auction Archive Settings', 'caseanti' ); ?></h3>
        <table class="form-table">
            <tbody>
                <tr>
                    <th scope="row"><label>Date</label></th>
                    <td><input class="datepicker" id="date" type="text" name="date" value="<?php echo $date ?>" /><div class="description">Select the date of this auction.</div></td>
                </tr>
                <tr>
                    <th scope="row" valign="top"><label for="meta[auction_id]"><?php _e( 'Live Auctioneers Auction ID', 'caseanti' ); ?></label></th>
                    <td>
                        <input id="meta[auction_id]" name="meta[auction_id]" type="text" value="<?php if ( isset( $meta['auction_id'] ) ) print( $meta['auction_id'] ); ?>" size="20" />
                        <p class="description"><?php _e( 'Add the Live Auctioneers Auction ID to display <strong>Bid Now</strong> and <strong>Sold Price</strong> links next to each item in this auction.', 'caseanti' ); ?></p>
                    </td>
                </tr>
            </tbody>
        </table>
        <?php
    }
}

$AuctionTaxonomy = AuctionTaxonomy::get_instance();

add_action( 'init', array( $AuctionTaxonomy, 'init_callback' ), 11 );
add_action( 'admin_init', array( $AuctionTaxonomy, 'admin_init_callback' ) );
add_action( 'admin_enqueue_scripts', array( $AuctionTaxonomy, 'admin_enqueue_scripts' ) );

// Handle additional meta fields for auctions
add_action( 'edited_auction', array( $AuctionTaxonomy,'save_auction_callback' ), 10, 2 );
add_action( 'delete_auction', array( $AuctionTaxonomy, 'delete_auction_callback' ), 10, 2 );

// Modifying query for taxonomy-auction.php
add_action( 'pre_get_posts', array( $AuctionTaxonomy, 'pre_get_posts' ) );
?>