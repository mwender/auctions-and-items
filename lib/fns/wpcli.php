<?php

namespace AuctionsAndItems\cli;
use function AuctionsAndItems\utilities\{is_sold};

/**
 * Manage unsold auction items.
 *
 * [--delete]
 * : Delete unsold items.
 *
 * @param      string  $args        The arguments
 * @param      string  $assoc_args  The associated arguments
 */
$items = function( $args, $assoc_args ){
  \WP_CLI::success( '🔔 Initiating unsold items count...' );

  $delete = false;
  if( array_key_exists( 'delete', $assoc_args) )
    $delete = true;
  if( $delete )
    \WP_CLI::success( '`--delete` flag detected. Unsold items will be deleted.' );

  $items = get_posts([
    'posts_per_page' => -1,
    'post_status'    => 'publish',
    'post_type'      => 'item',
    'fields'         => 'ids',
  ]);
  //\WP_CLI::success( '🔔 $items = ' . print_r( $items, true ) );

  if( $items ){
    $BackgroundDeleteItemProcess = $GLOBALS['BackgroundDeleteItemProcess'];
    $total_items = count( $items );

    $unsold_items = 0;
    $progress = \WP_CLI\Utils\make_progress_bar( 'Counting unsold items...', $total_items );
    foreach( $items as $item_id ){
      if( ! is_sold( $item_id ) ){
        $unsold_items++;
        if( $delete ){
          //\WP_CLI::success('Deleting Item #' . $item_id );
          $BackgroundDeleteItemProcess->push_to_queue( $item_id );
        }
      }
      $progress->tick();
    }
    if( $delete )
      $BackgroundDeleteItemProcess->save()->dispatch();
    $progress->finish();
    \WP_CLI::line( '🔔 ' . $total_items . ' total items found.' );
    \WP_CLI::line( '🔔 ' . $unsold_items . ' unsold items found.' );
  }
};
if( class_exists( '\\WP_CLI' ) )
  \WP_CLI::add_command( 'items unsold', $items );