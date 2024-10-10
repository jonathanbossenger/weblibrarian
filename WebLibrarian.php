<?php
/**
 * Plugin Name: Web Librarian WP Plugin
 * Plugin URI: http://www.deepsoft.com/WebLibrarian
 * Description: A plugin that implements a web-based library catalog and circulation System
 * Version: 3.5.8.4
 * Author: Robert Heller
 * Author URI: http://www.deepsoft.com/
 * Text Domain: weblibrarian
 * Domain Path: /languages
 *
 *  Web Librarian WP Plugin
 *  Copyright (C) 2011  Robert Heller D/B/A Deepwoods Software
 *			51 Locke Hill Road
 *			Wendell, MA 01379-9728
 *
 *  This program is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program; if not, write to the Free Software
 *  Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
 *
 *
 */

define('WEBLIB_FILE', basename(__FILE__));
define('WEBLIB_DIR' , dirname(__FILE__));
define('WEBLIB_INCLUDES', WEBLIB_DIR . '/includes');
define('WEBLIB_CONTEXTUALHELP', WEBLIB_DIR . '/contextual_help');


/* Load Database code */
require_once(WEBLIB_INCLUDES . '/database_code.php');
/* Load Admin Code */
require_once(WEBLIB_INCLUDES . '/admin_page_classes.php');
/* Load Front End code */
require_once(WEBLIB_INCLUDES . '/short_codes.php');
require_once(WEBLIB_INCLUDES . '/WEBLIB_PatronShortCodes.php');
class WebLibrarian {
  
    static private $pluginFile = __FILE__;
    private $version;
    private $admin_page;
    private $short_code_class;
    private $patron_short_code_class;
    private $awsxml_class;
    static function _getVersion() {
      $version = '';
      $fp = fopen(self::$pluginFile,'r');
      if ($fp) {
        while ($line = fgets($fp)) {
          if (preg_match("/^\s*\*\s*$/",$line) > 0) {break;}
          if (preg_match('/^\s*\*\s*Version:\s*(.*)$/',$line,$matches) > 0) {
            $version = $matches[1];
            break;
          }
        }
        fclose($fp);
      }
      return $version;
    }
    

    /* Constructor: register our activation and deactivation hooks and then
     * add in our actions.
     */
    function __construct() {
        $this->version = self::_getVersion();
        define('WEBLIB_BASEURL',plugins_url( '', __FILE__));
        define('WEBLIB_CSSURL', plugins_url('/css',__FILE__));
        define('WEBLIB_JSURL', plugins_url('/js',__FILE__));
        define('WEBLIB_IMAGEURL', plugins_url('/images',__FILE__));
        //define('WEBLIB_DOCURL', plugins_url('/user_manual',__FILE__));
	// Add the installation and uninstallation hooks
	register_activation_hook(WEBLIB_DIR . '/' . WEBLIB_FILE, 
				array($this,'install'));
	register_deactivation_hook(WEBLIB_DIR . '/' . WEBLIB_FILE,
				array($this,'deinstall'));
        add_action('init', array($this, 'init_action'));
	add_action('admin_print_scripts', array($this, 'add_admin_scripts'));
	add_action('wp_print_scripts', array($this, 'add_front_scripts'));
	add_action('wp_head', array($this, 'wp_head'));
	add_action('admin_head', array($this, 'admin_head'));
	add_action('widgets_init', array($this, 'widgets_init'));
	add_action('admin_menu', array($this, 'admin_menu'));
	add_filter('set-screen-option',array($this, 'set_screen_options'), 10, 3);
	add_filter('body_class', array($this, 'body_class'));
	add_action('weblib_admin_daily_event',array($this,'every_day'));
	add_option('weblib_aws_public_key','');
	add_option('weblib_aws_private_key','');
	add_option('weblib_aws_regiondom','com');
	add_option('weblib_associate_tag','');
	add_option('weblib_debugdb','off');

	load_plugin_textdomain('weblibrarian',WEBLIB_BASEURL.'/languages/',
                                          basename(WEBLIB_DIR).'/languages/');
	
        $this->short_code_class = new WEBLIB_ShortCodes();
        $this->patron_short_code_class = new WEBLIB_PatronShortCodes();
        $this->awsxml_class = new WEBLIB_AWS_Ajax();
	if (is_admin()) {
          //  wp_enqueue_script('jquery-ui-sortable');
          add_action('wp_ajax_UpdatePatronID', array($this, 'UpdatePatronID') );
          add_action('wp_ajax_FindPatron', array($this, 'FindPatron') );
          add_action('wp_ajax_PlaceHoldOnItem', array($this, 'PlaceHoldOnItem') );
          add_action('wp_ajax_RenewItem', array($this, 'RenewItem') );
          add_action('admin_post_AWSForm', array($this,'AWSForm') );
          add_action('admin_post_ExportLibraryData', array($this,'ExportLibraryData'));
          add_action('admin_post_ExportLibraryStats', array($this,'ExportLibraryStats'));
	}
    }
    function init_action() {
      wp_enqueue_style('weblib-front-style',WEBLIB_CSSURL . '/front.css',
                       null,$this->version);
      if (is_admin()) {
        wp_enqueue_style('jquery-ui-lightness',WEBLIB_CSSURL . '/jquery-ui-lightness/jquery-ui-lightness.css',null,$this->version);
        wp_enqueue_style('jquery-ui-resizable',WEBLIB_CSSURL . '/jquery-ui-lightness/jquery.ui.resizable.css',null,$this->version);
        wp_enqueue_style('weblib-admin-style',WEBLIB_CSSURL . '/admin.css',
                         array('weblib-front-style','jquery-ui-resizable'),$this->version);
      }
    }
    function MyVersion() {return $this->version;}
    function install() {
	$this->add_roles_and_caps();
	WEBLIB_make_tables();
	wp_schedule_event(mktime(2,0,0), 'daily', 'weblib_admin_daily_event');
    }
    function deinstall() {
	$this->remove_roles_and_caps();
	wp_clear_scheduled_hook('weblib_admin_daily_event');
    }
    function add_roles_and_caps() {
	global $wp_roles;
	$librarian = get_role('librarian');
	if ($librarian == null) {
          add_role('librarian', __('Librarian','weblibrarian'), 
                   array('read' => true,
                         'view_admin_dashboard' => true,
                         'edit_users' => true,
                         'manage_patrons' => true,
                         'manage_collection' => true,
                         'manage_circulation' => true));
	} else {
          $librarian->add_cap('edit_users');
          $librarian->add_cap('view_admin_dashboard');
          $librarian->add_cap('manage_patrons');
          $librarian->add_cap('manage_collection');
          $librarian->add_cap('manage_circulation');
	}
	$senioraid = get_role('senioraid');
	if ($senioraid == null) {
          add_role('senioraid', __('Senior Aid','weblibrarian'), 
                   array('read' => true,
                         'view_admin_dashboard' => true,
                         'manage_collection' => true,
                         'manage_circulation' => true));
	} else {
          $senioraid->add_cap('view_admin_dashboard');
          $senioraid->add_cap('manage_collection');
          $senioraid->add_cap('manage_circulation');
	}
	$volunteer = get_role('volunteer');
	if ($volunteer == null) {
          add_role('volunteer', __('Volunteer','weblibrarian'), 
                   array('read' => true,
                         'view_admin_dashboard' => true,
                         'manage_circulation' => true));
	} else {
          $volunteer->add_cap('view_admin_dashboard');
          $volunteer->add_cap('manage_circulation');
	}
    }
    function remove_roles_and_caps() {
	global $wp_roles;
	$librarian = get_role('librarian');
	if ($librarian  != null) {
	    $librarian->remove_cap ( 'manage_patrons' );
	    $librarian->remove_cap ( 'manage_collection' );
	    $librarian->remove_cap ( 'manage_circulation' );
            $librarian->remove_cap ( 'view_admin_dashboard' );
	    $librarian->remove_cap ( 'edit_users' );
        }
        remove_role('librarian');   
	$senioraid = get_role('senioraid');
	if ($senioraid  != null) {
	    $senioraid->remove_cap ( 'manage_collection' );
	    $senioraid->remove_cap ( 'manage_circulation' );
            $senioraid->remove_cap ( 'view_admin_dashboard' );
        }
        remove_role('senioraid');
	$volunteer = get_role('volunteer');
	if ($volunteer  != null) {
          $volunteer->remove_cap ( 'manage_circulation' );
          $volunteer->remove_cap ( 'view_admin_dashboard' );
        }
        remove_role('volunteer');  
    }
    static function localize_vars_front() {
      return array(
                   'WEBLIB_BASEURL' => WEBLIB_BASEURL,
                   'ajax_url' => admin_url( 'admin-ajax.php' ),
                   'hold' => __('Hold','weblibrarian'),
                   'holds' => __('Holds','weblibrarian'),
                   'nodata' => __('Ajax error:  No Data Received','weblibrarian'),
                   'ajaxerr' => __('Ajax error: ','weblibrarian')
                   );
    }
    static function localize_vars_admin() {
	return array(
                     'WEBLIB_BASEURL' => WEBLIB_BASEURL,
                      'ajax_url' => admin_url( 'admin-ajax.php' ),
                      'hold' => __('Hold','weblibrarian'),
                      'holds' => __('Holds','weblibrarian'),
                      'nodata' => __('Ajax error:  No Data Received','weblibrarian'),
                      'ajaxerr' => __('Ajax error: ','weblibrarian'),
                      'totalResultsFount' => __('%d total results found','weblibrarian'),
                      'loading' => __('Loading','weblibrarian'),
                      'lookupItem' => __('Lookup Item','weblibrarian'),
                      'insertItem' => __('Insert Item','weblibrarian'),
                      'lookupComplete' => __('Lookup Complete.','weblibrarian'),
                      'formInsertionComplete' => __('Form insertion complete.','weblibrarian'),
                      'lookingUpPatron' => __('Looking up Patron','weblibrarian'),
                      'noMatchingPatrons' => __('No matching patrons found.','weblibrarian'),
                      'selectPatron' => __('Select Patron','weblibrarian'),
                      'insertTitle' => __('Insert Title','weblibrarian'),
                      'insertISBN' => __('Insert ISBN','weblibrarian'),
                      'insertThumbnail' => __('Insert Thumbnail','weblibrarian'),
                      'addToAuthor' => __('Add to Author','weblibrarian'),
                      'insertAsDate' => __('Insert as date','weblibrarian'),
                      'insertAsPublisher' => __('Insert As Publisher','weblibrarian'),
                      'insertEdition' => __('Insert Edition','weblibrarian'),
                      'addToMedia' => __('Add to Media','weblibrarian'),
                      'addToDescription' => __('Add to description','weblibrarian'),
                      'addToKeywords' => __('Add to keywords','weblibrarian')
	);
    }
    function add_admin_scripts() {
      //$this->add_front_scripts();
      wp_enqueue_script('jquery-ui-resizable');
      wp_enqueue_script('admin_js',WEBLIB_JSURL . '/admin.js', array('front_js','jquery-ui-resizable'), $this->version);
      wp_localize_script( 'admin_js','admin_js',self::localize_vars_admin() );
    }
    function add_front_scripts() {
      wp_enqueue_script('jquery');
      wp_enqueue_script('front_js',WEBLIB_JSURL . '/front.js', array(), $this->version);
      wp_localize_script( 'front_js','front_js',self::localize_vars_front() );
    }
    function wp_head() {
    }
    function admin_head() {
    }
    function widgets_init() {
      register_widget('WEBLIB_StrippedMeta');
      register_widget('WEBLIB_PartonCirs');
    }
    function body_class($classes='') {
	$classes[] = "no-js";
	return $classes;
    }
    function every_day() {
	$cleared = WEBLIB_HoldItem::ClearExpiredHolds();
    }
    function admin_menu() {
	$this->admin_page = new WEBLIB_AdminPages();
	if (current_user_can('manage_circulation')) {
	   add_action('wp_dashboard_setup',array($this->admin_page,
						 'wp_dashboard_setup'));
	}
	add_action('wp_dashboard_setup',array($this->admin_page,
						'user_wp_dashboard_setup'));
    }
    function set_screen_options($status,$option,$value) {
      //file_put_contents("php://stderr","*** WebLibrarian::set_screen_options($status,$option,$value)\n");
      if (WEBLIB_AdminPages::set_screen_options($status,$option,$value)) return $value;
    }
    // AJAX callbacks
    function UpdatePatronID() {
      $userid = sanitize_text_field($_REQUEST['userid']);
      $patronid = sanitize_text_field($_REQUEST['patronid']);
      $xml_response = '<?xml version="1.0" ?>';

      if (get_userdata($userid) == null || !current_user_can('edit_users') ) {
        $xml_response .= '<answer><userid>'.$userid.'</userid><patronid>'.$patronid.'</patronid></answer>';
      } else if ($patronid == '' || $patronid == 0) {
        delete_user_meta( $userid, 'PatronID', get_user_meta($userid, 'PatronID',true) );
        $xml_response .= '<answer><userid>'.$userid.'</userid><patronid>0</patronid></answer>';
      } else {
        $oldpid = get_user_meta($userid, 'PatronID', true);
        update_user_meta( $userid, 'PatronID', $patronid, $oldpid);
        $xml_response .= '<answer><userid>'.$userid.'</userid><patronid>'.$patronid.'</patronid></answer>';
      }
      /* http Headers */
      @header('Content-Type: text/xml');
      @header('Content-Length: '.strlen($xml_response));
      @header("Pragma: no-cache");
      @header("Expires: 0");
      @header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
      @header("Robots: none");
      echo "";	/* End of headers */
      echo $xml_response;
      wp_die();
    }
    function FindPatron() {
      if (!current_user_can('manage_circulation')) {
        wp_die( __('You do not have sufficient permissions to access this page.','weblibrarian') );
      }
      $searchname = sanitize_text_field($_REQUEST['searchname']);
      $xml_response = '<?xml version="1.0" ?>';

      $patronlist = WEBLIB_Patron::FindPatronByName($searchname.'%');

      $xml_response .= '<patronlist>';

      for ($j = 0; $j < count($patronlist); $j++) {
        $xml_response .= '<patron>';
        $xml_response .= '<id>'.$patronlist[$j]['patronid'].'</id>';
        $xml_response .= '<name>'.$patronlist[$j]['name'].'</name>';
        $xml_response .= '</patron>';
      }

      $xml_response .= '</patronlist>';

      /* http Headers */
      @header('Content-Type: text/xml');
      @header('Content-Length: '.strlen($xml_response));
      @header("Pragma: no-cache");
      @header("Expires: 0");
      @header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
      @header("Robots: none");
      echo "";	/* End of headers */
      echo $xml_response;
      wp_die();
    }
    function PlaceHoldOnItem() {
      $barcode = sanitize_text_field($_REQUEST['barcode']);
      $xml_response = '<?xml version="1.0" ?>';

      if (current_user_can('manage_circulation') && isset($_REQUEST['patronid'])) {
        $patronid = sanitize_text_field($_REQUEST['patronid']);
      } else {
        $patronid = get_user_meta(wp_get_current_user()->ID,'PatronID',true);
      }
      //file_put_contents("php://stderr","*** PlaceHoldOnItem.php: (before if) xml_response = $xml_response\n");

      if (!WEBLIB_ItemInCollection::IsItemInCollection($barcode)) {
        $xml_response .= '<message>'.sprintf(__('No such item: %s!','weblibrarian'),$barcode).'</message>';
      } else if ($patronid == '' || !WEBLIB_Patron::ValidPatronID($patronid)) {
        $xml_response .= '<message>'.sprintf(__('No such patron id: %d!','weblibrarian'),$patronid).'</message>';
      } else if (WEBLIB_HoldItem::PatronAlreadyHolds($patronid,$barcode)) {
        $xml_response .= '<message>'.__('Patron already has a hold on this item!','weblibrarian').'</message>';
      } else {
        $item = new WEBLIB_ItemInCollection($barcode);
        if ($item->type() == '' && !WEBLIB_Type::KnownType($item->type())) {
          $xml_response .= '<message>'.sprintf(__('Item has invalid type: %s!','weblibrarian'),$item->type()).'</message>';
        } else {
          $type = new WEBLIB_Type($item->type());
          $expiredate = date('Y-m-d',time()+($type->loanperiod()*24*60*60));
          $transaction = $item->hold($patronid, 'Local', $expiredate);
          if ($transaction > 0) {
            $newhold = new WEBLIB_HoldItem($transaction);
            $patronid = $newhold->patronid();
            $telephone = WEBLIB_Patrons_Admin::addtelephonedashes(WEBLIB_Patron::TelephoneFromId($patronid));
            $userid = WEBLIB_Patron::UserIDFromPatronID($patronid);
            $email = get_userdata( $userid )->user_email;
            $patronname = WEBLIB_Patron::NameFromID($patronid);
            $expires = mysql2date('F j, Y',$newhold ->dateexpire());
            $xml_response .= '<result><barcode>'.$barcode.'</barcode><holdcount>'.
            WEBLIB_HoldItem::HoldCountsOfBarcode($barcode).
            '</holdcount><name>'.$patronname.'</name><email>'.$email.
            '</email><telephone>'.$telephone.'</telephone><expires>'.$expires.
            '</expires></result>';
            //file_put_contents("php://stderr","*** PlaceHoldOnItem.php: (after transaction) xml_response = $xml_response\n");
          } else {
            $xml_response .= '<message>Hold failed!</message>';
          }
        }
      }

      //file_put_contents("php://stderr","*** PlaceHoldOnItem.php: (after if) xml_response = $xml_response\n");

      /* http Headers */
      @header('Content-Type: text/xml');
      @header('Content-Length: '.strlen($xml_response));
      @header("Pragma: no-cache");
      @header("Expires: 0");
      @header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
      @header("Robots: none");
      echo "";	/* End of headers */
      echo $xml_response;
      wp_die();
    }
    function RenewItem() {
      $barcode = sanitize_text_field($_REQUEST['barcode']);
      $xml_response = '<?xml version="1.0" ?>';

      $outitem = WEBLIB_OutItem::OutItemByBarcode($barcode);
      if ($outitem != null) {
        $numberofholds = WEBLIB_HoldItem::HoldCountsOfBarcode($outitem->barcode());
        if ($numberofholds > 0) {
          $xml_response .= '<message>Someone else has a hold on this item!</message>';
        } else if ($outitem->patronid() == 
                   get_user_meta(wp_get_current_user()->ID,'PatronID',true) ||
                   current_user_can('manage_circulation') ) {
          $type = new WEBLIB_Type($outitem->type());
          $currentdue = strtotime($outitem->datedue());
          $originalout = strtotime($outitem->dateout());
          $newduedate = $currentdue + ($type->loanperiod() * 24 * 60 * 60);
          $totalloandays = ($newduedate - $originalout) / (24 * 60 * 60);
          $renewals = $totalloandays / $type->loanperiod();
          unset($type);
          if ($renewals > 3) {
            $xml_response .= '<message>Maximum number of renewals reached.</message>';
          } else {
            $duedate = date('Y-m-d',$newduedate);
            $outitem->set_datedue($duedate);
            $outitem->store();
            $xml_response .= '<result><barcode>'.$barcode.'</barcode><duedate>'.
            mysql2date('F j, Y',$duedate).'</duedate></result>';
          }
        } else {
          $xml_response .= '<message>You do not have enough priviledge to do this!</message>';
        }
      } else {
        $xml_response .= '<message>Item is not checked out!</message>';
      }
      
      /* http Headers */
      @header('Content-Type: text/xml');
      @header('Content-Length: '.strlen($xml_response));
      @header("Pragma: no-cache");
      @header("Expires: 0");
      @header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
      @header("Robots: none");
      echo "";	/* End of headers */
      echo $xml_response;
      wp_die();
    }
    function AWSForm () {
      //file_put_contents("php://stderr","*** WebLibrarian::AWSForm called.\n");
      if (function_exists('admin_url')) {
        wp_admin_css_color('classic', __('Blue'), admin_url("css/colors-classic.css"), array('#073447', '#21759B', '#EAF3FA', '#BBD8E7'));
        wp_admin_css_color('fresh', __('Gray'), admin_url("css/colors-fresh.css"), array('#464646', '#6D6D6D', '#F1F1F1', '#DFDFDF'));
      } else {
        wp_admin_css_color('classic', __('Blue'), get_bloginfo('wpurl').'/wp-admin/css/colors-classic.css', array('#073447', '#21759B', '#EAF3FA', '#BBD8E7'));
        wp_admin_css_color('fresh', __('Gray'), get_bloginfo('wpurl').'/wp-admin/css/colors-fresh.css', array('#464646', '#6D6D6D', '#F1F1F1', '#DFDFDF'));
      }
    
      wp_enqueue_script( 'common' );
      wp_enqueue_script( 'jquery-color' );

      wp_enqueue_style('weblib-front-style',WEBLIB_CSSURL . '/front.css',
                       null,$version);
      wp_enqueue_style('weblib-admin-style',WEBLIB_CSSURL . '/admin.css',
                       array('weblib-front-style'),$version);

      wp_enqueue_script('front_js',WEBLIB_JSURL . '/front.js', array(), $version);
      wp_localize_script( 'front_js','front_js',WebLibrarian::localize_vars_front());
      wp_enqueue_script('jquery-ui-resizable');
      wp_enqueue_script('admin_js',WEBLIB_JSURL . '/admin.js', array('front_js','jquery-ui-resizable'), $version);
      wp_localize_script( 'admin_js','admin_js',WebLibrarian::localize_vars_admin());
      wp_enqueue_script('AWSFunctions_js',WEBLIB_JSURL . '/AWSFunctions.js', 
                        array('admin_js'), $version);

    ?><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
      <html xmlns="http://www.w3.org/1999/xhtml" <?php do_action('admin_xml_ns'); ?> <?php language_attributes(); ?>>
      <head>
        <meta http-equiv="Content-Type" content="<?php bloginfo('html_type'); ?>; charset=<?php echo get_option('blog_charset'); ?>" />
        <title><?php bloginfo('name') ?> &rsaquo; <?php _e('Uploads'); ?> &#8212; <?php _e('WordPress'); ?></title>
        <?php
          wp_enqueue_style( 'global' );
          wp_enqueue_style( 'wp-admin' );
          wp_enqueue_style( 'colors' );
          wp_enqueue_style( 'media' );
        ?>
        <script type="text/javascript">
        //<![CDATA[
         function addLoadEvent(func) {if ( typeof wpOnload!='function'){wpOnload=func;}else{ var oldonload=wpOnload;wpOnload=function(){oldonload();func();}}}
         //]]>
        </script>
        <?php
          do_action('admin_print_styles');
          do_action('admin_print_scripts');
          do_action('admin_head');
          if ( isset($content_func) && is_string($content_func) )
          do_action( "admin_head_{$content_func}" );
          $tab = isset($_REQUEST['tab'])?sanitize_text_field($_REQUEST['tab']):'links';
          
      ?></head>
        <body id="aws-form" class="wp-admin branch-3-3 version-3-3-2 admin-color-fresh">
        <div id="item-aws">
          <span id="amazon-search-workstatus"></span><br clear="all" />
          <div id="amazon-result-list"></div>
          <span id="amazon-page-buttons">
            <input type="text" id="amazon-page-1" class="page-label" 
             value="1" readonly="readonly" size="1"/>
            <input type="button" id="amazon-goto-page-1" 
             value="<<" class="page-button" 
               onclick="AWSGotoFirstPage();" />
              <input type="button" id="amazon-goto-previous-page"
               value="<" class="page-button"
                onclick="AWSGotoPrevPage();" />
               <input type="button" id="amazon-goto-page"
                value="<?php _e('Goto Page:','weblibrarian'); ?>" class="page-button"
                onclick="AWSGotoPage();" />
               <input type="text" id="amazon-page-current" 
                class="page-label"
                value="  "  size="3"/>
               <input type="button" id="amazon-goto-next-page"
                value=">" class="page-button"
               onclick="AWSGotoNextPage();" />
              <input type="button" id="amazon-goto-last-page"
               value=">>" class="page-button"
             onclick="AWSGotoLastPage();" />
            <input type="text" id="amazon-page-N" class="page-label"
             value="  " readonly="readonly" size="3" />
            <br /></span>
          <span id="amazon-search-box">
            <label for="SearchIndex"><?php _e('Search In:','weblibrarian'); ?></label>
            <select id="SearchIndex">
              <option value="Books" selected="selected"><?php _e('Books','weblibrarian'); ?></option>
              <option value="DVD"><?php _e('DVD','weblibrarian'); ?></option>
              <option value="Music"><?php _e('Music','weblibrarian'); ?></option>
              <option value="Video"><?php _e('Video','weblibrarian'); ?></option>
            </select>
            <label for="FieldName"><?php _e('for','weblibrarian'); ?></label>
            <select id="FieldName">
              <option value="Title" selected="selected"><?php _e('Title','weblibrarian'); ?></option>
              <option value="Artist"><?php _e('Artist','weblibrarian'); ?></option>
              <option value="Author"><?php _e('Author','weblibrarian'); ?></option>
              <option value="Keywords"><?php _e('Keywords','weblibrarian'); ?></option>
            </select>
            <input id="SearchString" type='text' value="" />
            <input type="button" id="Go" onclick="AWSSearch(1);" value="<?php _e('Go','weblibrarian'); ?>" />
          </span>
        </div>
        <a name="amazon-item-lookup-display"></a>
        <div id="amazon-item-lookup-display"></div>
        <span id="amazon-item-lookup-workstatus"></span><br clear="all" />
        </body></html><?php 
       wp_die();  
    }
    function ExportLibraryData() {
      if (headers_sent()) {
        wp_die("Opps too late");
      }
      $dataselection = sanitize_text_field($_REQUEST['dataselection']);
      
      //file_put_contents("php://stderr","*** WebLibrarian::ExportLibraryData: dataselection = $dataselection\n");
      switch ($dataselection) {
      case 'patrons':
        if (!current_user_can('manage_patrons')) {
          @header('Content-Type: ' . get_option('html_type') . '; charset=' . get_option('blog_charset'));
          echo "";
          wp_die( __('You do not have sufficient permissions to access this page.','weblibrarian') );
        }
        $csv = WEBLIB_Patron::export_csv();
        $filename = 'patrons.csv';
        break;
      case 'collection':
        //file_put_contents("php://stderr","*** WebLibrarian::ExportLibraryData: priv check\n");
        if (!current_user_can('manage_collection')) {
          @header('Content-Type: ' . get_option('html_type') . '; charset=' . get_option('blog_charset'));
          echo "";
          wp_die( __('You do not have sufficient permissions to access this page.','weblibrarian') );
        }
        //file_put_contents("php://stderr","*** WebLibrarian::ExportLibraryData: priv check OK\n");
        $csv = WEBLIB_ItemInCollection::export_csv();
        //file_put_contents("php://stderr","*** WebLibrarian::ExportLibraryData: csv is ".strlen($csv)." bytes long.\n");
        $filename = 'collection.csv';
        break;
      default:
        @header('Content-Type: ' . get_option('html_type') . '; charset=' . get_option('blog_charset'));
        echo "";
        wp_die(__('Bad value for dataselection!','weblibrarian'));
        break;
      }
      
      //file_put_contents("php://stderr","*** WebLibrarian::ExportLibraryData: headers_sent() returns ".headers_sent()."\n");
      
      header("Content-type: text/csv");
      //file_put_contents("php://stderr","*** WebLibrarian::ExportLibraryData: sent Content-type header\n");
      header("Content-Disposition: attachment; filename=".$filename);
      //file_put_contents("php://stderr","*** WebLibrarian::ExportLibraryData: sent Content-Disposition header\n");
      header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
      //file_put_contents("php://stderr","*** WebLibrarian::ExportLibraryData: sent Cache-Control header\n");
      header("Content-Length: " . strlen($csv));
      //file_put_contents("php://stderr","*** WebLibrarian::ExportLibraryData: sent Content-Length header\n");
      echo "";
      //file_put_contents("php://stderr","*** WebLibrarian::ExportLibraryData: sent end of headers\n");

      echo $csv;
      die();
    }
    function csv_quote($string) {
      return addcslashes($string,"\\".'"');
    }
    function ExportLibraryStats() {
      if (!current_user_can('manage_circulation')) {
        wp_die( __('You do not have sufficient permissions to access this page.','weblibrarian') );
      }
      
      $year  = isset( $_REQUEST['year'] )  ? sanitize_text_field($_REQUEST['year'])  : date('Y',time());
      $month = isset( $_REQUEST['month'] ) ? sanitize_text_field($_REQUEST['month']) : date('m',time());
      $MonthNames = 
      array('Month Totals','January','February','March','April','May','June',
            'July','August','September', 'October','November','December');
      

      $csv = '';

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
          $csv .= '"'.$this->csv_quote($type).'",'.WEBLIB_Statistic::TypeCount($type,$year,$month)."\n";
        }
        $csv .= '"Total",'.WEBLIB_Statistic::MonthTotal($year,$month)."\n";
      }
      
      header("Content-type: text/csv");
      header("Content-Disposition: attachment; filename=librarystats.csv");
      header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
      header("Content-Length: " . strlen($csv));
      
      echo $csv;
      die();
    }
}

/* Define widget code */
class WEBLIB_StrippedMeta extends WP_Widget {
    function __construct() {
	$widget_ops = array('classname' => 'widget_strippedmeta', 'description' => __( "Log in/out, admin",'weblibrarian') );
	parent::__construct('strippedmeta', __('Stripped Meta','weblibrarian'), $widget_ops);
    }

    function widget( $args, $instance ) {
	extract($args);
	echo $before_widget;
        
?>
	    <ul>
	    <?php
		if (is_user_logged_in()){ ?>
		    <li><a href="<?php echo bloginfo('wpurl');
		    ?>/wp-admin" alt="admin">Dashboard</a></li><?php
		} else { ?>
		    <li><a href="<?php
		    echo site_url('wp-login.php?action=register', 'login');
		    ?>">Register</a></li><?php
		} ?>
	    <li><?php wp_loginout(); ?></li>
	    </ul>
<?php
	echo $after_widget;
    }

    function update( $new_instance, $old_instance ) {
	$instance = $old_instance;
	return $instance;
    }

    function form( $instance ) {
	$instance = wp_parse_args( (array) $instance, array( 'title' => '' ) );
    }
}

class WEBLIB_PartonCirs extends WP_Widget {
  function __construct() {
    $widget_ops = array('classname' => 'widget_patroncirs', 'description' => __( "Patron Circulation Stats",'weblibrarian') );
    parent::__construct('patroncirs', __('Patron Circulation Stats','weblibrarian'), $widget_ops);
  }
  function widget( $args, $instance ) {
    extract($args);
    echo $before_widget;
    $patronid = get_user_meta(wp_get_current_user()->ID,'PatronID',true);
    if ($patronid != '' && WEBLIB_Patron::ValidPatronID($patronid)) {
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
    echo $after_widget;
  }
  
  function update( $new_instance, $old_instance ) {
    $instance = $old_instance;
    return $instance;
  }
  
  function form( $instance ) {
    $instance = wp_parse_args( (array) $instance, array( 'title' => '' ) );
  }
}
        


class WEBLIB_AWS_Ajax {

  /* AWS Ajax support code: Sign an AWS Amazon Product Advertising API
    request and issue a request and send the XML back out to the 
    JavaScript */

  /*
    Modified to use CURL : Sameer Borate
    Original code Copyright (c) 2009 Ulrich Mierendorff
    
    Permission is hereby granted, free of charge, to any person obtaining a
    copy of this software and associated documentation files (the "Software"),
    to deal in the Software without restriction, including without limitation
    the rights to use, copy, modify, merge, publish, distribute, sublicense,
    and/or sell copies of the Software, and to permit persons to whom the
    Software is furnished to do so, subject to the following conditions:
    
    The above copyright notice and this permission notice shall be included in
    all copies or substantial portions of the Software.
    
    THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
    IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
    FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL
    THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
    LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
    FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER
    DEALINGS IN THE SOFTWARE.
    */
  
  
  /*
    
    More information on the authentication process can be found here:
    http://docs.amazonwebservices.com/AWSECommerceService/latest/DG/BasicAuthProcess.html
    
    */



  function  aws_signed_request($region,$params,$public_key,$private_key)
  {
    
    $method = "GET";
    $host = "webservices.amazon.".$region; // must be in small case
    $uri = "/onca/xml";
    
    
    $params["Service"]          = "AWSECommerceService";
    $params["AWSAccessKeyId"]   = $public_key;
    $params["Timestamp"]        = gmdate("Y-m-d\TH:i:s\Z");
    $params["Version"]          = "2009-03-31";
    
    /* The params need to be sorted by the key, as Amazon does this at
      their end and then generates the hash of the same. If the params
      are not in order then the generated hash will be different thus
      failing the authetication process.
      */
    ksort($params);
    
    $canonicalized_query = array();
    
    foreach ($params as $param=>$value)
    {
      $param = str_replace("%7E", "~", rawurlencode($param));
      $value = str_replace("%7E", "~", rawurlencode($value));
      $canonicalized_query[] = $param."=".$value;
    }
    
    $canonicalized_query = implode("&", $canonicalized_query);
    
    $string_to_sign = $method."\n".$host."\n".$uri."\n".$canonicalized_query;
    
    /* calculate the signature using HMAC with SHA256 and base64-encoding.
      The 'hash_hmac' function is only available from PHP 5 >= 5.1.2.
      */
    $signature = base64_encode(hash_hmac("sha256", $string_to_sign, $private_key, True));
    
    /* encode the signature for the request */
    $signature = str_replace("%7E", "~", rawurlencode($signature));
    
    /* create request */
    $request = "http://".$host.$uri."?".$canonicalized_query."&Signature=".$signature;
    
    /* I prefer using CURL */
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL,$request);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    
    $xml_response = curl_exec($ch);
    
    /* If cURL doesn't work for you, then use the 'file_get_contents'
      function as given below.
      */
    //$xml_response = file_get_contents($request);
    
    if ($xml_response === False)
    {
      return False;
    }
    else
    {	
      /* Return the raw XML -- we will be sending it off to the JavaScript
        which will deal with parsing it.
        */
      return $xml_response;
    }
  }
  function __construct() {
    if (is_admin()) {
      add_action('wp_ajax_AWSXmlGet', array($this, 'AWSXmlGet') );
    }
  }
  function AWSXmlGet () {
    if (!current_user_can('manage_collection')) {
      wp_die( __('You do not have sufficient permissions to access this page.','weblibrarian') );
    }
    $params = $_REQUEST;
    unset($params['nocache']);
    unset($params['action']);
    //file_put_contents("php://stderr","*** AWSXmlGet.php: params = ".print_r($params,true)."\n");

    $region      = get_option('weblib_aws_regiondom');
    if ($region == 'jp') {
      $region = 'co.jp';
    } else if ($region == 'uk') {
      $region = 'co.uk';
    }
    $public_key  = get_option('weblib_aws_public_key');
    $private_key = get_option('weblib_aws_private_key');
    $params['AssociateTag'] = get_option('weblib_associate_tag');

    //file_put_contents("php://stderr","*** AWSXmlGet.php: region = $region, public_key = $public_key, private_key = $private_key \n");

    $xml_response = $this->aws_signed_request($region,$params,$public_key,$private_key);

    //file_put_contents("php://stderr","*** AWSXmlGet.php: xml_response = '$xml_response'\n");

    if ($xml_response) {
      /* http Headers */
      @header('Content-Type: text/xml');
      @header('Content-Length: '.strlen($xml_response));
      @header("Pragma: no-cache");
      @header("Expires: 0");
      @header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
      @header("Robots: none");
      echo "";	/* End of headers */
      echo $xml_response;
    } else {
      @header('Status: 500 Request Failed');
      @header('Content-Type: text/html');
      @header("Pragma: no-cache");
      @header("Expires: 0");
      @header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
      @header("Robots: none");
      echo "";	/* End of headers */
    ?><html><head><title>500 Request Failed</title></head><body>
      <h1>500 Request Failed</h1></body></html>
    <?php
    }
    wp_die();
  }
}


  
/* Create an instanance of the plugin */
global $weblibrarian;
$weblibrarian = new WebLibrarian();

