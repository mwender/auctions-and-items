<?php

namespace CaseAntiques\shortcodes;
use function AuctionsAndItems\handlebars\{render_template};
use function AuctionsAndItems\utilities\{format_price};

/**
 * Displays an archive listing of an auction.
 *
 * @return     string  HTML for an arhcive listing.
 */
function auction_archive(){
  $html = [];
  $html[] = '<div class="auction-display-toggle">View Options: <ul>
      <li><a href="#" class="view-thumbnails active" title="Thumbnail View"></a></li>
      <li><a href="#" class="view-table" title="Table View"></a></li>
    </ul></div>';

  $html[] = file_get_contents( plugin_dir_path( __FILE__ ) . '/../includes/auction-thumbnails.datatables.html' );

  global $wp_query;
  $value    = get_query_var( $wp_query->query_vars['taxonomy'] );
  $current_term = get_term_by( 'slug', $value, $wp_query->query_vars['taxonomy'] );

  $date = get_metadata( 'auction', $current_term->term_id, 'date', true );
  if ( $date ) {
    $auction_date = new \DateTime( $date );
    $todays_date = new \DateTime( current_time( 'mysql' ) );
    $interval = $auction_date->diff( $todays_date );
  }

  $auction_name = $current_term->name;
  preg_match( '/([0-9]{4})\s([0-9]{2})\s([0-9]{2})/', $auction_name, $matches );
  if ( $matches ) {
    $auction_timestamp = strtotime( $matches[1] . '-' . $matches[2] . '-' .$matches[3] );
    $auction_date = date( 'l, F j, Y', $auction_timestamp );
    $auction_name = str_replace( $matches[0], $auction_date, $auction_name );
  }

  //$date_comparison = '<pre>$date = '.$date.'<br />$auction_name = '.$auction_name.'<br />$auction_date = '.$auction_date->format('Y-m-d').'<br/>$todays_date = '.$todays_date->format('Y-m-d').'<br />$date_comparison = ' . $interval->format( '%R%a days' ) . '</pre>';

  // Show Estimated Prices for auctions up to 7 days after their date
  $show_realized = get_metadata( 'auction', $current_term->term_id, 'show_realized', true );
  $file = ( true == $show_realized )? 'auction-table.datatables.html' : 'auction-table.datatables.estimated.html' ;

  $filepath = plugin_dir_path( __FILE__ ) . '/../includes/' . $file ;
  $auction_table_format = file_get_contents( $filepath );

  $html[] = sprintf( $auction_table_format, $auction_name );

  return implode("\n", $html );
}
add_shortcode( 'auction_archive', __NAMESPACE__ . '\\auction_archive' );

/**
 * Displays the term description for the auction.
 */
function auction_description(){
  $data['description'] = apply_filters( 'the_content', term_description() );
  $html = render_template( 'auction-description', $data );
  return $html;
}
add_shortcode( 'auction_description', __NAMESPACE__ . '\\auction_description' );

/**
 * Displays a link to the current auction, useful for manually
 * creating breadcrumbs with an Elementor text widget.
 *
 * @return     string  A link to the current auction.
 */
function current_auction_link(){
  global $post;
  $terms = wp_get_post_terms( $post->ID, 'auction' );

  $paged = '';
  $paged_text = '';
  $url = parse_url( wp_get_referer() );
  if ( $url['path'] ) {
    preg_match( '/page\/([0-9]+)/', $url['path'], $matches );
    $page = ( $matches )? $matches[1] : null;
    $paged = ( $page )? 'page/' . $page . '/' : '';
    $paged_text = ' &ndash; Page ';
    $paged_text.= ( $page )? $page : '1';
  }

  if ( $terms ) {
    $auction = $terms[0];
    $crumbs[1] = '<a href="' . get_term_link( $auction->term_id, 'auction' ) . $paged . '" title="Back to ' . esc_attr( $auction->name ) . '">' . $auction->name . $paged_text . '</a>';
  }

  return '<a href="#">' . $crumbs[1] . '</a>';
}
add_shortcode('current_auction', __NAMESPACE__ . '\\current_auction_link' );

/**
 * Displays bidding information for an item.
 *
 * @return     string  HTML for an item's bidding box.
 */
function bidding_box(){
  global $post;
  $data = [];

  $high_est = get_post_meta( $post->ID, '_high_est', true );
  $low_est = get_post_meta( $post->ID, '_low_est', true );
  $realized = get_post_meta( $post->ID, '_realized', true );
  $lotnum = get_post_meta( $post->ID, '_lotnum', true );

  $terms = wp_get_object_terms( $post->ID, 'auction' );
  if( $terms ){
    foreach ( $terms as $term ) {
      if ( $term->taxonomy == 'auction' ) {
        $button_classes = array( 'button' );
        // ACF meta data
        $show_realized = get_field( 'show_realized', $term );
        $show_realized = ( is_array( $show_realized ) && ! empty( $show_realized ) )? $show_realized[0] : false ;

        $auction_meta = [
          'date'          => get_field( 'date', $term ),
          'show_realized' => $show_realized,
          'auction_id'    => get_field( 'auction_id', $term ),
          'bidsquare_id'  => get_field( 'bidsquare_id', $term ),
        ];
        //echo '<li><pre>' . print_r( $auction_meta, true ) . '</pre></li>';
        $auction_timestamp = ( ! is_null( $auction_meta['date'] ) )? strtotime( $auction_meta['date'] ) : null ;
        //$current_timestamp = strtotime( date( 'Y-m-d', current_time( 'timestamp' ) ) );
        $current_timestamp = current_time( 'timestamp' );
        if ( is_null( $auction_timestamp ) || $current_timestamp < $auction_timestamp ) {
          $link_text = 'Bid Now';
          $button_classes[] = 'green';
        } else {
          if ( ! $realized )
            $realized = 'PASSED';
          $link_text = 'View Final Price';
        }
        // END ACF meta data

        $data['realized'] = ( ! empty( $realized ) && is_numeric( $realized ) )? '<li><h2>SOLD! <span>for ' . format_price( $realized ) . '.</span></h2><p class="note">(Note: Prices realized include a buyer\'s premium.)</p></li>' : '' ;
      }
    }
  }


  $bidding_box = render_template( 'bidding-box', $data );
  return $bidding_box;
}
add_shortcode( 'bidding_box', __NAMESPACE__ . '\\bidding_box' );

/**
 * Displays all the image attachments for an auction item.
 *
 * @return     string  HTML for the item's photo gallery.
 */
function item_gallery(){
  global $post;

  $parent_title = get_the_title( $post->ID );

  $args = array(
    'post_parent' => $post->ID,
    'post_type' => 'attachment',
    'post_mime_type' => 'image',
    'orderby' => 'menu_order',
    'order' => 'ASC'
  );
  $images = get_children( $args );

  if ( $images ) {
    $gallery_images = array();
    foreach ( $images as $attachment_id => $attachment ) {
      $image_ids[] = $attachment_id;
      $fullsize = wp_get_attachment_image_src( $attachment_id, 'fullsize' );
      $thumbnail = wp_get_attachment_image_src( $attachment_id, 'thumbnail' );

      $atts = array(
        'alt' => esc_attr( $parent_title ),
      );

      $gallery_images[] = '<a href="' . $fullsize[0] . '" class="image gallery" data-flare-thumb="' . $thumbnail[0] . '">' . wp_get_attachment_image( $attachment_id, 'large', false, $atts ) . '</a>';
    }
    return '<div class="item-gallery">' . implode( "\n", $gallery_images ) . '</div>';
  }
}
add_shortcode('item_gallery', __NAMESPACE__ . '\\item_gallery' );
