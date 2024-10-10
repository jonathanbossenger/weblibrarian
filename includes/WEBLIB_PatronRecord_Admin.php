<?php



/*************************** LOAD THE BASE CLASS *******************************
 *******************************************************************************
 * The WP_List_Table class isn't automatically available to plugins, so we need
 * to check if it's available and load it if necessary.
 */
if(!class_exists('WP_List_Table')){
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}


class WEBLIB_PatronRecord_Common extends WP_List_Table {

  function __construct() {
    parent::__construct(array(
                              'singular' => __('Item','weblibrarian'),
                              'plural' => __('Items','weblibrarian')
                              ) );

  }

  function check_permissions() {
    $patronid = get_user_meta(wp_get_current_user()->ID,'PatronID',true);
    if ($patronid == '' || !WEBLIB_Patron::ValidPatronID($patronid)) {
      wp_die ( __('You do not have a patron ID yet.','weblibrarian') );
    }
  }



  function get_column_info() {
    if ( isset( $this->_column_headers ) ) return $this->_column_headers;

    $columns = $this->get_columns( );
    $hidden = array();
    $sortable = $this->get_sortable_columns( );

    $this->_column_headers = array( $columns, $hidden, $sortable );

    return $this->_column_headers;
  }

  function get_sortable_columns() {
	return array('barcode' => array('barcode',false), 
		     'title' => array('title',false), 
		     'author' => array('author',false));
  }  

  function column_barcode ($item) {
    return $item;
  }

  function column_author ($item) {
    $theitem = new WEBLIB_ItemInCollection($item);
    return $theitem->author();
  }
  function column_type ($item) {
    $theitem = new WEBLIB_ItemInCollection($item);
    return $theitem->type();
  }

  function column_callnumber ($item) {
    $theitem = new WEBLIB_ItemInCollection($item);
    return $theitem->callnumber();
  }

  function column_default($item, $column_name) {
    return apply_filters( 'manage_items_custom_column','',$column_name,$item['patronid']);
  }

  function get_columns() {
    return array('cb' => '<input type="checkbox" />',
		 'barcode' => __('Barcode','weblibrarian'),
		 'title' => __('Title','weblibrarian'),
		 'author' => __('Author','weblibrarian'),
		 'type' => __('Type','weblibrarian'),
		 'callnumber' => __('Call Number','weblibrarian'),
		 'status' => __('Status','weblibrarian'));
  }
  function column_cb ($item) {
    return '<input type="checkbox" name="checked[]" value="'.$item.'" />';
  }

  function column_title ($item) {
    $theitem = new WEBLIB_ItemInCollection($item);
    return ($theitem->title());
  }
  function column_status ($item) {
    $outitem = WEBLIB_OutItem::OutItemByBarcode($item);
    $numberofholds = WEBLIB_HoldItem::HoldCountsOfBarcode($item);
    $status = '';
    if ($outitem != null) {
      $status = 'Due ';
      $duedate = $outitem->datedue();
      if (mysql2date('U',$duedate) < time()) {
        $status .= '<span id="due-date-'.$item.'" class="overdue" >'.strftime('%x',mysql2date('U',$duedate)).'</span>';
      } else {
	$status .= '<span id="due-date-'.$item.'" >'.strftime('%x',mysql2date('U',$duedate)).'</span>';
      }
      if ($outitem->patronid() == 
		get_user_meta(wp_get_current_user()->ID,'PatronID',true)) {
	$status .= '<br /><input class="button" type="button" value="'.__('Renew','weblibrarian').'" onClick="Renew('."'".$item."'".')" />';
      }
      unset($outitem);
    }
    $status .= '<br />';
    $status .= '<span id="hold-count-'.$item.'">';
    if ($numberofholds > 0) {
      $status .= $numberofholds.' Hold';
      if ($numberofholds > 1) $status .= 's';
    }
    $status .= '</span>';
    return $status;
  }

  function extra_tablenav ( $which ) {
    if ('top' != $which) return;

    ?><div id="ajax-message"></div><?php
  }
  private $sortfield = 'barcode';
  private $sortorder = 'ASC';
  function sort_items(&$items,$orderby,$order) {
    $this->sortfield = $orderby;
    $this->sortorder = $order;
    usort($items,array($this,'sort_cmp'));
  }
  function sort_cmp ($a, $b) {
    $aitem = new WEBLIB_ItemInCollection($a);
    $bitem = new WEBLIB_ItemInCollection($b);
    switch ($this->sortfield) {
      case 'barcode': $akey = $a; $bkey = $b; break;
      case 'title':   $akey = $aitem->title(); $bkey = $bitem->title(); break;
      case 'author':  $akey = $aitem->author(); $bkey = $bitem->author(); break;
    }
    unset($aitem); unset($bitem);
    if ($akey == $bkey) return 0;
    if ($akey > $bkey) {
      if ($this->sortorder == 'ASC') {
	return 1;
      } else {
	return -1;
      }
    } else {
      if ($this->sortorder == 'ASC') {
	return -1;
      } else {
	return 1;
      }
    }
  }
}

class WEBLIB_PatronHoldRecord_Admin extends WEBLIB_PatronRecord_Common {
  private $patronid;
  static $my_per_page = 'weblib_patron_helditems_per_page';

  function __construct() {
    global $weblib_contextual_help;

    $screen_id = add_submenu_page('users.php','Your Items on Hold','Holds',
				  'read','weblib-patron-holdlist',
				  array($this,'patron_holds'));
    $weblib_contextual_help->add_contextual_help($screen_id,'weblib-patron-holdlist');
    add_action("load-$screen_id", array($this,'add_per_page_option'));
    $this->patronid = get_user_meta(wp_get_current_user()->ID,'PatronID',true);

    parent::__construct();
  }
  function add_per_page_option() {
    $args['option'] = WEBLIB_PatronHoldRecord_Admin::$my_per_page;
    $args['label'] = __('Items','weblibrarian');
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


  function patron_holds() {
    $message = $this->prepare_items();
    ?><div class="wrap"><div id="icon-users" class="icon32"><br /></div>
      <h2>Your Items on Hold</h2><?php
      if ($message != '') {
	?><div id="message" class="update fade"><?php echo $message; ?></div><?php
      }
      ?><form method="get" action="<?php echo admin_url('admin.php'); ?>">
	<input type="hidden" name="page" value="weblib-patron-holdlist" />
	<?php $this->display(); ?></form></div><?php
  }

  function get_bulk_actions() {
    return array ('removehold' => __('Release Selected Holds','weblibrarian') );
  }
  function current_action() {
    if ( isset( $_REQUEST['action'] ) && -1 != sanitize_text_field($_REQUEST['action']) )
      return sanitize_text_field($_REQUEST['action']);

    if ( isset( $_REQUEST['action2'] ) && -1 != sanitize_text_field($_REQUEST['action2']) )
      return sanitize_text_field($_REQUEST['action2']);
  }

  function process_bulk_action() {
    $action = $this->current_action();
    switch ($action) {
      case 'removehold':
	if ( isset($_REQUEST['checked']) && !empty(sanitize_text_field($_REQUEST['checked']))) {
	  foreach ( sanitize_text_field($_REQUEST['checked']) as $theitem ) {
	    WEBLIB_HoldItem::DeleteHeldItemByBarcodeAndPatronId($theitem,
							$this->patronid);
	  }
        } else if ( isset($_REQUEST['barcode']) ) {
	  WEBLIB_HoldItem::DeleteHeldItemByBarcodeAndPatronId(
			sanitize_text_field($_REQUEST['barcode']),$this->patronid);
	}
	break;
    }
  }

  function prepare_items() {
    $message = '';
    $this->process_bulk_action();
    $orderby = isset( $_REQUEST['orderby'] ) ? sanitize_text_field($_REQUEST['orderby']) : 'barcode';
    if ( empty( $orderby ) ) $orderby = 'barcode';
    $order = isset( $_REQUEST['order'] ) ? sanitize_text_field($_REQUEST['order']) : 'ASC';
    if ( empty( $order ) ) $order = 'ASC';
    // Deal with columns
    $columns = $this->get_columns();    // All of our columns
    $hidden  = array();         // Hidden columns [none]
    $sortable = $this->get_sortable_columns(); // Sortable columns
    $this->_column_headers = array($columns,$hidden,$sortable); // Set up columns


    $per_page = $this->get_per_page();
    //file_put_contents("php://stderr","*** WEBLIB_PatronHoldRecord_Admin::prepare_items: per_page = '$per_page'\n");

    $helditems = WEBLIB_HoldItem::HeldItemsOfPatron($this->patronid);
    $all_items = array();
    foreach ($helditems as $transaction) {
      $outitem = new WEBLIB_HoldItem($transaction);
      $all_items[] = $outitem->barcode();
    }
    $this->sort_items($all_items,$orderby,$order);
    
    if ($all_items == null) $all_items = array();

    $total_items = count($all_items);
    $this->set_pagination_args( array (
	'total_items' => $total_items,
	'per_page'    => $per_page ));
    $total_pages = $this->get_pagination_arg( 'total_pages' );
    $pagenum = $this->get_pagenum();
    if ($pagenum < 1) {
      $pagenum = 1;
    } else if ($pagenum > $total_pages && $total_pages > 0) {
      $pagenum = $total_pages;
    }
    $start = ($pagenum-1)*$per_page;
    if ($total_items == 0) {
      $this->items = array();
    } else {
      $this->items = array_slice( $all_items,$start,$per_page );
    }
    return $message;
  }
}

class WEBLIB_PatronOutRecord_Admin extends WEBLIB_PatronRecord_Common {
  private $patronid;
  static $my_per_page = 'weblib_patron_outitems_per_page';

  function __construct() {
    global $weblib_contextual_help;

    $screen_id = add_submenu_page('users.php','My Checked out Items',
				  'Checkouts','read','weblib-patron-outlist',
				  array($this,'patron_outs'));
    $weblib_contextual_help->add_contextual_help($screen_id,'weblib-patron-outlist');
    add_action("load-$screen_id", array($this,'add_per_page_option'));
    $this->patronid = get_user_meta(wp_get_current_user()->ID,'PatronID',true);

    parent::__construct();
  }
  function add_per_page_option() {
    $args['option'] = WEBLIB_PatronOutRecord_Admin::$my_per_page;
    $args['label'] = __('Items','weblibrarian');
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

  function patron_outs() {
    $message = $this->prepare_items();
    ?><div class="wrap"><div id="icon-users" class="icon32"><br /></div>
      <h2><?php echo __('Your Checked out Items','weblibrarian'); ?></h2><?php
      if ($message != '') {
	?><div id="message" class="update fade"><?php echo $message; ?></div><?php
      }
      ?><form method="get" action="<?php echo admin_url('admin.php'); ?>">
        <input type="hidden" name="page" value="weblib-patron-outlist" />
	<?php $this->display(); ?></form></div><?php
  }

  function get_bulk_actions() {
    return array ('renew' => __('Renew Selected Items','weblibrarian') );
  }
  function current_action() {
    if ( isset( $_REQUEST['action'] ) && -1 != sanitize_text_field($_REQUEST['action']) )
      return sanitize_text_field($_REQUEST['action']);

    if ( isset( $_REQUEST['action2'] ) && -1 != sanitize_text_field($_REQUEST['action2']) )
      return sanitize_text_field($_REQUEST['action2']);
  }

  function process_bulk_action() {
    $message = '';
    $action = $this->current_action();
    if ( isset($_REQUEST['action']) && sanitize_text_field($_REQUEST['action']) != -1 ) {
      $theaction = sanitize_text_field($_REQUEST['action']);
    } else if ( isset($_REQUEST['action2']) && sanitize_text_field($_REQUEST['action2']) != -1 ) {
      $theaction = sanitize_text_field($_REQUEST['action2']);
    } else {
      $theaction = 'none';
    }
    switch ($action) {
      case 'renew':
	if ( isset($_REQUEST['barcode']) ) {
	  $m = WEBLIB_OutItem::RenewByBarcodeAndPatronID(
				sanitize_text_field($_REQUEST['barcode']),$this->patronid);
	  if (preg_match('/ Renewed\.$/',$m)) {
	    $message .= '<p>'.$m.'</p>';
	  } else {
	    $message .= '<p><span id="error">'.$m.'</span></p>';
	  }
	} else {
	  foreach ( sanitize_text_field($_REQUEST['checked']) as $barcode ) {
	    $m = WEBLIB_OutItem::RenewByBarcodeAndPatronID(
				$barcode,$this->patronid);
	    if (preg_match('/ Renewed\.$/',$m)) {
	      $message .= '<p>'.$m.'</p>';
	    } else {
	      $message .= '<p><span id="error">'.$m.'</span></p>';
	    }
	  }
	}
	break;
    }
    return $message;
  }

  function prepare_items() {
    $message = '';
    $message = $this->process_bulk_action();
    $orderby = isset( $_REQUEST['orderby'] ) ? sanitize_text_field($_REQUEST['orderby']) : 'barcode';
    if ( empty( $orderby ) ) $orderby = 'barcode';
    $order = isset( $_REQUEST['order'] ) ? sanitize_text_field($_REQUEST['order']) : 'ASC';
    if ( empty( $order ) ) $order = 'ASC';

    $screen = get_current_screen();
    $option = str_replace( '-', '_', $screen->id . '_per_page' );
    $per_page = $this->get_items_per_page($option);

    $outitems = WEBLIB_OutItem::OutItemsOfPatron($this->patronid);
    $all_items = array();
    foreach ($outitems as $transaction) {
      $outitem = new WEBLIB_OutItem($transaction);
      $all_items[] = $outitem->barcode();
    }
    $this->sort_items($all_items,$orderby,$order);
    
    if ($all_items == null) $all_items = array();

    $total_items = count($all_items);
    $this->set_pagination_args( array (
	'total_items' => $total_items,
	'per_page'    => $per_page ));
    $total_pages = $this->get_pagination_arg( 'total_pages' );
    $pagenum = $this->get_pagenum();
    if ($pagenum < 1) {
      $pagenum = 1;
    } else if ($pagenum > $total_pages && $total_pages > 0) {
      $pagenum = $total_pages;
    }
    $start = ($pagenum-1)*$per_page;
    if ($total_items == 0) {
      $this->items = array();
    } else {
      $this->items = array_slice( $all_items,$start,$per_page );
    }
    return $message;
  }
}



