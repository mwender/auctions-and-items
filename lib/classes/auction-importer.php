<?php
class AuctionImporter extends AuctionsAndItems{

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

    public function admin_enqueue_scripts( $hook ){
    	if( 'item_page_import-items' == $hook ){
    		wp_enqueue_script( 'import-csv', plugin_dir_url( __FILE__ ) . '../js/import-csv.js', array( 'jquery', 'media-upload', 'thickbox' ), filemtime( plugin_dir_path( __FILE__ ) . '../js/import-csv.js' ) );
    		wp_localize_script( 'import-csv', 'ajax_vars', array( 'ajax_url' => admin_url( 'admin-ajax.php' ) ) );
    		wp_enqueue_style( 'import-csv', plugin_dir_url( __FILE__ ) . '../css/import-csv.css', array(), 1.0, 'screen' );
    	}
    }

	/**
	 * Adds a our Auction Import page to the admin menu.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
    public function admin_menu(){
    	global $auctionimporter_hook;

    	$auctionimporter_hook = add_submenu_page( 'edit.php?post_type=item', 'Import Auction Items', 'Import Auction', 'edit_posts', 'import-items', array( $this, 'auction_import_page' ) );
    }

    public function auction_importer_callback(){
    	// Restrict access to WordPress `editor` role or higher
    	if( ! current_user_can( 'delete_posts' ) )
    		return;

    	$response = new stdClass();

    	$cb_action = $_POST['cb_action'];
    	$id = $_POST['csvID'];

    	switch( $cb_action ){

    		case 'delete_csv':
				wp_delete_attachment( $id );
				delete_transient( 'csv_' . $id ); // deletes transient set in $this->open_csv()
				$data['deleted'] = true;

				$response->data = $data;
    		break;

    		case 'get_csv_list':
				$args = array(
					'post_type' => 'attachment',
					'numberposts' => -1,
					'post_mime_type' => 'text/csv',
					'orderby' => 'date',
					'order' => 'DESC'

				);
				$files = get_posts( $args );
				$x = 0;
				foreach ( $files as $file ) {
					setup_postdata( $file );
					$data['csv'][$x]['id'] = $file->ID;
					$data['csv'][$x]['post_title'] = $file->post_title;
					$data['csv'][$x]['timestamp'] = date( 'm/d/y g:i:sa', strtotime( $file->post_date ) );
					$data['csv'][$x]['filename'] = basename( $file->guid );
					$data['csv'][$x]['image_folder'] = get_post_meta( $file->ID, '_image_folder', true );
					$data['csv'][$x]['last_import'] = get_post_meta( $file->ID, '_last_import', true );
					if ( empty( $data['csv'][$x]['last_import'] ) )
						$data['csv'][$x]['last_import'] = 0;
					$args = array( 'taxonomy'=>'auction', 'name'=>'auction-'.$file->ID, 'id'=>'auction-'.$file->ID, 'echo'=>false, 'hierarchical'=>true, 'orderby'=>'name', 'hide_empty'=>false, 'selected'=>get_post_meta( $file->ID, '_auction', true ), 'show_option_none'=>'Select an auction...' );
					$auction_cats = wp_dropdown_categories( $args );
					$auction_cats = preg_replace( "#<select([^>]*)>#", "<select$1 onchange=\"updateCSVAuction($file->ID, this.options[this.selectedIndex].value);\">", $auction_cats );
					$data['csv'][$x]['auction'] = $auction_cats;
					$x++;
				}
				$data['imgfolders'] = $this->get_img_dirs();

				$response->data = $data;
    		break;

    		case 'import_csv':
				$limit = 1; // limit the number of rows to import
				$offset = $_POST['csvoffset'];

				$response->id = $id;
				$response->title = get_the_title( $id );

				// Get the URL and filename of the CSV
				$url = wp_get_attachment_url( $id );
				$response->url = $url;
				$response->filename = basename( $url );

				// Get the auction assigned to this CSV
				$auction = get_post_meta( $id, '_auction', true );
				$term = get_term( $auction, 'auction' );
				$auction_slug = $term->slug;

				// Get the folder where this auction's images are stored
				$imgpath = get_post_meta( $id, '_image_folder', true );

				// Open this CSV
				$csvfile = str_replace( get_bloginfo( 'url' ) . '/', ABSPATH, $url );
				$csv = $this->open_csv( $csvfile, $id );
				$response->total_rows = count( $csv['rows'] );
				$csv['rows'] = array_slice( $csv['rows'], $offset, $limit );
				$response->selected_rows = count( $csv['rows'] );
				$response->csv = $csv;
				foreach ( $response->csv['rows'] as $row ) {
					$x = 0;
					$item = array();
					foreach ( $row as $key => $value ) {
						$assoc_key = $csv['columns'][$x];
						$item[$assoc_key] = $value;
						$x++;
					}
					$last_import = $offset + $limit;

					$args = array(
						'item' => $item,
						'auction' => $auction,
						'auction_slug' => $auction_slug,
						'csvID' => $id,
						'offset' => $last_import,
					);
					$post_ID = $this->import_item( $args );
					$upload_dir = wp_upload_dir();
					$imgdir = $upload_dir['basedir'] . '/auctions/' . $imgpath . '/';
					$response->images = $this->get_img_from_dir( $post_ID, $item['LotNum'], $imgdir );
					$response->post_ID = $post_ID;
					$response->imgdir = $imgdir;
				}
				$response->current_offset = ( 1 == $limit )? 'Importing row ' . ( $offset + 1 ) : 'Importing rows '.( $offset + 1 ).' - '.( $offset + $limit );
				$response->offset = $offset + $limit;
    		break;

    		case 'import_image':
				$post_ID = $_POST['itemID'];
				$imgdir = $_POST['imgdir'];
				$image = $_POST['image'];
				$import_status = $this->import_single_attachment( $post_ID, $imgdir, $image );
				$response->message = ( true == $import_status )? 'SUCCESS: Imported ' . $image . ', attached to ' . get_the_title( $post_ID ). ' (' . $post_ID . ').' : 'FAIL: Unable to import ' . $image;
				$response->status = $import_status;
				$response->image = $image;
    		break;

    		case 'load_csv':
				$url = wp_get_attachment_url( $id );
				$data['id'] = $id;
				$data['url'] = $url;
				$data['title'] = get_the_title( $id );
				$data['filename'] = basename( $url );
				$csvfile = str_replace( get_bloginfo( 'url' ). '/', ABSPATH, $url );
				$data['filepath'] = $csvfile;
				$data['csv'] = $this->open_csv( $csvfile, $id );
				$data['imgpath'] = get_post_meta( $id, '_image_folder', true );
				if ( $auction_id = get_post_meta( $id, '_auction', true ) ) {
					if ( $term = get_term( $auction_id, 'auction' ) ) {
						$auction = $term->name;
					} else {
						$auction = '<em>Does not exist (term_id = '.$auction_id.').</em>';
					}
				} else {
					$auction = '<em>Not set.</em>';
				}
				$data['auction'] = $auction;
				$data['offset'] = 0;

				$response->csv = $data;
    		break;

    		case 'updateauction':
				// verify if this is an auto save routine. If it is our form has not been submitted, so we don't want to do anything
				if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) die();
				$auction = $_POST['auction'];
				if ( is_numeric( $id ) ) {
					if ( get_post_meta( $id, '_auction' ) == '' )
						add_post_meta( $id, '_auction', $auction );
					elseif ( $auction != get_post_meta( $id, '_auction', true ) )
						update_post_meta( $id, '_auction', $auction );
					elseif ( $auction == '' )
						delete_post_meta( $id, '_auction', get_post_meta( $id, '_auction', true ) );
					$data['status'] = 'Saved.';
				} else {
					$data['status'] = 'Not saved! Try again.';
				}

				$response->data = $data;
    		break;

    		case 'updateimgpath':
				// verify if this is an auto save routine. Then the form has not been submitted, so we don't want to do anything
				if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) die();
				$imgpath = $_POST['imgpath'];
				if ( is_numeric( $id ) ) {
					if ( get_post_meta( $id, '_image_folder' ) == '' )
						add_post_meta( $id, '_image_folder', $imgpath );
					elseif ( $imgpath != get_post_meta( $id, '_image_folder', true ) )
						update_post_meta( $id, '_image_folder', $imgpath );
					elseif ( $imgpath == '' )
						delete_post_meta( $id, '_image_folder', get_post_meta( $id, '_image_folder', true ) );
					$data['status'] = 'Saved.';
				} else {
					$data['status'] = 'Not saved! Try again.';
				}

				$response->data = $data;
    		break;
    	}

    	wp_send_json( $response );
    }

	/**
	 * Displays our Auction Import page.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
    public function auction_import_page(){
		?>
	<div class="wrap">
		<h2>Import Auction</h2>
		<p>For instructions, please click on the "Help" button in the upper right hand corner, underneath your username.</p>
		<div id="import-progress">
			<h2></h2>
			<div id="import-progress-container"><div id="import-progress-bar"></div><div id="import-percent"></div></div>
			<p id="import-stats"></p>
			<p id="import-image-stats"></p>
			<p id="import-note"><strong>IMPORTANT:</strong> Do not leave or refresh this screen until the import completes!</p>
		</div>

		<div id="import-table" style="display: none;">
			<h3>Import Preview for <span id="csv-name">One moment. Loading...</span> <span id="stats"></span></h3>
			<h4 id="run-import"></h4>
			<table class="widefat page" id="csvimport" style=" margin-bottom: 60px;">
				<thead><tr></tr></thead>
				<tbody></tbody>
			</table>
		</div>

		<table class="widefat page" id="csv_list">
			<col width="30%" /><col width="30%" /><col width="20%" /><col width="10%" /><col width="10%" />
			<thead><tr><th scope="col" class="manage-column">Title/Filename</th><th scope="col" class="manage-column">Image Folder</th><th scope="col" class="manage-column">Auction</th><th scope="col" class="manage-column">Last Import</th><th scope="col" class="manage-column">&nbsp;</th></tr></thead>
			<tbody><tr class="alternate"><td colspan="5" style="text-align: center">One moment. Loading CSV list...</td></tr></tbody>
		</table>

		<div id="upload-csv">
			<h4>Upload a CSV</h4>
			<input id="upload_csv" type="text" size="36" name="upload_csv" value="" />
			<input id="upload_csv_button" type="button" value="Upload CSV" />
			<br />Upload a CSV file to the server.
		</div>

	</div>
		<?php
    }

	/**
	 * Checks for the existance of DB objects related to this plugin.
	 *
	 * Currently, this function can check for the existence of:
	 *
	 * 	- `item` CPTs
	 * 	- Attachments of a given post_parent, post_title.
	 *
	 * @see $wpdb->get_var(), get_posts()
	 * @global object $wpdb WP global DB object.
	 *
	 * @access $this->import_item(), $this->get_img_from_dir()
	 * @since 1.0.0
	 *
	 * @param array $args Array of arguments.
	 * @return int|bool Returns the ID of the DB object or `false`.
	 */
	private function auction_object_exists( $args ){
		$defaults = array(
			'exists' => 'item',
			'lotnum' => null,
			'auction_slug' => null,
			'post_parent' => null,
			'post_title' => null,
		);

		$args = wp_parse_args( $args, $defaults );

		switch( $args['exists'] ){
			case 'attachment':
				global $wpdb;
				$attachment_id = $wpdb->get_var( 'SELECT ID FROM ' . $wpdb->posts . ' WHERE post_type="attachment" AND post_parent=' . $args['post_parent'] . ' AND post_title="' . $args['post_title'] . '"' );
				return ( $attachment_id )? $attachment_id : false;
			break;

			case 'item':
				$get_posts_args = array(
					'meta_value' => $args['lotnum'],
					'meta_key' => '_lotnum',
					'post_type' => 'item',
					'taxonomy' => 'auction',
					'term' => $args['auction_slug'],
				);

				$items = get_posts( $get_posts_args );
				if( $items ){
					/*
					foreach( $items as $item ){
						return $item->ID;
					}
					*/
					return $items[0]->ID;
				} else {
					return false;
				}
			break;
		}
	}

	/**
	 * Returns contextual help for the Auction Importer
	 *
	 * @since 1.0.0
	 *
	 * @param string $contextual_help HTML for contextual help.
	 * @param string $screen_id ID for the current screen.
	 * @param string $screen
	 * @return string HTML for display in the WordPress contextual help section.
	 */
    public function contextual_help( $contextual_help, $screen_id, $screen ){
    	global $auctionimporter_hook;

    	if( $auctionimporter_hook == $screen_id )
    		$contextual_help = file_get_contents( plugin_dir_path( __FILE__ ) . '../html/help.auction-importer.html' );

    	return $contextual_help;
    }

	/**
	 * Returns a list of images prefixed with the given lotnum that are not already WP attachments
	 */
	function get_img_from_dir( $post_ID, $lotnum, $imgdir ) {
		if ( $dh = @dir( $imgdir ) ) {
			global $wpdb;
			while ( false !== ( $entry = $dh->read() ) ) {
				$fn = array();
				$pattern = '/^([0-9]+[a-zA-Z]*)_([0-9]+)\.([a-zA-Z]+)/'; // matches 101_1.jpg, 101_2.jpg, 101a_1.jpg, etc.

				// skip hidden files
				if ( $entry[0] == "." ) continue;

				if ( is_dir( $imgdir.$entry ) ) {
					continue;
				} else if ( is_file( $imgdir.$entry ) ) {
						preg_match( $pattern, $entry, $matches );
						if ( $matches ) {
							if ( $matches[1] == $lotnum ) {
								$attachment_id = $this->auction_object_exists( array( 'exists' => 'attachment', 'post_parent' => $post_ID, 'post_title' => $entry ) );
								if ( false == $attachment_id ) { // attachment doesn't exist, add to array for import
									$data[] = $entry;
								} else { // attachment exists, don't add to array and update database record for that attachment
									preg_match( $pattern , $entry, $matches );
									$wpdb->update( $wpdb->posts, array( 'menu_order' => $matches[2] ), array( 'ID' => $attachment_id ), array( '%d' ), array( '%d' ) );
								}
							}
						} // if($matches)
					}
			}
			$dh->close();
		} else {
			$data[] = 'Failed opening '.$imgdir.' for reading.';
		}
		return $data;
	}

	/**
	 * Returns all dirs found under /wp-content/uploads/auctions/
	 *
	 * @since 1.0.0
	 *
	 * @return array Array of dirs under /wp-content/uploads/auctions/
	 */
    public function get_img_dirs(){
		$upload_dir = wp_upload_dir();
		$imagedir = $upload_dir['basedir'] . '/auctions/';
		if ( $dh = @dir( $imagedir ) ) {
			while ( false !== ( $entry = $dh->read() ) ) {
				// skip hidden files
				if ( '.' == $entry[0] ) continue;

				if ( is_dir( $imagedir.$entry ) )
					$data[] = $entry;
			}
			$dh->close();
		} else {
			$data[] = 'Failed opening '.$imagedir.' for reading.';
		}
		return $data;
    }

	/**
	 * Imports an `item` CPT
	 *
	 * @since 1.0.0
	 *
	 * @param array $args {
	 * 		An array of arguments.
	 *
	 *		@type array		$item			Item array with keys Title, Description, LotNum,
	 *										LowEst, HighEst, StartPrice, Realized, and Highlight
	 *		@type int		$auction		Auction taxonomy ID.
	 *		@type string 	$auction_slug 	Auction taxonomy slug.
	 *		@type int		$csvID			Post ID of the CSV this item was imported from.
	 *		@type int 		$offset 		The next row from the CSV to import.
	 *
	 * }
	 * @return int Post ID of created/update `item` CPT.
	 */
	public function import_item( $args ) {

		$defaults = array(
			'item' => null,
			'auction' => null,
			'auction_slug' => '',
			'csvID' => null,
			'offset' => null,
		);

		$args = wp_parse_args( $args, $defaults );
		extract( $args );


		if ( $item['Title'] == 'NO LOT' )
			return;

		$tagitem = true;

		// if this item exists, add the ID to the query so that it gets updated
		if ( $postid = $this->auction_object_exists( array( 'exists' => 'item', 'lotnum' => $item['LotNum'], 'auction_slug' => $auction_slug ) ) ) {
			$post['ID'] = $postid;
			$tagitem = false;
		}

		$post['post_title'] = $item['Title'];
		$post['post_content'] = $item['Description'];
		$post['post_type'] = 'item';
		$post['post_status'] = 'publish';
		$post_ID = wp_insert_post( $post );

		// Add this item to an auction
		if ( $tagitem == true )
			wp_set_object_terms( $post_ID, array( intval( $auction ) ), 'auction' );

		// assign the item to any specified galleries
		if ( !empty( $item['Gallery'] ) ) {
			$galleries = array();
			$galleries = explode( ',', $item['Gallery'] );
			$galleries = array_map( 'intval', $galleries );
			$galleries = array_unique( $galleries );
			wp_set_object_terms( $post_ID, $galleries, 'gallery' );
		} else {
			wp_set_object_terms( $post_ID, null, 'gallery' ); // remove all galleries for an item
		}
		update_post_meta( $post_ID, '_lotnum', $item['LotNum'] );
		update_post_meta( $post_ID, '_low_est', $item['LowEst'] );
		update_post_meta( $post_ID, '_high_est', $item['HighEst'] );
		update_post_meta( $post_ID, '_start_price', $item['StartPrice'] );
		update_post_meta( $post_ID, '_realized', $item['Realized'] );
		$highlight = ( $item['Highlight'] == 'TRUE' || $item['Highlight'] == 'true' )? 1 : 0;
		update_post_meta( $post_ID, '_highlight', $highlight );

		/**
		 * IGAVEL AUCTION LINKS
		 *
		 * Add a meta field for iGavel lot numbers which we'll use in single-item.php to build an iGavel
		 * link like the following example:
		 *
		 * http://bid.igavelauctions.com/Bidding.taf?_function=detail&Auction_uid1=2872353
		 */
		if ( !empty( $item['iGavelLotNum'] ) )
			update_post_meta( $post_ID, '_igavel_lotnum', $item['iGavelLotNum'] );

		if ( !empty( $csvID ) && !empty( $offset ) )
			update_post_meta( $csvID, '_last_import', $offset );

		return $post_ID;
	}

	/**
	 * Adds a given image to an item post_type.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_ID Item post ID.
	 * @param str $imgdir Directory where attachment image is found.
	 * @param str $image Filename of the image.
	 * @return bool Success (true||false)
	 */
	public function import_single_attachment( $post_ID = '', $imgdir = '', $image = '' ) {
		if( empty( $post_ID ) || empty( $imgdir ) || empty( $image ) )
			return $data['message'] = 'ERROR: Empty argument supplied to theme_import_single_attachment!';

		$import_status = false;

		$update = false;
		if ( file_exists( $imgdir.$image ) ) {
			$wp_filetype = wp_check_filetype( $image, null );
			preg_match( '/^([0-9]+[a-zA-Z]*)_([0-9]+)\.([a-zA-Z]+)/' , $image, $matches );
			$attachment = array(
				'post_mime_type' => $wp_filetype['type'],
				'post_title' => $image,
				'post_content' => '',
				'post_status' => 'inherit',
				'menu_order' => intval( $matches[2] )
			);
			if ( $attachment_id = $this->auction_object_exists( array( 'exists' => 'attachment', 'post_parent' => $post_ID, 'post_title' => $image ) ) ) {
				$attachment['ID'] = $attachment_id;
				$update = true;
			}

			// Remove unused image sizes from Centric Pro theme
			remove_image_size( 'featured-page' );
			remove_image_size( 'featured-post' );

			$attach_id = wp_insert_attachment( $attachment, $imgdir.$image, $post_ID );
			if ( $update == false ) {
				require_once ABSPATH . "wp-admin" . '/includes/image.php';
				$attach_data = wp_generate_attachment_metadata( $attach_id, $imgdir.$image );
				wp_update_attachment_metadata( $attach_id,  $attach_data );
			}

			$import_status = true;
		}

		return $import_status;
	}

	/**
	 * Opens a CSV file, populates an array, and returns said array
	 */
	public function open_csv( $csvfile = '', $csvID = null ) {
		if( empty( $csvfile ) )
			return $csv['error'] = 'No CSV specified!';

		if( false === ( $csv = get_transient( 'csv_' . $csvID ) ) ) {
			$csv = array( 'row_count' => 0, 'column_count' => 0, 'columns' => array(), 'rows' => array() );
			if ( !empty( $csvfile ) && file_exists( $csvfile ) ) {
				if ( ( $handle = @fopen( $csvfile, 'r' ) ) !== false ) {
					$x = 0;
					while ( $row = fgetcsv( $handle, 2048, ',' ) ) {
						if ( $x == 0 ) {
							// trim spaces from column headings
							foreach( $row as $key => $heading ){
								$row[$key] = trim( $heading );
							}
							$csv['columns'] = $row;
						} else {
							array_walk( $row, array( $this, 'trim_csv_row' ) );
							$csv['rows'][] = $row;
							$csv['row_count']++;
						}
						$x++;
					}
					$csv['column_count'] = count( $csv['columns'] );
				}
			}
			set_transient( 'csv_' . $csvID, $csv );
		}

		return $csv;
	}

	/**
	 * Trim spaces from CSV column values
	 */
	function trim_csv_row( &$value, $key ){
		$value = htmlentities( utf8_encode( trim( $value ) ), ENT_QUOTES, 'UTF-8' );
	}
}

$AuctionImporter = AuctionImporter::get_instance();

add_action( 'admin_menu', array( $AuctionImporter, 'admin_menu' ) );
add_action( 'admin_enqueue_scripts', array( $AuctionImporter, 'admin_enqueue_scripts' ) );
add_action( 'wp_ajax_auction_importer', array( $AuctionImporter, 'auction_importer_callback' ) );
add_action( 'contextual_help', array( $AuctionImporter, 'contextual_help' ), 10, 3 );
?>