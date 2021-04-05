<?php

namespace AuctionsAndItems\utilities;

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