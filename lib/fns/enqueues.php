<?php

namespace AuctionsAndItems\enqueues;

/**
 * Enqueues scripts.
 *
 * @since 1.x.x
 *
 * @return void
 */
function enqueue_scripts(){
  wp_register_style( 'featherlight', '//cdn.jsdelivr.net/npm/featherlight@1.7.14/release/featherlight.min.css', null, '1.7.14' );

  if( is_single() && 'item' == get_post_type() )
    wp_enqueue_style( 'featherlight-gallery', '//cdn.jsdelivr.net/npm/featherlight@1.7.14/release/featherlight.gallery.min.css', ['featherlight'], '1.7.14' );

  wp_register_script( 'featherlight', '//cdn.jsdelivr.net/npm/featherlight@1.7.14/release/featherlight.min.js', ['jquery'], '1.7.14', true );
  wp_register_script( 'featherlight-gallery', '//cdn.jsdelivr.net/npm/featherlight@1.7.14/release/featherlight.gallery.min.js', ['featherlight'], '1.7.14', true );

  wp_enqueue_style( 'aai-base-styles', AAI_PLUGIN_URL . '/lib/' . AAI_CSS_DIR . '/main.css', null, filemtime( AAI_PLUGIN_PATH . '/lib/' . AAI_CSS_DIR . '/main.css') );

  if( is_single() && 'item' == get_post_type() )
    wp_enqueue_script( 'item-gallery', AAI_PLUGIN_URL . 'lib/js/gallery.js', ['featherlight-gallery'], filemtime( AAI_PLUGIN_PATH . '/lib/js/gallery.js'), true );

  if( is_tax( 'auction' ) || is_tax( 'item_tags' ) || is_tax( 'item_category' ) ){
    wp_enqueue_script( 'datatables-user' );
    wp_enqueue_style( 'aai-base-styles' );

    $localize_args = [];
    $localize_args['ajax_url'] = admin_url( 'admin-ajax.php' );

    global $wp_query;
    $query_taxonomy = get_query_var( 'taxonomy' );
    $query_term_slug = get_query_var( 'term' );
    $current_term = get_term_by( 'slug', $query_term_slug, $query_taxonomy );
    //error_log('$query_taxonomy = '.$query_taxonomy.'; $query_term_slug = '.$query_term_slug.'; $current_term = ' . print_r( $current_term, true ) );

    if( is_tax( 'auction' ) ){

      $localize_args['show_realized'] = get_metadata( 'auction', $current_term->term_id, 'show_realized', true );
      $localize_args['auction'] = $current_term->term_id;
    } else {
      $localize_args['show_realized'] = false;
      // Send term_id and term taxonomy
      $localize_args['term_id'] = $current_term->term_id;
      $localize_args['term_taxonomy'] = $query_taxonomy;
      //error_log('$wp_query->query_vars = ' . print_r($wp_query->query_vars,true) );
    }

    wp_localize_script( 'datatables-user', 'wpvars', $localize_args );
    wp_enqueue_style( 'datatables' );
    wp_enqueue_style( 'dashicons' );
  }
}
add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\\enqueue_scripts', 10 );

/**
 * Enqueue admin styles.
 *
 * @since 1.0.0
 */
function admin_enqueue_scripts(){
  wp_enqueue_style( 'auctions-and-items', AAI_PLUGIN_URL . '/lib/' . AAI_CSS_DIR . '/admin.css', null, filemtime( AAI_PLUGIN_PATH . '/lib/' . AAI_CSS_DIR . '/admin.css' ) );
}
add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\\admin_enqueue_scripts' );

/**
 * Registers scripts.
 *
 * @since 1.x.x
 *
 * @return void
 */
function register_scripts(){
  $styles = array(
    0 => array(
        'handle' => 'footable',
        'src' => 'footable/css/footable.core.min.css',
        'type' => 'style',
    ),
  );
  foreach( $styles as $args ){
    register_bower_script( $args );
  }

  $scripts = array(
    1 => array(
        'handle' => 'footable',
        'src' => 'footable/js/footable.js',
        'deps' => array( 'jquery' ),
    ),
    2 => array(
        'handle' => 'footable-sort',
        'src' => 'footable/js/footable.sort.js',
        'deps' => array( 'jquery', 'footable' ),
    ),
    3 => array(
        'handle' => 'footable-filter',
        'src' => 'footable/js/footable.filter.js',
        'deps' => array( 'jquery', 'footable' ),
    ),
    4 => array(
        'handle' => 'footable-striping',
        'src' => 'footable/js/footable.striping.js',
        'deps' => array( 'jquery', 'footable' ),
    ),
  );
  foreach( $scripts as $args ){
    register_bower_script( $args );
  }

  wp_register_script( 'footable-user', AAI_PLUGIN_URL . 'lib/js/footable.js' , array( 'jquery', 'footable' ), filemtime( AAI_PLUGIN_PATH . 'lib/js/footable.js' ) );
  wp_register_script( 'datatables', AAI_PLUGIN_URL . 'lib/js/datatables/datatables.min.js', null, filemtime( AAI_PLUGIN_PATH . 'lib/js/datatables/datatables.min.js' ) );
  wp_register_style( 'datatables', AAI_PLUGIN_URL . 'lib/js/datatables/datatables.min.css', null, filemtime( AAI_PLUGIN_PATH . 'lib/js/datatables/datatables.min.css' ) );
  wp_register_script( 'datatables-user', AAI_PLUGIN_URL . 'lib/js/datatables.js' , array( 'jquery', 'datatables' ), filemtime( AAI_PLUGIN_PATH . 'lib/js/datatables.js' ) );
}
add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\\register_scripts', 9 );

/**
 * Registers bower script compenents via `wp_register_script`
 *
 * @access enqueue_scripts()
 * @since 1.x.x
 *
 * @param array $args
 *          @type string $handle Name for the script.
 *          @type string $src File path to the script.
 *          @type array $deps Array of handles of all registered scripts required by this script.
 *          @type bool $in_footer Should this script be placed in the footer.
 * }
 * @return void
 */
function register_bower_script( $args ){
    $defaults = array(
        'handle' => null,
        'src' => null,
        'deps' => null,
        'in_footer' => null,
        'media' => 'screen',
        'type' => 'script',
    );

    $args = wp_parse_args( $args, $defaults );

    if( ! stristr( $args['src'], 'bower_components/' ) )
        $args['src'] = 'bower_components/' . $args['src'];

    $src_url = AAI_PLUGIN_URL . $args['src'];
    $ver = filemtime( AAI_PLUGIN_PATH . $args['src'] );

    if( 'script' == $args['type'] ){
        \wp_register_script( $args['handle'], $src_url, $args['deps'], $ver, $args['in_footer'] );
    } elseif ( 'style' == $args['type'] ){
        \wp_register_style( $args['handle'], $src_url, $args['deps'], $ver, $args['media'] );
    }

}