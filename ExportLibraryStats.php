<?php
/* Minimal WP set up -- we are called directly, not through the normal WP
 * process.  We won't be displaying full fledged WP pages either.
 */

if (!defined('ABSPATH')) {
  die();
}

$wp_root = dirname(__FILE__) .'/../../../';
if(file_exists($wp_root . 'wp-load.php')) {
      require_once($wp_root . "wp-load.php");
} else if(file_exists($wp_root . 'wp-config.php')) {
      require_once($wp_root . "wp-config.php");
} else {
      exit;
}

@error_reporting(0);
  
global $wp_db_version;
if ($wp_db_version < 8201) {
	// Pre 2.6 compatibility (BY Stephen Rider)
	if ( ! defined( 'WP_CONTENT_URL' ) ) {
		if ( defined( 'WP_SITEURL' ) ) define( 'WP_CONTENT_URL', WP_SITEURL . '/wp-content' );
		else define( 'WP_CONTENT_URL', get_option( 'url' ) . '/wp-content' );
	}
	if ( ! defined( 'WP_CONTENT_DIR' ) ) define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' );
	if ( ! defined( 'WP_PLUGIN_URL' ) ) define( 'WP_PLUGIN_URL', WP_CONTENT_URL. '/plugins' );
	if ( ! defined( 'WP_PLUGIN_DIR' ) ) define( 'WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins' );
}

require_once(ABSPATH.'wp-admin/admin.php');

if (!current_user_can('manage_circulation')) {
  wp_die( __('You do not have sufficient permissions to access this page.','weblibrarian') );
}

/* Make sure we are first and only program */
if (headers_sent()) {
  @header('Content-Type: ' . get_option('html_type') . '; charset=' . get_option('blog_charset'));
  wp_die(__('The headers have been sent by another plugin - there may be a plugin conflict.','weblibrarian'));
}

define('WEBLIB_FILE', basename(__FILE__));
define('WEBLIB_DIR' , dirname(__FILE__));
define('WEBLIB_INCLUDES', WEBLIB_DIR . '/includes');
require_once(WEBLIB_INCLUDES . '/database_code.php');

$year  = isset( $_REQUEST['year'] )  ? $_REQUEST['year']  : date('Y',time());
$month = isset( $_REQUEST['month'] ) ? $_REQUEST['month'] : date('m',time());
$MonthNames = 
	array('Month Totals','January','February','March','April','May','June',
	      'July','August','September', 'October','November','December');


$csv = '';

function csv_quote($string) {
  return addcslashes($string,"\\".'"');
}



preg_replace('/["\\]/',"\\\\$0",$string);

if ($month == 0) {
  /* Monthy totals */
  $csv .= "Month,Count\n";
  for ($imonth = 1; $imonth < 13; $imonth++) {
    $csv .= '"'.$MonthNames[$imonth].'",'.WEBLIB_Statistic::MonthTotal($year,$imonth)."\n";
  }
  $csv .= '"Total",'.WEBLIB_Statistic::AnnualTotal($year)."\n";
} else {
  /* Month count by type */
  $types = WEBLIB_Type::AllTypes();
  $csv .= "Type,Count\n";
  foreach ($types as $type) {
    $csv .= '"'.csv_quote($type).'",'.WEBLIB_Statistic::TypeCount($type,$year,$month)."\n";
  }
  $csv .= '"Total",'.WEBLIB_Statistic::MonthTotal($year,$month)."\n";
}

header("Content-type: text/csv");
header("Content-Disposition: attachment; filename=librarystats.csv");
header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
header("Content-Length: " . strlen($csv));

echo $csv;
exit;

