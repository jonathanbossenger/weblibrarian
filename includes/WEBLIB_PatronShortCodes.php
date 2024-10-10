<?php
/* -*- php -*- ****************************************************************
 *
 *  System        : 
 *  Module        : 
 *  Object Name   : $RCSfile$
 *  Revision      : $Revision$
 *  Date          : $Date$
 *  Author        : $Author$
 *  Created By    : Robert Heller
 *  Created       : Sat Jul 9 14:37:01 2016
 *  Last Modified : <220710.0830>
 *
 *  Description	
 *
 *  Notes
 *
 *  History
 *	
 ****************************************************************************
 *
 *    Copyright (C) 2016  Robert Heller D/B/A Deepwoods Software
 *			51 Locke Hill Road
 *			Wendell, MA 01379-9728
 *
 *    This program is free software; you can redistribute it and/or modify
 *    it under the terms of the GNU General Public License as published by
 *    the Free Software Foundation; either version 2 of the License, or
 *    (at your option) any later version.
 *
 *    This program is distributed in the hope that it will be useful,
 *    but WITHOUT ANY WARRANTY; without even the implied warranty of
 *    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *    GNU General Public License for more details.
 *
 *    You should have received a copy of the GNU General Public License
 *    along with this program; if not, write to the Free Software
 *    Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
 *
 * 
 *
 ****************************************************************************/


class WEBLIB_PatronRecord_CommonFront_Table {
  public $patronid;
  public $items;
  private $_actions;
  protected $_pagination_args = array();
  private $_pagination;
  protected $_column_headers;
  protected $_args;
  function __construct($args) {
    $args = wp_parse_args( $args, 
                          array('plural' =>  __('Item','weblibrarian'),
                                'singular' => __('Items','weblibrarian'),
                                'per_page_option' => 'weblib_patron_items_per_page',
                                'default_per_page' => 20,
                                ));
    //file_put_contents("php://stderr","*** WEBLIB_PatronRecord_CommonFront_Table::__construct: args = ".print_r($args,true)."\n");
    $args['plural'] = sanitize_key( $args['plural'] );
    $args['singular'] = sanitize_key( $args['singular'] );
    $this->_args = $args;
    //file_put_contents("php://stderr","*** WEBLIB_PatronRecord_CommonFront_Table::__construct: this->_args = ".print_r($this->_args,true)."\n");
  }
  function check_permissions() {
    if (!is_user_logged_in()) {
      return __("You must be logged in.",'weblibrarian');
    }
    $this->patronid = get_user_meta(wp_get_current_user()->ID,'PatronID',true);
    if ($this->patronid == '' || !WEBLIB_Patron::ValidPatronID($this->patronid)) {
      return __('You do not have a patron ID yet.','weblibrarian');
    } else {
      return "";
    }
  }
  public function prepare_items() {
    die( 'function WEBLIB_PatronRecord_CommonFront_Table::prepare_items() must be over-ridden in a sub-class.');
  }
  protected function set_pagination_args( $args ) {
    //file_put_contents("php://stderr","*** WEBLIB_PatronRecord_CommonFront_Table::set_pagination_args(".print_r($args,true).")\n");
    $args = wp_parse_args( $args, array(
                                        'total_items' => 0,
                                        'total_pages' => 0,
                                        'per_page' => 0,
                                        ) );
    
    //file_put_contents("php://stderr","*** WEBLIB_PatronRecord_CommonFront_Table::set_pagination_args: args = ".print_r($args,true)."\n");
    if ( !$args['total_pages'] && $args['per_page'] > 0 )
      $args['total_pages'] = ceil( $args['total_items'] / $args['per_page'] );
    
    //file_put_contents("php://stderr","*** WEBLIB_PatronRecord_CommonFront_Table::set_pagination_args: args (after total_pages check) = ".print_r($args,true)."\n");
      
    // Redirect if page number is invalid and headers are not already sent.
    if ( ! headers_sent() && ( ! defined( 'DOING_AJAX' ) || ! DOING_AJAX ) && $args['total_pages'] > 0 && $this->get_pagenum() > $args['total_pages'] ) {
      wp_redirect( add_query_arg( 'paged', $args['total_pages'] ) );
      exit;
    }
    
    $this->_pagination_args = $args;
  }
  public function get_pagination_arg( $key ) {
    if ( 'page' === $key ) {
      return $this->get_pagenum();
    }
    
    if ( isset( $this->_pagination_args[$key] ) ) {
      return $this->_pagination_args[$key];
    }
  }
  public function has_items() {
    return !empty( $this->items );
  }
  public function no_items() {
    return __( 'No items found.' );
  }
  public function search_box( $text, $input_id ) {
    return "";
    if ( empty( $_REQUEST['s'] ) && !$this->has_items() ) return "";
    $result = "";
    $input_id = $input_id . '-search-input';

    if ( ! empty( $_REQUEST['orderby'] ) )
    $result .= '<input type="hidden" name="orderby" value="' . esc_attr( $_REQUEST['orderby'] ) . '" />';
    if ( ! empty( $_REQUEST['order'] ) )
    $result .= '<input type="hidden" name="order" value="' . esc_attr( $_REQUEST['order'] ) . '" />';
    if ( ! empty( $_REQUEST['post_mime_type'] ) )
    $result .= '<input type="hidden" name="post_mime_type" value="' . esc_attr( $_REQUEST['post_mime_type'] ) . '" />';
    if ( ! empty( $_REQUEST['detached'] ) )
    $result .= '<input type="hidden" name="detached" value="' . esc_attr( $_REQUEST['detached'] ) . '" />';
    $result .= '<p class="search-box">'."\n";
    $result .= '<label class="screen-reader-text" for="'.$input_id.'">'.$text.":</label>\n";
    $result .= '<input type="search" id="'.$input_id.'" name="s" value="'._admin_search_query().'" />'."\n";
    $result .= submit_button( $text, 'button', '', false, array('id' => 'search-submit') )."</p>\n";
    return $result;
  }
  protected function get_bulk_actions() {
    return array();
  }
  protected function bulk_actions( $which = '' ) {
    if ( is_null( $this->_actions ) ) {
      $no_new_actions = $this->_actions = $this->get_bulk_actions();
      $this->_actions = array_intersect_assoc( $this->_actions, $no_new_actions );
      $two = '';
    } else {
      $two = '2';
    }
    
    if ( empty( $this->_actions ) ) return;
    $result = "";
    $result .= '<label for="bulk-action-selector-' . esc_attr( $which ) . '" class="screen-reader-text">' . __( 'Select bulk action' ) . '</label>';
    $result .= '<select name="action' . $two . '" id="bulk-action-selector-' . esc_attr( $which ) . "\">\n";
    $result .= '<option value="-1">' . __( 'Bulk Actions' ) . "</option>\n";
    
    foreach ( $this->_actions as $name => $title ) {
      $class = 'edit' === $name ? ' class="hide-if-no-js"' : '';
      
      $result .= "\t" . '<option value="' . $name . '"' . $class . '>' . $title . "</option>\n";
    }
    
    $result .= "</select>\n";
    
    $result .= '<input type="submit" id="doaction'.$two.'" value="'.__( 'Apply' ).'" />';
    $result .= "\n";
    return $result;
  }
  public function current_action() {
    if ( isset( $_REQUEST['filter_action'] ) && ! empty( $_REQUEST['filter_action'] ) )
      return false;
    
    if ( isset( $_REQUEST['action'] ) && -1 != $_REQUEST['action'] )
      return $_REQUEST['action'];

    if ( isset( $_REQUEST['action2'] ) && -1 != $_REQUEST['action2'] )
      return $_REQUEST['action2'];
      
    return false;
  }
  public function get_pagenum() {
    $pagenum = isset( $_REQUEST['paged'] ) ? absint( $_REQUEST['paged'] ) : 0;
    
    if ( isset( $this->_pagination_args['total_pages'] ) && $pagenum > $this->_pagination_args['total_pages'] )
      $pagenum = $this->_pagination_args['total_pages'];

    return max( 1, $pagenum );
  }
  protected function get_items_per_page( ) {
    //file_put_contents("php://stderr","*** WEBLIB_PatronRecord_CommonFront_Table::get_items_per_page: _args = ".print_r($this->_args,true)."\n");
    $per_page = (int) get_user_option( $this->_args['per_page_option'] );
    //file_put_contents("php://stderr","*** WEBLIB_PatronRecord_CommonFront_Table::get_items_per_page: per_page = $per_page\n");
    if ( empty( $per_page ) || $per_page < 1 ) $per_page = $this->_args['default_per_page'];
    //file_put_contents("php://stderr","*** WEBLIB_PatronRecord_CommonFront_Table::get_items_per_page: per_page (after check) = $per_page\n");
    return $per_page;
  }
  protected function pagination( $which ) {
    if ( empty( $this->_pagination_args ) ) {
      return;
    }

    $total_items = $this->_pagination_args['total_items'];
    $total_pages = $this->_pagination_args['total_pages'];
    $infinite_scroll = false;
    if ( isset( $this->_pagination_args['infinite_scroll'] ) ) {
      $infinite_scroll = $this->_pagination_args['infinite_scroll'];
    }

    //if ( 'top' === $which && $total_pages > 1 ) {
    //  $this->screen->render_screen_reader_content( 'heading_pagination' );
    //}

    $output = '<span class="displaying-num">' . sprintf( _n( '%s item', '%s items', $total_items ), number_format_i18n( $total_items ) ) . '</span>';
    
    $current = $this->get_pagenum();
    
    $current_url = set_url_scheme( 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] );
    
    $current_url = remove_query_arg( array( 'hotkeys_highlight_last', 'hotkeys_highlight_first' ), $current_url );
    
    $page_links = array();
    
    $total_pages_before = '<span class="paging-input">';
    $total_pages_after  = '</span>';
    
    $disable_first = $disable_last = $disable_prev = $disable_next = false;
    
    if ( $current == 1 ) {
      $disable_first = true;
      $disable_prev = true;
    }
    if ( $current == 2 ) {
      $disable_first = true;
    }
    if ( $current == $total_pages ) {
      $disable_last = true;
      $disable_next = true;
    }
    if ( $current == $total_pages - 1 ) {
      $disable_last = true;
    }
    
    if ( $disable_first ) {
      $page_links[] = '<span class="weblib_tablenav-pages-navspan" aria-hidden="true">&laquo;</span>';
    } else {
      $page_links[] = sprintf( "<a class='first-page' href='%s'><span class='screen-reader-text'>%s</span><span aria-hidden='true'>%s</span></a>",
                              esc_url( remove_query_arg( 'paged', $current_url ) ),
                              __( 'First page' ),
                              '&laquo;'
                              );
    }
    
    if ( $disable_prev ) {
      $page_links[] = '<span class="weblib_tablenav-pages-navspan" aria-hidden="true">&lsaquo;</span>';
    } else {
      $page_links[] = sprintf( "<a class='prev-page' href='%s'><span class='screen-reader-text'>%s</span><span aria-hidden='true'>%s</span></a>",
                              esc_url( add_query_arg( 'paged', max( 1, $current-1 ), $current_url ) ),
                              __( 'Previous page' ),
                              '&lsaquo;'
                              );
    }
    
    if ( 'bottom' === $which ) {
      $html_current_page  = $current;
      $total_pages_before = '<span class="screen-reader-text">' . __( 'Current Page' ) . '</span><span id="table-paging" class="paging-input">';
    } else {
      $html_current_page = sprintf( "%s<input class='current-page' id='current-page-selector' type='text' name='paged' value='%s' size='%d' aria-describedby='table-paging' />",
                                   '<label for="current-page-selector" class="screen-reader-text">' . __( 'Current Page' ) . '</label>',
                                   $current,
                                   strlen( $total_pages )
                                   );
    }
    $html_total_pages = sprintf( "<span class='total-pages'>%s</span>", number_format_i18n( $total_pages ) );
    $page_links[] = $total_pages_before . sprintf( _x( '%1$s of %2$s', 'paging' ), $html_current_page, $html_total_pages ) . $total_pages_after;
    
    if ( $disable_next ) {
      $page_links[] = '<span class="weblib_tablenav-pages-navspan" aria-hidden="true">&rsaquo;</span>';
    } else {
      $page_links[] = sprintf( "<a class='next-page' href='%s'><span class='screen-reader-text'>%s</span><span aria-hidden='true'>%s</span></a>",
                              esc_url( add_query_arg( 'paged', min( $total_pages, $current+1 ), $current_url ) ),
                              __( 'Next page' ),
                              '&rsaquo;'
                              );
    }
    
    if ( $disable_last ) {
      $page_links[] = '<span class="weblib_tablenav-pages-navspan" aria-hidden="true">&raquo;</span>';
    } else {
      $page_links[] = sprintf( "<a class='last-page' href='%s'><span class='screen-reader-text'>%s</span><span aria-hidden='true'>%s</span></a>",
                              esc_url( add_query_arg( 'paged', $total_pages, $current_url ) ),
                              __( 'Last page' ),
                              '&raquo;'
                              );
    }
    
    $pagination_links_class = 'pagination-links';
    if ( ! empty( $infinite_scroll ) ) {
      $pagination_links_class = ' hide-if-js';
    }
    $output .= "\n<span class='$pagination_links_class'>" . join( "\n", $page_links ) . '</span>';
    
    if ( $total_pages ) {
      $page_class = $total_pages < 2 ? ' one-page' : '';
    } else {
      $page_class = ' no-pages';
    }
    $this->_pagination = "<div class='weblib_tablenav-pages{$page_class}'>$output</div>";
    
    return $this->_pagination;
  }
  //public function get_columns() {
  //  die( 'function WEBLIB_PatronRecord_CommonFront_Table::get_columns() must be over-ridden in a sub-class.' );
  //}
  protected function get_default_primary_column_name() {
    $columns = $this->get_columns();
    $column = '';

    if ( empty( $columns ) ) {
      return $column;
    }

    // We need a primary defined so responsive views show something,
    // so let's fall back to the first non-checkbox column.
    foreach ( $columns as $col => $column_name ) {
      if ( 'cb' === $col ) {
        continue;
      }
      
      $column = $col;
      break;
    }
    
    return $column;
  }
  //public function get_primary_column() {
  //  return $this->get_primary_column_name();
  //}
  //protected function get_primary_column_name() {
  //  $columns = get_column_headers( $this->screen );
  //  $column = $this->get_default_primary_column_name();
  //  
  //  // If the primary column doesn't exist fall back to the
  //  // first non-checkbox column.
  //  if ( ! isset( $columns[ $column ] ) ) {
  //    $column = WEBLIB_PatronRecord_CommonFront_Table::get_default_primary_column_name();
  //  }
  //
  //  return $column;
  //}
  function get_column_info() {
    if ( isset( $this->_column_headers ) ) return $this->_column_headers;

    $columns = $this->get_columns( );
    $hidden = array();
    $sortable = $this->get_sortable_columns( );

    $this->_column_headers = array( $columns, $hidden, $sortable );

    return $this->_column_headers;
  }

  public function get_column_count() {
    list ( $columns, $hidden ) = $this->get_column_info();
    $hidden = array_intersect( array_keys( $columns ), array_filter( $hidden ) );
    return count( $columns ) - count( $hidden );
  }
  public function print_column_headers( $with_id = true ) {
    list( $columns, $hidden, $sortable/*, $primary*/ ) = $this->get_column_info();
    
    $current_url = set_url_scheme( 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] );
    $current_url = remove_query_arg( 'paged', $current_url );

    if ( isset( $_GET['orderby'] ) ) {
      $current_orderby = $_GET['orderby'];
    } else {
      $current_orderby = '';
    }

    if ( isset( $_GET['order'] ) && 'desc' === $_GET['order'] ) {
      $current_order = 'desc';
    } else {
      $current_order = 'asc';
    }

    if ( ! empty( $columns['cb'] ) ) {
      static $cb_counter = 1;
      $columns['cb'] = '<label class="screen-reader-text" for="cb-select-all-' . $cb_counter . '">' . __( 'Select All' ) . '</label>'
      . '<input id="cb-select-all-' . $cb_counter . '" type="checkbox" />';
      $cb_counter++;
    }
    $result = "";
    foreach ( $columns as $column_key => $column_display_name ) {
      $class = array( 'manage-column', "column-$column_key" );
      
      if ( in_array( $column_key, $hidden ) ) {
        $class[] = 'hidden';
      }
      
      if ( 'cb' === $column_key )
      $class[] = 'check-column';
      elseif ( in_array( $column_key, array( 'posts', 'comments', 'links' ) ) )
      $class[] = 'num';
      
      //if ( $column_key === $primary ) {
      //  $class[] = 'column-primary';
      //}
      
      if ( isset( $sortable[$column_key] ) ) {
        list( $orderby, $desc_first ) = $sortable[$column_key];
        
        if ( $current_orderby === $orderby ) {
          $order = 'asc' === $current_order ? 'desc' : 'asc';
          $class[] = 'sorted';
          $class[] = $current_order;
        } else {
          $order = $desc_first ? 'desc' : 'asc';
          $class[] = 'sortable';
          $class[] = $desc_first ? 'asc' : 'desc';
        }
        
        $column_display_name = '<a href="' . esc_url( add_query_arg( compact( 'orderby', 'order' ), $current_url ) ) . '"><span>' . $column_display_name . '</span><span class="sorting-indicator"></span></a>';
      }
      
      $tag = ( 'cb' === $column_key ) ? 'td' : 'th';
      $scope = ( 'th' === $tag ) ? 'scope="col"' : '';
      $id = $with_id ? "id='$column_key'" : '';
      
      if ( !empty( $class ) )
      $class = "class='" . join( ' ', $class ) . "'";
      
      $result .= "<$tag $scope $id $class>$column_display_name</$tag>";
    }
    return $result;
  }
  public function display() {
    $singular = $this->_args['singular'];
    
    $result = "";
    
    $result .= $this->display_tablenav( 'top' );
    
    $result .= '<table class="weblib-list-table '.implode( ' ', $this->get_table_classes() ).'">'."\n";
    $result .= "  <thead>\n";
    $result .= "    <tr>\n";
    $result .= $this->print_column_headers()."\n";
    $result .= "    </tr>\n";
    $result .= "  </thead>\n";
    $result .= "\n";
    $result .= '  <tbody id="the-list"';
    if ( $singular ) {
      $result .= " data-wp-lists='list:$singular'";
    } 
    $result .= ">\n";
    $result .= $this->display_rows_or_placeholder()."\n";
    $result .= "  </tbody>\n";
    $result .= "\n";
    $result .= "  <tfoot>\n";
    $result .= "    <tr>\n";
    $result .= $this->print_column_headers( false )."\n";
    $result .= "    </tr>\n";
    $result .= "  </tfoot>\n";
    $result .= "\n";
    $result .= "</table>\n";
    $result .= $this->display_tablenav( 'bottom' )."\n";
    return $result;
  }
  protected function get_table_classes() {
    return array( 'widefat', 'fixed', 'striped', $this->_args['plural'] );
  }

  protected function display_tablenav( $which ) {
    //if ( 'top' === $which ) {
    //  wp_nonce_field( 'bulk-' . $this->_args['plural'] );
    //}
    $result = "";
    
    $result .= '	<div class="weblib_tablenav '.esc_attr( $which ).'">'."\n";
    $result .= "\n";
    if ( $this->has_items() ) {
      $result .= '		<div class="alignleft actions bulkactions">'."\n";
      $result .= $this->bulk_actions( $which )."\n";
      $result .= '		</div>'."\n";
    }
    $result .= $this->extra_tablenav( $which )."\n";
    $result .= $this->pagination( $which )."\n";
    $result .= "\n";
    $result .= '		<br class="clear" />'."\n";
    $result .= '      	</div>'."\n";
    return $result;
  }
  //protected function extra_tablenav( $which ) {return "";}
  public function display_rows_or_placeholder() {
    if ( $this->has_items() ) {
      return $this->display_rows();
    } else {
      $result = "";
      $result .= '<tr class="no-items"><td class="colspanchange" colspan="' . $this->get_column_count() . '">'."\n";
      $result .= $this->no_items()."\n";
      $result .= '</td></tr>'."\n";
      return $result;
    }
  }
  public function display_rows() {
    $result = "";
    foreach ( $this->items as $item ) {
      $result .= $this->single_row( $item );
    }
    return $result;
  }
  public function single_row( $item ) {
    $result = "";
    $result .= '<tr>';
    $result .= $this->single_row_columns( $item );
    $result .= "</tr>\n";
    return $result;
  }
  //function column_default( $item, $column_name ) {return "";}
  //function column_cb( $item ) {return "";}
  function single_row_columns( $item ) {
    list( $columns, $hidden, $sortable, $primary ) = $this->get_column_info();
    $result = "";
                
    foreach ( $columns as $column_name => $column_display_name ) {
      $classes = "$column_name column-$column_name";
      if ( $primary === $column_name ) {
        $classes .= ' has-row-actions column-primary';
      }
      
      if ( in_array( $column_name, $hidden ) ) {
        $classes .= ' hidden';
      }
      
      // Comments column uses HTML in the display name with screen reader text.
      // Instead of using esc_attr(), we strip tags to get closer to a user-friendly string.
      $data = 'data-colname="' . wp_strip_all_tags( $column_display_name ) . '"';
      
      $attributes = "class='$classes' $data";
      
      if ( 'cb' === $column_name ) {
        $result .=  '<th scope="row" class="check-column">';
        $result .=  $this->column_cb( $item );
        $result .=  '</th>';
      } elseif ( method_exists( $this, '_column_' . $column_name ) ) {
        $result .=  call_user_func(
                                   array( $this, '_column_' . $column_name ),
                                   $item,
                                   $classes,
                                   $data,
                                   $primary
                                   );
      } elseif ( method_exists( $this, 'column_' . $column_name ) ) {
        $result .=  "<td $attributes>";
        $result .=  call_user_func( array( $this, 'column_' . $column_name ), $item );
        $result .=  "</td>";
      } else {
        $result .=  "<td $attributes>";
        $result .=  $this->column_default( $item, $column_name );
        $result .=  "</td>";
      }
    }
    return $result;
  }

  function get_sortable_columns() {
	return array('barcode' => array('barcode',false), 
		     'title' => array('title',false), 
		     'author' => array('author',false));
  }  

  function column_barcode ($item) {
    return $item;
  }

  function column_title ($item) {
    $theitem = new WEBLIB_ItemInCollection($item);
    return $theitem->title();
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
    //return apply_filters( 'manage_items_custom_column','',$column_name,$item['patronid']);
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
    return '<div id="ajax-message"></div>';
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
  
class WEBLIB_PatronHoldRecord_Front extends WEBLIB_PatronRecord_CommonFront_Table {
  function __construct() {
    parent::__construct(array());
    add_shortcode('weblib_editpatronholds', array($this,'editpatronholds') );
  }
  

  function editpatronholds ($atts, $content=null, $code="") {
    $message = $this->check_permissions();
    if ($message != "") {
      return '<p><span id="error">'.$message.'</span></p>';
    }
    $message = $this->prepare_items();
    $result = "";
    $result .= "<h2>Your Items on Hold</h2>\n";
    if ($message != '') {
      $result .= '<div id="message" class="update fade">'.$message."</div>\n";
    }
    $result .= '<form method="post" action="">'."\n";
    $result .=  $this->display();
    $result .= "</form>\n";
    return $result;
  }
  function get_per_page() {
    return $this->get_items_per_page();
  }

  function get_bulk_actions() {
    return array ('removehold' => __('Release Selected Holds','weblibrarian') );
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
      case 'removehold':
	if ( isset($_REQUEST['checked']) && !empty($_REQUEST['checked'])) {
	  foreach ( $_REQUEST['checked'] as $theitem ) {
	    WEBLIB_HoldItem::DeleteHeldItemByBarcodeAndPatronId($theitem,
							$this->patronid);
	  }
        } else if ( isset($_REQUEST['barcode']) ) {
	  WEBLIB_HoldItem::DeleteHeldItemByBarcodeAndPatronId(
			$_REQUEST['barcode'],$this->patronid);
	}
	break;
    }
  }

  function prepare_items() {
    
    $message = '';
    $this->process_bulk_action();
    $orderby = isset( $_REQUEST['orderby'] ) ? $_REQUEST['orderby'] : 'barcode';
    if ( empty( $orderby ) ) $orderby = 'barcode';
    $order = isset( $_REQUEST['order'] ) ? $_REQUEST['order'] : 'ASC';
    if ( empty( $order ) ) $order = 'ASC';
    // Deal with columns
    $columns = $this->get_columns();    // All of our columns
    $hidden  = array();         // Hidden columns [none]
    $sortable = $this->get_sortable_columns(); // Sortable columns
    $this->_column_headers = array($columns,$hidden,$sortable); // Set up columns


    $per_page = $this->get_per_page();
    //file_put_contents("php://stderr","*** WEBLIB_PatronHoldRecord_Front::prepare_items: per_page = '$per_page'\n");
    //file_put_contents("php://stderr","*** WEBLIB_PatronHoldRecord_Front::prepare_items: this->patronid = ".$this->patronid."\n");
    $helditems = WEBLIB_HoldItem::HeldItemsOfPatron($this->patronid);
    //file_put_contents("php://stderr","*** WEBLIB_PatronHoldRecord_Front::prepare_items: helditems = ".print_r($helditems,true)."\n");
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

class WEBLIB_PatronOutRecord_Front extends WEBLIB_PatronRecord_CommonFront_Table {
  function __construct() {
    parent::__construct(array());
    add_shortcode('weblib_editpatroncircs', array($this,'editpatroncircs') );
  }
  function editpatroncircs ($atts, $content=null, $code="") {
    $message = $this->check_permissions();
    if ($message != "") {
      return '<p><span id="error">'.$message.'</span></p>';
    }
    $message = $this->prepare_items();
    $result = "";
    $result .= "<h2>".__('Your Checked out Items','weblibrarian')."</h2>\n";
    if ($message != '') {
      $result .= '<div id="message" class="update fade">'.$message."</div>\n";
    }
    $result .= '<form method="post" action="">'."\n";
    $result .=  $this->display();
    $result .= "</form>\n";
    return $result;
  }
  function get_per_page() {
    return $this->get_items_per_page();
  }

  function get_bulk_actions() {
    return array ('renew' => __('Renew Selected Items','weblibrarian') );
  }
  function current_action() {
    if ( isset( $_REQUEST['action'] ) && -1 != $_REQUEST['action'] )
      return $_REQUEST['action'];

    if ( isset( $_REQUEST['action2'] ) && -1 != $_REQUEST['action2'] )
      return $_REQUEST['action2'];
  }

  function process_bulk_action() {
    $message = '';
    //file_put_contents("php://stderr","*** WEBLIB_PatronOutRecord_Front::process_bulk_action(): _REQUEST = ".print_r($_REQUEST,true));
    $action = $this->current_action();
    //file_put_contents("php://stderr","*** WEBLIB_PatronOutRecord_Front::process_bulk_action(): action = $action\n");
    switch ($action) {
      case 'renew':
	if ( isset($_REQUEST['barcode']) ) {
	  $m = WEBLIB_OutItem::RenewByBarcodeAndPatronID(
				$_REQUEST['barcode'],$this->patronid);
	  if (preg_match('/ Renewed\.$/',$m)) {
	    $message .= '<p>'.$m.'</p>';
	  } else {
	    $message .= '<p><span id="error">'.$m.'</span></p>';
	  }
	} else {
	  foreach ( $_REQUEST['checked'] as $barcode ) {
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
    $orderby = isset( $_REQUEST['orderby'] ) ? $_REQUEST['orderby'] : 'barcode';
    if ( empty( $orderby ) ) $orderby = 'barcode';
    $order = isset( $_REQUEST['order'] ) ? $_REQUEST['order'] : 'ASC';
    if ( empty( $order ) ) $order = 'ASC';

    $per_page = $this->get_items_per_page();

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
    

/* Patron Short codes (front end) */ 

class WEBLIB_PatronShortCodes {
  private $editholds;
  private $editcircs;
  function __construct() {
    add_shortcode('weblib_editpatroninfo', array($this,'editpatroninfo') );
    $this->editholds = new WEBLIB_PatronHoldRecord_Front();
    $this->editcircs = new WEBLIB_PatronOutRecord_Front();
  }
  function editpatroninfo ($atts, $content=null, $code="") {
    if (!is_user_logged_in()) {
      return '<p><span id="error">'.__("You must be logged in.",'weblibrarian').'</span></p>';
    }
    $error = '';
    $message = '';
    $patron = WEBLIB_Patron::PatronFromCurrentUser($error);
    if ($patron != null) {
      if (isset($_REQUEST['updatepatron'])) {
        $message = $this->checkiteminform();
        $this->getitemfromform($patron);
        if ($message == '') {
          $patron->store();
          $message = '<p>'.sprintf(__('%s, %s %s updated.','weblibrarian'),
                                   $patron->lastname(),$patron->firstname(),
                                   $patron->extraname()).'</p>';
        }
      }
      return $this->PatronEditForm($patron,$message);
    } else {
      if (isset($_REQUEST['patronid']) ) {
        $patronid = $_REQUEST['patronid'];
        $patron = new WEBLIB_Patron($patronid);
        $error = '';
        if (! $patron->StorePatronIDWithCurrentUser($error) ) {
          $message = '<p><span id="error">'.$error.'</span></p>';
        } else {
          $message = '<p>'.__('Your Patron ID has been set. Thank you.','weblibrarian').'</p>';
          return $this->PatronForm($patron,$message);
        }
      }
      return $this->PatronSetIDForm($message);
    }
  }
  function PatronEditForm($patron,$message) {
    $result = '<div class="weblib-editpatron">';
    if ($message != '') {
      $result .= '<div id="message" class="update fade">'.$message.'</div>';
    }
    $result .= '<form name="editpatroninfo" method="POST" action="">';
    $result .= '<table class="form-table">'."\n";
    $result .= '  <tr valign="top">'."\n";
    $result .= '    <th scope="row"><label for="telephone" style="width:20%;">'.__('Telephone:','weblibrarian')."</label></th>\n";
    $result .= '    <td><input id="telephone" name="telephone" style="width:75%;" maxlength="20" value="'.WEBLIB_Patrons_Admin::addtelephonedashes($patron->telephone()).'" /></td></tr>'."\n";
    $result .= '  <tr valign="top">'."\n";
    $result .= '    <th scope="row"><label for="lastname" style="width:20%;">'.__('Last Name:','weblibrarian')."</label></th>\n";
    $result .= '    <td><input id="lastname" name="lastname" style="width:75%;" maxlength="32" value="'.stripslashes($patron->lastname()).'" /></td></tr>'."\n";
    $result .= '  <tr valign="top">'."\n";
    $result .= '    <th scope="row"><label for="firstname" style="width:20%;">'.__('First Name:','weblibrarian')."</label></th>\n";
    $result .= '    <td><input id="firstname" name="firstname" style="width:75%;" maxlength="32" value="'.stripslashes($patron->firstname()).'" /></td></tr>'."\n";
    $result .= '  <tr valign="top">'."\n";
    $result .= '    <th scope="row"><label for="extraname" style="width:20%;">'.__('Extra Name:','weblibrarian')."</label></th>\n";
    $result .= '    <td><input id="extraname" name="extraname" style="width:75%;" maxlength="32" value="'.stripslashes($patron->extraname()).'" /></td></tr>'."\n";
    $result .= '  <tr valign="top">'."\n";
    $result .= '    <th scope="row"><label for="address1" style="width:20%;">'.__('Address 1:','weblibrarian')."</label></th>\n";
    $result .= '    <td><input id="address1" name="address1" style="width:75%;" maxlength="32"  value="'.stripslashes($patron->address1()).'" /></td></tr>'."\n";
    $result .= '  <tr valign="top">'."\n";
    $result .= '    <th scope="row"><label for="address2" style="width:20%;">'.__('Address 2:','weblibrarian')."</label></th>\n";
    $result .= '    <td><input id="address2" name="address2" style="width:75%;" maxlength="32" value="'.stripslashes($patron->address2()).'" /></td></tr>'."\n";
    $result .= '  <tr valign="top">'."\n";
    $result .= '    <th scope="row"><label for="city" style="width:20%;">'.__('City:','weblibrarian')."</label></th>\n";
    $result .= '    <td><input id="city" name="city" style="width:75%;" maxlength="32" value="'.stripslashes($patron->city()).'" /></td></tr>'."\n";
    $result .= '  <tr valign="top">'."\n";
    $result .= '    <th scope="row"><label for="state" style="width:20%;">'.__('State:','weblibrarian')."</label></th>\n";
    $result .= '    <td><input id="state" name="state" style="width:75%;" maxlength="2" value="'.stripslashes($patron->state()).'" /></td></tr>'."\n";
    $result .= '  <tr valign="top">'."\n";
    $result .= '    <th scope="row"><label for="zip" style="width:20%;">'.__('Zip:','weblibrarian')."</label></th>\n";
    $result .= '    <td><input id="zip" name="zip" style="width:75%;" maxlength="10" value="'.$patron->zip().'" /></td></tr>'."\n";
    $result .= '</table>'."\n";
    $result .= '<p><input type="submit" name="updatepatron" class="button-primary" value="'.__('Update Patron','weblibrarian').'" /></p>'."\n";
    $result .= '</form></div>';
    return $result;
  }
  function PatronSetIDForm($message) {
    $result = '<div class="weblib-editpatron">';
    if ($message != '') {
      $result .= '<div id="message" class="update fade">'.$message.'</div>';
    }
    $result .= '<form name="editpatroninfo" method="POST" action="">';
    $result .= WEBLIB_Patron::PatronIdDropdown($patronid,array('onlyunassoc' => true),false);
    $result .= '<input type="submit" value="'.__('Set Your Patron Id','weblibrarian').'" />';
    $result .= '</form></div>';
    return $result;
  }
  function checkiteminform() {
    $result = '';
    $newtelephone = WEBLIB_Patrons_Admin::striptelephonedashes($_REQUEST['telephone']);
    if (!preg_match('/^\d+$/',$newtelephone) && strlen($newtelephone) != 10) {
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
    $newstate = $_REQUEST['state'];
    if ($newstate == '' || strlen($newstate) != 2) {
      $result .= '<br /><span id="error">'.__('State is invalid','weblibrarian') . '</span>';
    }
    $newzip = $_REQUEST['zip'];
    if (!($newzip != '' && (strlen($newzip) == 5 || strlen($newzip) == 10) &&
        preg_match('/\d+(-\d+)?/',$newzip) )) {
      $result .= '<br /><span id="error">'.__('Zip is invalid','weblibrarian') . '</span>';
    }
  }
  function getitemfromform($patron) {
    $patron->set_telephone(WEBLIB_Patrons_Admin::striptelephonedashes($_REQUEST['telephone']));
    $patron->set_lastname($_REQUEST['lastname']);
    $patron->set_firstname($_REQUEST['firstname']);
    $patron->set_extraname($_REQUEST['extraname']);
    $patron->set_address1($_REQUEST['address1']);
    $patron->set_address2($_REQUEST['address2']);
    $patron->set_city($_REQUEST['city']);
    $patron->set_state($_REQUEST['state']);
    $patron->set_zip($_REQUEST['zip']);
  }
}

