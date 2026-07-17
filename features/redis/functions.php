<?php
namespace NuxtSsr\Redis;

class Client {

  private static $instance;

  public static function client() {
    if ( null === static::$instance ) {
      static::$instance = new \Predis\Client( REDIS_OPTIONS );
    }
    return static::$instance;
  }

  public static function set( $key, $value, $expires_in = null ) {
    $key = \Utilities\encodeURIComponent( self::full_key( $key ) );
    if ( null === $expires_in ) {
      self::client()->set( $key, $value );
    } else {
      self::client()->set( $key, $value, 'EX', $expires_in );
    }
  }

  public static function unscoped_set( $key, $value, $expires_in = null ) {
    $key = \Utilities\encodeURIComponent( $key );
    if ( null === $expires_in ) {
      self::client()->set( $key, $value );
    } else {
      self::client()->set( $key, $value, 'EX', $expires_in );
    }
  }

  public static function get( $key, $options = array() ) {
    $key = \Utilities\encodeURIComponent( self::full_key( $key ) );
    return self::client()->get( $key );
  }

  public static function unscoped_get( $key, $options = array() ) {
    $key = \Utilities\encodeURIComponent( $key );
    return self::client()->get( $key );
  }

  public static function keys( $str = null ) {
    if ( ! $str ) {
      $str = '*';
    }
    return self::client()->keys( $str );
  }

  public static function delete_keys( $keys ) {
    if ( empty( $keys ) ) {
      return;
    }
    return self::client()->del( $keys );
  }

  private static function full_key( $key ) {
    $environment     = WP_ENV;
    $current_blog_id = get_current_blog_id();
    $key = getenv('PROJECT_NAME') . '_wp_' . $current_blog_id . '_' . $environment . "_$key";
    return $key;
  }
}

function redis_store( $key, $value, $expires_in = null ) {
  Client::set( $key, wp_json_encode( $value ), $expires_in );
}

function redis_unscoped_store( $key, $value, $expires_in = null ) {
  Client::unscoped_set( $key, wp_json_encode( $value ), $expires_in );
}

function redis_get( $key, $options = array() ) {
  return json_decode( Client::get( $key, $options ) ?? '', true );
}

function redis_unscoped_get( $key ) {
  return json_decode( Client::unscoped_get( $key ) ?? '', true );
}

function redis_keys( $key = null ) {
  return Client::keys( $key );
}

function delete_keys( $keys ) {
  return Client::delete_keys( $keys );
}

function redis_pub( $channel = 'WordPress', $payload = array() ) {
  Client::client()->publish( $channel, wp_json_encode( $payload ) );
}
