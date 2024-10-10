<?php



/*************************** LOAD THE BASE CLASS *******************************
 *******************************************************************************
 * The WP_List_Table class isn't automatically available to plugins, so we need
 * to check if it's available and load it if necessary.
 */
if(!class_exists('WP_List_Table')){
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

//    $this->set_row_actions(array(
//	__('Edit','weblibrarian') => add_query_arg(
//			array('page' => 'weblib-add-item-type', 
//				'mode' => 'edit'),
//			admin_url('admin.php'))));



class WEBLIB_Types_Database_Admin extends WP_List_Table {
  var $viewmode = 'add';
  var $viewtypename = '';
  var $viewloanperiod = 14;

  static $my_per_page = 'weblib_types_per_page';

  function __construct() {
    global $weblib_contextual_help;

    $screen_id =  add_menu_page(__('Circulation Types Database','weblibrarian'), __('Circulation Types','weblibrarian'),
				'manage_collection', 
				'weblib-item-types-database',
				array($this,'item_types_database'),
			WEBLIB_IMAGEURL.'/CircType_Menu.png');
    $weblib_contextual_help->add_contextual_help($screen_id,'weblib-item-types-database');
    add_action("load-$screen_id", array($this,'add_per_page_option'));
    $screen_id =  add_submenu_page('weblib-item-types-database', 
				   __('Add New Item Type','weblibrarian'), __('Add New','weblibrarian'),
				   'manage_collection', 
				   'weblib-add-item-type',
				   array($this,'add_item_type'));
    $weblib_contextual_help->add_contextual_help($screen_id,'weblib-add-item-type');

    parent::__construct(array(
		'singular' => __('Type','weblibrarian'),
		'plural' => __('Types','weblibrarian')
	));
  }
  function add_per_page_option() {
    $args['option'] = WEBLIB_Types_Database_Admin::$my_per_page;
    $args['label'] = __('Types','weblibrarian');
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

  function column_default($item, $column_name) {
    return apply_filters( 'manage_items_custom_column','',$column_name,$item['patronid']);
  }

  function column_type($item) {
    // Build row actions
    $actions = array(   
	'edit' => '<a href="'.add_query_arg(array('page' => 'weblib-add-item-type',
						  'mode' => 'edit',
						  'typename' => urlencode($item)),
					    admin_url('admin.php')).'">'.
		__('Edit','weblibrarian')."</a>"
	);
    return $item.$this->row_actions($actions);
  }
  function column_loanperiod($item) {
    $thetype = new WEBLIB_Type($item);
    return $thetype->loanperiod();
  }

  function get_columns() {
	return array('type' => __("Type",'weblibrarian'),
		     'loanperiod' => __("Loan Period (days)",'weblibrarian'));
  }

  function get_sortable_columns() {return array();}
  
  function check_permissions() {
    if (!current_user_can('manage_collection')) {
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
    $per_page = $this->get_per_page();
    // Deal with columns
    $columns = $this->get_columns();    // All of our columns
    $hidden  = array();         // Hidden columns [none]
    $sortable = $this->get_sortable_columns(); // Sortable columns
    $this->_column_headers = array($columns,$hidden,$sortable); // Set up columns

    $all_items = WEBLIB_Type::AllTypes();
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

  function item_types_database() {
    $message = $this->prepare_items();
    ?><div class="wrap"><div id="icon-types" class="icon32"><br /></div>
	<h2><?php _e('Circulation Types','weblibrarian'); ?> <a href="<?php
		echo add_query_arg( array('page' => 'weblib-add-item-type',
					  'mode' => 'add',
					  'id' => false),
				    admin_url('admin.php'));
	?>" class="button add-new-h2"><?php _e('Add New','weblibrarian');
	?></a></h2><?php
	if ($message != '') {
	  ?><div id="message" class="update fade"><?php echo $message; ?></div><?php
	}
	?><form method="get" action="<?php echo admin_url('admin.php'); ?>">
	<input type="hidden" name="page" value="weblib-item-types-database" />
	<?php $this->display(); ?></form></div><?php
  }

  function add_item_type() {
    $message = $this->prepare_one_item();
    ?><div class="wrap"><div id="<?php echo $this->add_item_icon(); ?>" class="icon32"><br />
    </div><h2><?php echo $this->add_item_h2(); ?></h2>
    <?php if ($message != '') {
	?><div id="message" class="update fade"><?php echo $message; ?></div><?php
	} ?>
    <form action="<?php echo admin_url('admin.php'); ?>" method="get">
	<input type="hidden" name="page" value="weblib-add-item-type" />
    <?php $this->display_one_item_form(
		add_query_arg(array('page' => 'weblib-item-types-database', 
				    'mode' => false, 
				    'id' => false))); 
	?></form></div><?php
	
  }
  function set_row_actions($racts) { $this->row_actions = $racts; }
  function get_items_per_page ($option, $default = 20) {
    if ( isset($_REQUEST['screen-options-apply']) &&
	 $_REQUEST['wp_screen_options']['option'] == $option ) {
      $per_page = (int) $_REQUEST['wp_screen_options']['value'];
    } else {
      $per_page = $default;
    }
    return (int) apply_filters( $option, $per_page );
  }

  function checkiteminform() {
    $result = '';
    if ($this->viewmode == 'add') {
      if (WEBLIB_Type::KnownType($_REQUEST['typename'])) {
	$result .= '<br /><span id="error">'.__('Duplicate typename','weblibrarian').'</span>';
      }
    }
    if (!preg_match('/^\d+$/',$_REQUEST['loanperiod'])) {
      $result .= '<br /><span id="error">'.__('Loan period not a whole number','weblibrarian').'</span>';
    }
    return $result;
  }
  function getitemfromform() {
    $this->viewtypename = $_REQUEST['typename'];
    $this->viewloanperiod = $_REQUEST['loanperiod'];
  }
  function add_item_icon() {
    switch ($this->viewmode) {
      case 'edit': return 'icon-type-edit';
      default:
      case 'add': return 'icon-type-add';
    }
  }
  function add_item_h2() {
    switch ($this->viewmode) {
      case 'edit': return __("Edit A Circulation Type's Loan Period",'weblibrarian');
      default:
      case 'add': return __('Add new Circulation Type','weblibrarian');
    }
  }

  function prepare_one_item() {
    $this->check_permissions();
    //file_put_contents("php://stderr","*** WEBLIB_Types_Database_Admin::prepare_one_item:: _REQUEST = ".print_r($_REQUEST,true)."\n");    $message = '';
    if ( isset($_REQUEST['addtype']) ) {
      $this->viewmode = 'add';
      $message = $this->checkiteminform();
      $this->getitemfromform();
      if ($message == '') {
	$item = new WEBLIB_Type($this->viewtypename);
	$item->set_loanperiod($this->viewloanperiod);
	$item->store();
	$message = '<p>'.sprintf(__('%s inserted, with a loan period of %d.','weblibrarian'),
				$item->type(),$item->loanperiod()).'</p>';
	$this->viewmode = 'edit';
	$this->viewtypename = $item->type();
	$this->viewloanperiod = $item->loanperiod();
      } else {
	$this->viewmode = 'add';
      }
    } else if ( isset($_REQUEST['updatetype']) ) {
      $this->viewmode = 'edit';
      $message = $this->checkiteminform();
      $this->getitemfromform();
      if ($message == '') {
	$item = new WEBLIB_Type($this->viewtypename);
	$item->set_loanperiod($this->viewloanperiod);
	$item->store();
	$message = '<p>'.sprintf(__('%s updated, with a loan period of %d.','weblibrarian'),
				$item->type(),$item->loanperiod()).'</p>';
      }
      $this->viewmode = 'edit';
    } else {
      $this->viewmode = isset($_REQUEST['mode']) ? $_REQUEST['mode'] : 'add';
      $this->viewtypename = isset($_REQUEST['typename']) ? $_REQUEST['typename'] : '';
      //file_put_contents("php://stderr","*** WEBLIB_Types_Database_Admin::prepare_one_item: this->viewtypename = '".$this->viewtypename."'\n");
      switch ($this->viewmode) {
	case 'edit':
	  if ($this->viewtypename == '') {
	    $this->viewmode = 'add';
	    $this->viewtypename = '';
	    $this->viewloanperiod = 14;
	  } else if (WEBLIB_Type::KnownType($this->viewtypename)) {
	    $item = new WEBLIB_Type($this->viewtypename);
	    $this->viewloanperiod = $item->loanperiod();
	  } else {
	    $this->viewmode = 'add';
	    $this->viewloanperiod = 14;
	  }
	  break;
	case 'add':
	  $this->viewtypename = '';
	  $this->viewloanperiod = 14;
	  break;
	default:
	  $this->viewmode = 'add';
	  $this->viewtypename = '';
	  $this->viewloanperiod = 14;
      }
    }
    return $message;
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
    ?><table class="form-table">
      <tr valign="top">
	<th scope="row"><label for="typename" style="width:20%;"><?php _e('Type name:','weblibrarian'); ?></label></th>
	<td><input id="typename"
		   name="typename"
		   style="width:75%;"
		   maxlength="16"
		   value="<?php echo stripslashes($this->viewtypename); ?>"<?php
	if ($this->viewmode != 'add') {
	  echo ' readonly="readonly"';
	} ?> /></td></tr>
      <tr valign="top">
	<th scope="row"><label for="loanperiod" style="width:20%;"><?php _e('Loan Period:','weblibrarian'); ?></label></th>
	<td><input id="loanperiod"
		   name="loanperiod"
		   style="width:75%;"
		   value="<?php echo $this->viewloanperiod; ?>" /></td></tr>
      </table>
      <p>
	<?php switch($this->viewmode) {
		case 'add':
		  ?><input type="submit" name="addtype" class="button-primary" value="<?php  _e('Add New Type','weblibrarian'); ?>" /><?php
		  break;
		case 'edit':
		  ?><input type="submit" name="updatetype" class="button-primary" value="<?php  _e('Update Type','weblibrarian'); ?>" /><?php
		  break;
	      }
	      ?><a href="<?php echo $returnURL; ?>" class="button-primary"><?php _e('Return','weblibrarian'); ?></a>
	</p><?php
  }

}


