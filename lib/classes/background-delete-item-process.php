<?php

class AAI_Delete_Item_Process extends WP_Background_Process {
  /**
   * @var string
   */
  protected $action = 'delete_item';

  /**
   * Handle
   *
   * Override this method to perform any actions required
   * during the async request.
   */
  protected function task( $item_id ) {
    $success = wp_delete_post( $item_id, true );
    //error_log('Attempting to delete Item #' . $item_id );
    if( ! $success ){
      error_log( '❌ Unable to delete Item #' . $item_id );
    } else {
      error_log( '✅ Deleted Item #' . $item_id );
    }

    return false;
  }

  /**
   * Complete
   *
   * Override if applicable, but ensure that the below actions are
   * performed, or, call parent::complete().
   */
  protected function complete() {
      parent::complete();
  }
}