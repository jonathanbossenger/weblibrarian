<?php
	/* Admin page classes */

class WEBLIB_Contextual_Help {
  private $help_map = array();

  function __construct() {
    add_filter('contextual_help', array($this,'provide_contextual_help'), 10, 3);
  }
  function provide_contextual_help($contextual_help, $screen_id, $screen) {
    //file_put_contents("php://stderr","*** WEBLIB_Contextual_Help::provide_contextual_help('$contextual_help','$screen_id',".print_r($screen,true).")\n");
    $helptext = @$this->help_map[$screen_id];
    //file_put_contents("php://stderr","*** WEBLIB_Contextual_Help::provide_contextual_help: helptext = '$helptext'\n");
    if ($helptext) {
      $contextual_help = $helptext;
    }
    //file_put_contents("php://stderr","*** WEBLIB_Contextual_Help::provide_contextual_help: contextual_help = '$contextual_help'\n");
    return $contextual_help;
  }

  function add_contextual_help($sid,$page) {
    //file_put_contents("php://stderr","*** WEBLIB_Contextual_Help::add_contextual_help('$sid','$page')\n");
    $default_helpfile = WEBLIB_CONTEXTUALHELP.'/'.$page.'.html';
    $locale_helpfile  = WEBLIB_CONTEXTUALHELP.'/'.get_locale().'/'.$page.'.html';
    if (file_exists($locale_helpfile)) {
      $helpfile = $locale_helpfile;
    } else {
      $helpfile = $default_helpfile;
    }
    //file_put_contents("php://stderr","*** WEBLIB_Contextual_Help::add_contextual_help: helpfile = '$helpfile'\n");
    $helptext = file_get_contents($helpfile);
    if ($helptext) {
      $helptext = preg_replace("/\n/",' ',$helptext);
      $this->help_map[$sid] = $helptext.
	  '<p><a href="http://plugins.svn.wordpress.org/weblibrarian/assets/user_manual/user_manual.pdf">'.__('Web Librarian User Manual (PDF)','weblibrarian').'</a></p>';
      $this->help_map[$sid] .= '<div style="vertical-align: text-top;"><form action="https://www.paypal.com/cgi-bin/webscr" method="post">'.__('Donate to the Web Librarian plugin software effort.','weblibrarian').
'<input type="hidden" name="cmd" value="_s-xclick">
<input type="hidden" name="hosted_button_id" value="RFSD2JRQVGP7C">
<input type="image" src="https://www.paypalobjects.com/en_US/i/btn/btn_donate_LG.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!">
<img alt="" border="0" src="https://www.paypalobjects.com/en_US/i/scr/pixel.gif" width="1" height="1">
</form></div>';
      $this->help_map[$sid] .= '<div style="vertical-align: text-top;"><a href="http://www.deepsoft.com/home/products/dwsmerch/" target="_blank">'.__('Buy some Deepwoods Software merchandise','weblibrarian').'</a></div>';
      $this->help_map[$sid] .= '<div style="vertical-align: text-top;"><a href="http://amzn.com/w/3679UKP8RZRI9">'.__("Deepwoods Software's Amazon Wish List",'weblibrarian').'</a></div>';
      //file_put_contents("php://stderr","*** WEBLIB_Contextual_Help::add_contextual_help: this->help_map is ".print_r($this->help_map,true)."\n");
    }
  }
}

global $weblib_contextual_help;
$weblib_contextual_help = new WEBLIB_Contextual_Help();

require_once(WEBLIB_INCLUDES . '/WEBLIB_Patrons_Admin.php');
require_once(WEBLIB_INCLUDES . '/WEBLIB_Users_Admin.php');
require_once(WEBLIB_INCLUDES . '/WEBLIB_Collection_Admin.php');
require_once(WEBLIB_INCLUDES . '/WEBLIB_Types_Database_Admin.php');
require_once(WEBLIB_INCLUDES . '/WEBLIB_Circulation_Admin.php');
require_once(WEBLIB_INCLUDES . '/WEBLIB_Statistics_Admin.php');
require_once(WEBLIB_INCLUDES . '/WEBLIB_PatronRecord_Admin.php');


class WEBLIB_AdminPages {

    private $user_admin_pages;
    private $patron_admin_pages;
    private $types_database_pages;
    private $statistics_pages;
    private $collection_pages;
    private $circulation_pages;
    private $patron_holdrecord_page;
    private $patron_outrecord_page;

    function __construct() {
	global $weblib_contextual_help;

	$this->patron_admin_pages = new WEBLIB_Patrons_Admin();
	$this->user_admin_pages = new WEBLIB_Users_Admin($this->patron_admin_pages);
	$this->collection_pages = new WEBLIB_Collection_Admin();
	$this->types_database_pages = new WEBLIB_Types_Database_Admin();
	$this->circulation_pages = new WEBLIB_Circulation_Admin();
	$this->statistics_pages = new WEBLIB_Statistics_Admin();
	$patronid = get_user_meta(wp_get_current_user()->ID,'PatronID',true);
	if ($patronid != '' && WEBLIB_Patron::ValidPatronID($patronid)) {
	  $this->patron_holdrecord_page = new WEBLIB_PatronHoldRecord_Admin();
	  $this->patron_outrecord_page = new WEBLIB_PatronOutRecord_Admin();
	}
	$screen_id = add_submenu_page('options-general.php',
			__('Web Librarian Configuration','weblibrarian'),__('Web Librarian','weblibrarian'),
			'manage_options','weblibrarian-options',
			array($this,'configuration_page'));
	$weblib_contextual_help->add_contextual_help($screen_id,'web-librarian-options');
        $screen_id =  add_menu_page(__('About the Web Librarian','weblibrarian'),__('About','weblibrarian'),
				    'manage_circulation','weblib-about',
				    array($this,'about_page'),
				    WEBLIB_IMAGEURL.'/Circulation_Menu.png');
    }
    function configuration_page() {
      //must check that the user has the required capability
      if (!current_user_can('manage_options'))
      {
	wp_die( __('You do not have sufficient permissions to access this page.','weblibrarian' ));
      }
      if ( get_option('weblib_debugdb') != 'off' && isset($_REQUEST['makedb']) ) {
	global $wpdb;
	$olderror = $wpdb->show_errors(true);
	WEBLIB_make_tables();
	$weblib_tables = $wpdb->get_results("SHOW TABLES LIKE '" . $wpdb->prefix . "weblib_%'",'ARRAY_A');
	?><pre><?php
	echo "weblib_tables = ";
	print_r($weblib_tables);
	?></pre><?php
	$wpdb->show_errors($olderror);
      }
      if ( get_option('weblib_debugdb') != 'off' && isset($_REQUEST['dumpdb']) ) {
	WEBLIB_dump_tables();
      } 
      if ( isset($_REQUEST['saveoptions']) ) {
        $new_public_key = (isset($_REQUEST['aws_public_key']))?sanitize_text_field($_REQUEST['aws_public_key']):'';
        $new_private_key = (isset($_REQUEST['aws_private_key']))?sanitize_text_field($_REQUEST['aws_private_key']):'';
        $new_regiondom = (isset($_REQUEST['aws_regiondom']))?sanitize_text_field($_REQUEST['aws_regiondom']):'';
        $new_associate_tag = (isset($_REQUEST['associate_tag']))?sanitize_text_field($_REQUEST['associate_tag']):'';
	$message = ''; $valid = true;
        if ($new_public_key == '' && $new_private_key == '' && $new_associate_tag == '') {
          $aws = false;
        } else {
          $aws = true;
        }
	if ($new_public_key == '' && $aws) {
	  $message .= '<p class="error">'.__('Public Key missing!','weblibrarian').'</p>';
	  $valid = false;
	}
	if ($new_private_key == '' && $aws) {
	  $message .= '<p class="error">'.__('Private Key missing!','weblibrarian').'</p>';
	  $valid = false;
	}
	if ($new_regiondom == '' && $aws) {
	  $message .= '<p class="error">'.__('Region Domain missing!','weblibrarian').'</p>';
	  $valid = false;
	}
	if ($new_associate_tag == '' && $aws) {
	  $message .= '<p class="error">'.__('Associate Tag missing!','weblibrarian').'</p>';
	  $valid = false;
	}
	if ($valid) {	
	  update_option('weblib_aws_public_key',sanitize_text_field($_REQUEST['aws_public_key']));
	  update_option('weblib_aws_private_key',sanitize_text_field($_REQUEST['aws_private_key']));
	  update_option('weblib_aws_regiondom',sanitize_text_field($_REQUEST['aws_regiondom']));
	  update_option('weblib_associate_tag',sanitize_text_field($_REQUEST['associate_tag']));
	  update_option('weblib_debugdb',sanitize_text_field($_REQUEST['debugdb']));
	  $message = '<p>'.__('Options Saved','weblibrarian').'</p>';
	}
	?><div id="message" class="updated fade"><?php echo $message; ?></p></div><?php
	
      }
      $aws_public_key = get_option('weblib_aws_public_key');
      $aws_private_key = get_option('weblib_aws_private_key');
      $aws_regiondom = get_option('weblib_aws_regiondom');
      $associate_tag = get_option('weblib_associate_tag');
      $debugdb = get_option('weblib_debugdb');
      ?><div class="wrap"><div id="icon-weblib-options" class="icon32"><br /></div><h2><?php _e('Configure Options','weblibrarian'); ?></h2>
        <form method="get" action="<?php echo admin_url('admin.php'); ?>">
	<input type="hidden" name="page" value="weblibrarian-options" />
	<table class="form-table">
	  <tr valign="top">
	     <th scope="row">
		<label for="aws_public_key" style="width:20%;"><?php _e('AWS Public Key:','weblibrarian'); ?></label></th>
	     <td><input type="text" id="aws_public_key" 
			name="aws_public_key" 
			value="<?php echo esc_attr($aws_public_key); ?>"
			 style="width:75%" /></td></tr>
	  <tr valign="top">
	     <th scope="row">
		<label for="aws_private_key" style="width:20%;"><?php _e('AWS Private Key:','weblibrarian'); ?></label></th>
	     <td><input type="text" id="aws_private_key" 
			name="aws_private_key" 
			value="<?php echo esc_attr($aws_private_key); ?>"
			 style="width:75%" /></td></tr>
	  <tr valign="top">
	     <th scope="row">
		<label for="aws_regiondom" style="width:20%;"><?php _e('AWS Region:','weblibrarian'); ?></label></th>
	     <td><select id="aws_regiondom" 
			 name="aws_regiondom" 
			 style="width:75%">
		   <option value="ca"<?php
			if ($aws_regiondom == 'ca')
			  echo 'selected="selected"'; ?>><?php _e('Canada','weblibrarian'); ?></option>
                   <option value="cn"<?php
                        if ($aws_regiondom == 'cn')
			  echo 'selected="selected"'; ?>><?php _e('China','weblibrarian'); ?></option>
                   <option value="de"<?php
			if ($aws_regiondom == 'de')
			  echo 'selected="selected"'; ?>><?php _e('Germany','weblibrarian'); ?></option>
                   <option value="es"<?php
                        if ($aws_regiondom == 'es')
                          echo 'selected="selected"'; ?>><?php _e('Spain','weblibrarian'); ?></option>
		   <option value="fr"<?php
			if ($aws_regiondom == 'fr')
			  echo 'selected="selected"'; ?>><?php _e('France','weblibrarian'); ?></option>
		   <option value="it"<?php
			if ($aws_regiondom == 'it')
			  echo 'selected="selected"'; ?>><?php _e('Italy','weblibrarian'); ?></option>
		   <option value="co.jp"<?php
			if ($aws_regiondom == 'jp' || $aws_regiondom == 'co.jp')
			  echo 'selected="selected"'; ?>><?php _e('Japan','weblibrarian'); ?></option>
		   <option value="co.uk"<?php
			if ($aws_regiondom == 'uk' || $aws_regiondom == 'co.uk')
			  echo 'selected="selected"'; ?>><?php _e('United Kingdom','weblibrarian'); ?></option>
		   <option value="com"<?php 
			if ($aws_regiondom == 'com') 
			  echo 'selected="selected"'; ?>><?php _e('United States','weblibrarian'); ?></option>
		</select></td></tr>
	  <tr valign="top">  
	     <th scope="row">
		<label for="associate_tag" style="width:20%;"><?php _e('Amazon Associate Tag:','weblibrarian'); ?></label></th>
	     <td><input type="text" id="associate_tag"
			name="associate_tag"
			value="<?php echo esc_attr($associate_tag); ?>"
			style="width:75%" /></td></tr>
	  <tr valign="top">
	     <th scope="row">
		<label for="debugdb" style="width:20%;"><?php _e('Debug Database:','weblibrarian'); ?></label></th>
	     <td><select id="debugdb" name="debugdb" style="width:75%">
		 <option value="on"<?php
			if ($debugdb == 'on') echo 'selected="selected"'; ?>><?php _e('On','weblibrarian'); ?></option>
		 <option value="off"<?php
			if ($debugdb == 'off') echo 'selected="selected"'; ?>><?php _e('Off','weblibrarian'); ?></option>
		 </select></td></tr>
	</table>
	<p>
	  <input type="submit" name="saveoptions" class="button-primary" 
			value="<?php _e('Save Options','weblibrarian'); ?>" />
	  <?php if ( get_option('weblib_debugdb') != 'off' ) {
		?><input type="submit" name="makedb" class="button-primary"
			value="<?php _e('Make Database','weblibrarian'); ?>" />
		  <input type="submit" name="dumpdb" class="button-primary"
			value="<?php _e('Dump Database','weblibrarian'); ?>" /><?php
		} ?>
	</p></form></div><?php
    }
    function about_page () {
      ?><div class="wrap"><div id="icon-weblib-options" class="icon32"><br /></div><h2><?php _e('About the Web Librarian','weblibrarian'); ?></h2>
      <h4><?php
	 global $weblibrarian;
	 printf(__('This is version %s of the Web Librarian.','weblibrarian'),$weblibrarian->MyVersion());
	?></h4>      
      <?php
      //Include the localized version of weblib-about.html
          $default_about = WEBLIB_CONTEXTUALHELP.'/'.'weblib-about.html';
          $i18n_about  = WEBLIB_CONTEXTUALHELP.'/'. get_locale() .'/weblib-about.html';
           if (file_exists($i18n_about)) {
             $aboutfile = $i18n_about;
           } else {
             $aboutfile = $default_about;
           } @include($aboutfile); ?>

      <p><a href="http://plugins.svn.wordpress.org/weblibrarian/assets/user_manual/user_manual.pdf"><?php
	_e('Web Librarian User Manual (PDF)','weblibrarian'); ?></a></p>
      <div style="vertical-align: text-top;"><form action="https://www.paypal.com/cgi-bin/webscr" method="post"><?php _e('Donate to the Web Librarian plugin software effort.','weblibrarian'); ?>
        <input type="hidden" name="cmd" value="_s-xclick">
        <input type="hidden" name="hosted_button_id" value="RFSD2JRQVGP7C">
        <input type="image" src="https://www.paypalobjects.com/en_US/i/btn/btn_donate_LG.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!">
        <img alt="" border="0" src="https://www.paypalobjects.com/en_US/i/scr/pixel.gif" width="1" height="1">
      </form></form></div>
      <div style="vertical-align: text-top;"><a href="http://www.deepsoft.com/home/products/dwsmerch/" target="_blank"><?php _e('Buy some Deepwoods Software merchandise','weblibrarian'); ?></a></div>
      <div style="vertical-align: text-top;"><a href="http://amzn.com/w/3679UKP8RZRI9"><?php _e("Deepwoods Software's Amazon Wish List",'weblibrarian'); ?></a></div>
      <?php
    }
    function wp_dashboard_setup() {
	wp_add_dashboard_widget('weblib-quick-stats', 
				__('Circulation Quick Stats','weblibrarian'),
				array($this, 'QuickStats'));
    }
    function user_wp_dashboard_setup() {
	$patronid = get_user_meta(wp_get_current_user()->ID,'PatronID',true);
	if ($patronid != '' && WEBLIB_Patron::ValidPatronID($patronid)) {
	  wp_add_dashboard_widget('weblib-user-stats',
				  __('Patron Circulation Stats','weblibrarian'),
				  array($this, 'PatronCirculationStats'));
	}
    }
    function QuickStats() {
	$thisyear = date('Y',time());
	$thismonth = date('m',time());
	$monthlyTotal = WEBLIB_Statistic::MonthTotal($thisyear,$thismonth);
	$types = WEBLIB_Type::AllTypes();
	$typetotals = array();
	foreach ($types as $type) {
	  $typetotals[$type] = WEBLIB_Statistic::TypeCount($type,$thisyear,$thismonth);
	}
	?><div class="table">
          <h3><?php _e('For','weblibrarian'); 
               ?>&nbsp;<?php echo date_i18n('F, Y',time()); ?></h3>
	    <table class="weblib-quick-stats" width="80%">
	    <thead><tr><th width="80%" align="left"><?php _e('Circulation Type','weblibrarian'); ?></th>
	    	       <th width="20%" align="right"><?php _e('Count','weblibrarian'); ?></th></tr></thead>
	    <tbody><?php
		foreach ($types as $type) {
		  ?><tr><td><?php echo $type;
		  ?></td><td align="right"><?php echo $typetotals[$type];
		  ?></td></tr><?php
		}
		?><tr><td><?php _e('Total','weblibrarian'); ?></td><td align="right"><?php echo $monthlyTotal;
		?></td></tr></tbody></table></div><?php
    }
    function PatronCirculationStats() {
      $patronid = get_user_meta(wp_get_current_user()->ID,'PatronID',true);
      $myholds = WEBLIB_HoldItem::HeldItemsOfPatron($patronid);
      $myouts  = WEBLIB_OutItem::OutItemsOfPatron($patronid);
      $name = WEBLIB_Patron::NameFromId($patronid);
      ?><div class="table">
	<h3><?php printf(__('Circulation Statistics for %s','weblibrarian'),$name); ?></h3>
	<table class="weblib-user-record-stats" width="80%">
	<tbody>
	<tr><td><?php _e('Items on hold:','weblibrarian'); ?></td><td><?php echo count($myholds); ?></td></tr>
	<tr><td><?php _e('Items checked out:','weblibrarian'); ?></td><td><?php echo count($myouts); ?></td></tr>
	</tbody></table></div><?php
    }
    static function set_screen_options($status,$option,$value) {
	//file_put_contents("php://stderr","*** WEBLIB_AdminPages::set_screen_options($status,$option,$value)\n");
	//file_put_contents("php://stderr","*** WEBLIB_Patrons_Admin::my_per_page = ".WEBLIB_Patrons_Admin::$my_per_page."\n");
	if ($option == WEBLIB_Patrons_Admin::$my_per_page) return true;
	//file_put_contents("php://stderr","*** WEBLIB_Users_Admin::my_per_page = ".WEBLIB_Users_Admin::$my_per_page."\n");
	if ($option == WEBLIB_Users_Admin::$my_per_page) return true;
	//file_put_contents("php://stderr","*** WEBLIB_Collection_Admin::my_per_page = ".WEBLIB_Collection_Admin::$my_per_page."\n");
	if ($option == WEBLIB_Collection_Admin::$my_per_page) return true;
	//file_put_contents("php://stderr","*** WEBLIB_Types_Database_Admin::my_per_page = ".WEBLIB_Types_Database_Admin::$my_per_page."\n");
	if ($option == WEBLIB_Types_Database_Admin::$my_per_page) return true;
	//file_put_contents("php://stderr","*** WEBLIB_Circulation_Admin::my_per_page = ".WEBLIB_Circulation_Admin::$my_per_page."\n");
	if ($option == WEBLIB_Circulation_Admin::$my_per_page) return true;
	//file_put_contents("php://stderr","*** WEBLIB_Statistics_Admin::my_per_page = ".WEBLIB_Statistics_Admin::$my_per_page."\n");
	if ($option == WEBLIB_Statistics_Admin::$my_per_page) return true;
	//file_put_contents("php://stderr","*** WEBLIB_PatronHoldRecord_Admin::my_per_page = ".WEBLIB_PatronHoldRecord_Admin::$my_per_page."\n");
	if ($option == WEBLIB_PatronHoldRecord_Admin::$my_per_page) return true;
	//file_put_contents("php://stderr","*** WEBLIB_PatronOutRecord_Admin::my_per_page = ".WEBLIB_PatronOutRecord_Admin::$my_per_page."\n");
	if ($option == WEBLIB_PatronOutRecord_Admin::$my_per_page) return true;
	return false;
    }

}

