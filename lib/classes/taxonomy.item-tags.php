<?php
class ItemTagTaxonomy extends AuctionsAndItems{

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
        add_action( 'wp_ajax_query_item_tags', array( $this, 'query_items_callback' ) );
        add_action( 'wp_ajax_nopriv_query_item_tags', array( $this, 'query_items_callback' ) );
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

        // Register `Item Tag` custom taxonomies
        register_taxonomy(
            'item_tags',
            'item',
            array(
                'label' => 'Item Tags',
                'labels' => array( 'singular_name' => 'Item Tag', 'search_items' => 'Search Item Tags', 'popular_items' => 'Popular Item Tags', 'all_items' => 'All Item Tags', 'parent_item' => 'Parent Item Tag', 'parent_item_colon' => 'Parent Item Tag:', 'edit_item' => 'Edit Item Tag', 'update_item' => 'Update Item Tag', 'add_new_item' => 'Add New Item Tag', 'new_item_name' => 'New Item Tag Name' ),
                'hierarchical' => false,
                'query_var' => true
            ) );
    }

    /**
     * Returns JSON formatted query data as per a DataTables request.
     *
     * This method has been built to work with data sent
     * by datatables.js. In particular, this function receives the
     * following $_POST vars:
     *
     *  @type int $draw - Draw counter. This is used by DataTables to ensure that the Ajax returns from server-side
     *      processing requests are drawn in sequence by DataTables.
     *  @type int $start - Paging first record indicator, maps to $wp_query->$args->$offset.
     *  @type int $length - Number of records for the table to display, maps to $wp_query->$args->$post_per_page.
     *  @type int $auction - The WP taxonomy ID for the queried auction, maps to $wp_query->$args->$tax_query->$terms.
     *  @type int $order[0]['column'] - The column which specifies $wp_query->$args->$orderby.
     *  @type str $order[0]['dir'] - Sort by ASC|DESC, maps to $wp_query->$args->$order.
     *  @type str $search['value'] - Search string, maps to $wp_query->$args->$s.
     *
     * For more info, see the [DataTables Server-side Processing docs]
     * (http://datatables.net/manual/server-side).
     *
     * @since 1.x.x
     *
     * @return string JSON formatted auction query data.
     */
    public function query_items_callback(){

        $response = new stdClass(); // returned as JSON
        $args = array(); // passed to WP_Query( $args )

        $response->draw = $_POST['draw']; // $draw == 1 for the first request when the page is requested
        $response->show_realized = $_POST['show_realized'];

        // Which auction are we viewing?
        if( ! isset( $_POST['auction'] ) || empty( $_POST['auction'] ) ){
            $response->data = array( 'lotnum' => 'n/a', 'image' => 'n/a', 'title' => 'No auction ID!', 'desc' => 'n/a', 'price' => '$0.00' );
            $response->draw = 1;
            $response->recordsTotal = 1;
            $response->recordsFiltered = 1;
            $response->error = 'No auction ID received!';
            return $response;
        }
        $response->auction_id = (int) $_POST['auction'];
        $args['tax_query'] = array(
            array(
                'taxonomy' => 'auction',
                'terms' => $response->auction_id,
            ),
        );

        // Paging and offset
        $response->offset = ( isset( $_POST['start'] ) )? $_POST['start'] : 0;
        $args['offset'] = $response->offset;

        $response->posts_per_page = ( isset( $_POST['length'] ) )? (int) $_POST['length'] : 10;
        $args['posts_per_page'] = $response->posts_per_page;

        // Orderby
        if( ! isset( $response->order_key ) ){

            $cols = array( 1 => '_lotnum', 3 => 'title', 5 => '_realized' );
            if( false == $response->show_realized ){
                $cols[5] = '_low_est';
                $cols[6] = '_high_est';
            }
            $order_key = ( isset( $_POST['order'][0]['column'] ) && array_key_exists( $_POST['order'][0]['column'], $cols ) )? $_POST['order'][0]['column'] : 1;
            $response->order_key = $cols[$order_key];
        }

        switch( $response->order_key ){
            case 'title':
                $args['orderby'] = 'title';
            break;
            default:
                $args['orderby'] = 'meta_value_num';
                $args['meta_key'] = $response->order_key;
            break;
        }

        // Sorting (ASC||DESC)
        if( ! isset( $response->order ) )
            $response->order = strtoupper( $_POST['order'][0]['dir'] );
        $args['order'] = ( isset( $response->order ) )? $response->order : 'ASC';

        // Search
        if( isset( $_POST['search']['value'] ) ){
            $args['s'] = $_POST['search']['value'];
            $response->s = $args['s'];
        }

        $query = new WP_Query( $args );
        $data = array();
        if( $query->have_posts() ){
            $x = 0;
            while( $query->have_posts() ){
                $query->the_post();
                $data[$x]['lotnum'] = get_post_meta( get_the_ID(), '_lotnum', true );
                $data[$x]['price'] = AuctionShortcodes::format_price( get_post_meta( get_the_ID(), '_realized', true ) );
                $data[$x]['low_est'] = AuctionShortcodes::format_price( get_post_meta( get_the_ID(), '_low_est', true ) );
                $data[$x]['high_est'] = AuctionShortcodes::format_price( get_post_meta( get_the_ID(), '_high_est', true ) );

                $image = AuctionShortcodes::get_gallery_image( get_the_ID() );
                if ( empty( $image ) || stristr( $image, 'src=""' ) )
                $image = '<img src="' . plugin_dir_url( __FILE__ ) . '../images/placeholder.180x140.jpg" style="width: 100%;" alt="No image found." />';
                $data[$x]['image'] = $image;

                $title = get_the_title();
                //$title = preg_replace( '/Lot\W[0-9]+:\W/', '', $title ); // Remove `Lot #:` from title
                $data[$x]['title'] = $title;

                $replace = AuctionShortcodes::get_static( 'thumbnail_atts' );
                $desc_image = str_replace( $replace, 'style="margin-top: 10px; max-width: 400px; height: auto;" class="alignleft"', $image );
                $item_content = get_the_content() . "\n\n" . ' [<a href="' . get_permalink() . '" target="_blank">See more photos &rarr;</a>]';
                $data[$x]['desc'] = $desc_image . apply_filters( 'the_content', $item_content );

                // Thumbnail
                $img = genesis_get_image( array(
                    'format'  => 'html',
                    'size'    => genesis_get_option( 'image_size' ),
                    'context' => 'archive',
                    'attr'    => genesis_parse_attr( 'entry-image', array ( 'alt' => get_the_title() ) ),
                ) );
                $permalink = get_permalink();
                $data[$x]['thumbnail'] = sprintf( '<div class="image-frame"><span class="helper"></span><a href="%1$s" aria-hidden="true">%2$s</a></div><h2 class="entry-title"><a href="%4$s">%3$s</a></h2>', $permalink, $img, $title, $permalink );


                $x++;
            }

            $response->recordsFiltered = (int) $query->found_posts;
            $response->recordsTotal = (int) $query->found_posts;
            $response->data = $data;
        }
        wp_send_json( $response );
    }

}
?>