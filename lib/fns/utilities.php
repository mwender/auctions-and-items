<?php

namespace AuctionsAndItems\utilities;
use function AuctionsAndItems\handlebars\{render_template};

/**
 * Returns an HTML alert.
 *
 * @param      array   $atts {
 *   Optional. An array of arguments.
 *
 *   @type      string  $type     The alert type (can be: primary, secondary, success, danger, warning, info, light, dark)
 *   @type      string  $heading  The alert heading
 *   @type      string  $message  The alert message
 * }
 *
 * @return     string  The alert HTML.
 */
function get_alert( $atts = [] ){
  $args = shortcode_atts( [
    'type' => 'primary',
    'heading' => false,
    'message' => '...',
  ], $atts );

  $available_types = [ 'primary', 'secondary', 'success', 'danger', 'warning', 'info', 'light', 'dark' ];
  $type = ( in_array( $args['type'], $available_types ) )? $args['type'] : 'primary' ;
  $data = [
    'type'    => $type,
    'heading' => $args['heading'],
    'message' => $args['message'],
  ];
  $html = render_template( 'alert', $data );

  return $html;
}

/**
 * Formats a number in USD.
 *
 * @param      int  $price  The price
 *
 * @return     string  Input formatted as USD.
 */
function format_price( $price ){
  settype( $price, 'int' );
  return '$'. number_format( str_replace( '$', '', $price ), 2 );
}

/**
 * Determines whether the specified item identifier is sold.
 *
 * @param      int  $item_id  The item ID
 *
 * @return     bool    True if the specified item identifier is sold, False otherwise.
 */
function is_sold( $item_id ){
  $realized = get_post_meta( $item_id, '_realized', true );
  $auctions = get_the_terms( $item_id, 'auction' );
  $highest_auction_timestamp = 0;
  if( ! empty( $auctions ) ){
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
    // To Be Determined
    //echo '<code style="padding: 4px; border-radius: 3px; background-color: #999; color: #333;">TBD</code>';
    return false;
  } else {
    if( ! empty( $realized ) && is_numeric( $realized ) ){
      // Sold
      //echo AuctionShortcodes::format_price( $realized );
      return true;
    } else {
      // Unsold
      //echo '<code style="padding: 4px; border-radius: 3px; background-color: #f00; color: #fff;">NOT SOLD</code>';
      return false;
    }
  }
}