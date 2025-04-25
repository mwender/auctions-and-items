<?php
use function AuctionsAndItems\utilities\{get_alert};

class AuctionImporter extends AuctionsAndItems{

    private static $instance = null;

    public static function get_instance() {
        if( null == self::$instance ) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    private function __construct() {
    	add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
		add_action( 'wp_ajax_auction_importer', array( $this, 'auction_importer_callback' ) );
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
    	$auctionimporter_hook = add_submenu_page( 'edit.php?post_type=item', 'Import Auction Items', 'Import Auction', 'edit_posts', 'import-items', array( $this, 'auction_import_page' ) );

    	if( $auctionimporter_hook )
    		add_action( 'load-' . $auctionimporter_hook, array( $this, 'contextual_help_tabs' ) );
    }

    public function auction_importer_callback(){
    	// Restrict access to WordPress `editor` role or higher
    	if( ! current_user_can( 'delete_posts' ) )
    		return;

    	$response = new stdClass();

    	$cb_action = $_POST['cb_action'];
    	$id = ( isset( $_POST['csvID'] ) )? $_POST['csvID'] : null ;

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
						$args = array( 'taxonomy' => 'auction', 'name' => 'auction-'.$file->ID, 'id' => 'auction-'.$file->ID, 'echo'=>false, 'hierarchical'=>true, 'orderby' => 'name', 'hide_empty' => false, 'selected' => get_post_meta( $file->ID, '_auction', true ), 'show_option_none' => 'Select an auction...' );
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

					//$csvfile = str_replace( get_bloginfo( 'url' ) . '/', ABSPATH, $url );
					$csvfile = get_attached_file( $id );
					uber_log('🔔🔔🔔 👉 $csvfile = ' . $csvfile );

				  /*
					 * Open this CSV
					 *
					 * open_csv() returns: 
					 *
					 * @structure array $csv The processed CSV data stored in the transient:
					 *     - `row_count` (int) The number of data rows in the CSV.
					 *     - `column_count` (int) The number of columns in the CSV.
					 *     - `columns` (array) An indexed array of column headers.
					 *     - `rows` (array) A multidimensional array containing CSV row data.
					 *     - `error` (string) An error message if no CSV is specified.
					 */
					$csv = $this->open_csv( $csvfile, $id );
					$response->total_rows = count( $csv['rows'] );
					$csv['rows'] = array_slice( $csv['rows'], $offset, $limit );
					$response->selected_rows = count( $csv['rows'] );
					$response->csv = $csv;
					foreach ( $response->csv['rows'] as $row ) {
						$x = 0;
						$item = array();
						foreach ( $row as $key => $value ) {
							$assoc_key = strtolower( $csv['columns'][$x] );
							$item[$assoc_key] = $value;
							$x++;
						}
						$last_import = $offset + $limit;

						/**
						 * FOR NEXT TIME:
						 * 
						 * 03/10/2025 (17:05) - Continue working on below to-dos:
						 * 
						 * TODO:
						 * - Update previous code to require `itemNumber` column in the import CSV.
						 *   - [x] Update Import Auction preview to display ItemNumber.
						 *   - [x] Throw an error if no `ItemNumber`.
						 *   - [x] Handle error in import-csv.js if no `ItemNumber`.
						 *   - [x] Add admin CSS with styling for .alert.alert-warning
						 *   - [x] Allow editing "Item Number"
						 *   - [x] Include "Item Number" in admin listing.
						 *   - [x] Add `hammerprice`.
						 *   - [x] `hammerprice` to become `realizedprice`
						 *   - [ ] Update how online bidding URLs work. Just use LotBiddingURL
						 *   - [x] Allow editing "Lot Bidding URL".
						 * 
						 * - Update following code to work with the new required `itemNumber` field.
						 *   - We don't need to skip image processing below if `itemNumber` is present.
						 *   - Perhaps we skip image processing when the auction item already exists?
						 */

						$args = array(
							'item' => $item,
							'auction' => $auction,
							'auction_slug' => $auction_slug,
							'csvID' => $id,
							'offset' => $last_import,
						);
						$post_ID = $this->import_item( $args );
						if( is_wp_error( $post_ID ) ){
							wp_send_json_error([
								'message' => $post_ID->get_error_message(),
							], 400 );
						}

						/**
						 * Skip image processing if we don't have a Lot Number. This is
						 * because we find images in the image upload folder based on
						 * the Lot Number. Without the Lot Number we don't know which
						 * images to attach to the item.
						 */
						if( ! array_key_exists( 'lotnumber', $item ) && ! array_key_exists( 'lotnum', $item ) )
							continue;
						if( array_key_exists( 'lotnumber', $item ) && ! is_numeric( $item['lotnumber'] ) )
							continue;
						if( array_key_exists( 'lotnum', $item ) && ! is_numeric( $item['lotnum'] ) )
							continue;

						$upload_dir = wp_upload_dir();
						$imgdir = $upload_dir['basedir'] . '/auctions/' . $imgpath . '/';

						$lotNumber = ( array_key_exists( 'lotnum', $item ) )? $item['lotnum'] : $item['lotnumber'];
						$response->images = $this->get_img_from_dir( $post_ID, $lotNumber, $imgdir );
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
					//$csvfile = str_replace( get_bloginfo( 'url' ). '/', ABSPATH, $url );
					$csvfile = get_attached_file( $id );
					$data['filepath'] = $csvfile;
					$data['csv'] = $this->open_csv( $csvfile, $id, false );
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
			<thead>
				<tr>
					<th scope="col" class="manage-column">ID Title/Filename</th>
					<th scope="col" class="manage-column">Image Folder</th>
					<th scope="col" class="manage-column">Auction</th>
					<th scope="col" class="manage-column">Last Import</th>
					<th scope="col" class="manage-column">&nbsp;</th>
				</tr>
			</thead>
			<tbody>
				<tr class="alternate">
					<td colspan="5" style="text-align: center">One moment. Loading CSV list...</td>
				</tr>
			</tbody>
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
			'exists' 		=> 'item',
			'itemnumber' 	=> null,
			'auction_slug' 	=> null,
			'post_parent' 	=> null,
			'post_title' 	=> null,
		);

		$args = wp_parse_args( $args, $defaults );

		switch( $args['exists'] ){
			case 'attachment':
				global $wpdb;
				$attachment_id = $wpdb->get_var( 'SELECT ID FROM ' . $wpdb->posts . ' WHERE post_type="attachment" AND post_parent=' . $args['post_parent'] . ' AND post_title="' . $args['post_title'] . '"' );
				return ( $attachment_id )? $attachment_id : false;
			break;

			case 'item':
				/**
				 * 03/10/2025 (15:06) - Previously we were also checking 
				 * `taxonomy=auction` and`term={$args['auction_slug']}. 
				 * Now that we are treating `ItemNumber` as a unique
				 * key, we are checking for _item_number only.
				 *
				 *	- 'taxonomy' => 'auction',
				 *	- 'term' => $args['auction_slug'],
				 * 
				 * @var array
				 */
				$get_posts_args = array(
					'meta_value' => $args['itemnumber'],
					'meta_key' => '_item_number',
					'post_type' => 'item',
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
	 * @return void
	 */
    public function contextual_help_tabs(){

    	$screen = get_current_screen();
    	$screen->add_help_tab( array(
    		'id' => 'auction-importer-import-help',
    		'title' => 'Import Instructions',
    		'content' => file_get_contents( plugin_dir_path( __FILE__ ) . '../html/help.auction-importer.html' ),
		) );

    	$screen->add_help_tab( array(
    		'id' => 'auction-importer-csv-setup-help',
    		'title' => 'CSV Setup',
    		'content' => file_get_contents( plugin_dir_path( __FILE__ ) . '../html/help.auction-importer.csv-setup.html' ),
		) );

    	$screen->add_help_tab( array(
    		'id' => 'auction-importer-ftp-permissions-help',
    		'title' => 'Upload Instructions',
    		'content' => file_get_contents( plugin_dir_path( __FILE__ ) . '../html/help.auction-importer.upload-instructions.html' ),
		) );

    	$screen->add_help_tab( array(
    		'id' => 'auction-importer-ftp-permissions-help',
    		'title' => 'FTP Permissions',
    		'content' => file_get_contents( plugin_dir_path( __FILE__ ) . '../html/help.auction-importer.ftp-permissions.html' ),
		) );

    	$screen->add_help_tab( array(
    		'id' => 'auction-importer-naming-images-help',
    		'title' => 'Naming Images',
    		'content' => file_get_contents( plugin_dir_path( __FILE__ ) . '../html/help.auction-importer.naming-images.html' ),
		) );

    	$screen->add_help_tab( array(
    		'id' => 'auction-importer-removing-items-help',
    		'title' => 'Removing Items',
    		'content' => file_get_contents( plugin_dir_path( __FILE__ ) . '../html/help.auction-importer.removing-items.html' ),
		) );
    }

	/**
	 * Returns a list of images prefixed with the given lotnum that are not already WP attachments
	 */
	function get_img_from_dir( $post_ID, $lotnum, $imgdir ) {
		uber_log( "🔔 get_img_from_dir( {$post_ID}, {$lotnum}, {$imgdir} ){}" );
		$data = [];

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
								}
								/**
								 * Commenting out the following prevents the `menu_order` value from being
								 * updated whenever we are reimporting the auction. This scenario comes into
								 * play whenever we add Auction Highlights for an existing auction. In cases
								 * like these, users may have updated the order of Item images by hand.
								 * Commenting out the following prevents the re-import from over writing the
								 * manually assigned image order.
								 */
								/*
								else { // attachment exists, don't add to array and update database record for that attachment
									preg_match( $pattern , $entry, $matches );
									$wpdb->update( $wpdb->posts, array( 'menu_order' => $matches[2] ), array( 'ID' => $attachment_id ), array( '%d' ), array( '%d' ) );
								}
								/**/
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
	 *										LowEst, HighEst, Realized, and Highlight
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

		// Check for `itemnumber`
		if( empty( $item['itemnumber'] ) ){
			$alert = get_alert([ 
				'type' => 'warning',
				'message' => '<strong>ERROR:</strong> ItemNumber is missing. Please ensure your CSV has an <code>ItemNumber</code> assigned to each auction item.'
			]);
			return new WP_Error( 'no-itemnumber', $alert );
		}

		// Skip items with empty or non-numeric Lot Numbers:
		//if( empty( $item['lotnumber'] ) || ! is_numeric( $item['lotnumber'] ) )
			//return;

		// if this item exists, add the ID to the query so that it gets updated
		$id = false;
		if( $id = $this->auction_object_exists([ 'exists' => 'item', 'itemnumber' => $item['itemnumber'] ]) )
			$post['ID'] = $id;

		$item_title = 'Untitled Lot';
		if( array_key_exists( 'lotnumber', $item ) && array_key_exists( 'lead', $item ) ){
			$item_title = 'Lot ' . $item['lotnumber'] . ': ' . $item['lead'];
			$post['post_title'] = $item_title;			
		} else if( $id ){
			$post['post_title'] = get_the_title( $id );
			$post['post_content'] = get_the_content( null, false,  $id );
		}

		$post['post_type'] = 'item';
		$post['post_status'] = 'publish';

		// Build the Item description
		if( array_key_exists( 'description', $item ) ){
			$post_content = $item['description'];				

			if( ! empty( $item['provenanceline'] ) )
				$post_content .= "\n\nPROVENANCE: " . $item['provenanceline'];
			if( ! empty( $item['condition'] ) )
				$post_content .= "\n\nCONDITION: " . $item['condition'];
			$post['post_content'] = $post_content;			
		}

		/**
		 * ⚠️ "NO LOT" Line Items
		 *
		 * The following code complies with the instructions found in the "Removing Items"
		 * contextual help. In /lib/html/help.auction-importer/removing-items.html, we 
		 * state, "Adding `NO LOT` anywhere in the title of an item will set that item to 
		 * `DRAFT` status, thereby removing it from public display."
		 * 
		 **/
		$valid_nolot_strings = array( 'no lot', 'nolot', 'no-lot' );
		foreach( $valid_nolot_strings as $string ){
			if( stristr( strtolower( $item_title ), $string ) )
				$post['post_status'] = 'draft';
		}

		// Create/Update the Item CPT
		// /**
		// CONTINUE WORKING HERE: Need to not create a new post
		// when we are updating. We are losing meta field values.
		if( array_key_exists( 'ID', $post ) ){
			$post_id = $post['ID'];
			$lot_num_before = get_post_meta( $post_id, '_lotnum', true );
			wp_update_post( $post );
			$lot_num_after = get_post_meta( $post_id, '_lotnum', true );
			uber_log( "🔔 Lot no. for Item #{$post_id} CPT:\n - Before: {$lot_num_before}\n - After: {$lot_num_after}" );
		} else {			
			$post['ID'] = wp_insert_post( $post );
		}

		// Add the Item to the Auction
		wp_set_object_terms( $post['ID'], array( intval( $auction ) ), 'auction' );

		// assign the item to any specified tags
		if ( array_key_exists( 'tags', $item ) ) {
			$terms = array();
			$item_tags = explode( ',', $item['tags'] );

			if( 0 < count( $item_tags ) ){
				foreach ( $item_tags as $tag ) {
					if( $term = term_exists( $tag, 'item_tags' ) ){
						$terms[ $term['term_id'] ] = $tag;
					} else if( ! empty( $tag ) ) {
						$term = wp_insert_term( $tag, 'item_tags' );
						if( ! is_wp_error( $term ) ){
							$terms[ $term['term_id'] ] = $tag;
						} else {
							uber_log('🟥 Tag creation failed with Error Code `' . $term->get_error_code() . '`: ' . $term->get_error_message() );
						}
					}
				}
				$term_ids = array_keys( $terms );

				wp_set_object_terms( $post['ID'], $term_ids, 'item_tags' );				
			} else {
				wp_set_object_terms( $post['ID'], null, 'item_tags' ); // remove all item_tags for an item	
			}
		} 

		// assign the item to any specified categories
		if( array_key_exists( 'categories', $item ) || array_key_exists( 'categoryname', $item ) ){
			$terms = array();
			$item_categories = ( ! empty( $item['categoryname'] ) )? explode( ',', $item['categoryname'] ) : explode( ',', $item['categories'] );

			if( 0 < count( $item_categories ) ){
				foreach ( $item_categories as $category ) {
					if( $term = term_exists( $category, 'item_category' ) ){
						$terms[$term['term_id']] = $category;
					} else if( ! empty( $category ) ) {
						$term = wp_insert_term( $category, 'item_category' );
						if( ! is_wp_error( $term ) ){
							$terms[$term['term_id']] = $category;	
						} else {
							uber_log('🟥 Tag creation failed with Error Code `' . $term->get_error_code() . '`: ' . $term->get_error_message() );
						}
						
					}
				}
				$term_ids = array_keys( $terms );

				wp_set_object_terms( $post['ID'], $term_ids, 'item_category' );				
			} else {
				wp_set_object_terms( $post['ID'], null, 'item_category' ); // remove all categories for an item	
			}
		}

		uber_log('🔔 $item = ' . print_r( $item,true ) );

		$meta_fields = [ 
			'_lotnum' 					=> 'lotnumber',
			'_low_est' 					=> 'lowestimate',
			'_high_est' 				=> 'highestimate',
			'_realized' 				=> 'realized',
			'_hammerprice'			=> 'hammerprice',
			'_item_number' 			=> 'itemnumber',
			'_lot_bidding_url' 	=> 'lotbiddingurl',
		];
		foreach ( $meta_fields as $meta_key => $item_key ) {
			if( ! array_key_exists( $item_key, $item ) ){
				uber_log( "👉 Skipping {$item_key}");
				// nothing
			} else {
				uber_log("Running: update_post_meta( {$post['ID']}, {$meta_key}, {$item[$item_key]} )");
				update_post_meta( $post['ID'], $meta_key, $item[ $item_key ] );	
			}
		}

		if( ! array_key_exists( 'ishighlight', $item ) )
			$item['ishighlight'] = false;
		$highlight = boolval( $item['ishighlight'] );
		update_post_meta( $post['ID'], '_highlight', $highlight );

		/**
		 * IGAVEL AUCTION LINKS
		 *
		 * Add a meta field for iGavel lot numbers which we'll use in single-item.php to build an iGavel
		 * link like the following example:
		 *
		 * http://bid.igavelauctions.com/Bidding.taf?_function=detail&Auction_uid1=2872353
		 */
		if ( array_key_exists( 'igavellotnum', $item ) )
			update_post_meta( $post['ID'], '_igavel_lotnum', $item['igavellotnum'] );

		/**
		 * BIDSQUARE LINKS
		 *
		 * Example: http://auctions.bidsquare.com/view-auctions/catalog/id/891/lot/12362
		 */
		if( array_key_exists( 'bidsquarelotnum', $item ) )
			update_post_meta( $post['ID'], '_bidsquare_lotnum', $item['bidsquarelotnum'] );

		/**
		 * LIVEAUCTIONEER LINKS
		 *
		 * Traditionally, we've handled these inside the "Auction"; however, with the
		 * introduction of multiday auctions, we need allow for these links to be
		 * generated at the Item level.
		 *
		 * Example: http://www.liveauctioneers.com/itemLookup/170967/43
		 */
		if( array_key_exists( 'liveauctioneersid', $item ) )
			update_post_meta( $post['ID'], '_liveauctioneers_id', $item['liveauctioneersid'] );

		if ( ! empty( $csvID ) && ! empty( $offset ) )
			update_post_meta( $csvID, '_last_import', $offset );

		return $post['ID'];
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
			$attachment = [
				'post_mime_type' => $wp_filetype['type'],
				'post_title' => $image,
				'post_content' => '',
				'post_status' => 'inherit',
			];
			if ( $attachment_id = $this->auction_object_exists( array( 'exists' => 'attachment', 'post_parent' => $post_ID, 'post_title' => $image ) ) ) {
				$attachment['ID'] = $attachment_id;
				$update = true;
			} else {
				// image doesn't exist, menu order from image file name
				$attachment['menu_order'] = intval( $matches[2] );
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
	 * Opens a CSV file, processes its contents, and stores the data in a transient.
	 *
	 * This method reads a CSV file, extracting its column headers and rows. If the CSV data is
	 * already stored in a transient (identified by `$csvID`), it retrieves the cached data instead
	 * of reprocessing the file.
	 *
	 * @param string      $csvfile Path to the CSV file.
	 * @param string|null $csvID   Unique identifier for storing/retrieving the transient data.
	 * @param bool        $cached  Whether to use the cached version (default true).
	 * @return array Processed CSV data, including an error message if no CSV file is specified.
	 *
	 * @structure array $csv The processed CSV data stored in the transient:
	 *     - `row_count` (int) The number of data rows in the CSV.
	 *     - `column_count` (int) The number of columns in the CSV.
	 *     - `columns` (array) An indexed array of column headers.
	 *     - `rows` (array) A multidimensional array containing CSV row data.
	 *     - `error` (string) An error message if no CSV is specified.
	 */
	public function open_csv( $csvfile = '', $csvID = null, $cached = true ) {
		uber_log('🔔 Running open_csv()...');
	  if ( empty( $csvfile ) ) {
	    return array( 'error' => 'No CSV specified!' );
	  }

	  if ( false === $cached || false === ( $csv = get_transient( 'csv_' . $csvID ) ) ) {
	    $csv = array(
	      'row_count'     => 0,
	      'column_count'  => 0,
	      'columns'       => array(),
	      'rows'          => array(),
	    );

	    uber_log('🔔 $csvfile = ' . $csvfile );

	    if ( file_exists( $csvfile ) ) {
	      // Normalize file encoding to UTF-8
	      $file_contents = file_get_contents( $csvfile );
	      $encoding      = mb_detect_encoding( $file_contents, 'UTF-8, ISO-8859-1, Windows-1252', true );
	      $file_contents = mb_convert_encoding( $file_contents, 'UTF-8', $encoding );
	      $file_contents = preg_replace( "/\r\n|\r/", "\n", $file_contents ); // Normalize line endings

	      // Write to temp file
	      $temp_file = tmpfile();
	      fwrite( $temp_file, $file_contents );
	      $meta      = stream_get_meta_data( $temp_file );
	      $csv_path  = $meta['uri'];

	      uber_log('🔔 $csv_path = ' . $csv_path );

	      if ( ( $handle = fopen( $csv_path, 'r' ) ) !== false ) {
	        $x = 0;
	        while ( ( $row = fgetcsv( $handle, 0, ',', '"' ) ) !== false ) {
	          if ( $x === 0 ) {
	            foreach ( $row as $key => $heading ) {
	              $row[ $key ] = trim( $heading );
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
	        fclose( $handle );
	      }

	      fclose( $temp_file ); // Clean up temp file
	    } else {
	    	uber_log('🟥 FILE NOT FOUND!');
	    }

	    set_transient( 'csv_' . $csvID, $csv );
	  }

	  return $csv;
	}

	/**
	 * Callback to sanitize and decode individual CSV values.
	 *
	 * @param string $value CSV cell value, passed by reference.
	 */
	public function trim_csv_row( &$value ) {
	  $value = trim( $value );
	  $value = html_entity_decode( $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	}
}

$AuctionImporter = AuctionImporter::get_instance();
?>