<?php
class AuctionShortcodes extends AuctionsAndItems{

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

    public function format_price( $price ){
		settype( $price, 'int' );
		return '$'. number_format( str_replace( '$', '', $price ), 2 );
    }

	private function get_gallery_image( $id = '', $cat_ID = '', $size = 'thumbnail', $return_url = false ) {
		global $wpdb, $post;
		if ( empty( $id ) ) $id = $post->ID;
		$image = $wpdb->get_row( 'SELECT ID, post_title, post_content, post_parent, guid, menu_order FROM ' . $wpdb->posts . ' WHERE post_parent='.$id.' AND post_type="attachment" AND post_mime_type LIKE "imag%" ORDER BY menu_order' );
		if ( $image ) {
			$data = image_get_intermediate_size( $image->ID, $size );
			if ( $return_url == true ) {
				return $data['url'];
			} else {
				( isset( $_GET['offset'] ) )? $offset = '?offset='.$_GET['offset'] : $offset = '';
				( !empty( $cat_ID ) && is_numeric( $cat_ID ) )? $link = get_category_link( $cat_ID ) : $link = get_permalink( $id ).$offset;
				$image = '<img src="'.$data['url'].'" class="size-thumbnail wp-image-'.$image->ID.'" style="width: 100%;" alt="'.get_the_title( $id ).'" title="'.get_the_title( $id ).'" />';
				return $image;
			}
		} else {
			return false;
		}
	}

	public function highlights_shortcode( $atts ){
		extract( shortcode_atts( array(
			'auction' => 0,
		), $atts ) );

		$flushcache = ( isset( $_GET['flushcache'] ) )? settype( $_GET['flushcache'], 'boolean' ) : false ;

		if( 0 == $auction || is_null( $auction ) )
			return;

		if ( false === ( $content = get_transient( 'auction_highlights_' . $auction ) ) || true == $flushcache ) {
			$content = array();
			$email = ( function_exists( 'cryptx' ) )? cryptx( 'info@caseantiques.com', '', '', 0 ) : '<a href="mailto:info@caseantiques.com">info@caseantiques.com</a>' ;

			$content[] = '<div class="alert alert-info highlight-alert"><p style="text-align: center">If you are interested in consigning items of this quality for future auctions, please contact us at ' . $email . '.<br />(<em>Note: Prices realized include a buyer\'s premium.</em>)</p></div>';

			$term = get_term( $auction, 'auction' );
			$args = array(
				'post_type' => 'item',
				'tax_query' => array(
					array(
						'taxonomy' => 'auction',
						'field' => 'slug',
						'terms' => $term->slug,
					)
				),
				'meta_query' => array(
					array(
						'key' => '_highlight',
						'value' => true,
						'compare' => '=',
					),
				),
				'posts_per_page' => -1,
				'orderby' => 'meta_value_num',
				'meta_key' => '_lotnum',
				'order' => 'ASC',
			);
			$posts = get_posts( $args );
			if( $posts ){
				global $post;

				foreach( $posts as $post ){
					setup_postdata( $post );
					$realized_price = get_post_meta( get_the_ID(), '_realized', true );
					$realized_price = $this->format_price( $realized_price );
					$low_est = get_post_meta( get_the_ID(), '_low_est', true );
					$low_est = $this->format_price( $low_est );
					$high_est = get_post_meta( get_the_ID(), '_high_est', true );
					$high_est = $this->format_price( $high_est );

					$image = '';
					$image = $this->get_gallery_image( $post->ID, null, 'medium' );
					$image_fullsize = $this->get_gallery_image( $post->ID, null, 'large', true );
					if ( !empty( $image_fullsize ) ) $image = '<a href="' . $image_fullsize . '" title="' . get_the_title() . ' - Realized: ' . $realized_price . '" rel="shadowbox[Gallery]">' . $image . '</a>';

					if ( empty( $image ) || stristr( $image, 'src=""' ) ) $image = '<img src="' . plugin_dir_url( __FILE__ ) . '../images/placeholder.180x140.jpg" style="width: 100%;" alt="No image found." />';
					$item_meta = '<h5>Low Estimate: '.$low_est.' &ndash; High Estimate: '.$high_est.'</h5><h5>Realized Price: '.$realized_price.'</h5>';

					$content[] = '<div class="highlight clearfix"><div class="first one-third">' . $image . '</div><div class="two-thirds"><h3><a href="' . get_the_permalink() . '">' . get_the_title() . '</a></h3>'.apply_filters( 'the_content', get_the_content() . $item_meta ).'</div></div>';
				}
			} else {
				$content[] = '<p class="clearfix alert alert-warning" style="text-align: center">No highlighted items found for this auction.</p>';
			}

			if( true == settype( $_GET['flushcache'], 'boolean' ) )
				$content[] = '<p class="clearfix" style="text-align: center;"><em>Auction Highlights generated on ' . date( 'l, F jS, Y \a\t g:ia', current_time( 'timestamp' ) ) . '</em></p>';

			$content = implode( "\n", $content );
			set_transient( 'auction_highlights_' . $auction, $content, 48 * HOUR_IN_SECONDS );
		}

		if( true == $flushcache )
			$content.= '<div class="alert alert-warning"><p style="text-align: center;"><em>The cache was flushed.</em></p></div>';

		return $content;
	}
}

$AuctionShortcodes = AuctionShortcodes::get_instance();
add_shortcode( 'highlights', array( $AuctionShortcodes, 'highlights_shortcode' ) );
?>