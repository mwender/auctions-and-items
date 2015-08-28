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

    public function enqueue_scripts(){
    	wp_register_style( 'footable', plugin_dir_url( __FILE__ ) . '../../bower_components/footable/css/footable.core.min.css', null, filemtime( plugin_dir_path( __FILE__ ) . '../../bower_components/footable/css/footable.core.min.css' ) );
    	//wp_register_style( 'footable-metro', plugin_dir_url( __FILE__ ) . '../../bower_components/footable/css/footable.metro.min.css', array( 'footable' ), filemtime( plugin_dir_path( __FILE__ ) . '../../bower_components/footable/css/footable.metro.min.css' ) );

    	wp_register_script( 'footable', plugin_dir_url( __FILE__ ) . '../../bower_components/footable/js/footable.js', array( 'jquery' ), filemtime( plugin_dir_path( __FILE__ ) . '../../bower_components/footable/js/footable.js' ) );


    	wp_register_script( 'footable-sort', plugin_dir_url( __FILE__ ) . '../../bower_components/footable/js/footable.sort.js', array( 'jquery', 'footable' ), filemtime( plugin_dir_path( __FILE__ ) . '../../bower_components/footable/js/footable.sort.js' ) );

		wp_register_script( 'footable-filter', plugin_dir_url( __FILE__ ) . '../../bower_components/footable/js/footable.filter.js', array( 'jquery', 'footable' ), filemtime( plugin_dir_path( __FILE__ ) . '../../bower_components/footable/js/footable.filter.js' ) );

		wp_register_script( 'footable-striping', plugin_dir_url( __FILE__ ) . '../../bower_components/footable/js/footable.striping.js', array( 'jquery', 'footable' ), filemtime( plugin_dir_path( __FILE__ ) . '../../bower_components/footable/js/footable.striping.js' ) );


    	wp_register_script( 'footable-user', plugin_dir_url( __FILE__ ) . '../js/footable.js' , array( 'jquery', 'footable' ), filemtime( plugin_dir_path( __FILE__ ) . '../js/footable.js' ) );
    }

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

		$metadata = wp_get_attachment_metadata( $image[0]->ID );
		if( ! $metadata )
			return false;

		$upload_dir = wp_upload_dir();

		$image_url = trailingslashit( $upload_dir['baseurl'] ) . dirname( $metadata['file'] ) . '/' . $metadata['sizes']['medium']['file'];

		if( true == $return_url )
			return $image_url;

		$esc_title = esc_attr( get_the_title( $id ) );
		$image = '<img src="' . $image_url . '" alt="' . $esc_title . '" title="' . $esc_title . '" style="max-height: 50px; width: auto;" />';
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
		wp_enqueue_style( 'footable' );
		wp_enqueue_script( 'footable-sort' );
		wp_enqueue_script( 'footable-filter' );
		wp_enqueue_script( 'footable-striping' );
		wp_enqueue_script( 'footable-user' );

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

				$rows = array();
				foreach( $posts as $post ){
					setup_postdata( $post );
					$lotnum = get_post_meta( get_the_ID(), '_lotnum', true );
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

					//$content[] = '<div class="highlight clearfix"><div class="first one-third" style=""><a href="' . get_permalink() . '" title="' . esc_attr( get_the_title() ) . '">' . $image . '</a></div><div class="two-thirds"><h3><a href="' . get_permalink() . '">' . get_the_title() . '</a></h3>'.apply_filters( 'the_content', get_the_content() . $item_meta ).'</div></div>';
					$title = get_the_title();
					$title = preg_replace( '/Lot\W[0-9]+:\W/', '', $title );

					$desc_image = str_replace( 'style="max-height: 50px; width: auto;"', 'style="max-width: 400px; height: auto;" class="alignleft"', $image );

					$content = get_the_content() . ' [<a href="' . get_permalink() . '" target="_blank">See more photos &rarr;</a>]';

					$rows[] = '<tr>
						<td>' . $lotnum . '</td>
						<td>' . $image . '</td>
						<td><a href="' . get_permalink() . '" target="_blank">' . $title . '</a></td>
						<td>' . $desc_image . apply_filters( 'the_content', $content ) . '</td>
						<td>'.$realized_price.'</td>
					</tr>';
				}
			} else {
				$content[] = '<p class="clearfix alert alert-warning" style="text-align: center">No highlighted items found for this auction.</p>';
			}

			$content = ( is_array( $content ) )? implode( "\n", $content ) : $content;

			$format_table = '
<div class="row" style="margin-bottom: 20px;">
	<div class="col-sm-9 legend">
		<strong>Legend:</strong> <span class="footable-icon footable-sort-indicator"></span> Click to sort <span class="footable-icon footable-toggle"></span> Click for item description and a link to more photos
	</div>
	<div class="col-sm-3" style="text-align: right;">
		<form class="form-inline">
			<div class="form-group">
				<label class="sr-only" for="search-highlights">Search:</label>
				<div class="input-group" style="width: 100%;">
					<input type="text" value="" class="form-control input-lg" id="search-highlights" placeholder="Search" />
					<div class="input-group-addon clear-filter">Clear</div>
				</div>
			</div>
		</form>
	</div>
</div>
<table class="footable metro-centric-red" data-filter="#search-highlights" data-page-size="4">
	<colgroup>
		<col style="width: 10%%" />
		<col style="width: 20%%" />
		<col style="width: 55%%" />
		<col style="width: 15%%" />
	</colgroup>
	<thead><tr>
		<th data-hide="phone" data-type="numeric">Lot No.</th>
		<th data-sort-ignore="true">Thumbnail</th>
		<th data-hide="phone,tablet">Title</th>
		<th data-hide="all">Description</th>
		<th data-type="numeric" data-sort-initial="descending">Realized Price</th>
	</tr></thead>
	<tbody>%1$s</tbody>
</table>';
			$table = sprintf( $format_table, implode( "\n", $rows ) );
			$content.= $table;

			set_transient( 'auction_highlights_' . $auction, $content, 48 * HOUR_IN_SECONDS );
		} else if( is_user_logged_in() && current_user_can( 'activate_plugins' ) ) {
			global $post;
			$content = '<div class="alert alert-warning" style="text-align: center;"><h4><strong>NOTICE:</strong> The highlights shown below have been pulled from cache. If the list appears incomplete, <a href=" ' . get_permalink( $post->ID ) . '?flushcache=true">CLICK HERE</a> to refresh the cache.</h4><p><em>This notice only shows to logged in Case Antiques administrators.</em></p></div>' . $content;
		}

		if( true == $flushcache )
			$content = '<div class="alert alert-success"><p style="text-align: center;"><strong>SUCCESS:</strong> The cache was flushed.</p></div>' . $content;

		if( true == $flushcache )
			$content.= '<p class="clearfix" style="text-align: center;"><em>Auction Highlights generated on ' . date( 'l, F jS, Y \a\t g:ia', current_time( 'timestamp' ) ) . '</em></p>';

		return $content;
	}
}

$AuctionShortcodes = AuctionShortcodes::get_instance();
add_shortcode( 'highlights', array( $AuctionShortcodes, 'highlights_shortcode' ) );
add_action( 'wp_enqueue_scripts', array( $AuctionShortcodes, 'enqueue_scripts' ) );
?>
