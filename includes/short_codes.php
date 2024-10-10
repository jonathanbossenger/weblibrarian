<?php

	/* Short codes (front end) */

class WEBLIB_ShortCodes {

  private $SearchTypes;

  function __construct() {

    $this->SearchTypes = array ('title' => __('Title','weblibrarian'),
				'author' => __('Author','weblibrarian'),
				'subject' => __('Subject','weblibrarian') , 
				'keyword' => __('Keyword','weblibrarian'), 
				'isbn' => __('ISBN','weblibrarian'));

    add_shortcode('weblib_searchform' ,array($this,'search_form'));
    add_shortcode('weblib_itemlist'  ,array($this,'item_list'));
    add_shortcode('weblib_itemdetail',array($this,'item_detail'));

  }

  function search_form ($atts, $content=null, $code="") {
    extract( shortcode_atts ( array(
			'name' => 'searchform',
			'actionurl' => '',
			'method' => 'GET' ), $atts ) );
    $searchby  = isset($_REQUEST['searchby'])  ? $_REQUEST['searchby']  : 'title';
    if (! empty($searchby) ) {
      if (!in_array($searchby,array_keys($this->SearchTypes))) {
        $searchby = '';
      }
    }
    $searchbox = isset($_REQUEST['searchbox']) ? $_REQUEST['searchbox'] : '';
    $searchbox = htmlentities($searchbox, ENT_QUOTES);
    $weblib_orderby = isset( $_REQUEST['weblib_orderby'] ) ? $_REQUEST['weblib_orderby'] : 'barcode';
    if (!in_array($weblib_orderby,array('barcode','title','author'))) {
      $weblib_orderby = 'barcode';
    }
    if ( empty( $weblib_orderby ) ) $weblib_orderby = 'barcode';
    $weblib_order = isset( $_REQUEST['weblib_order'] ) ? $_REQUEST['weblib_order'] : 'ASC';
    if (!in_array($weblib_order,array('ASC','DESC'))) {
      $weblib_order = 'ASC';
    }
    if ( empty( $weblib_order ) ) $weblib_order = 'ASC';

    $result  = '<form id="'.$name.'" method="'.$method.'" action="'.
		$actionurl.'">';
    $result .= '<p><label for="searchby">'. __('Search:','weblibrarian').'</label>';
    $result .= '<select id="searchby" name="searchby" >';
    foreach ($this->SearchTypes as $value => $label) {
      $result .= '<option value="'.$value.'"';
      if ($value == $searchby) {$result .= ' selected="selected"';}
      $result .= '>'.$label."</option>\n";
    }
    $result .= "</select>\n";
    $result .= '<label for="searchbox">&nbsp;'.__('for','weblibrarian').'</label><input id="searchbox" name="searchbox" value="'.$searchbox.'" /><br />';
    $result .= '<label for="weblib_orderby">'.__('Sort by:','weblibrarian').'</label>';
    $result .= '<select id="weblib_orderby" name="weblib_orderby">';
    foreach (array('barcode' => __('System Sorted','weblibrarian'), 
		   'title' => __('Title','weblibrarian'), 
		   'author' => __('Author','weblibrarian')) as $field => $label) {
      $result .= '<option value="'.$field.'"';
      if ($field == $weblib_orderby) {$result .= ' selected="selected"';}
      $result .= '>'.$label."</option>\n";
    }
    $result .= "</select>\n";
    $result .= '<select id="weblib_order" name="weblib_order">';
    foreach (array('ASC' => __('Ascending','weblibrarian'), 
		   'DESC' => __('Descending','weblibrarian')) as
		$value => $label) {
      $result .= '<option value="'.$value.'"';
      if ($value == $weblib_order) {$result .= ' selected="selected"';}
      $result .= '>'.$label."</option>\n";
    }
    $result .= "</select>\n";
    $result .= '<br /><input class="weblib-button" type="submit" value="'.__('Search','weblibrarian').'" /></p>';
    $result .= "</form>\n";
    return $result;
  }

  function item_list ($atts, $content=null, $code="") {
    extract( shortcode_atts ( array(
	'name' => 'itemlist',
	'per_page' => 10,
	'moreinfourl' => '',
	'inlinemoreinfo' => false,
	'holdbutton' => false ), $atts ) );
    $result = '';

    //$result = "\n<!-- barcodetable: _REQUEST is ".print_r($_REQUEST,true)." -->\n";

    $result .= "<!-- barcodetable: holdbutton passed as $holdbutton -->\n";
    if (is_user_logged_in()) {
      $user = wp_get_current_user();
      $patronid = get_user_meta($user->ID,'PatronID',true);
      if ($patronid == '') {
        $holdbutton = false;
      }
    } else {
      $holdbutton = false;
    }
    $result .= "<!-- barcodetable: holdbutton reduced to $holdbutton -->\n";

    if ($inlinemoreinfo) {
      $moreinfourl = get_permalink();
      if (isset($_REQUEST['barcode'])) {
	return $result.$this->item_detail(array('barcode' => $_REQUEST['barcode'], 
					        'holdbutton' => $holdbutton,
						'detaillevel' => 'long'));
      }
    }

    $searchby  = isset($_REQUEST['searchby'])  ? $_REQUEST['searchby']  : 'title';
    if (! empty($searchby) ) {
      if (!in_array($searchby,array_keys($this->SearchTypes))) {
        $searchby = '';
      }
    }
    $searchbox = isset($_REQUEST['searchbox']) ? $_REQUEST['searchbox'] : '';
    //file_put_contents("php://stderr","*** WEBLIB_ShortCodes::item_list: searchbox = '".$searchbox."'\n");
    $searchbox = htmlentities($searchbox, ENT_QUOTES);
    //file_put_contents("php://stderr","*** WEBLIB_ShortCodes::item_list: searchbox (after htmlentities) = '".$searchbox."'\n");
    $weblib_orderby = isset( $_REQUEST['weblib_orderby'] ) ? $_REQUEST['weblib_orderby'] : 'barcode';
    if (!in_array($weblib_orderby,array('barcode','title','author'))) {
      $weblib_orderby = 'barcode';
    }
    if ( empty( $weblib_orderby ) ) $weblib_orderby = 'barcode';
    $weblib_order = isset( $_REQUEST['weblib_order'] ) ? $_REQUEST['weblib_order'] : 'ASC';
    if (!in_array($weblib_order,array('ASC','DESC'))) {
      $weblib_order = 'ASC';
    }
    if ( empty( $weblib_order ) ) $weblib_order = 'ASC';

    if (false && $searchbox == '') {
      $all_items = WEBLIB_ItemInCollection::AllBarCodes($weblib_orderby,$weblib_order);
    } else {
      switch($searchby) {
	case 'title':
	  $all_items = WEBLIB_ItemInCollection::FindItemByTitle('%'.html_entity_decode($searchbox,ENT_QUOTES).'%',$weblib_orderby,$weblib_order);
	  break;
	case 'author':
	  $all_items = WEBLIB_ItemInCollection::FindItemByAuthor('%'.html_entity_decode($searchbox,ENT_QUOTES).'%',$weblib_orderby,$weblib_order);
	  break;
	case 'subject':
	  $all_items = WEBLIB_ItemInCollection::FindItemBySubject('%'.html_entity_decode($searchbox,ENT_QUOTES).'%',$weblib_orderby,$weblib_order);
	  break;
	case 'isbn':
	  $all_items = WEBLIB_ItemInCollection::FindItemByISBN('%'.html_entity_decode($searchbox,ENT_QUOTES).'%',$weblib_orderby,$weblib_order);
	  break;
	case 'keyword':
	  $all_items = WEBLIB_ItemInCollection::FindItemByKeyword('%'.html_entity_decode($searchbox,ENT_QUOTES).'%',$weblib_orderby,$weblib_order);
	  break;
        default:
          $searchby = '';
          $searchbox = '';
          $all_items = WEBLIB_ItemInCollection::AllBarCodes($weblib_orderby,$weblib_order);
          break;
      }
    }

    $per_page = isset($_REQUEST['per_page']) ? $_REQUEST['per_page'] : $per_page;
    if (is_numeric($per_page)) {
      $per_page=$per_page+0; /* Remove possible cruft (XSS attack check) */
    } else {
      $per_page = 10;        /* Replace garbage with default (10) */
    }
    if ($per_page < 1) $per_page = 1;

    $total_items = count($all_items);
    if ($total_items == 1 && $inlinemoreinfo) {
      return $result.$this->item_detail(array('barcode' => $all_items[0],
					      'holdbutton' => $holdbutton,
					      'detaillevel' => 'long'));
    }

    $result .= '<span class="weblib-total-results">';
    if ($total_items==1) {
	$result .= __('1 Item Matched.','weblibrarian');
    } else {
	$result .= sprintf(__('%d Items Matched.','weblibrarian'),$total_items);
    }
    $result .= '</span><br clear="all" />';

    $total_pages = ceil( $total_items / $per_page );
    $pagenum = isset($_REQUEST['pagenum']) ? $_REQUEST['pagenum'] : 1;
    if ($pagenum < 1) {
      $pagenum = 1;
    } else if ($pagenum > $total_pages && $total_pages > 0) {
      $pagenum = $total_pages;
    }
    $start = ($pagenum-1)*$per_page;
    $items = array_slice( $all_items,$start,$per_page );

    if ($moreinfourl != '') {
      $moreinfourl = add_query_arg(array('searchby' => $searchby,
					 'searchbox' => $searchbox,
					 'weblib_orderby' => $weblib_orderby,
					 'weblib_order' => $weblib_order),$moreinfourl);
    }

    $result .= $this->generate_pagination($pagenum,$total_pages,$per_page,
					  array('searchby' => $searchby,
						'searchbox' => $searchbox,
						'weblib_orderby' => $weblib_orderby,
						'weblib_order' => $weblib_order));
    $result .= '<div class="weblib-item-table">';
    $index = $start;
    foreach ($items as $barcode) {
      $result .= '<div class="weblib-item-row">';
      $result .= '<span class="weblib-item-index weblib-item-element">'.++$index.'.</span>';
      $result .= $this->item_detail(array('barcode' => $barcode,
      					  'getbarcode' => false,
					  'holdbutton' => $holdbutton,
					  'moreinfourl' => $moreinfourl));
      $result .= '</div>';
    }
    $result .= '</div>';
    

    $result .= $this->generate_pagination($pagenum,$total_pages,$per_page,
					  array('searchby' => $searchby,
						'searchbox' => $searchbox,
						'weblib_orderby' => $weblib_orderby,
						'weblib_order' => $weblib_order));
    return $result;
  }

  function generate_pagination($pagenum,$lastpage,$per_page,$otherparams) {

    //file_put_contents("php://stderr","*** WEBLIB_ShortCodes::generate_pagination($pagenum,$lastpage,$per_page,".print_r($otherparams,true).")\n");

    if ($lastpage == 1) {
      return '';
    }

    $result  = '<div class="weblib-item-pagination-table">';
    $result .= '<div class="weblib-item-pagination">';
    $result .= '<span class="pagelabel">Result&nbsp;Page:</span>';
    $result .= '<span class="pagelink" style="width=5%;"><a class="weblib-button" href="';
    $result .= add_query_arg(array_merge($otherparams,
					 array('pagenum' => 1,
					       'per_page' => $per_page)),
			     get_permalink( ));
    $result .= '">&lt;&lt;</a></span>';
    $result .= '<span class="pagelink" style="width=5%;"><a class="weblib-button" href="';
    $result .= add_query_arg(array_merge($otherparams,
					 array('pagenum' => 
						  $pagenum > 1 ? $pagenum-1 : 1,
					       'per_page' => $per_page)),
			     get_permalink( ));
    $result .= '">&lt;</a></span>';
    $result .= '<span class="pagelink pagenumform">';
    $result .= '<form action="'.get_permalink( ).'" method="get">';
    $result .= '<input class="weblib-button" type="submit" value="'. __('Goto Page','weblibrarian').'" />';
    $result .= '<input name="pagenum" type="text" size="2" maxlength="2" value="'.$pagenum.'" />';
    $result .= 'of '.$lastpage;
    foreach (array_merge($otherparams,array('per_page' => $per_page)) as $key => $val) {
      $result .= '<input type="hidden" name="'.$key.'" value="'.$val.'" />';
    }
    $result .= '</form></span>';
    $result .= '<span class="pagelink" style="width=5%;"><a class="weblib-button" href="';
    $result .= add_query_arg(array_merge($otherparams,
					 array('pagenum' =>
						$pagenum < $lastpage ? $pagenum+1 : $lastpage,
						'per_page' => $per_page)),
			     get_permalink( ));
    $result .= '">&gt;</a></span>';
    $result .= '<span class="pagelink" style="width=5%;"><a class="weblib-button" href="';
    $result .= add_query_arg(array_merge($otherparams,
			     		array('pagenum' => $lastpage,
				   	'per_page' => $per_page)),
			     get_permalink( ));
    $result .= '">&gt;&gt;</a></span>';
    $result .= '</div></div><br clear="all" />';
    return $result;
  }

  function item_detail ($atts, $content=null, $code="") {
    extract( shortcode_atts ( array(
      'name' => 'itemdetail[%i]',
      'barcode' => '',
      'getbarcode' => true,
      'holdbutton' => false,
      'detaillevel' => 'brief',
      'moreinfourl' => '' ), $atts ) );
    $result = '';

    if ($getbarcode) {
      $barcode = isset($_REQUEST['barcode']) ? $_REQUEST['barcode'] : $barcode;
    }

    if (!WEBLIB_ItemInCollection::IsItemInCollection($barcode)) {
      return '<p><span id="error">'.sprintf(__('No such item: %s','weblibrarian'),$barcode).'</span></p>';
    } else {
      $item = new WEBLIB_ItemInCollection($barcode);
    }

    switch ($detaillevel) {
      case 'long':
	$result .= '<div class="weblib-item-long">';
	$result .= '<div class="weblib-item-head weblib-item-row">';
	$result .= '<div class="weblib-item-left weblib-item-element">';
        $result .= '<div class="weblib-item-content-block">';
	$result .= '<span class="weblib-item-content-element">';
	$result .= '<span class="weblib-item-left-head">'.__('Title','weblibrarian').'</span>';
	$result .= '<span class="weblib-item-left-content weblib-item-title">'.$item->title().'</span>';
	$result .= '</span><!-- weblib-item-content-element -->';
	$result .= '<span class="weblib-item-content-element">';
	$result .= '<span class="weblib-item-left-head">'.__('Author','weblibrarian').'</span>';
	$result .= '<span class="weblib-item-left-content weblib-item-author">'.$item->author().'</span>';
	$result .= '</span><!-- weblib-item-content-element -->';
	$result .= '<span class="weblib-item-content-element">';
	$result .= '<span class="weblib-item-left-head">'.__('Published','weblibrarian').'</span>';
	$result .= '<span class="weblib-item-left-content">';
	$publoc  = $item->publocation();
	$pub     = $item->publisher();
	$pubyear = $item->pubyear();
	if ($publoc != '') {
	  $result .= $publoc.'&nbsp;:&nbsp;';
	}
	$result .= $pub;
        $result .= ' '.$pubyear;
	$result .= '</span><!-- weblib-item-left-content -->';	
	$result .= '</span><!-- weblib-item-content-element -->';
	$result .= '</div><!-- weblib-item-content-block -->';	
        $result .= '</div><!-- weblib-item-left -->';
	$result .= '<div class="weblib-item-right weblib-item-element">';
	$result .= '<span class="weblib-item-thumb">';
	if ($item->thumburl() != '') {
	  $result .= '<img src="'.$item->thumburl().'" border="0"  />';
	} else {
	  $result .= '<img src="'.WEBLIB_IMAGEURL.'/nothumb.png" border="0" width="48" height="72" />';
	}
        $result .= '</span><!-- weblib-item-thumb -->';
	if ($holdbutton) {
	  $result .= '<br /><span class="weblib-item-holdbutton">';
	  $result .= '<input class="weblib-button" type="button" value="'.__('Request','weblibrarian').'" onClick="PlaceHold('."'".$barcode."');".'" />';
	  $result .= '</span><!-- weblib-item-holdbutton -->';
	}
	$result .= '</div><!-- weblib-item-right" -->';
	$result .= '</div><!-- weblib-item-head -->';
	$result .= '<div class="weblib-item-body weblib-item-row">';
	$result .= '<div class="weblib-item-left weblib-item-element">';
	$result .= '<div class="weblib-item-content-block">';
	$result .= '<span class="weblib-item-content-element">';
	$result .= '<span class="weblib-item-left-head">'.__('Status:','weblibrarian').'</span>';
	$result .= '<span class="weblib-item-left-content">';
	$outitem = WEBLIB_OutItem::OutItemByBarcode($barcode);
	$numberofholds = WEBLIB_HoldItem::HoldCountsOfBarcode($barcode);
	if ($outitem != null) {
	  $result .= __('Due ','weblibrarian');
	  $duedate = $outitem->datedue();
	  if (mysql2date('U',$duedate) < time()) {
	    $result .= '<span class="overdue" >'.strftime('%x',mysql2date('U',$duedate)).'</span>';
	  } else {
	    $result .= strftime('%x',mysql2date('U',$duedate));
	  }
	} else {
	  $result .= __('Check Shelves','weblibrarian');
	}
	$result .= '&nbsp;<span id="hold-count-'.$barcode.'">';
	if ($numberofholds > 0) {
          $result .= sprintf(_n('%d Hold','%d Holds',$numberofholds,'weblibrarian'),
                             $numberofholds);
	}
	$result .= '</span><!-- hold-count-... -->';
	$result .= '</span><!-- weblib-item-left-content -->';
	$result .= '</span><!-- weblib-item-content-element -->';
	if ($item->subject() != '') {
	  $result .= '<span class="weblib-item-content-element">';
	  $result .= '<span class="weblib-item-left-head">'.__('Subject','weblibrarian').'</span>';
	  $result .= '<span class="weblib-item-left-content">'.$item->subject().'</span>';
	  $result .= '</span><!-- weblib-item-content-element -->';
	}
	if ($item->category() != '') {
	  $result .= '<span class="weblib-item-content-element">';
	  $result .= '<span class="weblib-item-left-head">'.__('Category','weblibrarian').'</span>';
	  $result .= '<span class="weblib-item-left-content">'.$item->category().'</span>';
	  $result .= '</span><!-- weblib-item-content-element -->';
	}
	if ($item->media() != '') {
	  $result .= '<span class="weblib-item-content-element">';
	  $result .= '<span class="weblib-item-left-head">'.__('Media','weblibrarian').'</span>';
	  $result .= '<span class="weblib-item-left-content">'.$item->media().'</span>';
	  $result .= '</span><!-- weblib-item-content-element -->';
	}
	if ($item->edition() != '') {
	  $result .= '<span class="weblib-item-content-element">';
	  $result .= '<span class="weblib-item-left-head">'.__('Edition','weblibrarian').'</span>';
	  $result .= '<span class="weblib-item-left-content">'.$item->edition().'</span>';
	  $result .= '</span><!-- weblib-item-content-element -->';
	}
	if ($item->isbn() != '') {
	  $result .= '<span class="weblib-item-content-element">';
	  $result .= '<span class="weblib-item-left-head">'.__('ISBN','weblibrarian').'</span>';
	  $result .= '<span class="weblib-item-left-content">'.$item->isbn().'</span>';
	  $result .= '</span><!-- weblib-item-content-element -->';
	}
	if ($item->callnumber() != '') {
	  $result .= '<span class="weblib-item-content-element">';
	  $result .= '<span class="weblib-item-left-head">'.__('Call Number','weblibrarian').'</span>';
	  $result .= '<span class="weblib-item-left-content">'.$item->callnumber().'</span>';
	  $result .= '</span><!-- weblib-item-content-element -->';
	}
	if ($item->type() != '') {
	  $result .= '<span class="weblib-item-content-element">';
	  $result .= '<span class="weblib-item-left-head">'.__('Type','weblibrarian').'</span>';
	  $result .= '<span class="weblib-item-left-content">'.$item->type().'</span>';
	  $result .= '</span><!-- weblib-item-content-element -->';
	}
	if ($item->description() != '') {
	  $result .= '<span class="weblib-item-content-element">';
	  $result .= '<span class="weblib-item-left-head">Description</span>';
	  $result .= '<div class="weblib-item-left-content">'.$item->description().'</div>';
	  $result .= '</span><!-- weblib-item-content-element -->';
	}
	$result .= '</div><!-- weblib-item-content-block -->';
	$result .= '</div><!-- weblib-item-left -->';
	$result .= '<div class="weblib-item-right weblib-item-element">';
	$result .= '<span class="weblib-item-center-head">'.__('Keywords','weblibrarian').'</span>';
	$result .= '<p class="weblib-item-keyword-list">';
	$space  = '';
	foreach ($item->keywordsof() as $keyword) {
	  $result .= $space.$keyword;
	  $space  = ' ';
	}
	$result .= '</p>';
	$result .= '</div><!-- weblib-item-right" -->';
	$result .= '</div><!-- weblib-item-body -->';
	$result .= '</div><!-- weblib-item-long -->';
	break;
      case 'brief':
      default:
	$result .= '<span class="weblib-item-brief weblib-item-thumb weblib-item-element">';
	if ($item->thumburl() != '') {
	  $result .= '<img src="'.$item->thumburl().'" border="0" />';
	} else {
	  $result .= '<img src="'.WEBLIB_IMAGEURL.'/nothumb.png" border="0" width="48" height="72" />';
	}
        $result .= '</span>';
	$result .= '<span class="weblib-item-brief weblib-item-info weblib-item-element">';
	if ($moreinfourl != '') {
	  $result .= '<a href="'.add_query_arg(array('barcode' => $barcode),
						$moreinfourl).'">';
	}
	$result .= $item->title();
	if ($moreinfourl != '') {$result .= '</a>';}
	$result .= '<br />';
	$result .= $item->author();
	if ($item->callnumber() != '') {
	  $result .= '<br />'.__('Call Number:','weblibrarian').'&nbsp;'.$item->callnumber();
	}
	$outitem = WEBLIB_OutItem::OutItemByBarcode($barcode);
	$numberofholds = WEBLIB_HoldItem::HoldCountsOfBarcode($barcode);
	if ($outitem != null) {
	  $result .= '<br />'.__('Due ','weblibrarian');
	  $duedate = $outitem->datedue();
	  if (mysql2date('U',$duedate) < time()) {
	    $result .= '<span class="overdue" >'.strftime('%x',mysql2date('U',$duedate)).'</span>';
	  } else {
	    $result .= strftime('%x',mysql2date('U',$duedate));
	  }
	  
	}
	$result .= '<br /><span id="hold-count-'.$barcode.'">';
	if ($numberofholds > 0) {
          $result .= sprintf(_n('%d Hold','%d Holds',$numberofholds,'weblibrarian'),
                             $numberofholds);
	}
	$result .= '</span></span>';
	if ($holdbutton) {
	  $result .= '<span class="weblib-item-holdbutton weblib-item-element">';
	  $result .= '<input class="weblib-button" type="button" value="'.__('Request','weblibrarian').'" onClick="PlaceHold('."'".$barcode."');".'" />';
	  $result .= '</span>';
	}
	break;
    }
    return $result;
  }


}

?>
