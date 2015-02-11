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

	private function get_gallery_image( $id = '', $return_url = false ) {
		global $wpdb, $post;
		if ( empty( $id ) ) $id = $post->ID;

		$args = array(
			'post_type' => 'attachment',
			'posts_per_page' => 1,
			'post_parent' => $id,
			'post_mime_type' => 'image',
			'orderby' => 'menu_order',
			'order' => 'ASC'
		);
		$image = get_posts( $args );
		if( ! $image )
			return false;


		$image_url = wp_get_attachment_url( $image[0]->ID );
		if( true == $return_url )
			return $image_url;

		$esc_title = esc_attr( get_the_title( $id ) );
		$image = '<img src="' . $image_url . '" alt="' . $esc_title . '" title="' . $esc_title . '" />';
		return $image;
	}

	/**
	 * Processes [highlights auction="ID"] shortcode
	 *
	 * @see get_term()	Retrieves the auction object for use in querying highlights.
	 *
	 * @since 1.0.0
	 *
	 * @param array $atts {
	 *		Array of shortcode attributes.
	 *
	 *		@type int $auction Auction taxonomy ID.
	 * }
	 * @return string HTML for auction highlights.
	 */
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
					$image = $this->get_gallery_image( $post->ID );

					if ( empty( $image ) || stristr( $image, 'src=""' ) )
						$image = '<img src="' . plugin_dir_url( __FILE__ ) . '../images/placeholder.180x140.jpg" style="width: 100%;" alt="No image found." />';

					$item_meta = '<h5>Low Estimate: '.$low_est.' &ndash; High Estimate: '.$high_est.'</h5><h5>Realized Price: '.$realized_price.'</h5>';

					$content[] = '<div class="highlight clearfix"><div class="first one-third" style=""><a href="' . get_permalink() . '" title="' . esc_attr( get_the_title() ) . '">' . $image . '</a></div><div class="two-thirds"><h3><a href="' . get_permalink() . '">' . get_the_title() . '</a></h3>'.apply_filters( 'the_content', get_the_content() . $item_meta ).'</div></div>';
				}
			} else {
				$content[] = '<p class="clearfix alert alert-warning" style="text-align: center">No highlighted items found for this auction.</p>';
			}

			if( true == $flushcache )
				$content[] = '<p class="clearfix" style="text-align: center;"><em>Auction Highlights generated on ' . date( 'l, F jS, Y \a\t g:ia', current_time( 'timestamp' ) ) . '</em></p>';

			$content = implode( "\n", $content );
			set_transient( 'auction_highlights_' . $auction, $content, 48 * HOUR_IN_SECONDS );
		} else if( is_user_logged_in() && current_user_can( 'activate_plugins' ) ) {
			global $post;
			$content = '<div class="alert alert-warning" style="text-align: center;"><h4><strong>NOTICE:</strong> The highlights shown below have been pulled from cache. If the list appears incomplete, <a href=" ' . get_permalink( $post->ID ) . '?flushcache=true">CLICK HERE</a> to refresh the cache.</h4><p><em>This notice only shows to logged in Case Antiques administrators.</em></p></div>' . $content;
		}

		if( true == $flushcache )
			$content = '<div class="alert alert-success"><p style="text-align: center;"><strong>SUCCESS:</strong> The cache was flushed.</p></div>' . $content;

		return $content;
	}
}

$AuctionShortcodes = AuctionShortcodes::get_instance();
add_shortcode( 'highlights', array( $AuctionShortcodes, 'highlights_shortcode' ) );
?>