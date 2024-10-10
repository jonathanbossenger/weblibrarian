<?php



/*************************** LOAD THE BASE CLASS *******************************
 *******************************************************************************
 * The WP_List_Table class isn't automatically available to plugins, so we need
 * to check if it's available and load it if necessary.
 */
if(!class_exists('WP_List_Table')){
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}




class WEBLIB_Statistics_Admin extends WP_List_Table {

  private static $MonthNames;

  private $mode = 'typecount';

  private $year;
  private $month;

  static $my_per_page = 'weblib_stats_per_page';

  function __construct() {
    if (! isset($MonthNames) ) {
      $MonthNames = array(__('Month Totals','weblibrarian'),
                          __('January','weblibrarian'),
                          __('February','weblibrarian'),
                          __('March','weblibrarian'),
                          __('April','weblibrarian'),
                          __('May','weblibrarian'),
                          __('June','weblibrarian'),
                          __('July','weblibrarian'),
                          __('August','weblibrarian'),
                          __('September','weblibrarian'),
                          __('October','weblibrarian'),
                          __('November','weblibrarian'),
                          __('December','weblibrarian')
                          );
    }
                          
    global $weblib_contextual_help;

    $screen_id =  add_menu_page(__('Circulation Statistics','weblibrarian'), __('Circulation Stats','weblibrarian'),
				'manage_circulation', 
				'weblib-circulation-statistics',
				array($this,'circulation_statistics'),
			WEBLIB_IMAGEURL.'/CircStats_Menu.png');
    $weblib_contextual_help->add_contextual_help($screen_id,'weblib-circulation-statistics');
    add_action("load-$screen_id", array($this,'add_per_page_option'));
    $screen_id =  add_submenu_page('weblib-circulation-statistics',
				   __('Export Circulation Stats','weblibrarian'), __('Export','weblibrarian'),
				   'manage_circulation',
				   'weblib-export-circulation-statistics',
				   array($this,
					 'export_circulation_statistics'));
    $weblib_contextual_help->add_contextual_help($screen_id,'weblib-export-circulation-statistics');

    $this->year = date('Y',time());
    $this->month = date('m',time());

    parent::__construct(array());
  }

  function add_per_page_option() {
    $args['option'] = WEBLIB_Statistics_Admin::$my_per_page;
    $args['label'] = __('Stats','weblibrarian');
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

  function column_label($item) {
    return $item->label;
  }
  function column_value($item) {
    return $item->value;
  }

  function column_default($item, $column_name) {
    return apply_filters( 'manage_items_custom_column','',$column_name,$item['patronid']);
  }

  function get_columns() {
    if ($this->mode == 'monthtotal') {
      return array('label' => __('Month','weblibrarian'), 
		   'value' => __('Count','weblibrarian'));
    } else {
      return array('label' => __('Type','weblibrarian'), 
		   'value' => __('Count','weblibrarian'));
    }
  }

  function get_sortable_columns() {return array();}
  
  function check_permissions() {
    if (!current_user_can('manage_circulation')) {
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


  function extra_tablenav( $which ) {
    if ($which == 'top') {
      ?><input type="hidden" name="year" value="<?php echo esc_attr($this->year); ?>" />
	<input type="hidden" name="month" value="<?php echo esc_attr($this->month); ?>" /><?php
    }

    ?><div class="alignleft actions">
    <label for="year_<?php echo $which; ?>"><?php _e('Year','weblibrarian'); ?></label>
    <select id="year_<?php echo $which; ?>" name="year_<?php echo $which; ?>"><?php
    $allyears = WEBLIB_Statistic::AllYears();
    if ( empty($allyears) ) {$allyears[] = $year;}
    foreach ($allyears as $y) {
      ?><option value="<?php echo $y; ?>"<?php
      if ($y == $this->year) echo ' selected="selected"';
      ?>><?php echo $y; ?></option><?php
    }
    ?></select>&nbsp;
    <label for="month_<?php echo $which; ?>"><?php _e('Month','weblibrarian'); ?></label>
    <select id="month_<?php echo $which; ?>" name="month_<?php echo $which; ?>"><?php
    foreach ($this->MonthNames as $m => $mtext) {
      ?><option value="<?php echo $m; ?>"<?php
      if ($m == $this->month) echo ' selected="selected"';
      ?>><?php echo $mtext; ?></option><?php
    }
    ?></select>&nbsp;<?php
    submit_button(__( 'Filter','weblibrarian'), 'secondary', 'filter_'.$which,false, 
		     array( 'id' => 'post-query-submit') );
  }

  function prepare_items() {
    //file_put_contents("php://stderr","*** WEBLIB_Statistics_Admin::prepare_items: _REQUEST = ".print_r($_REQUEST,true)."\n");
    $this->check_permissions();
    $message = '';
    $this->year = isset($_REQUEST['year']) ? sanitize_text_field($_REQUEST['year']) : date('Y',time());
    $this->month = isset($_REQUEST['month']) ? sanitize_text_field($_REQUEST['month']) : date('m',time());

    if ( isset($_REQUEST['filter_top']) ) {
      $this->year = isset($_REQUEST['year_top']) ? sanitize_text_field($_REQUEST['year_top']) : $this->year;
      $this->month = isset($_REQUEST['month_top']) ? sanitize_text_field($_REQUEST['month_top']) : $this->month;
    } else if ( isset($_REQUEST['filter_bottom']) ) {
      $this->year = isset($_REQUEST['year_bottom']) ? sanitize_text_field($_REQUEST['year_bottom']) : $this->year;
      $this->month = isset($_REQUEST['month_bottom']) ? sanitize_text_field($_REQUEST['month_bottom']) : $this->month;
    }
    //file_put_contents("php://stderr","*** WEBLIB_Statistics_Admin::prepare_items: this->year = $this->year\n");
    //file_put_contents("php://stderr","*** WEBLIB_Statistics_Admin::prepare_items: this->month = $this->month\n");

    if ($this->month == 0) {
      /* Monthy totals */
      $rowdata = array();
      for ($imonth = 1; $imonth < 13; $imonth++) {
	$rowdata[] = (object) array('label' => $this->MonthNames[$imonth],
				    'value' => WEBLIB_Statistic::MonthTotal($this->year,$imonth));
      }
      $rowdata[] = (object) array('label' => 'Total',
				  'value' => WEBLIB_Statistic::AnnualTotal($this->year));
      if ($this->mode != 'monthtotal') {
	unset($this->_column_headers);
	$this->mode = 'monthtotal';
      }
    } else {
      /* Month count by type */
      $types = WEBLIB_Type::AllTypes();
      $rowdata = array();
      foreach ($types as $type) {
	$rowdata[] = (object) array('label' => $type,
				    'value' => WEBLIB_Statistic::TypeCount($type,$this->year,$this->month));
      }
      $rowdata[] = (object) array('label' => __('Total','weblibrarian'),
				  'value' => WEBLIB_Statistic::MonthTotal($this->year,$this->month));
      if ($this->mode != 'typecount') {
	unset($this->_column_headers);
	$this->mode = 'typecount';
      }
    }

    $per_page = $this->get_per_page();
    // Deal with columns
    $columns = $this->get_columns();    // All of our columns
    $hidden  = array();         // Hidden columns [none]
    $sortable = $this->get_sortable_columns(); // Sortable columns
    $this->_column_headers = array($columns,$hidden,$sortable); // Set up columns

    $total_items = count($rowdata);
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
      $this->items = array_slice( $rowdata,$start,$per_page );
    }
    return $message;
  }

  function circulation_statistics () {
    $message = $this->prepare_items();
    ?><div class="wrap"><div id="icon-statistics" class="icon32"><br /></div>
      <h2><?php _e('Library Circulation Statistics','weblibrarian'); ?> <a href="<?php
		echo add_query_arg( 
		       array('page' => 'weblib-export-circulation-statistics')); 
	?>" class="button add-new-h2"><?php _e('Export Stats','weblibrarian'); ?></a></h2><?php
	if ($message != '') {
	  ?><div id="message" class="update fade"><?php echo $message; ?></div><?php
	}
	?><form method="get" action="<?php echo admin_url('admin.php'); ?>">
	<input type="hidden" name="page" value="weblib-circulation-statistics" />
	<?php $this->display(); ?></form></div><?php
  }

  function export_circulation_statistics () {
    ?><div class="wrap"><div id="icon-export-statistics" class="icon32"><br /></div>
      <h2><?php _e('Export Library Circulation Statistics','weblibrarian');?></h2>
      <form method="post" action="<?php 
	echo admin_url('admin-post.php'); ?>"><?php
	$year = date('Y',time());
	$month = date('m',time());
      ?><input type="hidden" name="action" value="ExportLibraryStats" /><p><label for="year"><?php _e('Year','weblibrarian'); ?></label><select id="year" name="year"><?php
 	$allyears = WEBLIB_Statistic::AllYears();
	if ( empty($allyears) ) {$allyears[] = $year;}
	foreach ($allyears as $y) {
	  ?><option value="<?php echo $y; ?>"<?php
	  if ($y == $year) echo ' selected="selected"';
	  ?>><?php echo $y; ?></option><?php
	}
      ?></select></p>
	<p><label for="month"><?php _e('Month','weblibrarian'); ?></label><select id="month" name="month"><?php
	foreach ($this->MonthNames as $m => $mtext) {
	  ?><option value="<?php echo $m; ?>"<?php
	  if ($m == $month) echo ' selected="selected"';
	  ?>><?php echo $mtext; ?></option><?php
	}
      ?></select></p><p><?php
      submit_button(__( 'Export','weblibrarian'), 'secondary', 'export',false,
			array( 'id' => 'post-query-submit') );
      ?></p><?php
  }

}


