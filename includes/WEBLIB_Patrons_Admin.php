<?php



/*************************** LOAD THE BASE CLASS *******************************
 *******************************************************************************
 * The WP_List_Table class isn't automatically available to plugins, so we need
 * to check if it's available and load it if necessary.
 */
if(!class_exists('WP_List_Table')){
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}




class WEBLIB_Patrons_Admin extends WP_List_Table {

  var $viewmode = 'add';
  var $viewid   = 0;
  var $viewitem;

  static $my_per_page = 'weblib_patrons_per_page';

  function __construct() {
     global $weblib_contextual_help;

     $screen_id =  add_menu_page(__('Library Patrons','weblibrarian'),
			__('Patrons','weblibrarian'),'manage_patrons',
			'weblib-list-patrons',array($this,'list_patrons'),
			WEBLIB_IMAGEURL.'/Patron_Menu.png');
     $weblib_contextual_help->add_contextual_help($screen_id,'weblib-list-patrons');

     add_action("load-$screen_id", array($this,'add_per_page_option'));

     $screen_id =  add_submenu_page('weblib-list-patrons',__('Add Library Patron','weblibrarian'),
			__('Add New','weblibrarian'),'manage_patrons',
			'weblib-add-patron',array($this,'add_patron'));
     $weblib_contextual_help->add_contextual_help($screen_id,'weblib-add-patron');
     $screen_id =  add_submenu_page('weblib-list-patrons',__('Add Bulk Library Patrons','weblibrarian'),
				    __('Add New Bulk','weblibrarian'),'manage_patrons',
				    'weblib-add-patron-bulk',
				    array($this,'add_patron_bulk'));
     $weblib_contextual_help->add_contextual_help($screen_id,'weblib-add-patron-bulk');

     parent::__construct(array(
		'singular' => __('Patron','weblibrarian'),
		'plural' =>  __('Patrons','weblibrarian')
     ) );
  }

  function add_per_page_option() {
    $args['option'] = WEBLIB_Patrons_Admin::$my_per_page;
    $args['label'] = __('Patrons','weblibrarian');
    $args['default'] = 20;
    add_screen_option('per_page', $args);
  }

  function get_per_page() {
    $user = get_current_user_id();
    $screen = get_current_screen();
    $option = $screen->get_option('per_page','option');
    $v = get_user_meta($user, $option, true);
    if (empty($v)  || $v < 1) {
      $v = $screen->get_option('per_page','default');
    }
    return $v;
  }

  /* Default column (nothing really here, since every displayed column gets 
   * its own function).
   */
  function column_default($item, $column_name) {
    return apply_filters( 'manage_items_custom_column','',
			  $column_name,$item['patronid']);
  }

  function column_cb ($item) {
    return '<input type="checkbox" name="checked[]" value="'.$item['patronid'].'" />';
  }
  function column_patronid ($item) {
    return $item['patronid'];
  }
  function column_patronname ($item) {
    // Build row actions
    $actions = array(
	'edit' => '<a href="'.add_query_arg(array('page' => 'weblib-add-patron',
						  'mode' => 'edit',
						  'patronid' => $item['patronid']),
					    admin_url('admin.php')).'">'.
			__('Edit','weblibrarian')."</a>",
	'view' => '<a href="'.add_query_arg(array('page' => 'weblib-add-patron',
						  'mode' => 'view',
						  'patronid' => $item['patronid']),
					    admin_url('admin.php')).'">'.
			__('View','weblibrarian')."</a>",
	'delete' => '<a href="'.add_query_arg(array('page' => $_REQUEST['page'],
						    'action' => 'delete',
						    'patronid' => $item['patronid']),
					    admin_url('admin.php')).'">'.
			__('Delete','weblibrarian')."</a>"
        );
    return $item['name'].$this->row_actions($actions);
  }
  function column_telephone ($item) {
    return WEBLIB_Patrons_Admin::addtelephonedashes(WEBLIB_Patron::TelephoneFromId($item['patronid']));
  }
  function column_username ($item) {
    $userid = WEBLIB_Patron::UserIDFromPatronID($item['patronid']);
    if ($userid == -1) {
      return '';
    } else {
      return get_userdata($userid)->user_login;
    }
  }

  function get_columns() {
	return array('cb' => '<input type="checkbox" />',
		     'patronid' => __('Patron Id','weblibrarian'),
		     'patronname' => __('Patron Name','weblibrarian'),
		     'telephone' => __('Telephone Number','weblibrarian'),
		     'username' => __('Username','weblibrarian'));
  }

  function get_sortable_columns() {
 	return array();
  }

  function get_bulk_actions() {
    return array ('delete' => __('Delete','weblibrarian') );
  }

  function current_action() {
    if ( isset( $_REQUEST['action'] ) && -1 != $_REQUEST['action'] )
      return $_REQUEST['action'];

    if ( isset( $_REQUEST['action2'] ) && -1 != $_REQUEST['action2'] )
      return $_REQUEST['action2'];
  }

  function process_bulk_action() {
    $action = $this->current_action();
    switch ($action) {
      case 'delete':
	if ( isset($_REQUEST['checked']) && !empty($_REQUEST['checked'])) {
	  foreach ( $_REQUEST['checked'] as $theitem ) {
	    WEBLIB_Patron::DeletePatronByID($theitem);
	  }
        } else if ( isset($_REQUEST['patronid']) ) {
	  WEBLIB_Patron::DeletePatronByID($_REQUEST['patronid']);
	}
	break;
    }
  }
  function check_permissions() {
    if (!current_user_can('manage_patrons')) {
      wp_die( __('You do not have sufficient permissions to access this page.','weblibrarian') );
    }
  }

  function get_column_info() {
    if ( isset($this->_column_headers) ) {return $this->_column_headers;}
    $this->_column_headers =
	array( $this->get_columns(),
	       array(), 
	       $this->get_sortable_columns() );
    return $this->_column_headers;
  }

  function prepare_items() {
    $this->check_permissions();
    $message = '';
    // Deal with columns
    $columns = $this->get_columns();    // All of our columns
    $hidden  = array();         // Hidden columns [none]
    $sortable = $this->get_sortable_columns(); // Sortable columns
    $this->_column_headers = array($columns,$hidden,$sortable); // Set up columns

    $this->process_bulk_action();
    $search = isset( $_REQUEST['s'] ) ? $_REQUEST['s'] : '';

    $per_page = $this->get_per_page();

    if ($search == '') {
      $all_items = WEBLIB_Patron::AllPatrons();
    } else {
      $all_items = WEBLIB_Patron::FindPatronByName($search . '%');
    }
    $current_page = $this->get_pagenum();
    $total_items = count($all_items);
    $data = array_slice($all_items,(($current_page-1)*$per_page),$per_page);
    $this->items = $data;
    $this->set_pagination_args( array (
                'total_items' => $total_items,
                'per_page'    => $per_page,
                'total_pages' => ceil($total_items/$per_page) ));
    return $message;
  }
  
  function list_patrons() {
    $message = $this->prepare_items();
    ?><div class="wrap"><div id="icon-patrons" class="icon32"><br /></div>
	<h2><?php _e('Library Patrons','weblibrarian'); ?> <a href="<?php
	   echo add_query_arg( array('page' => 'weblib-add-patron',
				     'mode' => 'add',
				     'patronid' => false),
				admin_url('admin.php'));
	?>" class="button add-new-h2"><?php _e('Add New','weblibrarian');
	?></a> <a href="<?php
	   echo add_query_arg( array('page' => 'weblib-add-patron-bulk' ),
				admin_url('admin.php'));
	?>" class="button add-new-h2"><?php _e('Add New Bulk','weblibrarian');
	?></a> <a href="<?php
          echo add_query_arg( array('dataselection' => 'patrons',
                                    'action' => 'ExportLibraryData'),
                              admin_url('admin-post.php'));
	?>" class="button add-new-h2"><?php _e('Export as CSV','weblibrarian');
	?></a></h2><?php
	if ($message != '') {
	  ?><div id="message" class="update fade"><?php echo $message; ?></div><?php
	}
	?><form method="get" action="<?php echo admin_url('admin.php'); ?>">
	<input type="hidden" name="page" value="weblib-list-patrons" />
	<?php $this->search_box(__( 'Search Patrons','weblibrarian' ), 'user' ); ?>
	<?php $this->display(); ?></form></div><?php
  }

  function add_patron() {
    $message = $this->prepare_one_item();
    ?><div class="wrap"><div id="<?php echo $this->add_item_icon(); ?>" class="icon32"><br />
    </div><h2><?php echo $this->add_item_h2(); ?></h2>
    <?php if ($message != '') {
	?><div id="message" class="update fade"><?php echo $message; ?></div><?php
	} ?>
    <form action="<?php echo admin_url('admin.php'); ?>" method="get">
	<input type="hidden" name="page" value="weblib-add-patron" />
    <?php $this->display_one_item_form(
		add_query_arg(array('page' => 'weblib-list-patrons', 
				    'mode' => false, 
				    'patronid' => false))); 
	?></form></div><?php
	
  }

  function get_items_per_page ($option, $default = 20) {
    if ( isset($_REQUEST['screen-options-apply']) &&
	 $_REQUEST['wp_screen_options']['option'] == $option ) {
      $per_page = (int) $_REQUEST['wp_screen_options']['value'];
    } else {
      $per_page = $default;
    }
    return (int) apply_filters( $option, $per_page );
  }




  /* Add/View/Edit page */
  function prepare_one_item($args = array()) {
    //file_put_contents("php://stderr","*** WEBLIB_Patrons_Admin::prepare_one_item(".print_r($args,true).")\n");
    extract (wp_parse_args($args, 
			array('mode' => 'add', 'id' => 0, 'self' => false)));
    //file_put_contents("php://stderr","*** WEBLIB_Patrons_Admin::prepare_one_item:: self = $self\n");
    //file_put_contents("php://stderr","*** WEBLIB_Patrons_Admin::prepare_one_item:: mode = $mode\n");
    //file_put_contents("php://stderr","*** WEBLIB_Patrons_Admin::prepare_one_item:: id = $id\n");
    //file_put_contents("php://stderr","*** WEBLIB_Patrons_Admin::prepare_one_item:: _REQUEST = ".print_r($_REQUEST,true)."\n");
    if ($self) {
      $error = '';
      $patron = WEBLIB_Patron::PatronFromCurrentUser($error);
      if ($patron == null) {
	wp_die( $error );
      }
      if ($mode != 'edit' && $id != $patron->ID() ) {
	wp_die( __('You do not have sufficient permissions to access this page.','weblibrarian') );
      }
    } else {
      $this->check_permissions();
    }
      
    $message = '';
    if ( isset($_REQUEST['addpatron']) ) {
      $message = $this->checkiteminform(0);
      $item    = $this->getitemfromform(0);
      $this->viewmode = 'add';
      $this->viewid   = 0;
      $this->viewitem = $item;
      if ($message == '') {
	$pid = isset($_REQUEST['patronid']) ? $_REQUEST['patronid'] : 0;
	$newid = $item->store($pid);
        if ($newid < 0) {
          $message = '<span id="error">'.__('Ill formed item').'</span>';
        } else if ($newid == 0) {
          $message = '<span id="error">'.__('Database error!').'</span>';
        } else {
          $message = '<p>'.sprintf(__('%s, %s %s inserted with Patron Id %d.','weblibrarian'), 
                                   $item->lastname(),$item->firstname(),
                                   $item->extraname(),$newid).'</p>';
          $this->viewmode = 'edit';
          $this->viewid   = $newid;
          $this->viewitem = $item;
        }
      }
    } else if ( isset($_REQUEST['updatepatron']) && 
		isset($_REQUEST['patronid']) ) {
      $message = $this->checkiteminform($_REQUEST['patronid']);
      $item    = $this->getitemfromform($_REQUEST['patronid']);
      if ($message == '') {
	$item->store();
	$message = '<p>'.sprintf(__('%s, %s %s updated.','weblibrarian'),
				$item->lastname(),$item->firstname(),
				$item->extraname()).'</p>';
      }
      $this->viewmode = 'edit';
      $this->viewid   = $item->ID();
      $this->viewitem = $item;
    } else {
      $this->viewmode = isset($_REQUEST['mode']) ? $_REQUEST['mode'] : $mode;
      $this->viewid   = isset($_REQUEST['patronid']) ? $_REQUEST['patronid'] : $id;
      switch ($this->viewmode) {
	case 'edit':
	case 'view':
	  if ($this->viewid == 0) {$this->viewmode = 'add';}
	  break;
	case 'add':
	  $this->viewid   = 0;
	  break;
	default:
	  $this->viewmode = 'add';
	  $this->viewid   = 0;
	  break;
      }
      $this->viewitem = new WEBLIB_Patron($this->viewid);
    }
    return $message;
  }
  function checkiteminform($id) {
    $result = '';
    $newtelephone = WEBLIB_Patrons_Admin::striptelephonedashes($_REQUEST['telephone']);
    if (!preg_match('/^\d+$/',$newtelephone) ) {
      $result .= '<br /><span id="error">'.__('Telephone invalid','weblibrarian') . '</span>';
    }
    $newlastname = $_REQUEST['lastname'];
    if ($newlastname == '') {
      $result .= '<br /><span id="error">'.__('Last name is invalid','weblibrarian') . '</span>';
    }
    $newfirstname = $_REQUEST['firstname'];
    if ($newfirstname == '') {
      $result .= '<br /><span id="error">'.__('First name is invalid','weblibrarian') . '</span>';
    }
    $newaddress1 = $_REQUEST['address1'];
    if ($newaddress1 == '') {
      $result .= '<br /><span id="error">'.__('Address 1 is invalid','weblibrarian') . '</span>';
    }
    $newcity = $_REQUEST['city'];
    if ($newcity == '') {
      $result .= '<br /><span id="error">'.__('City is invalid','weblibrarian') . '</span>';
    }
    //$newstate = $_REQUEST['state'];
    //if ($newstate == '' || strlen($newstate) != 2) {
    //  $result .= '<br /><span id="error">'.__('State is invalid','weblibrarian') . '</span>';
    //}
    //$newzip = $_REQUEST['zip'];
    //if (!($newzip != '' && (strlen($newzip) == 5 || strlen($newzip) == 10) &&
    //    preg_match('/\d+(-\d+)?/',$newzip) )) {
    //  $result .= '<br /><span id="error">'.__('Zip is invalid','weblibrarian') . '</span>';
    //}
    $newoutstandingfines = $_REQUEST['outstandingfines'];
    if (!is_numeric($newoutstandingfines)) {
      $result .= '<br /><span id="error">'.__('Outstanding fines invalid','weblibrarian') . '</span>';
    }
    $newexpiration = $_REQUEST['expiration'];
    WEBLIB_Patrons_Admin::ValidHumanDate($newexpiration,$theexpiration,__('Expiration','weblibrarian'),$result);
    return $result;
  }
  function getitemfromform($id) {
    $patron = new WEBLIB_Patron($id);
    $patron->set_telephone(WEBLIB_Patrons_Admin::striptelephonedashes($_REQUEST['telephone']));
    $patron->set_lastname($_REQUEST['lastname']);
    $patron->set_firstname($_REQUEST['firstname']);
    $patron->set_extraname($_REQUEST['extraname']);
    $patron->set_address1($_REQUEST['address1']);
    $patron->set_address2($_REQUEST['address2']);
    $patron->set_city($_REQUEST['city']);
    $patron->set_state($_REQUEST['state']);
    $patron->set_zip($_REQUEST['zip']);
    $patron->set_outstandingfines($_REQUEST['outstandingfines']);
    $dummy = '';
    WEBLIB_Patrons_Admin::ValidHumanDate($_REQUEST['expiration'],$theexpiration,__('Expiration','weblibrarian'),$dummy);
    $patron->set_expiration($theexpiration);
    return $patron;
  }
  function add_item_icon() {
    switch ($this->viewmode) {
      case 'edit': return 'icon-patron-edit';
      case 'view': return 'icon-patron-view';
      default:
      case 'add': return 'icon-patron-add';
    }
  }
  function add_item_h2() {
    switch ($this->viewmode) {
      case 'edit': return __('Edit Patron Info','weblibrarian');
      case 'view': return __('View Patron Info','weblibrarian');
      default:
      case 'add': return __('Add new Patron','weblibrarian');
    }
  }

  static function ValidHumanDate($datestring,&$mysqldate,$label,&$error) {
    $Months = array(__('jan','weblibrarian') => 1, 
                    __('feb','weblibrarian') => 2, 
                    __('mar','weblibrarian') => 3, 
                    __('apr','weblibrarian') => 4,
                    __('may','weblibrarian') => 5, 
                    __('jun','weblibrarian') => 6, 
                    __('jul','weblibrarian') => 7, 
                    __('aug','weblibrarian') => 8,
                    __('sep','weblibrarian') => 9, 
                    __('oct','weblibrarian') => 10, 
                    __('nov','weblibrarian') => 11, 
                    __('dec','weblibrarian') => 12);
    if (function_exists('nl_langinfo')) {
      $dfmt = nl_langinfo(D_FMT);
    } else {
      $dfmt = '%m/%d/%y';//All MS-Windows servers are presumed to be in North America
    }
    if ($dfmt == '%m/%d/%y') {
      $invdate = __('Invalid %s date (%s). Should be mm/yyyy or mm/dd/yyyy.','weblibrarian');
    } else {
      $invdate = __('Invalid %s date (%s). Should be mm/yyyy or dd/mm/yyyy.','weblibrarian');
    }
    $datearry=explode("/",$datestring); // splitting the array
    if (count($datearry) == 2) {/* only month and year given (presumed) */
      $month = $datearry[0];
      $year  = $datearry[1];
      $date  = 1; /* assume first of the month */
    } elseif (count($datearry) == 3) {
      if ($dfmt == '%m/%d/%y') {
        $month = $datearry[0];
        $date  = $datearry[1];
        $year  = $datearry[2];
      } else {
        $date  = $datearry[0];
        $month = $datearry[1];
        $year  = $datearry[2];
      }
    } else {
      $error .= '<br /><span id="error">';
      $error .= sprintf($invdate,$label,$datestring);
      $error .= '</span>';
      return false;
    }
    if (!is_int($month)) {
      $lowmonth = strtolower($month);
      if (strlen($lowmonth) > 3) {$lowmonth = substr($lowmonth,0,3);}
      if (isset($Months[$lowmonth])) {
	$month = $Months[$lowmonth];
      } else {
	$error .= '<br /><span id="error">';
        $mlist = '';
        $comma='';
	foreach ($Months as $k => $dummy) {
	  $mlist .= $comma.$k;
	  $comma = ', ';
	}
        $error .= sprintf(__('Invalid %s date (%s): illegal month (%s). Should be one of %s.','weblibrarian'),$label,$datestring,$month,$mlist);
	$error .= '</span>';
	return false;
      }
    }
    if (!checkdate($month,$date,$year)) {
      $error .= '<br /><span id="error">';
      $error .= sprintf(__('Invalid %s date (%s). Out of range.','weblibrarian'),$label,$datestring);
      $error .= '</span>';
      return false;
    }
    $mysqldate = sprintf("%04d-%02d-%02d",$year,$month,$date);
    return true;
  }
  static function striptelephonedashes($telephone) {
    $telephone = preg_replace('/\((\d+)\)[[:space:]]*/','$1-',$telephone);
    return preg_replace('/(\d+)-(\d+)-(\d+)/',
		      '$1$2$3',$telephone);
  }
  static function addtelephonedashes($telephone) {
     return preg_replace('/(\d\d\d)(\d\d\d)(\d\d\d\d)/',
	'$1-$2-$3',$telephone);
  }

  function display_one_item_form($returnURL) {
    if ( isset($_REQUEST['paged']) ) {
      $paged = sanitize_text_field($_REQUEST['paged']);
      ?><input type="hidden" name="paged" value="<?php echo $paged; ?>" /><?php
    }
    if ( isset($_REQUEST['screen-options-apply']) ) {
      $screenopts = sanitize_text_field($_REQUEST['screen-options-apply']);
      ?><input type="hidden" name="screen-options-apply" value="<?php echo $screenopts; ?>" /><?php
    }
    if ( isset($_REQUEST['wp_screen_options']['option']) ) {
      $wpscreenoptsopt = sanitize_text_field($_REQUEST['wp_screen_options']['option']);
      ?><input type="hidden" name="wp_screen_options[option]" value="<?php echo $wpscreenoptsopt; ?>" /><?php
    }
    if ( isset($_REQUEST['wp_screen_options']['value']) ) {
      $wpscreenoptsval = sanitize_text_field($_REQUEST['wp_screen_options']['value']);
      ?><input type="hidden" name="wp_screen_options[value]" value="<?php echo $wpscreenoptsval; ?>" /><?php
    }
    if ($this->viewmode == 'view') {
      $ro = ' readonly="readonly"';
      $ro_admin = $ro;
    } else {
      $ro = '';
      if (!current_user_can('manage_patrons')) {
	$ro_admin = ' readonly="readonly"';
      }
    }
    ?><table class="form-table">
      <tr valign="top">
	<th scope="row"><label for="patronid" style="width:20%;"><?php _e('Patron ID:','weblibrarian'); ?></label></th>
	<td><input id="patronid" 
		   name="patronid" 
		   style="width:75%;"
		   value="<?php echo $this->viewid; ?>"<?php
	if ($this->viewmode != 'add') {
	  echo ' readonly="readonly"';
	} ?> /></td></tr>
      <tr valign="top">
	<th scope="row"><label for="telephone" style="width:20%;"><?php _e('Telephone:','weblibrarian'); ?></label></th>
	<td><input id="telephone"
		   name="telephone"
		   style="width:75%;"
		   maxlength="20"
		   value="<?php echo WEBLIB_Patrons_Admin::addtelephonedashes($this->viewitem->telephone()); 
		?>"<?php echo $ro; ?> /></td></tr>
      <tr valign="top">
	<th scope="row"><label for="lastname" style="width:20%;"><?php _e('Last Name:','weblibrarian'); ?></label></th>
	<td><input id="lastname"
		   name="lastname"
		   style="width:75%;"
		   maxlength="32"
		   value="<?php echo stripslashes($this->viewitem->lastname()); ?>"<?php echo $ro; ?> /></td></tr>
      <tr valign="top">
	<th scope="row"><label for="firstname" style="width:20%;"><?php _e('First Name:','weblibrarian'); ?></label></th>
	<td><input id="firstname"
		   name="firstname"
		   style="width:75%;"
		   maxlength="32"
		   value="<?php echo stripslashes($this->viewitem->firstname()); ?>"<?php echo $ro; ?> /></td></tr>
      <tr valign="top">
	<th scope="row"><label for="extraname" style="width:20%;"><?php _e('Extra Name:','weblibrarian'); ?></label></th>
	<td><input id="extraname"
		   name="extraname"
		   style="width:75%;"
		   maxlength="32"
		   value="<?php echo stripslashes($this->viewitem->extraname()); ?>"<?php echo $ro; ?> /></td></tr>
      <tr valign="top">
	<th scope="row"><label for="address1" style="width:20%;"><?php _e('Address 1:','weblibrarian'); ?></label></th>
	<td><input id="address1"
		   name="address1"
		   style="width:75%;"
		   maxlength="32"
		   value="<?php echo stripslashes($this->viewitem->address1()); ?>"<?php echo $ro; ?> /></td></tr>
      <tr valign="top">
	<th scope="row"><label for="address2" style="width:20%;"><?php _e('Address 2:','weblibrarian'); ?></label></th>
	<td><input id="address2"
		   name="address2"
		   style="width:75%;"
		   maxlength="32"
		   value="<?php echo stripslashes($this->viewitem->address2()); ?>"<?php echo $ro; ?> /></td></tr>
      <tr valign="top">
	<th scope="row"><label for="city" style="width:20%;"><?php _e('City:','weblibrarian'); ?></label></th>
	<td><input id="city"
		   name="city"
		   style="width:75%;"
		   maxlength="32"
		   value="<?php echo stripslashes($this->viewitem->city()); ?>"<?php echo $ro; ?> /></td></tr>
      <tr valign="top">
	<th scope="row"><label for="state" style="width:20%;"><?php _e('State:','weblibrarian'); ?></label></th>
	<td><input id="state"
		   name="state"
		   style="width:75%;"
		   maxlength="16"
		   value="<?php echo stripslashes($this->viewitem->state()); ?>"<?php echo $ro; ?> /></td></tr>
      <tr valign="top">
	<th scope="row"><label for="zip" style="width:20%;"><?php _e('Zip:','weblibrarian'); ?></label></th>
	<td><input id="zip"
		   name="zip"
		   style="width:75%;"
		   maxlength="10"
		   value="<?php echo $this->viewitem->zip(); ?>"<?php echo $ro; ?> /></td></tr>
      <tr valign="top">
	<th scope="row"><label for="outstandingfines" style="width:20%;"><?php _e('Outstanding Fines: $','weblibrarian'); ?></label></th>
	<td><input id="outstandingfines"
		   name="outstandingfines"
		   style="width:75%;"
		   value="<?php echo $this->viewitem->outstandingfines(); ?>"<?php echo $ro_admin; ?> /></td></tr>
      <tr valign="top">
	<th scope="row"><label for="expiration" style="width:20%;"><?php _e('Expires on:','weblibrarian'); ?></label></th>
	<td><input id="expiration"
		   name="expiration"
		   style="width:75%;"
		   value="<?php echo $this->viewitem->expiration(); ?>"<?php echo $ro_admin; ?> /></td></tr>
      </table>
      <p>
	<?php switch($this->viewmode) {
		case 'add':
		  ?><input type="submit" name="addpatron" class="button-primary" value="<?php  _e('Add Patron','weblibrarian'); ?>" /><?php
		  break;
		case 'edit':
		  ?><input type="submit" name="updatepatron" class="button-primary" value="<?php  _e('Update Patron','weblibrarian'); ?>" /><?php
		  break;
	      }
	      if ($returnURL != '') {
		?><a href="<?php echo $returnURL; ?>" class="button-primary"><?php _e('Return','weblibrarian'); ?></a><?php
	      } ?>
      </p><?php
  }
  function add_patron_bulk() {
    $message = $this->process_bulk_upload();
    ?><div class="wrap"><div id="icon-patron-add-bulk" class="icon32"><br />
      </div><h2><?php _e('Add Library Patrons in bulk','weblibrarian'); ?></h2>
      <?php if ($message != '') {
	?><div id="message" class="update fade"><?php echo $message; ?></div><?php
        } ?>
      <form method="post" action=""  
	    enctype="multipart/form-data" >
      <input type="hidden" name="page" value="weblib-add-patron-bulk" />
      <?php $this->display_bulk_upload_form(
			add_query_arg(
				array('page' => 'weblib-list-patrons'))); 
	?></form></div><?php
  }
  function process_bulk_upload() {
    $this->check_permissions();
    //file_put_contents("php://stderr","*** WEBLIB_Patrons_Admin::process_bulk_upload: _REQUEST is ".print_r($_REQUEST,true)."\n");
    if (!isset($_REQUEST['doupload']) ) return '';
    $filename = $_FILES['file_name']['tmp_name'];
    $use_csv_headers = $_REQUEST['use_csv_header'];
    $field_sep = stripslashes($_REQUEST['field_sep']);
    $enclose_char = stripslashes($_REQUEST['enclose_char']);
    /*$escape_char = stripslashes($_REQUEST['escape_char']);*/
    $result = WEBLIB_Patron::upload_csv($filename,$use_csv_headers,$field_sep,
				$enclose_char/*,$escape_char*/);
    return $result;
  }
  function display_bulk_upload_form($returnURL) {
    if ( isset($_REQUEST['paged']) ) {
      $paged = sanitize_text_field($_REQUEST['paged']);
      ?><input type="hidden" name="paged" value="<?php echo $paged; ?>" /><?php
    }
    if ( isset($_REQUEST['screen-options-apply']) ) {
      $screenopts = sanitize_text_field($_REQUEST['screen-options-apply']);
      ?><input type="hidden" name="screen-options-apply" value="<?php echo $screenopts; ?>" /><?php
    }
    if ( isset($_REQUEST['wp_screen_options']['option']) ) {
      $wpscreenoptsopt = sanitize_text_field($_REQUEST['wp_screen_options']['option']);
      ?><input type="hidden" name="wp_screen_options[option]" value="<?php echo $wpscreenoptsopt; ?>" /><?php
    }
    if ( isset($_REQUEST['wp_screen_options']['value']) ) {
      $wpscreenoptsval = sanitize_text_field($_REQUEST['wp_screen_options']['value']);
      ?><input type="hidden" name="wp_screen_options[value]" value="<?php echo $wpscreenoptsval; ?>" /><?php
    }
  ?><p><label for="file_name"><?php _e('CSV File:','weblibrarian'); ?></label>
	 <input type="file" id="file_name" name="file_name" 
		value="<?php echo sanitize_file_name($_REQUEST['file_name']); ?>" /></p>
      <p><label for="use_csv_header"><?php _e('Use CSV Header?','weblibrarian'); ?></label>
	 <input type="checkbox" name="use_csv_header" id="use_csv_header" 
		value="1" <?php 
		if ($_REQUEST['use_csv_header']) echo ' checked="checked"'; ?> /></p>
      <p><label for="field_sep"><?php _e('Field Separater Character:','weblibrarian'); ?></label>
	 <select id="field_sep" name="field_sep">
	 <option value="," <?php if (!isset($_REQUEST['field_sep']) ||
				     $_REQUEST['field_sep'] == ',') {
				   echo 'selected="selected"'; 
				 } ?>>,</option>
	 <option value="<?php echo "\t"; ?>" <?php 
		if (isset($_REQUEST['field_sep']) && 
		    $_REQUEST['field_sep'] == "\t") {
		  echo 'selected="selected"'; 
		} ?>><?php _e('TAB','weblibrarian'); ?></option>
	 </select></p>
      <p><label for="enclose_char"><?php _e('Enclosure Character:','weblibrarian'); ?></label>
	 <select id="enclose_char" name="enclose_char">
	 <option value='<?php echo '"'; ?>' <?php
		if (!isset($_REQUEST['enclose_char']) ||
		    $_REQUEST['enclose_char'] == '"') {
		  echo 'selected="selected"'; 
		} ?>>&quot;</option>
	 <option value="'" <?php
		if (isset($_REQUEST['enclose_char']) &&
		    $_REQUEST['enclose_char'] == "'") {
		  echo 'selected="selected"';
		} ?>>'</option>
	 </select></p>
      <?php /*
      <p><label for="escape_char"><?php _e('Escape Character:','weblibrarian'); ?></label>
	 <input type="text" id="escape_char" name="escape_char"
		maxlength="1" size="1" value="<?php 
		if (isset($_REQUEST['escape_char'])) {
		  echo $_REQUEST['escape_char'];
		} else {
		  echo "\\";
		} ?>" /></p> */ ?>
      <p><input class="button-primary" type="submit" name="doupload" value="<?php _e('Upload File','weblibrarian'); ?>" />
	 <a href="<?php echo $returnURL; ?>" class="button-primary"><?php _e('Return','weblibrarian'); ?></a></p><?php
  }

}



