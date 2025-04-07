<?php

class AuctionsAndItems {
    const VER = '1.3.0';
    private static $instance = null;

    public static function get_instance(){
        if( null == self::$instance )
            self::$instance = new self;

        return self::$instance;
    }

    private function __construct(){

    }

    static function activate(){
        AuctionsAndItems::init_options();
    }

    public static function init_options(){
        update_option( 'auctions_and_items_ver', self::VER );
    }
    /**
    * END CLASS SETUP
    */
}