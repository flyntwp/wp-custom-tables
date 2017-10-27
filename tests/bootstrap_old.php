<?php
/**
 * PHPUnit bootstrap file
 *
 * @package ACF_Field_Group_Composer
 */


// First we need to load the composer autoloader so we can use WP Mock
require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once __DIR__ . '/wordpress/4.8/wpdb.php';

global $wpdb;
$wpdb = Mockery::mock( '\wpdb' )->makePartial();
$wpdb->dbh = false;
// $wpdb->use_mysqli = true;


require_once __DIR__. '/TestCase.php';
