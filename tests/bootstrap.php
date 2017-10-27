<?php
/**
 * PHPUnit bootstrap file
 *
 * @package ACF_Field_Group_Composer
 */


// First we need to load the composer autoloader so we can use WP Mock
$testsDir = getenv('WP_TESTS_DIR');
if (! $testsDir) {
    $testsDir = rtrim(sys_get_temp_dir(), '/\\') . '/wordpress-tests-lib';
}
if (! file_exists($testsDir . '/includes/functions.php')) {
    throw new Exception("Could not find $testsDir/includes/functions.php, have you run bin/install-wp-tests.sh ?");
}
// Give access to tests_add_filter() function.
require_once $testsDir . '/includes/functions.php';
/**
 * Manually load the plugin being tested.
 */
// function _manually_load_plugin() {
// 	require dirname( dirname( __FILE__ ) ) . '/sample-plugin.php';
// }
// tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );
// Start up the WP testing environment.
require $testsDir . '/includes/bootstrap.php';
require_once __DIR__. '/TestCase.php';
