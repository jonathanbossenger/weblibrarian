<?php



/*************************** LOAD THE BASE CLASS *******************************
 *******************************************************************************
 * The WP_List_Table class isn't automatically available to plugins, so we need
 * to check if it's available and load it if necessary.
 */
if(!class_exists('WP_List_Table')){
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}



class WEBLIB_Collection_Shared extends WP_List_Table {

  function __construct($args = array()) {
	parent::__construct(array(
		'singular' => __('Item','weblibrarian'),
		'plural' => __('Items','weblibrarian'),
                'screen' => isset( $args['screen'] ) ? $args['screen'] : null
        ) );

  }

  /* Default column (nothing really here, since every displayed column gets 
   * its own function).
   */
  function column_default($item, $column_name) {
    return apply_filters( 'manage_items_custom_column','',
			  $column_name,$item['patronid']);
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
  function search_box($text, $input_id) {
    if ( empty( sanitize_text_field($_REQUEST['s']) ) && !$this->has_items() ) return;

    $input_id = $input_id . '-search-input';

    if ( ! empty( sanitize_text_field($_REQUEST['orderby']) ) )
      echo '<input type="hidden" name="orderby" value="' . esc_attr( sanitize_text_field($_REQUEST['orderby']) ) . '" />';
    if ( ! empty( sanitize_text_field($_REQUEST['order']) ) )
      echo '<input type="hidden" name="order" value="' . esc_attr( sanitize_text_field($_REQUEST['order']) ) . '" />';
    $field = isset ($_REQUEST['f']) ? sanitize_text_field($_REQUEST['f']) : 'title';
?>
<p class="search-box">
	<label class="screen-reader-text" for="<?php echo $input_id; ?>"><?php echo $text; ?>:</label>
	<input type="text" id="<?php echo $input_id; ?>" name="s" value="<?php _admin_search_query(); ?>" />
	<select name="f">
	<?php foreach (array('Title' => 'title',
			 'Author' => 'author',
			 'Subject' => 'subject',
			 'ISBN'  => 'isbn',
			 'Keyword' => 'keyword') as $l => $f) {
		?><option value="<?php echo esc_attr($f); ?>"<?php
		  if ($f == $field) {echo ' selected="selected"';}
		?>><?php echo $l; ?></option>
		<?php
	      } ?>
	<?php submit_button( $text, 'button', false, false, array('id' => 'search-submit') ); ?>
</p>
<?php
  }

/*
  function get_column_info() {
    if ( isset( $this->_column_headers ) ) return $this->_column_headers;
    $screen = get_current_screen();  
    
    $columns = $this->get_columns( );
    $hidden = get_hidden_columns( $screen );

    $sortable = $this->get_sortable_columns();

    $this->_column_headers = array( $columns, $hidden, $sortable );

    return $this->_column_headers;
  }
*/
function get_sortable_columns() {
        $temp = array('barcode' => array('barcode',false),
		     'title' => array('title',false), 
		     'author' => array('author',false),
		     'callnumber' => array('callnumber',false) ); 
        //file_put_contents("php://stderr","*** WEBLIB_Collection_Admin::get_sortable_columns: ".print_r($temp,true)."\n");
	return $temp;
  }  

}


class WEBLIB_Collection_Admin extends WEBLIB_Collection_Shared {
  var $viewmode = 'add';
  var $viewbarcode   = '';
  var $viewitem;
  var $viewkeywords = array();

  static $my_per_page = 'weblib_collection_per_page';

  function __construct($args = array()) {
    global $weblib_contextual_help;

    $screen_id =  add_menu_page(__('Collection Database','weblibrarian'), __('Collection','weblibrarian'),
				'manage_collection','weblib-collection-database',
				array($this,'collection_database'),
                                WEBLIB_IMAGEURL.'/Collection_Menu.png');
    $args['screen'] = WP_Screen::get($screen_id);
    $weblib_contextual_help->add_contextual_help($screen_id,'weblib-collection-database');
    add_action("load-$screen_id", array($this,'add_per_page_option'));
    $screen_id =  add_submenu_page('weblib-collection-database',
				__('Add Item to Collection','weblibrarian'),__('Add New','weblibrarian'),
				'manage_collection','weblib-add-item-collection',
				array($this,'add_item'));
    $weblib_contextual_help->add_contextual_help($screen_id,'weblib-add-item-collection');
    $screen_id =  add_submenu_page('weblib-collection-database',
				__('Add Bulk Items to Collection','weblibrarian'),__('Add New Bulk','weblibrarian'),
				'manage_collection','weblib-add-item-collection-bulk',
				array($this,'add_item_bulk'));
    $weblib_contextual_help->add_contextual_help($screen_id,'weblib-add-item-collection-bulk');
    $screen_id =  add_submenu_page('weblib-collection-database',
				   __('DB Maintenance','weblibrarian'),__('DB Maint','weblibrarian'),
				   'manage_collection',
				   'weblib-collection-maintance',
				   array($this,'db_maintance'));
    $weblib_contextual_help->add_contextual_help($screen_id,'weblib-collection-maintance');
   parent::__construct($args); 

  }

  function add_per_page_option() {
    $args['option'] = WEBLIB_Collection_Admin::$my_per_page;
    $args['label'] = __('Items','weblibrarian');
    $args['default'] = 20;
    add_screen_option('per_page', $args);
  }

  function get_per_page() {
    $user = get_current_user_id();
    $screen = get_current_screen();
    $option = $screen->get_option('per_page','option');
    //file_put_contents("php://stderr","*** WEBLIB_Collection_Admin::get_per_page(): user = $user, screen = ".print_r($screen,true).", option = $option\n");
    $v = get_user_meta($user, $option, true);
    if (empty($v)  || $v < 1) {
      $v = $screen->get_option('per_page','default');
    }
    return $v;
  }

  function column_cb ($item) {
    return '<input type="checkbox" name="checked[]" value="'.esc_attr($item).'" />';
  }
  function column_title ($item) {
    $theitem = new WEBLIB_ItemInCollection($item);
    // Build row actions
    $actions = array(
	'edit' => '<a href="'.add_query_arg(array('page' => 'weblib-add-item-collection',
						  'mode' => 'edit',
						  'barcode' => $item),
					    admin_url('admin.php')).'">'.
		__('Edit','weblibrarian')."</a>",
	'view' => '<a href="'.add_query_arg(array('page' => 'weblib-add-item-collection',
						  'mode' => 'view',
						  'barcode' => $item),
					    admin_url('admin.php')).'">'.
		__('View','weblibrarian')."</a>",
	'delete' => '<a href="'.add_query_arg(array('page' => sanitize_text_field($_REQUEST['page']),
						    'action' => 'delete',
						  'barcode' => $item),
					    admin_url('admin.php')).'">'.
		__('Delete','weblibrarian')."</a>"
    );
    return $theitem->title().$this->row_actions($actions);
  }

  public function get_columns() {
    $temp = array('cb' => '<input type="checkbox" />',
		     'barcode' => __('Barcode','weblibrarian'),
		     'title' => __('Title','weblibrarian'),
		     'author' => __('Author','weblibrarian'),
		     'type' => __('Type','weblibrarian'),
		     'callnumber' => __('Call Number','weblibrarian') );
    //file_put_contents("php://stderr","*** WEBLIB_Collection_Admin::get_columns: ".print_r($temp,true)."\n");
    
    return $temp;
  }

  function get_bulk_actions() {
    return array ('delete' => __('Delete','weblibrarian') );
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
      case 'delete':
	if ( isset($_REQUEST['checked']) && !empty(sanitize_text_field($_REQUEST['checked']))) {
	  foreach ( sanitize_text_field($_REQUEST['checked']) as $thebarcode ) {
	    WEBLIB_ItemInCollection::DeleteItemByBarCode($thebarcode);
	    WEBLIB_ItemInCollection::DeleteKeywordsByBarCode($thebarcode);
	  }
	} else if ( isset($_REQUEST['barcode']) ) {
	  WEBLIB_ItemInCollection::DeleteItemByBarCode(sanitize_text_field($_REQUEST['barcode']));
	  WEBLIB_ItemInCollection::DeleteKeywordsByBarCode(sanitize_text_field($_REQUEST['barcode']));
	}	  
	break;
    }
  }
  function check_permissions() {
    if (!current_user_can('manage_collection')) {
      wp_die( __('You do not have sufficient permissions to access this page.','weblibrarian') );
    }
  }

  function prepare_items() {
    //file_put_contents("php://stderr","*** WEBLIB_Collection_Admin::prepare_items:: _REQUEST = ".print_r($_REQUEST,true)."\n");
    $this->check_permissions();
    // Deal with columns
    $columns = $this->get_columns();    // All of our columns
    $hidden  = array();         // Hidden columns [none]
    $sortable = $this->get_sortable_columns(); // Sortable columns
    $this->_column_headers = array($columns,$hidden,$sortable); // Set up columns

    $message = '';
    if ( isset($_REQUEST['fixbrokenbarcodes']) ) {
      $n = WEBLIB_ItemInCollection::fixBrokenBarcodes();
      $message .= sprintf(__('Broken barcodes fixed: %d','weblibrarian'),$n);
    }

    $this->process_bulk_action();

    $search = isset( $_REQUEST['s'] ) ? sanitize_text_field($_REQUEST['s']) : '';
    $field  = isset( $_REQUEST['f'] ) ? sanitize_text_field($_REQUEST['f']) : 'title';
    $orderby = isset( $_REQUEST['orderby'] ) ? sanitize_text_field($_REQUEST['orderby']) : 'barcode';
    if ( empty( $orderby ) ) $orderby = 'barcode';
    $order = isset( $_REQUEST['order'] ) ? sanitize_text_field($_REQUEST['order']) : 'ASC';
    if ( empty( $order ) ) $order = 'ASC';

    $per_page = $this->get_per_page();
    
    if (false && $search == '') {
      $all_items = WEBLIB_ItemInCollection::AllBarCodes($orderby,$order);
    } else {
      switch($field) {
	case 'title':
	  $all_items = WEBLIB_ItemInCollection::FindItemByTitle('%'.$search.'%',$orderby,$order);
	  break;
	case 'author':
	  $all_items = WEBLIB_ItemInCollection::FindItemByAuthor('%'.$search.'%',$orderby,$order);
	  break;
	case 'subject':
	  $all_items = WEBLIB_ItemInCollection::FindItemBySubject('%'.$search.'%',$orderby,$order);
	  break;
	case 'isbn':
	  $all_items = WEBLIB_ItemInCollection::FindItemByISBN('%'.$search.'%',$orderby,$order);
	  break;
	case 'keyword':
	  $all_items = WEBLIB_ItemInCollection::FindItemByKeyword('%'.$search.'%',$orderby,$order);
	  break;
      }
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

  function collection_database() {
    $message = $this->prepare_items();
    ?><div class="wrap"><div id="icon-collection" class="icon32"><br /></div>
	<h2><?php _e('Library Collection','weblibrarian'); ?> <a href="<?php
		echo add_query_arg( array('page' => 'weblib-add-item-collection',
					  'mode' => 'add',
					  'barcode' => false));
	?>" class="button add-new-h2"><?php _e('Add New','weblibrarian');?></a> <a href="<?php
	   echo add_query_arg( array('page' => 'weblib-add-item-collection-bulk'));
	?>" class="button add-new-h2"><?php _e('Add New Bulk','weblibrarian');
	?></a> <a href="<?php
          echo add_query_arg( array('dataselection' => 'collection',
                                    'action' => 'ExportLibraryData'),
                              admin_url('admin-post.php'));
	?>" class="button add-new-h2"><?php _e('Export as CSV','weblibrarian');
	?></a></h2><?php
	if ($message != '') {
	  ?><div id="message" class="update fade"><?php echo $message; ?></div><?php
	}
	?><form method="get" action="<?php echo admin_url('admin.php'); ?>"> 
	<input type="hidden" name="page" value="weblib-collection-database" />
	<?php $this->search_box(__( 'Search Collection' ,'weblibrarian'), 'collection' ); ?>
	<?php submit_button(__( 'Fix Broken Barcodes','weblibrarian' ),  'secondary', 
			'fixbrokenbarcodes', false, 
			array( 'id' => 'post-query-submit') ); ?>
	<?php $this->display(); ?></form></div><?php
  }
	

  /* Add/View/Edit page */
  function add_item() {
    $message = $this->prepare_one_item();
    ?><div class="wrap"><div id="<?php echo $this->add_item_icon(); ?>" class="icon32"><br />
    </div><h2><?php echo $this->add_item_h2(); ?></h2>
    <?php if ($message != '') {
	?><div id="message" class="update fade"><?php echo $message; ?></div><?php
	} ?>
    <form action="<?php echo admin_url('admin.php'); ?>" method="get">
	<input type="hidden" name="page" value="weblib-add-item-collection" />
    <?php $this->display_one_item_form(
		add_query_arg(array('page' => 'weblib-collection-database', 
				    'mode' => false, 
				    'barcode' => false))); 
	?></form></div><?php
	
  }

  function prepare_one_item() {
    $message = '';
    if ( isset($_REQUEST['additem']) ) {
      $message = $this->checkiteminform('');
      $item    = $this->getitemfromform('');
      if ($message == '') {
	$barcode = isset($_REQUEST['barcode']) ? sanitize_text_field($_REQUEST['barcode']) : '';
	$newbarcode  = $item->store($barcode);
	$keywords = $this->getkeywordsfromform();
	foreach ($keywords as $keyword) {
	  $item->addkeywordto($keyword);
	}
	$message = '<p>'.sprintf(__('%s inserted with barcode %s.','weblibrarian'),
				 $item->title(),$newbarcode).'</p>';
	$this->viewmode = 'edit';
	$this->viewbarcode = $newbarcode;
	$this->viewitem = $item;
	$this->viewkeywords = $keywords;
      } else {
	$this->viewmode = 'add';
	$this->viewbarcode = $barcode;
	$this->viewitem = $item;
	$this->viewkeywords = $this->getkeywordsfromform();
      }
    } else if ( isset($_REQUEST['updateitem']) && 
		isset($_REQUEST['barcode']) ) {
      $message = $this->checkiteminform(sanitize_text_field($_REQUEST['barcode']));
      $item    = $this->getitemfromform(sanitize_text_field($_REQUEST['barcode']));
      if ($message == '') {
	$item->store();
	$keywords = $this->getkeywordsfromform();
	$oldkeywords = $item->keywordsof();
	$removedkeywords = array_diff($oldkeywords,$keywords);
	foreach ($removedkeywords as $keyword) {
	  $item->removekeywordfrom($keyword);
	}
	$newkeywords = array_diff($keywords,$oldkeywords);
	foreach ($newkeywords as $keyword) {
	  $item->addkeywordto($keyword);
	}
	$message = '<p>'.sprintf(__('%s updated.','weblibrarian'),
				$item->title()).'</p>';
      }
      $this->viewmode = 'edit';
      $this->viewbarcode   = $item->BarCode();
      $this->viewitem = $item;
      $this->viewkeywords = $item->keywordsof();
    } else {
      $this->viewmode = isset($_REQUEST['mode']) ? sanitize_text_field($_REQUEST['mode']) : 'add';
      $this->viewbarcode = isset($_REQUEST['barcode']) ? sanitize_text_field($_REQUEST['barcode']) : '';
      switch ($this->viewmode) {
	case 'edit':
	case 'view':
	  if ($this->viewbarcode == '') {$this->viewmode = 'add';}
	  break;
	case 'add':
	  $this->viewbarcode = '';
	  break;
	default:
	  $this->viewmode = 'add';
	  $this->viewbarcode = '';
	  break;
      }
      $this->viewitem = new WEBLIB_ItemInCollection($this->viewbarcode);
      //file_put_contents("php://stderr","*** WEBLIB_Collection_Admin::prepare_one_item(): this->viewitem is ".print_r($this->viewitem,true)."\n");
      if ($this->viewbarcode == '') {
	$this->viewkeywords = array();
      } else {
	$this->viewkeywords = $this->viewitem->keywordsof();
      }
    }
    return $message;
  }

  function add_item_icon() {
    switch ($this->viewmode) {
      case 'view': return 'icon-item-view';
      case 'edit': return 'icon-item-edit';
      default:
      case 'add': return 'icon-item-add';
    }
  }
  function add_item_h2() {
    switch ($this->viewmode) {
      case 'view': return __("View an item in the collection",'weblibrarian');
      case 'edit': return __("Edit an item in the collection",'weblibrarian');
      default:
      case 'add': return __('Add a new item to the collection','weblibrarian');
    }
  }

  function display_one_item_form($returnURL) {
    if ( isset($_REQUEST['paged']) ) {
      $paged = sanitize_text_field($_REQUEST['paged']);
      ?><input type="hidden" name="paged" value="<?php echo esc_attr($paged); ?>" /><?php
    }
    if ( isset($_REQUEST['screen-options-apply']) ) {
      $screenopts = sanitize_text_field($_REQUEST['screen-options-apply']);
      ?><input type="hidden" name="screen-options-apply" value="<?php echo esc_attr($screenopts); ?>" /><?php
    }
    if ( isset($_REQUEST['wp_screen_options']['option']) ) {
      $wpscreenoptsopt = sanitize_text_field($_REQUEST['wp_screen_options']['option']);
      ?><input type="hidden" name="wp_screen_options[option]" value="<?php echo esc_attr($wpscreenoptsopt); ?>" /><?php
    }
    if ( isset($_REQUEST['wp_screen_options']['value']) ) {
      $wpscreenoptsval = sanitize_text_field($_REQUEST['wp_screen_options']['value']);
      ?><input type="hidden" name="wp_screen_options[value]" value="<?php echo esc_attr($wpscreenoptsval); ?>" /><?php
    }
    if ($this->viewmode == 'view') {
      $ro = ' readonly="readonly"';
    } else {
      $ro = '';
    }
    ?><table class="form-table">
      <tr valign="top">
	<th scope="row"><label for="barcode" style="width:20%;"><?php _e('Barcode:','weblibrarian'); ?></label></th>
	<td><input id="barcode"
                   name="barcode"
                   style="width:75%;"
                   maxlength="16"
                   value="<?php echo esc_attr(stripslashes($this->viewbarcode)); ?>"<?php
                   if ($this->viewmode != 'add') {
                     echo ' readonly="readonly"';
                   } ?> /></td></tr>
      <tr valign="top">
	<th scope="row"><label for="title" style="width:20%;"><?php _e('Title:','weblibrarian'); ?></label></th>
	<td><input id="title"
		   name="title"
		   style="width:75%;"
		   maxlength="128"
		   value="<?php echo esc_attr(stripslashes($this->viewitem->title())); ?>"<?php echo $ro; ?> /></td></tr>
      <tr valign="top">
	<th scope="row"><label for="itemauthor" style="width:20%;"><?php _e('Author:','weblibrarian'); ?></label></th>
	<td><input id="itemauthor"
		   name="itemauthor"
		   style="width:75%;"
		   maxlength="64"
		   value="<?php echo esc_attr(stripslashes($this->viewitem->author())); ?>"<?php echo $ro; ?> /></td></tr>
      <tr valign="top">
	<th scope="row"><label for="subject" style="width:20%;"><?php _e('Subject:','weblibrarian'); ?></label></th>
	<td><input id="subject"
		   name="subject"
		   style="width:75%;"
		   maxlength="128"
		   value="<?php echo esc_attr(stripslashes($this->viewitem->subject())); ?>"<?php echo $ro; ?> /></td></tr>
      <tr valign="top">
	<th scope="row"><label for="description" style="width:20%;"><?php _e('Description:','weblibrarian'); ?></label></th>
	<td><textarea id="description"
		      name="description"
		      style="width:75%);"
		      rows="5" cols="64"
		      <?php echo $ro; ?>><?php echo esc_attr(stripslashes($this->viewitem->description())); ?></textarea></td></tr>
      <tr valign="top">
	<th scope="row"><label for="itemcategory" style="width:20%;"><?php _e('Category:','weblibrarian'); ?></label></th>
	<td><input id="itemcategory"
		   name="itemcategory"
		   style="width:75%;"
		   maxlength="36"
		   value="<?php echo esc_attr(stripslashes($this->viewitem->category())); ?>"<?php echo $ro; ?> /></td></tr>
      <tr valign="top">
	<th scope="row"><label for="media" style="width:20%;"><?php _e('Media:','weblibrarian'); ?></label></th>
	<td><input id="media"
		   name="media"
		   style="width:75%;"
		   maxlength="36"
		   value="<?php echo esc_attr(stripslashes($this->viewitem->media())); ?>"<?php echo $ro; ?> /></td></tr>
      <tr valign="top">
	<th scope="row"><label for="publisher" style="width:20%;"><?php _e('Publisher:','weblibrarian'); ?></label></th>
	<td><input id="publisher"
		   name="publisher"
		   style="width:75%;"
		   maxlength="36"
		   value="<?php echo stripslashes($this->viewitem->publisher()); ?>"<?php echo $ro; ?> /></td></tr>
      <tr valign="top">
	<th scope="row"><label for="publocation" style="width:20%;"><?php _e('Publisher Location:','weblibrarian'); ?></label></th>
	<td><input id="publocation"
		   name="publocation"
		   style="width:75%;"
		   maxlength="36"
		   value="<?php echo esc_attr(stripslashes($this->viewitem->publocation())); ?>"<?php echo $ro; ?> /></td></tr>
      <tr valign="top">
	<th scope="row"><label for="pubdate" style="width:20%;"><?php _e('Publish Date:','weblibrarian'); ?></label></th>
	<td><input id="pubdate"
		   name="pubdate"
		   style="width:75%;"
		   maxlength="40"
		   value="<?php echo esc_attr($this->viewitem->pubdate()); ?>"<?php echo $ro; ?> /></td></tr>
      <tr valign="top">
	<th scope="row"><label for="edition" style="width:20%;"><?php _e('Edition:','weblibrarian'); ?></label></th>
	<td><input id="edition"
		   name="edition"
		   style="width:75%;"
		   maxlength="36"
		   value="<?php echo esc_attr(stripslashes($this->viewitem->edition())); ?>"<?php echo $ro; ?> /></td></tr>
      <tr valign="top">
	<th scope="row"><label for="isbn" style="width:20%;"><?php _e('ISBN:','weblibrarian'); ?></label></th>
	<td><input id="isbn"
		   name="isbn"
		   style="width:75%;"
		   maxlength="20"
		   value="<?php echo esc_attr(stripslashes($this->viewitem->isbn())); ?>"<?php echo $ro; ?> /></td></tr>
      <tr valign="top">
	<th scope="row"><label for="type" style="width:20%;"><?php _e('Type:','weblibrarian'); ?></label></th>
	<td><?php
	  if ($this->viewmode == 'view') {
	    ?><input id="type" 
		     name="type" 
		     style="width:75%;" 
		     value="<?php echo esc_attr(stripslashes($this->viewitem->type())); ?>"
		     readonly="readonly" /><?php
	  } else {
	    ?><select id="type" name="type" style="width:75%;"><?php
	    $alltypes = WEBLIB_Type::AllTypes();
	    $existingtype = $this->viewitem->type();
	    if ($existingtype == '') $existingtype = $alltypes[0];
	    foreach ($alltypes as $atype) {
	      ?><option value="<?php echo esc_attr($atype); ?>"<?php
		if ($atype == $existingtype) echo ' selected="selected"';
	      ?>><?php echo esc_html($atype); ?></option><?php
	    }
	    ?></select><?php
	  } ?></td></tr>
      <tr valign="top">
	<th scope="row"><label for="thumburl" style="width:20%;"><?php _e('Thumbnail URL:','weblibrarian'); ?></label></th>
	<td><input id="thumburl"
		   name="thumburl"
		   style="width:75%;"
		   maxlength="256"
		   value="<?php echo esc_attr(stripslashes($this->viewitem->thumburl())); ?>"<?php echo $ro; ?> /></td></tr>
              <tr valign="top">
	<th scope="row"><label for="callnumber" style="width:20%;"><?php _e('Call Number:','weblibrarian'); ?></label></th>
	<td><input id="callnumber"
		   name="callnumber"
		   style="width:75%;"
		   maxlength="36"
		   value="<?php echo esc_attr(stripslashes($this->viewitem->callnumber())); ?>"<?php echo $ro; ?> /></td></tr>
      <tr valign="top">
	<td colspan="2" width="100%">
	<div id="itemedit-keyword-div">
	<?php if ($this->viewmode != 'view') {
		?><div class="jaxkeyword">
		  <div class="nojs-keywords hide-if-js">
		  <p>Add or remove keywords</p><?php
	      } else {
		?><label for="itemedit-keyword-list"><?php _e('Keywords:','weblibrarian'); ?></label><br /><?php
	      }
		?><textarea id="itemedit-keyword-list" name="keywordlist" 
			    rows="3" cols="20" class="the-keywords"<?php echo $ro; ?> ><?php
	      echo esc_html(implode(',',$this->viewkeywords)); ?></textarea><?php
	   if ($this->viewmode != 'view') {
	  ?></div><div class="hide-if-no-js">
		<label class="screen-reader-text" 
		       for="itemedit-new-keyword-item_keyword"><?php _e('Item Keywords','weblibrarian'); ?></label>
		<div class="keywordhint"><?php _e('Add New Keyword','weblibrarian'); ?></div>
	    <p><input type="text" id="itemedit-new-keyword-item_keyword" 
		      name="newkeyword" class="newkeyword form-input-tip" 
		      size="16" autocomplete="off" value="" />
	       <input type="button" class="button" value="<?php _e('Add','weblibrarian'); ?>" 
			onclick="WEBLIB_AddKeyword('itemedit');" /></p>
	    <p class="howto"><?php _e('Separate keywords with commas','weblibrarian'); ?></p></div> 
		<div id="itemedit-keywordchecklist" class="keywordchecklist">
		<script type="text/javascript">
			WEBLIB_WriteKeywords('itemedit');</script></div><?php
	    } ?></div></td></tr>
      </table>
      <?php 
	if ($this->viewmode != 'view' && $this->haveAWSoptions()) {
        ?><div id="resizable" class="ui-widget-content">
              <div id="amazon-logo" class="ui-widget-header"><br /></div>
              <iframe src="<?php 
                 echo add_query_arg(array('action' => 'AWSForm'),
                                    admin_url('admin-post.php')); ?>" 
               id="aws-formframe">
              </iframe>              
            </div><?php
	   }
	 ?>
      <p>
	<?php switch($this->viewmode) {
		case 'add':
		  ?><input type="submit" name="additem" class="button-primary" value="<?php  _e('Add New Item','weblibrarian'); ?>" /><?php
		  break;
		case 'edit':
		  ?><input type="submit" name="updateitem" class="button-primary" value="<?php  _e('Update Item','weblibrarian'); ?>" /><?php
		  break;
	      }
	      ?><a href="<?php echo $returnURL; ?>" class="button-primary"><?php _e('Return','weblibrarian'); ?></a>
	</p><?php
  }
  function haveAWSoptions() {
    $aws_public_key = get_option('weblib_aws_public_key');
    $aws_private_key = get_option('weblib_aws_private_key');
    $associate_tag = get_option('weblib_associate_tag');
    if ($aws_public_key != '' && $aws_private_key != '' && $associate_tag != '') {
      return true;
    } else {
      return false;
    }
  }

  function checkiteminform($barcode)
  {
    $result = '';
    if ($this->viewmode == 'add') {
      $newbarcode = sanitize_text_field($_REQUEST['barcode']);
      if ($newbarcode != '') {
	if (!preg_match('/^[a-zA-Z0-9]+$/',$newbarcode) || strlen($barcode) > 16) {
	  $result .= '<br /><span id="error">'.__('Bad barcode.  Must be alphanumerical and not more than 16 characters long','weblibrarian').'</span>';
	}
      }
    }
    if (sanitize_text_field( $_REQUEST['title'] ) == '') {
      $result .= '<br /><span id="error">'.__('Title is invalid','weblibrarian').'</span>';
    }
    if (sanitize_text_field($_REQUEST['itemauthor']) == '') {
      $result .= '<br /><span id="error">'.__('Author is invalid','weblibrarian').'</span>';
    }
    if (sanitize_text_field($_REQUEST['subject']) == '') {
      $result .= '<br /><span id="error">'.__('Subject is invalid','weblibrarian').'</span>';
    }
    WEBLIB_Patrons_Admin::ValidHumanDate(sanitize_text_field($_REQUEST['pubdate']),$dummy,__('Publication Date','weblibrarian'),$result);
    if (sanitize_text_field($_REQUEST['type']) == '') {
      $result .= '<br /><span id="error">'.__('Type is invalid','weblibrarian').'</span>';
    }
    return $result;    
  }

  function getitemfromform($barcode)
  {
    $item = new WEBLIB_ItemInCollection($barcode);
    $item->set_title(sanitize_text_field($_REQUEST['title']));
    $item->set_author(sanitize_text_field($_REQUEST['itemauthor']));
    $item->set_subject(sanitize_text_field($_REQUEST['subject']));
    $item->set_description(sanitize_text_field($_REQUEST['description']));
    $item->set_category(sanitize_text_field($_REQUEST['itemcategory']));
    $item->set_media(sanitize_text_field($_REQUEST['media']));
    $item->set_publisher(sanitize_text_field($_REQUEST['publisher']));
    $item->set_publocation(sanitize_text_field($_REQUEST['publocation']));
    if (WEBLIB_Patrons_Admin::ValidHumanDate(sanitize_text_field($_REQUEST['pubdate']),$thepubdate,'Publication Date',$error)) {
      $item->set_pubdate($thepubdate);
    }
    $item->set_edition(sanitize_text_field($_REQUEST['edition']));
    $item->set_isbn(sanitize_text_field($_REQUEST['isbn']));
    $item->set_type(sanitize_text_field($_REQUEST['type']));
    $item->set_thumburl(sanitize_text_field($_REQUEST['thumburl']));
    $item->set_callnumber(sanitize_text_field($_REQUEST['callnumber']));
    return $item;
  }

  function getkeywordsfromform()
  {
    return explode(',',sanitize_text_field($_REQUEST['keywordlist']));
  }

  function add_item_bulk() {
    $message = $this->process_bulk_upload();
    ?><div class="wrap"><div id="icon-item-add-bulk" class="icon32"><br />
      </div><h2><?php _e('Add Items to the collection in bulk','weblibrarian'); ?></h2>
      <?php if ($message != '') {
	?><div id="message" class="update fade"><?php echo $message; ?></div><?php
        } ?>
      <form method="post" action=""  enctype="multipart/form-data" >
      <input type="hidden" name="page" value="weblib-add-item-collection-bulk" />
      <?php $this->display_bulk_upload_form(
			add_query_arg(
				array('page' => 'weblib-collection-database'))); 
	?></form></div><?php
  }
  function process_bulk_upload() {
    //file_put_contents("php://stderr","*** WEBLIB_Collection_Admin::process_bulk_upload: _REQUEST is ".print_r($_REQUEST,true)."\n");
    $this->check_permissions();
    if (!isset($_REQUEST['doupload']) ) return '';
    $filename = $_FILES['file_name']['tmp_name'];
    $use_csv_headers = sanitize_text_field($_REQUEST['use_csv_header']);
    $field_sep = stripslashes(sanitize_text_field($_REQUEST['field_sep']));
    $enclose_char = stripslashes(sanitize_text_field($_REQUEST['enclose_char']));
    /*$escape_char = stripslashes($_REQUEST['escape_char']);*/
    $result = WEBLIB_ItemInCollection::upload_csv($filename,$use_csv_headers,
						  $field_sep,$enclose_char
						  /*,$escape_char*/);
    return $result;
  }
  function display_bulk_upload_form($returnURL) {
    if ( isset($_REQUEST['paged']) ) {
      ?><input type="hidden" name="paged" value="<?php echo esc_attr(sanitize_text_field($_REQUEST['paged'])) ?>" /><?php
    }
    if ( isset($_REQUEST['screen-options-apply']) ) {
      ?><input type="hidden" name="screen-options-apply" value="<?php echo esc_attr(sanitize_text_field($_REQUEST['screen-options-apply'])) ?>" /><?php
    }
    if ( isset($_REQUEST['wp_screen_options']['option']) ) {
      ?><input type="hidden" name="wp_screen_options[option]" value="<?php echo esc_attr(sanitize_text_field($_REQUEST['wp_screen_options']['option'])) ?>" /><?php
    }
    if ( isset($_REQUEST['wp_screen_options']['value']) ) {
      ?><input type="hidden" name="wp_screen_options[value]" value="<?php echo esc_attr(sanitize_text_field($_REQUEST['wp_screen_options']['value'])) ?>" /><?php
    }
    ?><p><label for="file_name"><?php _e('CSV File:','weblibrarian'); ?></label>
	 <input type="file" id="file_name" name="file_name" 
		value="<?php echo esc_attr(sanitize_text_field($_REQUEST['file_name'])); ?>" /></p>
      <p><label for="use_csv_header"><?php _e('Use CSV Header?','weblibrarian'); ?></label>
	 <input type="checkbox" name="use_csv_header" id="use_csv_header" 
		value="1"<?php 
                if ($_REQUEST['use_csv_header']) echo ' checked="checked"'; ?> /></p>
      <p><label for="field_sep"><?php _e('Field Separater Character:','weblibrarian'); ?></label>
	 <select id="field_sep" name="field_sep">
	 <option value="," <?php if (!isset($_REQUEST['field_sep']) ||
				     sanitize_text_field($_REQUEST['field_sep']) == ',') {
				   echo 'selected="selected"'; 
				 } ?>>,</option>
	 <option value="<?php echo "\t"; ?>" <?php 
		if (isset($_REQUEST['field_sep']) && 
		    sanitize_text_field($_REQUEST['field_sep']) == "\t") {
		  echo 'selected="selected"'; 
		} ?>><?php _e('TAB','weblibrarian'); ?></option>
	 </select></p>
      <p><label for="enclose_char"><?php _e('Enclosure Character:','weblibrarian'); ?></label>
	 <select id="enclose_char" name="enclose_char">
	 <option value='<?php echo '"'; ?>' <?php
		if (!isset($_REQUEST['enclose_char']) ||
		    sanitize_text_field($_REQUEST['enclose_char']) == '"') {
		  echo 'selected="selected"'; 
		} ?>>&quot;</option>
	 <option value="'" <?php
		if (isset($_REQUEST['enclose_char']) &&
		    sanitize_text_field($_REQUEST['enclose_char']) == "'") {
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
  /* DB Maintenance page */
  function db_maintance() {
    //must check that the user has the required capability
    if (!current_user_can('manage_collection'))
    {
	wp_die( __('You do not have sufficient permissions to access this page.','weblibrarian' ));
    }
    if (isset($_REQUEST['deleteorphans'])) {
      $orphancheckouts = WEBLIB_OutItem::RemoveOrphanCheckouts();
      $orphanholds     = WEBLIB_HoldItem::RemoveOrphanHolds();
    } else {
      $orphancheckouts = array();
      $orphanholds     = array();
    }
    ?><div class="wrap"><div id="icon-weblib-db-maint" class="icon32"><br /></div><h2><?php _e('Database Maintenance','weblibrarian'); ?></h2>
      <?php
	if (count($orphancheckouts) > 0) { 
	  ?><table><thead><tr><th><?php _e('Transaction','weblibrarian'); 
		       ?></th><th><?php _e('Barcode','weblibrarian');
		       ?></th><th><?php _e('Patron ID','weblibrarian');
		       ?></th><th><?php _e('Title','weblibrarian');
		       ?></th><th><?php _e('Date Out','weblibrarian');
		       ?></th><th><?php _e('Due Data','weblibrarian'); 
		       ?></th></tr></thead><tfoot><tr><th><?php _e('Transaction','weblibrarian'); 
		       ?></th><th><?php _e('Barcode','weblibrarian');
		       ?></th><th><?php _e('Patron ID','weblibrarian');
		       ?></th><th><?php _e('Title','weblibrarian');
		       ?></th><th><?php _e('Date Out','weblibrarian');
		       ?></th><th><?php _e('Due Date','weblibrarian'); 
		       ?></th></tr></tfoot><tbody><?php
	  foreach ($orphancheckouts as $ocheck) {
	    ?><tr><td><?php echo $ocheck->transaction(); 
	    ?></td><td><?php echo $ocheck->barcode();
	    ?></td><td><?php echo $ocheck->patronid();
	    ?></td><td><?php echo stripslashes($ocheck->title());
	    ?></td><td><?php echo mysql2date('M/j/Y',$ocheck->dateout());
	    ?></td><td><?php echo mysql2date('M/j/Y',$ocheck->duedate());
	    ?></td></tr><?php
	  }
	  ?></tbody></table><?php
	}
	if (count($orphanholds) > 0) {
	  ?><table><thead><tr><th><?php _e('Transaction','weblibrarian');
		       ?></th><th><?php _e('Barcode','weblibrarian');
		       ?></th><th><?php _e('Patron ID','weblibrarian');
		       ?></th><th><?php _e('Title','weblibrarian');
		       ?></th><th><?php _e('Hold Date','weblibrarian');
		       ?></th><th><?php _e('Expiration Date','weblibrarian');
		       ?></th></tr></thead><tfoot><tr><th><?php _e('Transaction','weblibrarian');
		       ?></th><th><?php _e('Barcode','weblibrarian');
		       ?></th><th><?php _e('Patron ID','weblibrarian');
		       ?></th><th><?php _e('Title','weblibrarian');
		       ?></th><th><?php _e('Hold Date','weblibrarian');
		       ?></th><th><?php _e('Expiration Date','weblibrarian');
		       ?></th></tr></tfoot><tbody><?php
	  foreach ($orphanholds as $ohold) {
	    ?><tr><td><?php echo $ohold->transaction();
	    ?></td><td><?php echo $ohold->barcode();
	    ?></td><td><?php echo $ohold->patronid();
	    ?></td><td><?php echo stripslashes($ohold->title());
	    ?></td><td><?php echo mysql2date('M/j/Y',$ohold->dateheld());
	    ?></td><td><?php echo mysql2date('M/j/Y',$ohold->dateexpire());
	    ?></td></tr><?php
	  }
	  ?></tbody></table><?php
	}
      ?>
      <form method="get" action="<?php echo admin_url('admin.php'); ?>">
	<input type="hidden" name="page" value="weblib-collection-maintance" />
	<p>
	    <input type="submit" name="deleteorphans" class="button-primary"
		   value="<?php _e('Delete orphan holds and checkouts','weblibrarian'); ?>" />
	</p></form></div><?php
  }
}


