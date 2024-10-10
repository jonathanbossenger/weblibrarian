<?php

require_once(dirname(__FILE__) . '/WEBLIB_Collection_Admin.php');




class WEBLIB_Circulation_Admin extends WEBLIB_Collection_Shared {

  var $mode = 'circulationdesk';
  var $checkinlist = array();
  var $barcode = '';
  var $patronid = 0;
  var $searchname = '';

  static $my_per_page = 'weblib_circulationdesk_per_page';


  function __construct($args = array()) {
    global $weblib_contextual_help;

    $screen_id =  add_menu_page(__('Circulation Desk','weblibrarian'),__('Circulation Desk','weblibrarian'),
				'manage_circulation','weblib-circulation-desk',
				array($this,'circulation_desk'),
			WEBLIB_IMAGEURL.'/Circulation_Menu.png');
    $args['screen'] = WP_Screen::get($screen_id);
    //file_put_contents("php://stderr","*** WEBLIB_Circulation_Admin::__construct: args\['screen'\] = ".print_r($args['screen'],true)."\n");
    $weblib_contextual_help->add_contextual_help($screen_id,'weblib-circulation-desk');
    add_action("load-$screen_id", array($this,'add_per_page_option'));
    parent::__construct($args);
  }

  function add_per_page_option() {
    $args['option'] = WEBLIB_Circulation_Admin::$my_per_page;
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

  function column_title ($item) {
    $theitem = new WEBLIB_ItemInCollection($item);
    //echo $theitem->title();
    //echo '<br />';
    if ($this->mode != 'checkinpage') {
      $actions = array(
	'select' => '<input class="button" type="button" value="'.__('Select','weblibrarian').'"
		onClick="document.location.href=\''. 
			add_query_arg( array('barcode' => $item,
					  'barcodelookup' => 'yes',
					  'page' => 'weblib-circulation-desk'),
				    admin_url('admin.php')).'\';" />');
    } else {
      $actions = array();
    }
    return $theitem->title().$this->row_actions($actions);
  }
  function column_status ($item) {
    //file_put_contents("php://stderr","*** WEBLIB_Circulation_Admin::column_status($item)\n");
    $outitem = WEBLIB_OutItem::OutItemByBarcode($item);
    $numberofholds = WEBLIB_HoldItem::HoldCountsOfBarcode($item);
    $status = '';
    $brattr = ' style="display:none;"';
    if ($outitem != null) {
      $status = 'Due ';
      $duedate = $outitem->datedue();
      if (mysql2date('U',$duedate) < time()) {
        $status .= '<span id="due-date-'.$item.'" class="overdue" >'.strftime('%x',mysql2date('U',$duedate)).'</span>';
      } else {
	$status .= '<span id="due-date-'.$item.'" >'.strftime('%x',mysql2date('U',$duedate)).'</span>';
      }
      $status .= '<br /><input class="button" type="button" value="'.__('Renew','weblibrarian').'" onClick="Renew('."'".$item."'".')" />';
      unset($outitem);
    } else {
      $status .= __('Check Shelves', 'weblibrarian');
    }
    $status .= '<br />';
    $status .= '<span id="hold-count-'.$item.'">';
    if ($numberofholds > 0) {
      if ($numberofholds > 1) {
        $status .= sprintf(__('%d Holds','weblibrarian'),$numberofholds);
      } else {
        $status .= sprintf(__('%d Hold','weblibrarian'),$numberofholds);
      }
      $brattr = '';
    }
    $status .= '</span>';
    $status .= '<br id="hold-br-'.$item.'" '.$brattr.' /><input class="button" type="button" value="'.__('Place Hold','weblibrarian').'" onClick="PlaceHold('."'".$item."');".'" />';
    return $status;
  }

  function column_patron($item) {
    //file_put_contents("php://stderr","*** WEBLIB_Circulation_Admin::column_patron($item)\n");
    $outitem = WEBLIB_OutItem::OutItemByBarcode($item);
    //file_put_contents("php://stderr","*** WEBLIB_Circulation_Admin::column_patron(): outitem = ".print_r($outitem,true)."\n");
    $numberofholds = WEBLIB_HoldItem::HoldCountsOfBarcode($item);
    //file_put_contents("php://stderr","*** WEBLIB_Circulation_Admin::column_patron(): numberofholds = $numberofholds\n");
    $patroninfo = '';
    if ($outitem != null) {
      $patronid = $outitem->patronid();
      $telephone = WEBLIB_Patron::TelephoneFromId($patronid);
      $userid = WEBLIB_Patron::UserIDFromPatronID($patronid);
      $email = get_userdata( $userid )->user_email;
      if ($email != '') {
        $patroninfo = '<a href="mailto:'.$email.'">'.WEBLIB_Patron::NameFromID($patronid).'</a>';
      } else {
        $patroninfo = WEBLIB_Patron::NameFromID($patronid);
      }
      $patroninfo .= '<br />'.WEBLIB_Patrons_Admin::addtelephonedashes($telephone);
      //file_put_contents("php://stderr","*** WEBLIB_Circulation_Admin::column_patron(): (outitem != null) patroninfo = $patroninfo\n");
    }
    if ($numberofholds > 0 && $patroninfo == '') {
      $holds = WEBLIB_HoldItem::HeldItemsByBarcode($item);
      $firsthold = new WEBLIB_HoldItem($holds[0]);
      $patronid = $firsthold->patronid();
      $telephone = WEBLIB_Patron::TelephoneFromId($patronid);
      $userid = WEBLIB_Patron::UserIDFromPatronID($patronid);
      $email = get_userdata( $userid )->user_email;
      if ($email != '') {
        $patroninfo = '<a href="mailto:'.$email.'">'.WEBLIB_Patron::NameFromID($patronid).'</a>';
      } else {
        $patroninfo = WEBLIB_Patron::NameFromID($patronid);
      }
      $patroninfo .= '<br />'.WEBLIB_Patrons_Admin::addtelephonedashes($telephone);
      $patroninfo .= '<br />Expires: '.strftime('%x',mysql2date('U',$firsthold->dateexpire()));
      //file_put_contents("php://stderr","*** WEBLIB_Circulation_Admin::column_patron(): (numberofholds > 0) patroninfo = $patroninfo\n");
    }
    //file_put_contents("php://stderr","*** WEBLIB_Circulation_Admin::column_patron(): (after both ifs) patroninfo = $patroninfo\n");    
    $result =  '<span id="patron-info-'.$item.'">'.$patroninfo.'</span>';
    //file_put_contents("php://stderr","*** WEBLIB_Circulation_Admin::column_patron(): result is $result\n");
    return $result;
  }
  
  public function single_row_columns( $item ) {
    $columns = $this->get_columns();
    $hidden  = get_hidden_columns( $this->screen );
    //file_put_contents("php://stderr","*** WEBLIB_Circulation_Admin::single_row_columns(): columns = ".print_r($columns,true)."\n");
    foreach ( $columns as $column_name => $column_display_name ) {
      $class = "class='$column_name column-$column_name'";
      
      $style = '';
      if ( in_array( $column_name, $hidden ) )
      $style = ' style="display:none;"';
      
      $attributes = "$class$style";
      
      if ( 'cb' == $column_name ) {
        echo '<th scope="row" class="check-column">';
        echo $this->column_cb( $item );
        echo '</th>';
      }
      elseif ( method_exists( $this, 'column_' . $column_name ) ) {
        echo "<td $attributes>";
        echo call_user_func( array( $this, 'column_' . $column_name ), $item );
        echo "</td>";
      }
      else {
        echo "<td $attributes>";
        echo $this->column_default( $item, $column_name );
        echo "</td>";
      }
    }
  }
  
  
  function get_columns() {
    if ($this->mode == 'patroncircrecord') {
      $temp = array('barcode' => __('Barcode','weblibrarian'),
		   'title' => __('Title','weblibrarian'),
		   'author' => __('Author','weblibrarian'),
		   'type' => __('Type','weblibrarian'),
		   'callnumber'  => __('Call Number','weblibrarian'),
		   'status' => __('Status','weblibrarian'));
    } else {
      $temp = array('barcode' => __('Barcode','weblibrarian'),
		   'title' => __('Title','weblibrarian'),
		   'author' => __('Author','weblibrarian'),
		   'type' => __('Type','weblibrarian'),
		   'callnumber'  => __('Call Number','weblibrarian'),
		   'status' => __('Status','weblibrarian'),
		   'patron' => __('Patron','weblibrarian'));
    }
    //file_put_contents("php://stderr","*** WEBLIB_Circulation_Admin::get_columns: ".print_r($temp,true)."\n");
    return $temp;
  }

  function extra_tablenav ( $which ) {
    if ('top' != $which) return;

    ?><input type="hidden" name="mode" value="<?php echo $this->mode; ?>" /><?php
    if ($this->mode == 'checkinpage') {
      foreach ($this->checkinlist as $index => $bc) {
	?><input type="hidden" name="checkinlist[<?php echo $index; ?>]" value="<?php echo stripslashes($bc); ?>" /><?php
      }
    }
    // barcode entry / dropdown + mode selection
    ?><div id="ajax-message"></div>
	<div class="circulation-desk"><div class="weblib-row"><div id="weblib-inputs"><div class="weblib-inputitem"><label for="barcode" class="inputlab"><?php _e('Scanned Barcode','weblibrarian'); ?></label><input id="barcode" name="barcode" value="<?php echo stripslashes($this->barcode); ?>" class="weblib-input-fill" /><?php
    switch ($this->mode) {
      case 'checkinpage':
	?><input class="button" type="submit" name="checkinitem" value="<?php _e('Check in Item','weblibrarian'); ?>" /><?php
	break;
      case 'patroncircrecord':
	?><input class="button" type="submit" name="checkoutitem" value="<?php _e('Checkout Item','weblibrarian'); ?>" /><?php
	break;
      default:
	?><input class="button" type="submit" name="barcodelookup" value="<?php _e('Lookup Barcode','weblibrarian'); ?>" /><?php
	break;
    }
    ?></div><?php
    if ($this->mode != 'checkinpage') {
	// patron search and dropdown
	?><div class="weblib-inputitem"><label for="searchname" class="inputlab"><?php _e('Find Patron:','weblibrarian'); ?></label>
	<input id="searchname" name="searchname" value="<?php 
		echo $this->searchname;
	?>" class="weblib-input-fill" /><input class="button" type="button" name="patronfind" value="<?php _e('Find Patron','weblibrarian'); ?>" onclick="FindPatron();" /></div>
	<div class="weblib-inputitem"><?php 
		WEBLIB_Patron::PatronIdDropdown(
			$this->patronid,
			array('selectclass' => 'weblib-input-fill',
			      'labelclass' => 'inputlab' ));
	?><input class="button" type="submit" name="patronlookup" value="<?php _e('Lookup Patron','weblibrarian'); ?>" /></div>
	<div class="weblib-inputitem" id="weblib-patronlist"></div>
	<?php
    }
    ?></div><div id="weblib-buttons"><?php
    if ($this->mode != 'checkinpage') {
      ?><div class="weblib-inputitem-button"><input class="button" type="submit" name="checkin" value="<?php _e('Check in','weblibrarian'); ?>" /></div>
        <div class="weblib-inputitem-button"><input class="button" type="submit" name="listholds" value="<?php _e('List Holds','weblibrarian'); ?>" /></div>
	<div class="weblib-inputitem-button"><input class="button" type="submit" name="listouts" value="<?php _e('List Checked Out Items','weblibrarian'); ?>" /></div><?php
    }      
    if ($this->mode != 'circulationdesk') {
      ?><div class="weblib-inputitem-button"><input class="button" type="submit" name="resetmode" value="<?php _e('Back to Main Circulation','weblibrarian'); ?>" /></div><?php
    }
    ?></div></div></div><br clear="all" /><?php
  }

	/**
	 * Generate the table navigation above or below the table
	 *
	 * @since 3.1.0
	 * @access protected
	 */
	function display_tablenav( $which ) {
		if ( 'top' == $which )
			wp_nonce_field( 'bulk-' . $this->_args['plural'], 
					"_wpnonce", false );
?>
	<div class="tablenav <?php echo esc_attr( $which ); 
		  if ( 'top' == $which ) echo ' '.esc_attr('weblib-circdesk'); 
		?>">

		<div class="alignleft actions">
			<?php $this->bulk_actions( $which ); ?>
		</div>
<?php
		$this->extra_tablenav( $which );
		$this->pagination( $which );
?>

		<br class="clear" />
	</div>
<?php
	}

  


  function search_box($text, $input_id) {
    /*if ( empty( $_REQUEST['s'] ) && !$this->has_items() ) return;*/

    $input_id = $input_id . '-search-input';

    if ( ! empty( $_REQUEST['orderby'] ) )
      echo '<input type="hidden" name="orderby" value="' . esc_attr( $_REQUEST['orderby'] ) . '" />';
    if ( ! empty( $_REQUEST['order'] ) )
      echo '<input type="hidden" name="order" value="' . esc_attr( $_REQUEST['order'] ) . '" />';
    $field = isset ($_REQUEST['f']) ? $_REQUEST['f'] : 'title';
?>
<p class="search-box">
	<label class="screen-reader-text" for="<?php echo $input_id; ?>"><?php echo $text; ?>:</label>
	<input type="text" id="<?php echo $input_id; ?>" name="s" value="<?php _admin_search_query(); ?>" />
	<select name="f">
	<?php foreach (array(__('Title','weblibrarian') => 'title',
			 __('Author','weblibrarian') => 'author',
			 __('Subject','weblibrarian') => 'subject',
			 __('ISBN','weblibrarian')  => 'isbn',
			 __('Keyword','weblibrarian') => 'keyword') as $l => $f) {
		?><option value="<?php echo $f; ?>"<?php
		  if ($f == $field) {echo ' selected="selected"';}
		?>><?php echo $l; ?></option>
		<?php
	      } ?>
	<?php submit_button( $text, 'button', false, false, array('id' => 'search-submit') ); ?>
</p>
<?php
  }
	function get_column_info() {
		/*if ( isset( $this->_column_headers ) )
			return $this->_column_headers;*/

		$columns = get_column_headers( $this->screen );
		$hidden = get_hidden_columns( $this->screen );

		$sortable_columns = $this->get_sortable_columns();
		/**
		 * Filter the list table sortable columns for a specific screen.
		 *
		 * The dynamic portion of the hook name, `$this->screen->id`, refers
		 * to the ID of the current screen, usually a string.
		 *
		 * @since 3.5.0
		 *
		 * @param array $sortable_columns An array of sortable columns.
		 */
		$_sortable = apply_filters( "manage_{$this->screen->id}_sortable_columns", $sortable_columns );

		$sortable = array();
		foreach ( $_sortable as $id => $data ) {
			if ( empty( $data ) )
				continue;

			$data = (array) $data;
			if ( !isset( $data[1] ) )
				$data[1] = false;

			$sortable[$id] = $data;
		}

		$this->_column_headers = array( $columns, $hidden, $sortable );
                return array( $columns, $hidden, $sortable );
		/*return $this->_column_headers;*/
	}

  function prepare_items() {
    //file_put_contents("php://stderr","*** WEBLIB_Circulation_Admin::prepare_items: _REQUEST = ".print_r($_REQUEST,true)."\n");
    $this->check_permissions();
    $message = '';

    // Deal with columns
    $columns = $this->get_columns();    // All of our columns
    $hidden  = array();         // Hidden columns [none]
    $sortable = $this->get_sortable_columns(); // Sortable columns
    $this->_column_headers = array($columns,$hidden,$sortable); // Set up columns

    // get: patron id, mode, current barcode etc.

    $this->mode = isset($_REQUEST['mode']) ? $_REQUEST['mode'] : 'circulationdesk';
    if (isset($_REQUEST['checkin'])) {
      $this->mode = 'checkinpage';
      unset($this->_column_headers);
    }
    if (isset($_REQUEST['listholds'])) {
      $this->mode = 'holdlist';
      unset($this->_column_headers);
    }
    if (isset($_REQUEST['listouts'])) {
      $this->mode = 'outlist';
      unset($this->_column_headers);
    }
    if (isset($_REQUEST['resetmode'])) {
      $this->mode = 'circulationdesk';
      unset($this->_column_headers);
    }
    $this->checkinlist = isset($_REQUEST['checkinlist']) ? $_REQUEST['checkinlist'] : array();
    $this->barcode = isset($_REQUEST['barcode']) ? $_REQUEST['barcode'] : '';
    $this->patronid = isset($_REQUEST['patronid']) ? $_REQUEST['patronid'] : 0;
    if (isset($_REQUEST['barcodelookup']) && $this->mode != 'checkinpage') {
      $this->mode = 'itemcircrecord';
      /*unset($this->_column_headers);
      $this->get_column_info();*/
    } else if (isset($_REQUEST['patronlookup']) && $this->mode != 'checkinpage') {
      $this->mode = 'patroncircrecord';
      /*unset($this->_column_headers);
      $this->get_column_info();*/
    } else if ($this->mode == 'patroncircrecord' && 
		isset($_REQUEST['checkoutitem'])) {
      $outitem = WEBLIB_OutItem::OutItemByBarcode($this->barcode);
      $checkouttrans = 0;
      if ($outitem != null) {
	$message .= '<p><span id="error">'.__('Item is already checked out!','weblibrarian').'</span></p>';
      } else {
	$holds = WEBLIB_HoldItem::HeldItemsByBarcode($this->barcode);
	$hasholds = (! empty($holds));
	if (! empty($holds) ) {
	  foreach ($holds as $trans) {
	    $hold = new WEBLIB_HoldItem($trans);
	    if ($hold->patronid() == $this->patronid) {
	      $type = new WEBLIB_Type($hold->type());
	      $duedate = date('Y-m-d',time() + ($type->loanperiod() * 24 * 60 * 60));
	      unset($type);
	      $checkouttrans = $hold->checkout($duedate);
	      $hasholds = false;
	      unset($hold);
	      $hasholds = false;
	      break;
	    }
	  }
	}
	if ($hasholds) {
	  $message .= '<p><span id="error">';
          $message .= __('Someone else has a hold on this item!','weblibrarian');
          $message .= '</span></p>';
	} else if ($checkouttrans == 0) {
	  if (WEBLIB_ItemInCollection::IsItemInCollection($this->barcode)) {
	    $item = new WEBLIB_ItemInCollection($this->barcode);
	    $type = new WEBLIB_Type($item->type());
	    $duedate = date('Y-m-d',time() + ($type->loanperiod() * 24 * 60 * 60));
	    unset($type);
	    $checkouttrans = $item->checkout($this->patronid, __('Local','weblibrarian'), $duedate);
	    unset($item);
	  } else {/* item not in collection */
	    $message .= '<p><span id="error">';
            $message .= __('Item is not in the collection!','weblibrarian');
            $message .= '</span></p>';
	  }
	}
	if ($checkouttrans > 0) {
	  $message .= '<p>';
          $message .= sprintf(__('Item checked out, transaction is %d, due: %s.',
                                 'weblibrarian'),
                                 $checkouttrans,
                                 strftime('%x',mysql2date('U',$duedate)));
          $message .= "</p>\n";
	} else {
	  $message .= '<p><span id="error">';
          $message .= sprintf(__('Error checking out!  Result code is %d.',
                                 'weblibrarian'),$checkouttrans);
          $message .= '</span></p>';
	}
      }
    } else if ($this->mode == 'checkinpage' && 
		isset( $_REQUEST['checkinitem'] ) &&
		$this->barcode != '') {
      $outitem = WEBLIB_OutItem::OutItemByBarcode($this->barcode);
      if ($outitem == null) {
	$message .= '<p><span id="error">'.__('Item not checked out!','weblibrarian').'</span></p>';
      } else {
	$outitem->checkin(0.10);
	$this->checkinlist[] = $this->barcode;
      }
    } else if ( ! empty($_REQUEST['s']) ) {
      $this->mode = 'circulationdesk';
    }
    $this->searchname = isset($_REQUEST['searchname']) ? $_REQUEST['searchname'] : '';

    //file_put_contents("php://stderr","*** WEBLIB_Circulation_Admin::prepare_items: this->mode = $this->mode\n");
    $search = isset( $_REQUEST['s'] ) ? $_REQUEST['s'] : '';
    $field  = isset( $_REQUEST['f'] ) ? $_REQUEST['f'] : 'title';
    $orderby = isset( $_REQUEST['orderby'] ) ? $_REQUEST['orderby'] : 'barcode';
    if ( empty( $orderby ) ) $orderby = 'barcode';
    $order = isset( $_REQUEST['order'] ) ? $_REQUEST['order'] : 'ASC';
    if ( empty( $order ) ) $order = 'ASC';

    $per_page = $this->get_per_page();
    
    // subset all_items -- by items checked out [by patron] / on hold [by patron]
    switch ($this->mode) {
      case 'checkinpage':
	$all_items = $this->checkinlist;
	$this->sort_items($all_items,$orderby,$order);
	break;
      case 'holdlist':
	$all_holds = WEBLIB_HoldItem::AllHeldItems();
	$all_items = array();
	foreach ($all_holds as $hold) {
	  $helditem = new WEBLIB_HoldItem($hold);
	  $all_items[] = $helditem->barcode();
	}
	$this->sort_items($all_items,$orderby,$order);
	break;
      case 'outlist':
	$all_outs = WEBLIB_OutItem::AllOutItems();
	$all_items = array();
	foreach ($all_outs as $out) {
	  $outitem = new WEBLIB_OutItem($out);
	  $all_items[] = $outitem->barcode();
	}
	$this->sort_items($all_items,$orderby,$order);
	break;
      case 'patroncircrecord':
	$outitems = WEBLIB_OutItem::OutItemsOfPatron($this->patronid);
	$helditems = WEBLIB_HoldItem::HeldItemsOfPatron($this->patronid);
	$all_items = array();
	foreach ($outitems as $transaction) {
	  $outitem = new WEBLIB_OutItem($transaction);
	  $all_items[] = $outitem->barcode();
	}
	foreach ($helditems as $transaction) {
	  $helditem = new WEBLIB_HoldItem($transaction);
	  $all_items[] = $helditem->barcode();
	}	
	$this->sort_items($all_items,$orderby,$order);
	break;
      case 'itemcircrecord':
	if ( isset($_REQUEST['barcodelookup']) && $this->barcode != '') {
	  
	  if (WEBLIB_ItemInCollection::IsItemInCollection($this->barcode)) {
	    $all_items = array($this->barcode);
	  } else {
	    $all_items = array();
	  }
	}
	break;
      case 'circulationdesk':
	//file_put_contents("php://stderr","*** WEBLIB_Circulation_Admin::prepare_items (circulationdesk): search = $search, field = $field\n");
	if ($search == '') {
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
	break;
      default:
	break;
    } 

    //file_put_contents("php://stderr","*** WEBLIB_Circulation_Admin::prepare_items (circulationdesk): ".print_r($all_items,true)."\n");
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


  function circulation_desk() {
    $message = $this->prepare_items();
    ?><div class="wrap"><div id="icon-circulation" class="icon32"><br /></div>
	<h2><?php
         switch ($this->mode) {
            case 'circulationdesk': _e( 'Library Circulation Desk','weblibrarian'); break;
	    case 'checkinpage': _e( 'Library Circulation Desk -- Check Items In','weblibrarian'); break;
	    case 'holdlist':    _e( 'Library Circulation Desk -- Items with Holds','weblibrarian'); break;
	    case 'outlist':     _e( 'Library Circulation Desk -- Items Checked out','weblibrarian'); break;
	    case 'patroncircrecord': echo sprintf(__("Library Circulation Desk -- %s's Circulation Record",'weblibrarian'),WEBLIB_Patron::NameFromId($this->patronid)); break;
	    case 'itemcircrecord': echo sprintf(__('Library Circulation Desk -- Circulation Record for %s','weblibrarian'),$this->barcode); break;
	    default: break;
	  }
	?></h2><?php
	if ($message != '') {
	  ?><div id="message" class="update fade"><?php echo $message; ?></div><?php
	}
	?><form method="post" action="<?php echo add_query_arg(array('page' => 'weblib-circulation-desk'),admin_url('admin.php')); ?>">
	<?php /* <input type="hidden" name="page" value="weblib-circulation-desk" /> */ ?>
	<?php if ($this->mode != 'checkinpage')
		$this->search_box(__( 'Search Collection','weblibrarian' ), 'collection' ); ?>
	<?php $this->display(); ?></form></div><?php
  }

  function check_permissions() {
    if (!current_user_can('manage_circulation')) {
      wp_die( __('You do not have sufficient permissions to access this page.','weblibrarian') );
    }
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

