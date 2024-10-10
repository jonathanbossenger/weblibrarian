<?php



/*************************** LOAD THE BASE CLASS *******************************
 *******************************************************************************
 * The WP_List_Table class isn't automatically available to plugins, so we need
 * to check if it's available and load it if necessary.
 */
if(!class_exists('WP_List_Table')){
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class WEBLIB_Users_Admin extends WP_List_Table {

  private $patron_admin_pages;
  static $my_per_page = 'weblib_users_per_page';

  function __construct($pa_pages) {
    global $weblib_contextual_help;

    $this->patron_admin_pages = $pa_pages;

    $screen_id = add_submenu_page('users.php',__('Edit your Patron info','weblibrarian'),
			__('Edit Patron info','weblibrarian'),'read','weblib-edit-your-patron-info',
			array($this,'edit_your_patron_info'));

    $weblib_contextual_help->add_contextual_help($screen_id,'weblib-edit-your-patron-info');
    $screen_id =  add_submenu_page('users.php',__('Add Patron ID to a user','weblibrarian'),
			__('Add Patron ID','weblibrarian'),'edit_users',
			'weblib-add-patron-id-to-a-user',array($this,'add_patron_id'));
    $weblib_contextual_help->add_contextual_help($screen_id,'weblib-add-patron-id-to-a-user');
    add_action("load-$screen_id", array($this,'add_per_page_option'));
    parent::__construct(array(
		'singular' => __('user','weblibrarian'),
		'plural' => __('users','weblibrarian')
    ) );
  }

  function add_per_page_option() {
    $args['option'] = WEBLIB_Users_Admin::$my_per_page;
    $args['label'] = __('Users','weblibrarian');
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
			  $column_name,$item->id);
  }

  function column_username($item) {
    return $item->user_login;
  }
  function column_patronid($item) {
    $patron_id = get_user_meta($item->id,'PatronID',true);
    if ($patron_id == '') { $patron_id = 0; }
    return '<span id="displayed-patronid-'.$item->id.'">'.$patron_id.'</span>';
  }
  function column_patronname($item) {
    $patron_id = get_user_meta($item->id,'PatronID',true);
    if ($patron_id == '') {
      $patron_name = '';
    } else {
      $patron_name = WEBLIB_Patron::NameFromId($patron_id);
    }
    return $patron_name;
    
  }
  function column_update($item) {
    $patron_id = get_user_meta($item->id,'PatronID',true);
    if ($patron_id == '') { $patron_id = 0; }
    ?><?php
      WEBLIB_Patron::PatronIdDropdown($patron_id,
			array('onlyunassoc' => false,
			      'name' => 'patronid-'.$item->id));
    ?><input type="button" class="button" name="setid" value="<?php _e('Update Id','weblibrarian'); ?>" 
	     onClick="UpdatePatronID(<?php echo $item->id; ?>);" />
      <?php
    return "";
  }
  function get_columns() {
	return array('username' => __('Username','weblibrarian'),
		     'patronid' => __('Patron ID','weblibrarian'),
		     'patronname' => __('Patron Name','weblibrarian'),
		     'update' => __('Update','weblibrarian') );
  }
  
  function get_sortable_columns() {
    return array();
  }

  function check_permissions() {
    if (!current_user_can('edit_users')) {
      wp_die( __('You do not have sufficient permissions to access this page.','weblibrarian') );
    }
  }

  function get_column_info() {
    if ( isset($this->_column_headers) ) {return $this->_column_headers;}
    $columns = $this->get_columns( );
    $hidden = array();
    $sortable = $this->get_sortable_columns();
    $this->_column_headers =
	array( $columns, $hidden, $sortable );
    return $this->_column_headers;
  }

  function prepare_items() {
    $this->check_permissions();
    $message = '';
    if ( isset( $_REQUEST['setid']) ) {
      $patronid = sanitize_text_field($_REQUEST['patronid']);
      $user_id  = sanitize_text_field($_REQUEST['user_id']);
      $user = get_userdata($user_id);
      $patron = new WEBLIB_Patron($patronid);
      $error = '';
      if (! $patron->StorePatronIDWithSelectedUser($user_id,$error,true)) {
	$message = $error;
      } else {
	$message = '<p>'.sprintf(__('Patron ID %d (%s) has been set for user %d (%s).',
					'weblibrarian'),
				$patronid,WEBLIB_Patron::NameFromId($patronid),
				$user_id,$user->user_login).'</p>';
      }
    }
    global $usersearch;
    $usersearch = isset( $_REQUEST['s'] ) ? stripslashes( trim( sanitize_text_field($_REQUEST['s']) ) ) : '';
    // Deal with columns
    $columns = $this->get_columns();    // All of our columns
    $hidden  = array();         // Hidden columns [none]
    $sortable = $this->get_sortable_columns(); // Sortable columns
    $this->_column_headers = array($columns,$hidden,$sortable); // Set up columns

    $per_page = $this->get_per_page($option);
    $paged = $this->get_pagenum();
    
    $args = array(
		'number' => $per_page,
		'offset' => ( $paged-1 ) * $per_page,
		'search' => $usersearch,
		'fields' => array('id','user_login') );

    if ( '' !== $args['search'] )
        $args['search'] = '*' . $args['search'] . '*';
    
    $wp_user_search = new WP_User_Query( $args );

    $this->items = $wp_user_search->get_results();

    $this->set_pagination_args( array(
	'total_items' => $wp_user_search->get_total(),
	'per_page'    => $per_page
    ) );
    return $message;
  }
  function no_items() {
    _e( 'No matching users were found.','weblibrarian' );
  }

  function edit_your_patron_info() {
    $error = '';
    $patron = WEBLIB_Patron::PatronFromCurrentUser($error);
    $formtype = 'none';
    if ($patron != null) {
	$message = $this->patron_admin_pages->prepare_one_item(
			array('mode' => 'edit',
			      'id' => $patron->ID(),
			      'self' => true));
      $formtype = 'edit';
    } else {
      $formtype = 'setid';
      $patronid = 0;
      if (isset($_REQUEST['patronid']) ) {
	$patronid = sanitize_text_field($_REQUEST['patronid']);
	$patron = new WEBLIB_Patron($patronid);
	$error = '';
	if (! $patron->StorePatronIDWithCurrentUser($error) ) {
	  $message = '<p><span id="error">'.$error.'</span></p>';
	} else {
	  $message = '<p>'.__('Your Patron ID has been set. Thank you.','weblibrarian').'</p>';
	  $formtype = 'none';
	}
      }
    }      
    ?><div class="wrap"><div id="icon-users" class="icon32"><br /></div><h2><?php _e('Edit your patron info','weblibrarian'); ?></h2><?php
    if ($message != '') {
      ?><div id="message" class="update fade"><?php echo $message; ?></div><?php
    }
    ?><form action="<?php echo admin_url('admin.php'); ?>" method="get">
	<input type="hidden" name="page" value="weblib-edit-your-patron-info" /><?php
    switch ($formtype) {
      case 'none': break;
      case 'edit': $this->patron_admin_pages->display_one_item_form(null); 
		   break;
      case 'setid' :
	WEBLIB_Patron::PatronIdDropdown($patronid,array('onlyunassoc' => true));
	?><input type="submit" value="<?php _e('Set Your Patron Id','weblibrarian'); ?>" /><?php
	break;
    }
    ?></form></div><?php
  }

  function add_patron_id() {
    //file_put_contents("php://stderr","*** WEBLIB_Users_Admin::add_patron_id: current_screen is '".print_r(get_current_screen(),true)."'\n");
    $message = $this->prepare_items();
    ?><div class="wrap"><div id="icon-users" class="icon32"><br /></div>
	<h2><?php _e('Add Patron Ids to Users','weblibrarian'); ?></h2><?php
	if ($message != '') {
	  ?><div id="message" class="update fade"><?php echo $message; ?></div><?php
	}
	?><form method="get" action="<?php echo admin_url('admin.php'); ?>">
	<input type="hidden" name="page" value="weblib-add-patron-id-to-a-user" />
	<?php $this->search_box(__( 'Search Users','weblibrarian' ), 'user' ); ?>
	<?php $this->display(); ?></form></div><?php
  }


}


