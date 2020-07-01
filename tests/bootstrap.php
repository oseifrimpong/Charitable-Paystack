<?php

$_tests_dir = getenv('WP_TESTS_DIR');

if ( !$_tests_dir ) $_tests_dir = '/tmp/wordpress-tests-lib';

require_once $_tests_dir . '/includes/functions.php';

function _manually_load_plugins() {
	require dirname( __FILE__ ) . '/../../../plugins/easy-digital-downloads/easy-digital-downloads.php';
	require dirname( __FILE__ ) . '/../../../plugins/charitable/charitable.php';	
	require dirname( __FILE__ ) . '/../charitable-edd.php';
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugins' );

require $_tests_dir . '/includes/bootstrap.php';

activate_plugin( 'easy-digital-downloads/easy-digital-downloads.php' );
echo "Installing Easy Digital Downloads...\n";

activate_plugin( 'charitable/charitable.php' );
echo "Installing Charitable...\n";

activate_plugin( 'charitable-edd/charitable-edd.php' );
echo "Installing Charitable - Easy Digital Downloads Connect...\n";

// Install Charitable
charitable()->activate();
charitable_edd()->activate();

require 'helpers/class-benefactor-helper.php';
require 'helpers/class-edd-cart-helper.php';
require 'helpers/charitable/class-campaign-helper.php';
require 'helpers/edd/class-helper-download.php';
require 'helpers/edd/class-helper-payment.php';