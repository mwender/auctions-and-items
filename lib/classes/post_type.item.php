<?php
class AuctionItem extends AuctionsAndItems{

    private static $instance = null;
    public $slug = 'item';

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
     * Column content for `item` CPT custom columns
     *
     * @since 1.0.0
     *
     * @param string $column Column name.
     * @return void
     */
    public function column_content_for_items( $column ){
        global $post;
        switch ( $column ) {
            case 'ID':
                echo $post->ID;
                break;
            case 'auction':
                $auctions = get_the_terms( $post->ID, 'auction' );
                if ( !empty( $auctions ) ) {
                    $out = array();
                    foreach ( $auctions as $c )
                        $out[] = '<a href="edit-tags.php?action=edit&post_type=item&taxonomy=auction&tag_ID='.$c->term_id.'">' . $c->name . '</a>';
                        // esc_html(sanitize_term_field('name', $c->name, $c->term_id, 'auction', 'display'))
                    echo join( ', ', $out );
                } else {
                    _e( 'No auction' );
                }
                break;
        }
    }

    /**
     * Custom columns for `item` CPT
     *
     * @since 1.0.0
     *
     * @param array $defaults Default columns for `item` listing.
     * @return array Modified columns for `item` listing.
     */
    public function columns_for_items( $defaults ){
        $defaults = array(
            'cb' => '<input type="checkbox" />',
            'title' => 'Item',
            'auction' => 'Auctions',
            'date' => 'Date',
        );
        return $defaults;
    }

    /**
     * Hooked to WordPress `init` action.
     *
     * Performs the following:
     *
     *  - Registers `item` CPT.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function init_callback(){
        global $wpdb;

        // Setup `Item` CPT
        $labels = array( 'singular_name' => 'Item', 'add_new' => 'Add New Item', 'edit_item' => 'Edit Item', 'new_item' => 'New Item', 'view_item' => 'View Item', 'search_items' => 'Search Items', 'not_found' => 'No items found', 'not_found_in_trash' => 'No items found in trash', 'parent_item_colon' => 'Parent Item:' );
        $args = array(
            'label' => 'Items',
            'labels' => $labels,
            'description' => 'Slides appear on the home page in the "Featured" slider.',
            'has_archive' => false,
            'public' => true,
            'exclude_from_search' => false,
            'show_ui' => true,
            'hierarchical' => false,
            'supports' => array( 'title', 'editor' , 'genesis-seo', 'custom-fields', 'thumbnail' ),
            'menu_position' => 5,
            'show_in_nav_menus' => true,
            'register_meta_box_cb' => array( $this, 'item_metabox_callback' ),
            'menu_icon' => 'dashicons-format-gallery'
        );
        register_post_type( $this->slug, $args );

    }

    /**
     * Adds a meta box to the `item` CPT.
     *
     * Called by register_post_type( 'item' ) inside init_callback().
     *
     * @see add_meta_box()
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function item_metabox_callback(){
        add_meta_box( 'item-meta', 'Item Options', array( $this, 'metabox_for_item' ), $this->slug, 'normal', 'low' );
    }

    /**
     * Displays `item` CPT meta box.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function metabox_for_item(){
        global $post;
        $lotnum = get_post_meta( $post->ID, '_lotnum', true );
        $low_est = get_post_meta( $post->ID, '_low_est', true );
        $high_est = get_post_meta( $post->ID, '_high_est', true );
        $start_price = get_post_meta( $post->ID, '_start_price', true );
        $realized = get_post_meta( $post->ID, '_realized', true );
        $highlight = get_post_meta( $post->ID, '_highlight', true );
        $item_redirect = get_post_meta( $post->ID, '_item_redirect', true );
    ?>
        <input type="hidden" id="item_options" name="item_options" value="true" />
    <table class="form-table">
        <col width="18%" /><col width="82%" />
        <tr>
            <th scope="row"><strong>Lot Number</strong></th>
            <td><label for="lotnum"><input id="lotnum" type="text" style="width: 80px; text-align: right" name="lotnum" value="<?php echo $lotnum ?>" /></label></td>
        </tr>
        <tr>
            <th scope="row"><strong>Low Estimate</strong></th>
            <td><label for="low_est"><input id="low_est" type="text" style="width: 100px; text-align: right" name="low_est" value="<?php echo $low_est ?>" /></label></td>
        </tr>
        <tr>
            <th scope="row"><strong>High Estimate</strong></th>
            <td><label for="high_est"><input id="high_est" type="text" style="width: 100px; text-align: right" name="high_est" value="<?php echo $high_est ?>" /></label></td>
        </tr>
        <tr>
            <th scope="row"><strong>Start Price</strong></th>
            <td><label for="start_price"><input id="start_price" type="text" style="width: 100px; text-align: right" name="start_price" value="<?php echo $start_price ?>" /></label></td>
        </tr>
        <tr>
            <th scope="row"><strong>Realized Price</strong></th>
            <td><label for="realized"><input id="realized" type="text" style="width: 100px; text-align: right" name="realized" value="<?php echo $realized ?>" /></label></td>
        </tr>
        <tr>
            <th scope="row"><strong>Highlight Item</strong></th>
            <td><label for="highlight"><input type="checkbox" name="highlight" id="highlight" value="1"<?php if ( $highlight == true ) echo ' checked="checked"' ?> />Display this item in this auction's highlights. ($highlight = <?php echo $highlight ?>)</label></td>
        </tr>
        <tr>
            <th scope="row"><strong>Sub-Auction Redirect</strong></th>
            <td><label for="redirect"><?php
            $args = array();
        $args['id'] = 'item_redirect';
        $args['name'] = 'item_redirect';
        $args['taxonomy'] = 'auction';
        $args['show_option_none'] = 'Select an auction or sub-auction...';
        $args['hierarchical'] = true;
        $args['order'] = 'ASC';
        $args['orderby'] = 'name';
        $args['selected'] = $item_redirect;
        wp_dropdown_categories( $args );
        ?><br />If selected, when this item is accessed, the page will redirect to the selected auction.<p style="margin-left: 0">NOTE: This feature is used with sub-auctions with names like "<em>Select Sampling of Uncataloged Items...</em>". In cases like these, this item would simply serve as a placeholder which redirects to a sub-auction containing more items for display.</p></label></td>
        </tr>
    </table>
        <?php
    }

    /**
     * Saving additional meta fields for items
     *
     * @since 1.0.0
     *
     * @param int $post_id Post ID.
     * @param int $post Post object.
     * @return void
     */
    public function save_item_callback( $post_id, $post, $update ){

        // If this isn't an `item` CPT, don't update it.
        if( $this->slug != $post->post_type )
            return;

        // verify if this is an auto save routine. If it is our form has not been submitted, so we don't want to do anything
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
            return $post_id;

        $valid_fields = array( '_lotnum' => '', '_low_est' => '', '_high_est' => '', '_start_price' => '', '_realized' => '', '_highlight' => false, '_item_redirect' => 0 );
        foreach ( $valid_fields as $field => $default ) {

            $post_field = substr( $field, 1 );
            $value = ( isset( $_POST[$post_field] ) && !empty( $_POST[$post_field] ) )? $_POST[$post_field] : $default;
            if ( get_post_meta( $post_id, $field ) == '' )
                add_post_meta( $post_id, $field, $value );
            elseif ( $value != get_post_meta( $post_id, $field, true ) )
                update_post_meta( $post_id, $field, $value );
            elseif ( $value == '' )
                delete_post_meta( $post_id, $field, get_post_meta( $post_id, $field, true ) );
        }
    }
}

$AuctionItem = AuctionItem::get_instance();

add_action( 'init', array( $AuctionItem, 'init_callback' ) );

// Handle additional meta fields for items
add_action( 'save_post', array( $AuctionItem, 'save_item_callback' ), 10, 3 );
add_filter( 'manage_edit-item_columns', array( $AuctionItem, 'columns_for_items' ) );
add_action( 'manage_posts_custom_column', array( $AuctionItem, 'column_content_for_items' ), 10, 2 );
?>