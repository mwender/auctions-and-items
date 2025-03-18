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
        add_filter( 'get_next_post_join', function( $join ){
            return $this->item_nextprev_item_sql( array( 'type' => 'join', 'sql' => $join ) );
        }, 999 );
        add_filter( 'get_next_post_where', function( $where ){
            return $this->item_nextprev_item_sql( array( 'type' => 'where', 'sql' => $where ) );
        }, 999 );
        add_filter( 'get_next_post_sort', function(){
            return $this->item_orderby_sql( array( 'sort' => 'ASC' ) );
        }, 999 );

        add_filter( 'get_previous_post_join', function( $join ){
            return $this->item_nextprev_item_sql( array( 'type' => 'join', 'sql' => $join ) );
        }, 999 );
        add_filter( 'get_previous_post_where', function( $where ){
            return $this->item_nextprev_item_sql( array( 'type' => 'where', 'sql' => $where, 'compare' => '<' ) );
        }, 999 );
        add_filter( 'get_previous_post_sort', function(){
            return $this->item_orderby_sql( array( 'sort' => 'DESC' ) );
        }, 999 );

        add_action( 'before_delete_post', [$this, 'delete_item_images'] );
        add_action( 'aai_empty_trash', [$this, 'delete_item_images'], 10, 1 );
    }

    /**
    * END CLASS SETUP
    */

    /**
     * Deletes images attached to an auction item
     *
     * @see get_attached_media, wp_delete_attachment
     * @global object $post WordPress post object.
     *
     * @since 1.3.0
     *
     * @return void
     */
    static public function delete_item_images( $post_id = null ){
        if( is_null( $post_id ) ){
            global $post;
            $post_id = $post->ID;
        }

        if( ! 'item' == get_post_type( $post_id ) )
            return;

        $images = get_attached_media( 'image', $post_id );
        if( is_array( $images ) && 0 < count( $images ) ){
            foreach( $images as $image ){
                wp_delete_attachment( $image->ID, true );
            }
        }
    }

    /**
     * Builds SQL for filtering get_adjacent_post for `item` CPT.
     *
     * @see get_post_meta()
     * @see get_meta_sql()
     * @global object $post Global WordPress post object.
     * @global object $wpdb Global WordPress database object.
     *
     * @since 1.1.0
     *
     * @param array $args {
     *      Array of arguments.
     *
     *      @type string $compare Either `>` or `<`.
     *      @type string $sql SQL passed from filter during callback.
     *      @type string $type Either `where` or `join`. Used to specify which part of
     *          the array returned by get_meta_sql() that we want returned.
     * }
     * @return string SQL used to filter get_{$adjacent}_post_join, get_{$adjacent}_post_where, and get_{$adjacent}_post_sort.
     */
    public function item_nextprev_item_sql( $args ){

        global $post, $wpdb;

        $args = shortcode_atts( array(
            'compare' => '>',
            'sql' => null,
            'type' => null,
        ), $args );

        if( is_null( $args['type'] ) )
            return false;

        $current_lotnum = get_post_meta( $post->ID, '_lotnum', true );

        $meta_query = array(
            array(
                'key' => '_lotnum',
                'value' => $current_lotnum,
                'compare' => $args['compare'],
                'type' => 'NUMERIC',

            ),
        );
        $meta_sql = get_meta_sql( $meta_query, 'post', $wpdb->posts, 'ID' );

        $sql = $meta_sql[$args['type']];

        if( ! is_null( $args['sql'] ) )
            $sql = $args['sql'].$sql;

        if( stristr( $sql, $wpdb->posts ) )
            $sql = str_replace( $wpdb->posts, 'p', $sql );

        if( 'where' == strtolower( $args['type'] ) ){
            if( preg_match( '/(p.post_date.*AND[[:space:]])/U', $sql, $matches ) ){
                $sql = str_replace( $matches[1], '', $sql );
            }
        }

        return $sql;
    }

    /**
     * Returns SQL for sorting `item` CPT get_adjacent_post().
     *
     * @global object $wpdb Global WordPress database object.
     *
     * @since 1.1.0
     *
     * @param array $args {
     *      Optional. An array of arguments.
     *
     *      @type string $sort Either `ASC` or `DESC`.
     *
     * }
     * @return string SQL for sorting `item` CPT get_adjacent_post().
     */
    public function item_orderby_sql( $args ){
        global $wpdb;

        $args = shortcode_atts( array(
            'sort' => 'ASC'
        ), $args );

        return 'ORDER BY CAST(' . $wpdb->postmeta . '.meta_value AS SIGNED) ' . strtoupper( $args['sort'] ) . ' LIMIT 1';
    }

    /**
     * Column content for `item` CPT custom columns
     *
     * @since 1.0.0
     *
     * @param string $column Column name.
     * @return void
     */
    public function column_content_for_items( $column, $post_id ){

        switch ( $column ) {
            case 'ID':
                echo $post_id;
                break;

            case 'item_number':
              $item_number = get_post_meta( $post_id, '_item_number', true );
              echo $item_number;
              break;

            case 'auction':
                $auctions = get_the_terms( $post_id, 'auction' );
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

            case 'item_categories':
                if( $item_categories = wp_get_object_terms( $post_id, 'item_category' ) ){
                    foreach ($item_categories as $category_object ) {
                        $categories[] = $category_object->name;
                    }
                    echo implode(', ', $categories );
                }
                break;

            case 'item_tags':
                if( $item_tags = wp_get_object_terms( $post_id, 'item_tags' ) ){
                    foreach ($item_tags as $category_object ) {
                        $tags[] = $category_object->name;
                    }
                    echo implode(', ', $tags );
                }
                break;

            case 'realized':
                $realized = get_post_meta( $post_id, '_realized', true );
                $auctions = get_the_terms( $post_id, 'auction' );
                if( ! empty( $auctions ) ){
                    $highest_auction_timestamp = 0;
                    foreach( $auctions as $a ){
                        $date = get_field( 'date', $a );
                        if( $date ){
                            $auction_timestamp = strtotime( $date );
                            if( $auction_timestamp > $highest_auction_timestamp )
                                $highest_auction_timestamp = $auction_timestamp;
                        }
                    }
                }
                $current_timestamp = current_time( 'timestamp' );
                if( $current_timestamp < $highest_auction_timestamp ){
                    echo '<code style="padding: 4px; border-radius: 3px; background-color: #999; color: #333;">TBD</code>';
                } else {
                    if( ! empty( $realized ) && is_numeric( $realized ) ){
                        echo AuctionShortcodes::format_price( $realized );
                    } else {
                        echo '<code style="padding: 4px; border-radius: 3px; background-color: #f00; color: #fff;">NOT SOLD</code>';
                    }
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
            'item_number' => 'Item No.',
            'title' => 'Item',
            'auction' => 'Auctions',
            'item_categories' => 'Categories',
            'item_tags' => 'Tags',
            'realized' => 'Sold',
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
            'has_archive' => true,
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
        $item_number = get_post_meta( $post->ID, '_item_number', true );
        $lotnum = get_post_meta( $post->ID, '_lotnum', true );
        $lot_bidding_url = get_post_meta( $post->ID, '_lot_bidding_url', true );
        $low_est = get_post_meta( $post->ID, '_low_est', true );
        $high_est = get_post_meta( $post->ID, '_high_est', true );
        $start_price = get_post_meta( $post->ID, '_start_price', true );
        $hammerprice = get_post_meta( $post->ID, '_hammerprice', true );
        $realized = get_post_meta( $post->ID, '_realized', true );
        $highlight = get_post_meta( $post->ID, '_highlight', true );
        $item_redirect = get_post_meta( $post->ID, '_item_redirect', true );
    ?>
        <input type="hidden" id="item_options" name="item_options" value="true" />
    <table class="form-table item-options table-striped">
        <colgroup><col width="20%" /><col width="60%" /><col width="20%" /></colgroup>
        <tr>
            <th scope="row" style="padding-left: 8px;"><strong>Item Number</strong></th>
            <td><label for="lotnum"><input id="item_number" type="number" style="width: 160px; text-align: right" name="item_number" value="<?php echo $item_number ?>" /></label></td>
            <td><code>_item_number</code></td>
        </tr>
        <tr>
            <th scope="row" style="padding-left: 8px;"><strong>Lot Number</strong></th>
            <td><label for="lotnum"><input id="lotnum" type="number" style="width: 160px; text-align: right" name="lotnum" value="<?php echo $lotnum ?>" /></label></td>
            <td><code>_lotnum</code></td>
        </tr>
        <tr>
            <th scope="row" style="padding-left: 8px;"><strong>Lot Bidding URL</strong></th>
            <td>
              <label for="lotbiddingurl"><input id="lotbiddingurl" type="text" style="width: 80%; text-align: left; margin-bottom: .5em" placeholder="https://" name="lot_bidding_url" value="<?php echo $lot_bidding_url ?>" /></label>
              <br>
              Include the full URL (with the <code>https://</code>) to the external bidding page.
            </td>
            <td><code>_lot_bidding_url</code></td>
        </tr>        
        <tr>
            <th scope="row" style="padding-left: 8px;"><strong>Low Estimate</strong></th>
            <td><label for="low_est"><input id="low_est" type="number" style="width: 160px; text-align: right" name="low_est" value="<?php echo $low_est ?>" /></label></td>
            <td><code>_low_est</code></td>
        </tr>
        <tr>
            <th scope="row" style="padding-left: 8px;"><strong>High Estimate</strong></th>
            <td><label for="high_est"><input id="high_est" type="number" style="width: 160px; text-align: right" name="high_est" value="<?php echo $high_est ?>" /></label></td>
            <td><code>_high_est</code></td>
        </tr>
        <tr>
            <th scope="row" style="padding-left: 8px;"><strong>Start Price</strong></th>
            <td><label for="start_price"><input id="start_price" type="number" style="width: 160px; text-align: right" name="start_price" value="<?php echo $start_price ?>" /></label></td>
            <td><code>_start_price</code></td>
        </tr>
        <tr>
            <th scope="row" style="padding-left: 8px;"><strong>Hammer Price</strong></th>
            <td><label for="realized"><input id="realized" type="number" style="width: 160px; text-align: right" name="hammerprice" value="<?php echo $hammerprice ?>" /></label></td>
            <td><code>_hammerprice</code></td>
        </tr>
        <tr>
            <th scope="row" style="padding-left: 8px;"><strong>Realized Price</strong></th>
            <td><label for="realized"><input id="realized" type="number" style="width: 160px; text-align: right" name="realized" value="<?php echo $realized ?>" /></label></td>
            <td><code>_realized</code></td>
        </tr>
        <tr>
            <th scope="row" style="padding-left: 8px;"><strong>Highlight Item</strong></th>
            <td><label for="highlight"><input type="checkbox" name="highlight" id="highlight" value="1"<?php if ( $highlight == true ) echo ' checked="checked"' ?> />Display this item in this auction's highlights.</label></td>
            <td><code>_highlight</code></td>
        </tr>
        <tr>
            <th scope="row" style="padding-left: 8px;"><strong>Sub-Auction Redirect</strong></th>
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
            <td><code>_item_redirect</code></td>
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

        $valid_fields = array( '_lotnum' => '', '_low_est' => '', '_high_est' => '', '_start_price' => '', '_hammerprice' => '', '_realized' => '', '_highlight' => false, '_item_redirect' => 0, '_item_number' => '', '_lot_bidding_url' => '' );
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