<?php 
 /* Database code.
  */

/* Global constants */
global $wpdb;
define('WEBLIB_PATRONS',     $wpdb->prefix . 'weblib_patrons');
define('WEBLIB_COLLECTION',  $wpdb->prefix . 'weblib_collection');
define('WEBLIB_KEYWORDS',    $wpdb->prefix . 'weblib_keywords');
define('WEBLIB_OUTITEMS',    $wpdb->prefix . 'weblib_outitems');
define('WEBLIB_HOLDITEMS',   $wpdb->prefix . 'weblib_holditems');
define('WEBLIB_STATISICS',   $wpdb->prefix . 'weblib_statistics');
define('WEBLIB_TYPES',	     $wpdb->prefix . 'weblib_types');

/* Initialize the database */
function WEBLIB_make_tables() {
  global $wpdb;

  /*
   * Patron info table:
   *
   */

  $sql = 'CREATE TABLE ' . WEBLIB_PATRONS . " (
  id int NOT NULL AUTO_INCREMENT unique,
  firstname varchar(32) not null check (firstname <> ''),
  lastname varchar(32) not null check (lastname <> ''),
  extraname varchar(32) not null default '',
  address1 varchar(32) not null check (address1 <> ''),
  address2 varchar(32) not null default '',
  city varchar(32) not null check(city <> ''),
  state char(16) ,
  zip char(10) ,
  telephone char(16) ,
  outstandingfines decimal(5,2) default 0.0,
  expiration date default '2015-12-31',
  PRIMARY KEY  (id) 
  );";

  require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
  $result = dbDelta($sql);

  /*
   * Collection table:
   *
   */

  $sql = 'CREATE TABLE ' . WEBLIB_COLLECTION . " (
  barcode varchar(16) not null unique check (barcode <> ''),
  title varchar(128) not null check (title <> ''),
  author varchar(64) not null check (author <> ''),
  subject varchar(128) not null check (subject <> ''),
  description text not null,
  category varchar(36) not null default '',
  media varchar(36) not null default '',
  publisher varchar(36) not null default '',
  publocation varchar(36) not null default '',
  pubdate date not null default '1900-01-01',
  edition varchar(36) not null default '',
  isbn varchar(20) not null default '',
  type varchar(16) not null check (type <> ''),
  thumburl varchar(256) not null default '',
  callnumber varchar(36) not null default '',
  PRIMARY KEY  (barcode),
  KEY (title),
  KEY (author),
  KEY (subject),
  KEY (isbn)
  );";

  //require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
  $result = dbDelta($sql);

  /*
   *    Keywords table:
   */

  $sql = 'CREATE TABLE ' . WEBLIB_KEYWORDS . " (
  keyword VARCHAR(64) NOT NULL,
  barcode varchar(16) not null check (barcode <> ''),
  KEY  (keyword) 
  );";

  //require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
  $result = dbDelta($sql);

  /*
   * Checked out items table:
   *
   */

  $sql = 'CREATE TABLE ' . WEBLIB_OUTITEMS . " (
  transaction serial,
  barcode varchar(16) not null check (barcode <> ''),
  title varchar(128) not null check (title <> ''),
  source varchar(16) not null check (source <> ''),
  type varchar(16) not null check (type <> ''),
  patronid int not null,
  dateout date not null check (dateout <> ''),
  datedue date not null check (datedue <> ''),
  PRIMARY KEY  (transaction)
  );";


  //require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
  $result = dbDelta($sql);

  /*
   * Items on hold table:
   *
   */

  $sql = 'CREATE TABLE ' . WEBLIB_HOLDITEMS . " (
  transaction serial,
  barcode varchar(16) not null check (barcode <> ''),
  title varchar(128) not null check (title <> ''),
  source varchar(16) not null check (source <> ''),
  type varchar(16) not null check (type <> ''),
  patronid int not null,
  dateheld date not null check (dateheld <> ''),
  dateexpire date not null check (dateexpire <> ''),
  PRIMARY KEY  (transaction) 
  );";

  //require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
  $result = dbDelta($sql);

  /*
   * Statistics table:
   *
   */

  $sql = 'CREATE TABLE ' . WEBLIB_STATISICS . " (
  type varchar(16) not null check (type <> ''),
  year numeric(4) unsigned not null,
  month numeric(2) unsigned not null,
  count integer unsigned not null default 0,
  KEY  (type),
  KEY  (year),
  KEY  (month)
  );";

  //require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
  $result = dbDelta($sql);

  /*
   * Item types table:
   *
   */

  $sql = 'CREATE TABLE ' . WEBLIB_TYPES . " (
  type varchar(16) not null unique check (type <> ''),
  loanperiod integer unsigned not null default 14,
  PRIMARY KEY  (type) 
  );";

  //require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
  $result = dbDelta($sql);

  /*$weblib_tables = $wpdb->get_results("SHOW TABLES LIKE '" . $wpdb->prefix . "weblib_%'",'ARRAY_A');
  //file_put_contents("php://stderr","*** WEBLIB_make_tables: weblib_tables = ".print_r($weblib_tables,true)."\n");*/
  /*$wpdb->show_errors($olderror);*/
}

function WEBLIB_dump_tables()
{
  global $wpdb;
  $tables = $wpdb->get_results("SHOW TABLES LIKE '" . $wpdb->prefix . "weblib_%'",'ARRAY_A');
  foreach ($tables as $table_a) {
    foreach ($table_a as $table) {
      ?><h3><?php _e('Table: ','weblibrarian'); echo $table; ?></h3>
	<pre><?php print_r($wpdb->get_results('select * from '.$table)); ?></pre><?php
    }
  }
}

/* Patron class object */

class WEBLIB_Patron {
	private $record;		/* The Patron record itself */
	private $dirty = true;		/* Is the incore copy different from the DB copy ? */
	private $insert = true;		/* Does the incore copy need to be inserted? */
	private $theid = 0;		/* The Patron id */

	/*
	 * Constructor.  Argument is the ID, 0 means create a fresh one, non-0
	 *		 means pull it out if the DB. 
	 */
	function __construct($id = 0) {
	  $this->theid = $id;
	  if ($this->theid == 0) {	/* Create a fresh Patron record */
	    $this->insert = true;
	    $this->dirty  = true;
	    $this->record = array( 
		'firstname' => '',
		'lastname'  => '',
		'extraname' => '',
		'address1'  => '',
		'address2'  => '',
		'city'      => '',
		'state'     => '',
		'zip'       => '',
		'telephone' => '',
		'outstandingfines' => 0.00,
		'expiration' => WEBLIB_Patron::FiveYears() );
	  } elseif (WEBLIB_Patron::ValidPatronID($this->theid)) {
	    global $wpdb;
	    /* Yank a record from the DB */
	    $olderror = $wpdb->show_errors(get_option('weblib_debugdb') != 'off');
	    $sql = $wpdb->prepare('SELECT firstname,lastname,extraname,address1,' .
			'address2,city,state,zip,telephone,' .
			'outstandingfines,expiration FROM ' . WEBLIB_PATRONS .
			' where id = %d',$this->theid);
	    $rawrecord = $wpdb->get_row($sql, 'ARRAY_A' );
	    $wpdb->show_errors($olderror);
	    $this->record = array();
	    /* Strip the slashes */
	    foreach ($rawrecord as $k => $v) {
	      $this->record[$k] = stripslashes($v);
	    }	    
	    $this->insert = false;
	    $this->dirty  = false;
	  } else {
	    /* No such record. */
	    trigger_error("Not a valid patron ID: $this->theid\n", E_USER_ERROR);
	  }
	}

	/* Destructor.  Write out a dirty record */
	function __destruct() {
	  if ($this->dirty) $this->store();
	}

	/* 
	 * Accessor functions 
         * (Uses 'set_' functions to force updating the dirty flag.)
	 */
        function firstname() {return $this->record['firstname']; }
	function set_firstname($fn) { $this->record['firstname'] = $fn; $this->dirty = true;}
        function lastname() {return $this->record['lastname']; }
	function set_lastname($fn) { $this->record['lastname'] = $fn; $this->dirty = true;}
        function extraname() {return $this->record['extraname']; }
	function set_extraname($fn) { $this->record['extraname'] = $fn; $this->dirty = true;}
	function address1() {return $this->record['address1']; }
	function set_address1($ad) { $this->record['address1'] = $ad; $this->dirty = true;}
	function address2() {return $this->record['address2']; }
	function set_address2($ad) { $this->record['address2'] = $ad; $this->dirty = true;}
	function city() {return $this->record['city']; }
	function set_city($c) { $this->record['city'] = $c; $this->dirty = true;}
	function state() {return $this->record['state']; }
	function set_state($c) { $this->record['state'] = $c; $this->dirty = true;}
	function zip() {return $this->record['zip']; }
	function set_zip($c) { $this->record['zip'] = $c; $this->dirty = true;}
	function telephone() { return $this->record['telephone']; }
	function set_telephone($t) { $this->record['telephone'] = $t; $this->dirty = true;}
	function outstandingfines() { return $this->record['outstandingfines']; }
	function set_outstandingfines($f) { $this->record['outstandingfines'] = $f; $this->dirty = true;}
	function expiration() { 
          $date = new DateTime($this->record['expiration']);
          if (function_exists('nl_langinfo')) {
            $dfmt = nl_langinfo(D_FMT);
          } else {
            $dfmt = '%m/%d/%y';
          }
          if ($dfmt == '%m/%d/%y') {
            return $date->format('M/j/Y');
          } else {
            return $date->format('j/M/Y');
          }
        }
	function set_expiration($e) { $this->record['expiration'] = $e; $this->dirty = true;}

	/* Function to store the record */
	function store($newid = 0) {
	  if (!$this->dirty) return $this->theid;
	  /* Validate the record. */
	  if ($this->record['firstname'] == '' ||
	      $this->record['lastname']  == '' ||
	      $this->record['address1']  == '' ||
	      $this->record['city']  == '' ||
	      $this->record['outstandingfines'] == '' ||
	      $this->record['expiration']  == '') return(-1);
	  if (! is_numeric($this->record['outstandingfines']) ) return(-1);
	  global $wpdb;
	  if ($this->insert) {
	    /* Store the new record with a specificed ID */
	    if ($newid != 0) {
	      $insertrec = array(
		'id' => $newid,
		'firstname' => $this->record['firstname'],
		'lastname' => $this->record['lastname'],
		'extraname' => $this->record['extraname'],
		'address1' => $this->record['address1'],
		'address2' => $this->record['address2'],
		'city' => $this->record['city'],
		'state' => $this->record['state'],
		'zip' => $this->record['zip'],
		'telephone' => $this->record['telephone'],
		'outstandingfines' => $this->record['outstandingfines'],
		'expiration' => $this->record['expiration'] );
	      $insertfmt = array('%d','%s','%s','%s','%s','%s','%s','%s','%s',
				 '%s','%f','%s');
	    } else {
	      /* Generate the new id */
	      $insertrec = array(
		'firstname' => $this->record['firstname'],
		'lastname' => $this->record['lastname'],
		'extraname' => $this->record['extraname'],
		'address1' => $this->record['address1'],
		'address2' => $this->record['address2'],
		'city' => $this->record['city'],
		'state' => $this->record['state'],
		'zip' => $this->record['zip'],
		'telephone' => $this->record['telephone'],
		'outstandingfines' => $this->record['outstandingfines'],
		'expiration' => $this->record['expiration'] );
	      $insertfmt = array('%s','%s','%s','%s','%s','%s','%s','%s',
				 '%s','%f','%s');
	    }
	    $olderror = $wpdb->show_errors(get_option('weblib_debugdb') != 'off');
	    $wpdb->insert(WEBLIB_PATRONS, $insertrec, $insertfmt);
	    $wpdb->show_errors($olderror);
	    $this->theid = $wpdb->insert_id;
	    $this->insert = false;
	    $this->dirty  = false;
	  } else {
	    /* Update an existing record */
	    $updaterec = array(
		'firstname' => $this->record['firstname'],
		'lastname' => $this->record['lastname'],
		'extraname' => $this->record['extraname'],
		'address1' => $this->record['address1'],
		'address2' => $this->record['address2'],
		'city' => $this->record['city'],
		'state' => $this->record['state'],
		'zip' => $this->record['zip'],
		'telephone' => $this->record['telephone'],
		'outstandingfines' => $this->record['outstandingfines'],
		'expiration' => $this->record['expiration'] );
	    $updatefmt = array('%s','%s','%s','%s','%s','%s','%s','%s',
				 '%s','%f','%s');
	    $where = array('id' => $this->theid );
	    $wherefmt = '%d';
	    $olderror = $wpdb->show_errors(get_option('weblib_debugdb') != 'off');
	    $wpdb->update(WEBLIB_PATRONS, $updaterec, $where, $updatefmt,
					$wherefmt);
	    $wpdb->show_errors($olderror);
	    $this->dirty  = false;
	  }
	  return $this->theid;
	}
	/* Delete this record from the DB */
	function delete() {
	  if ($this->insert) return true;
	  $this->dirty = false;
	  WEBLIB_Patron::DeletePatronByID($this->theid);
	  WEBLIB_HoldItem::DeleteHoldsByPatronId($this->theid);
	  WEBLIB_OutItem::DeleteCheckoutsByPatronId($this->theid);
	  $this->insert = true;
	  return true;
	}
	/* Is this record in the database? */
	function indatabase() {
	  if ($this->insert) return false;
	  else 		     return true;
	}
	/* Clear the dirty flag */
	function clean() {$this->dirty = false;}
	/* Return our ID */
	function ID() {return $this->theid;}
	/* Record dirty? */
	function isdirty() {return $this->dirty;}
	/*
	 * Associate this record with the selected WP user id
	 */
	function StorePatronIDWithSelectedUser($user_id,&$error,$replace = false) {
	  if ($this->theid == 0) {
	    $error .= '<p><span id="error">No patron id!</span></p>';
	  }
	  $patronid = get_user_meta($user_id,'PatronID',true);
	  if ($patronid != '' && !$replace) {
	    $error .= '<p><span id="error">Patron ID already set for this user!</span></p>';
	    return false;
	  }
	  update_user_meta( $user_id, 'PatronID', $this->theid, $patronid );
	  return true;
	}
	/*
	 * Store this record with the current WP user
	 */
	function StorePatronIDWithCurrentUser(&$error) {
	  if ($this->theid == 0) {
	    $error .= '<p><span id="error">No patron id!</span></p>';
	    return false;
	  }
	  $user = wp_get_current_user();
	  if (! $user instanceof WP_User ) {
	    $error .= '<p><span id="error">Not logged in!</span></p>';
	    return false;
	  }
	  $patronid = get_user_meta($user->ID,'PatronID',true);
	  if ($patronid != '') {
	    $error .= '<p><span id="error">Patron ID already set for this user!</span></p>';
	    return false;
	  }
	  update_user_meta( $user->ID, 'PatronID', $this->theid, $patronid );
	  return true;
	}
	/* Static functions: */
	/*
	 * 5 years into the future
	 */
	static function FiveYears() {
	  // floor(5*365.2425) = 1826.0 -- 5 years, allowing for a leapyear
	  $year = date('Y',floor(time()+1826*24*60*60));
	  return $year.'-12-31';
	}
	/*
	 * return a list of all patrons
	 */
	static function AllPatrons() {
	  global $wpdb;
	  $olderror = $wpdb->show_errors(get_option('weblib_debugdb') != 'off');
	  $patrons = $wpdb->get_results(
			'SELECT lastname, firstname, extraname, id FROM '.
			WEBLIB_PATRONS.' order by lastname,firstname','ARRAY_A');
	  $wpdb->show_errors($olderror);
	  $result = array();
	  foreach ($patrons as $patron) {
	    //file_put_contents("php://stderr","*** WEBLIB_Patron::AllPatrons(): patron[lastname] = ".$patron['lastname'].", patron[firstname] = ".$patron['firstname'].",  patron[id] = ".$patron['id']."\n");
	    $result[] = array('name' => stripslashes($patron['lastname']).', '.stripslashes($patron['firstname']).' '.stripslashes($patron['extraname']),
			      'patronid' => $patron['id'] );
	  }
	  //foreach ($result as $index => $p) {
	  //  file_put_contents("php://stderr","*** WEBLIB_Patron::AllPatrons(): result[$index] is:\n");
	  //  foreach ($p as $k => $v) {
	  //    file_put_contents("php://stderr","***      $k => $v\n");
	  //  }
	  //}
	  return $result;
	}
	/*
  	 * Delete a patron by id
 	 */
	static function DeletePatronByID($id) {
	  global $wpdb;
	  $olderror = $wpdb->show_errors(get_option('weblib_debugdb') != 'off');
	  $sql = $wpdb->prepare('DELETE FROM '.WEBLIB_PATRONS.' where id = %d',
				$id);
	  $wpdb->query($sql);
	  $wpdb->show_errors($olderror);
	}
	/*
	 * Find a patron by name
	 */
	static function FindPatronByName($pattern) {
	  global $wpdb;
	  $olderror = $wpdb->show_errors(get_option('weblib_debugdb') != 'off');
	  $sql = $wpdb->prepare(
		'SELECT lastname, firstname, extraname, id FROM '.
		WEBLIB_PATRONS.
		" where concat(lastname,', ',firstname,' ',extraname)".
		' like %s order by lastname, firstname',$pattern);
	  $patrons = $wpdb->get_results($sql,'ARRAY_A');
	  $wpdb->show_errors($olderror);
	  $result = array();
	  foreach ($patrons as $patron) {
	    //file_put_contents("php://stderr","*** WEBLIB_Patron::FindPatronByName(): patron[lastname] = ".$patron['lastname'].", patron[firstname] = ".$patron['firstname'].",  patron[id] = ".$patron['id']."\n");
	    $result[] = array('name' => stripslashes($patron['lastname']).', '.stripslashes($patron['firstname']).' '.stripslashes($patron['extraname']),
			      'patronid' => $patron['id'] );
	  }
	  //foreach ($result as $index => $p) {
	  //  file_put_contents("php://stderr","*** WEBLIB_Patron::FindPatronByName(): result[$index] is:\n");
	  //  foreach ($p as $k => $v) {
	  //    file_put_contents("php://stderr","***      $k => $v\n");
	  //  }
	  //}
	  return $result;
	}
	/*
	 * Return a patron name from his/her id
	 */	  
	static function NameFromId($patronid) {
	  global $wpdb;
	  $olderror = $wpdb->show_errors(get_option('weblib_debugdb') != 'off');
	  $sql = $wpdb->prepare(
		'SELECT firstname,lastname,extraname FROM ' . WEBLIB_PATRONS . 
		' where id = %d',$patronid);
	  $names = $wpdb->get_row($sql, 'ARRAY_A' );
	  $wpdb->show_errors($olderror);
	  return stripslashes($names['lastname']).', '.stripslashes($names['firstname']).' '.stripslashes($names['extraname']);
	}
	/*
	 * Return a patron telephone number from his/her id
	 */
	static function TelephoneFromId($patronid) {
	  global $wpdb;
	  $olderror = $wpdb->show_errors(get_option('weblib_debugdb') != 'off');
	  $sql = $wpdb->prepare(
		'SELECT telephone FROM ' . WEBLIB_PATRONS . 
		' where id = %d',$patronid);
	  return $wpdb->get_var($sql);
	  $wpdb->show_errors($olderror);
	}
	/*
	 * return the patron record for the current logged in WP user.
	 */
	static function PatronFromCurrentUser(&$error) {
	  $user = wp_get_current_user();
	  if (! $user instanceof WP_User ) {
	    $error .= '<p><span id="error">Not logged in!</span></p>';
	    return null;
	  }
	  $patronid = get_user_meta($user->ID,'PatronID',true);
	  if ($patronid == '') {
	    $error .= '<p><span id="error">No patron id associated with this user!</span></p>';
	    return null;
	  }
	  return new WEBLIB_Patron($patronid);
	}
	/* 
	 * Return the patron record for the selected WP user id
	 */
	static function PatronFromSelectedUser($user_id,&$error) {
	  $patronid = get_user_meta($user_id,'PatronID',true);
	  if ($patronid == '') {
	    $error .= '<p><span id="error">No patron id associated with this user!</span></p>';
	    return null;
	  }
	  return new WEBLIB_Patron($patronid);
	}
	/*
	 * Is this patron id valid?
	 */
	static function ValidPatronID($patronid) {
	  global $wpdb;
	  $olderror = $wpdb->show_errors(get_option('weblib_debugdb') != 'off');
	  $sql = $wpdb->prepare('SELECT count(id) FROM '.WEBLIB_PATRONS.
				' where id = %d',$patronid);
	  $result = $wpdb->get_var($sql) > 0;
	  $wpdb->show_errors($olderror);
	  return $result;
	}

	static function UserIDFromPatronID($patronid) {
	  $users = get_users( array('fields' => 'id' ) );
	  foreach ($users as $userid) {
	    if (get_user_meta($userid,'PatronID',true) == $patronid) {
		return $userid;
	    }
	  }
	  return -1;
	}

	static function PatronIdDropdown($selected,$args,$echoit=true) {
          $result = '';
          $result .= '<!-- *** WEBLIB_Patron::PatronIdDropdown: args = '.print_r($args,true)." -->\n";
	  extract (wp_parse_args($args, array('onlyunassoc' => false,
					      'label' => __('Patron:','weblibrarian'),
					      'name'  => 'patronid',
					      'beforesel' => '',
					      'aftersel'  => '',
					      'selectclass' => '',
					      'labelclass' => '')));
	  $result .=  '<!-- *** WEBLIB_Patron::PatronIdDropdown: onlyunassoc = '.$onlyunassoc." -->\n";
	  $result .=  '<!-- *** WEBLIB_Patron::PatronIdDropdown: selected = '.$selected." -->\n";
          $result .=  '<label for="'.$name.'"';
          if ($labelclass != '') { $result .= ' class="'.$labelclass.'"'; }
          $result .= '>'.$label.'</label>'.$beforesel; 
          $result .= '<select id="'.$name.'" name="'.$name.'" class="patrondroplist';
          if ($selectclass != '') { $result .= ' '.$selectclass; }
          $result .= '">';
	  $result .= '<option value="0"';
          if ($selected == 0) { $result .= ' selected="selected"'; } 
          $result .= '></option>';
          $patrons = WEBLIB_Patron::AllPatrons();
          foreach ($patrons as $patron) {
            if ($onlyunassoc) {
              $userid = WEBLIB_Patron::UserIDFromPatronID($patron['patronid']);
              $result .= '<!-- *** WEBLIB_Patron::PatronIdDropdown: userid = '.$userid." -->\n";
              if ($userid != -1) {continue;}
            }
            $result .= '<option value="'.$patron['patronid'].'"';
            if ( $patron['patronid'] == $selected ) {
              $result .= ' selected="selected"';
            }
            $result .= '>'.$patron['name'].'</option>';
          }
          $result .= '</select>'.$aftersel;
          if ($echoit) {
            echo $result;
          } else {
            return $result;
          }
	}
	static function upload_csv($filename,$use_csv_headers,$field_sep,
				   $enclose_char/*,$escape_char*/) {
	  //file_put_contents("php://stderr","*** WEBLIB_Patron::upload_csv: field_sep = '$field_sep'\n");
	  //file_put_contents("php://stderr","*** WEBLIB_Patron::upload_csv: enclose_char = '$enclose_char'\n");
	  global $wpdb;
	  $message = '';
	  $skipfirstline = false;
	  $fpointer = fopen($filename,"r");
	  $firstline = fgetcsv($fpointer, 10*1024, $field_sep,$enclose_char/*,$escape_char*/);
	  fclose($fpointer);
	  $dataok = true;
	  $columns = array();
	  $formats = array();
	  if ($use_csv_headers) {
	    $skipfirstline = true;
	    foreach($firstline as $h) {
	      if (trim($h) != "") {
		$col = strtolower(trim($h));
		if (in_array($col,array("id","firstname","lastname","extraname",
				      "address1","address2","city","state",
				      "zip","telephone","outstandingfines",
				      "expiration"))) {
		  if ($col == 'id') {
		    $formats[] = '%d';
		  } else if ($col == 'outstandingfines') {
		    $formats[] = '%f';
		  } else {
		    $formats[] = '%s';
		  }
		  $columns[] = $col;
		} else {
		  $message .= '<p><span id="error">'.
                  sprintf(__('Undefined column: %s','weblibrarian'),$h).
                  '</span></p>';
		  $dataok = false;
		}
	      }
	    }
	    $manditorycolcount = 0;
	    foreach ($columns as $col) {
	      if (in_array($col,array('firstname','lastname','address1','city',
				      'state','zip','telephone'))) 
		$manditorycolcount++;
	    }
	    if ($manditorycolcount < 7) {
	      $message .= '<p><span id="error">' . __('Some mandatory columns are missing!','weblibrarian') . '</span></p>';
	      $dataok = false;
	    }
	  } else {
	    switch (count($firstline)) {
	      case 7:
		$colfmts = array("firstname" => "%s","lastname" => "%s",
				 "address1" => "%s","city" => "%s",
				 "state" => "%s","zip" => "%s",
				 "telephone" => "%s");
		break;
	      case 8:
		$colfmts = array("id" => '%d',"firstname" => "%s",
				 "lastname" => "%s","address1" => "%s",
				 "city" => "%s","state" => "%s","zip" => "%s",
				 "telephone" => "%s");
		break;
	      case 9:
		$colfmts = array("id" => '%d',"firstname" => "%s",
				 "lastname" => "%s","address1" => "%s",
				 "address2" => "%s","city" => "%s",
				 "state" => "%s","zip" => "%s",
				 "telephone" => "%s");
		break;
	      case 10:
		$colfmts = array("id" => '%d',"firstname" => "%s","lastname",
				 "extraname" => "%s","address1" => "%s",
				 "address2" => "%s","city" => "%s",
				 "state" => "%s","zip" => "%s",
				 "telephone" => "%s");
		break;
	      default:
		$message .= '<p><span id="error">'.
                sprintf(__('Bad number of columns: %d','weblibrarian'),count($firstline)).
                '</span></p>';
		$dataok = false;
	    }
	    if ($dataok) {
	      foreach ($colfmts as $col => $fmt) {
		$columns[] = $col;
		$formats[] = $fmt;
	      }
	    }
	  }
	  if (!$dataok) return $message;
	  $fpointer = fopen($filename,"r");
	  if ($skipfirstline) 
		$dummy = fgetcsv($fpointer, 10*1024, $field_sep,
				 $enclose_char/*,$escape_char*/);
	  $rowcount = 0;
	  while ($line = fgetcsv($fpointer, 10*1024, $field_sep,
				$enclose_char/*,$escape_char*/)) {
	    if (count($line) != count($columns)) continue;
	    $data = array();
	    foreach ($columns as $i => $col) {
	      $data[$col] = $line[$i];
	    }
	    $olderror = $wpdb->show_errors(get_option('weblib_debugdb') != 'off');
	    $wpdb->insert(WEBLIB_PATRONS,$data,$formats);
	    $wpdb->show_errors($olderror);
	    $rowcount++;
	  }
	  fclose($fpointer);
	  $message .= '<p>';
          if ($rowcount == 1) {
            $message .= sprintf(__('%d patron inserted','weblibrarian'),$rowcount);
          } else {
            $message .= sprintf(__('%d patrons inserted','weblibrarian'),$rowcount);
          }
          $message .= '</p>';
	  return $message;
	}
	static function export_csv() {
	  global $wpdb;
	  $allcols = array("id","firstname","lastname","extraname",
				      "address1","address2","city","state",
				      "zip","telephone","outstandingfines",
				      "expiration");
	  $csv = '';
	  $comma = '';
	  foreach ($allcols as $col) {
	    $csv .= $comma.'"'.WEBLIB_Patron::csv_quote($col).'"';
	    $comma = ',';
	  }
	  $csv .= "\n";
	  $olderror = $wpdb->show_errors(get_option('weblib_debugdb') != 'off');
	  $data = $wpdb->get_results('SELECT * FROM '.WEBLIB_PATRONS,ARRAY_A);
	  $wpdb->show_errors($olderror);
	  foreach ($data as $row) {
	    $comma = '';
	    foreach ($allcols as $col) {
	      switch ($col) {
		case 'id':
		case 'outstandingfines':
		  $csv .= $comma.$row[$col];
		  break;
		default:
		  $csv .= $comma.'"'.WEBLIB_Patron::csv_quote(stripslashes($row[$col])).'"';
		  break;
	      }
	      $comma = ',';
	    }
	    $csv .= "\n";
	  }
	  return $csv;
	}
	static function csv_quote($string) {
	  return addcslashes($string,"\\".'"');
	}
}

class WEBLIB_ItemInCollection {

	private $thebarcode = '';
	private $record;                /* The Item Record itself */
	private $insert = true;
	private $dirty = true;

	function __construct($barcode = '') {
	  $this->thebarcode = $barcode;
	  if ($this->thebarcode != '') {
	    global $wpdb;
	    if (WEBLIB_ItemInCollection::IsItemInCollection($this->thebarcode)) {
	      $olderror = $wpdb->show_errors(get_option('weblib_debugdb') != 'off');
	      $sql = $wpdb->prepare('SELECT * FROM '. WEBLIB_COLLECTION .
				  ' where barcode = %s',$this->thebarcode);
	      $rawrecord = $wpdb->get_row($sql, 'ARRAY_A' );
	      $wpdb->show_errors($olderror);
	      //file_put_contents("php://stderr","*** WEBLIB_ItemInCollection::__construct: rawrecord = ".print_r($rawrecord,true)."\n");
	      $this->record = array();
	      foreach ($rawrecord as $k => $v) {
		if ($k == 'barcode') continue;
		$this->record[$k] = stripslashes($v);
	      }
	      //file_put_contents("php://stderr","*** WEBLIB_ItemInCollection::__construct: this->record = ".print_r($this->record,true)."\n");
	      $this->insert = false;
	      $this->dirty  = false;
	    } else {
	      trigger_error("Not a valid barcode: $this->thebarcode\n", E_USER_ERROR);
	    }
	  } else {
	    $this->record = array ('title' => "", 'author'    => "", 
				   'subject'   => "", 'description' => "", 
				   'category'  => "", 'media' => "", 
				   'publisher' => "", 'publocation' => "", 
				   'pubdate'   => "0000-01-01", 
				   'edition'   => "", 'isbn' => "", 
				   'type'      => "unknown", 'thumburl' => "",
				   'callnumber'   => "" );
	  }
	}

	function __destruct() {
	  if ($this->dirty) $this->store();
	}
	function title() {return $this->record['title'];}
	function set_title($t) {
          $this->record['title'] = substr($t,0,128);
          $this->dirty = true;
        }
	function author() {return $this->record['author'];}
	function set_author($t) {
          $this->record['author'] = substr($t,0,64);
          $this->dirty = true;
        }
	function subject() {return $this->record['subject'];}
	function set_subject($s) {
          $this->record['subject'] = substr($s,0,128);
          $this->dirty = true;
        }
	function description() {return $this->record['description'];}
	function set_description($s) {$this->record['description'] = $s;$this->dirty = true;}
	function category() {return $this->record['category'];}
	function set_category($s) {
          $this->record['category'] = substr($s,0,36);
          $this->dirty = true;
        }
	function media() {return $this->record['media'];}
	function set_media($s) {
          $this->record['media'] = substr($s,0,36);
          $this->dirty = true;
        }
	function publisher() {return $this->record['publisher'];}
	function set_publisher($s) {
          $this->record['publisher'] = substr($s,0,36);
          $this->dirty = true;
        }
	function publocation() {return $this->record['publocation'];}
	function set_publocation($s) {
          $this->record['publocation'] = substr($s,0,36);
          $this->dirty = true;
        }
	function pubdate() {
          $date = new DateTime($this->record['pubdate']);
          if (function_exists('nl_langinfo')) {
            $dfmt = nl_langinfo(D_FMT);
          } else {
            $dfmt = '%m/%d/%y';
          }
          if ($dfmt == '%m/%d/%y') {
            return $date->format('M/j/Y');
          } else {
            return $date->format('j/M/Y');
          }
        }
        function pubyear() {
          $date = new DateTime($this->record['pubdate']);
          return $date->format('Y');
        }
	function set_pubdate($s) {$this->record['pubdate'] = $s;$this->dirty = true;}
	function edition() {return $this->record['edition'];}
	function set_edition($s) {
          $this->record['edition'] = substr($s,0,36);
          $this->dirty = true;
        }
	function isbn() {return $this->record['isbn'];}
	function set_isbn($s) {
          $this->record['isbn'] = substr($s,0,20);
          $this->dirty = true;
        }
	function thumburl() {return $this->record['thumburl'];}
	function set_thumburl($s) {
          $this->record['thumburl'] = substr($s,0,256);
          $this->dirty = true;
        }
	function callnumber() {return $this->record['callnumber'];}
	function set_callnumber($s) {
          $this->record['callnumber'] = substr($s,0,36);
          $this->dirty = true;
        }
	function type() {return $this->record['type'];}
	function set_type($t) {
          $this->record['type'] = substr($t,0,16);
          $this->dirty = true;
        }
	function clean() {$this->dirty = false;}
	function BarCode() {return $this->thebarcode;}
	function isdirty() {return $this->dirty;}
	function delete() {
	  if ($this->insert) return true;
	  $this->dirty = false;
	  WEBLIB_ItemInCollection::DeleteItemByBarCode($this->thebarcode);
	  WEBLIB_ItemInCollection::DeleteKeywordsByBarCode($this->thebarcode);
	  WEBLIB_HoldItem::DeleteHeldItemByBarcode($this->thebarcode);
	  WEBLIB_OutItem::DeleteOutItemByBarcode($this->thebarcode);
	  $this->insert = true;
	  return true;
	}
	function indatabase() {
	  if ($this->insert) return false;
	  else               return true;
	}	


	function store($newbarcode = '') {
	  //file_put_contents("php://stderr","*** WEBLIB_ItemInCollection::store('$newbarcode')\n");
          //file_put_contents("php://stderr","*** WEBLIB_ItemInCollection::store: this->dirty = $this->dirty\n");
          if (!$this->dirty) return $this->thebarcode;
	  if ($this->record['title'] == '' ||
	      $this->record['author'] == '' ||
	      $this->record['type'] == '' ||
	      $this->record['subject'] == '') return(-1);
	  global $wpdb;
          //file_put_contents("php://stderr","*** WEBLIB_ItemInCollection::store: this->insert = $this->insert\n");
	  if ($this->insert) {
	    if ($newbarcode != '') {
	      $this->thebarcode = $newbarcode;
	      $insertrec = array(
		'barcode' => $newbarcode,
		'title'   => $this->record['title'],
		'author'  => $this->record['author'],
		'subject' => $this->record['subject'],
		'description' => $this->record['description'],
		'category' => $this->record['category'],
		'media' => $this->record['media'],
		'publisher' => $this->record['publisher'],
		'publocation' => $this->record['publocation'],
		'pubdate' => $this->record['pubdate'],
		'edition' => $this->record['edition'],
		'isbn' => $this->record['isbn'],
		'type'    => $this->record['type'],
		'thumburl'    => $this->record['thumburl'],
		'callnumber'  => $this->record['callnumber']
		);
	      $insertfmt = array('%s','%s','%s','%s','%s','%s','%s','%s',
				 '%s','%s','%s','%s','%s','%s','%s');
	    } else {
              //file_put_contents("php://stderr","*** WEBLIB_ItemInCollection::store: newbarcode is empty\n");
	      $olderror = $wpdb->show_errors(get_option('weblib_debugdb') != 'off');
	      $lastbarcode = $wpdb->get_var('SELECT barcode FROM '.WEBLIB_COLLECTION." order by barcode desc limit 1");
	      //file_put_contents("php://stderr","*** WEBLIB_ItemInCollection::store: lastbarcode = '$lastbarcode'\n");
              $wpdb->show_errors($olderror);
              $newbarcode = WEBLIB_ItemInCollection::incrstring($lastbarcode);
              //file_put_contents("php://stderr","*** WEBLIB_ItemInCollection::store: newbarcode = '$newbarcode'\n");
              while (WEBLIB_ItemInCollection::IsItemInCollection($newbarcode)) {
                $newbarcode = WEBLIB_ItemInCollection::incrstring($newbarcode);
                file_put_contents("php://stderr","*** WEBLIB_ItemInCollection::store: newbarcode = '$newbarcode'\n");
              }
	      $this->thebarcode = $newbarcode;
	      //file_put_contents("php://stderr","*** WEBLIB_ItemInCollection::store: this->thebarcode = '$this->thebarcode'\n");
              $insertrec = array(
	      	'barcode' => $this->thebarcode,
		'title'   => $this->record['title'],
		'author'  => $this->record['author'],
		'subject' => $this->record['subject'],
		'description' => $this->record['description'],
		'category' => $this->record['category'],
		'media' => $this->record['media'],
		'publisher' => $this->record['publisher'],
		'publocation' => $this->record['publocation'],
		'pubdate' => $this->record['pubdate'],
		'edition' => $this->record['edition'],
		'isbn' => $this->record['isbn'],
		'type'    => $this->record['type'],
		'thumburl' => $this->record['thumburl'],
		'callnumber'  => $this->record['callnumber']);
	      $insertfmt = array('%s', '%s','%s','%s','%s','%s','%s','%s','%s',
				 '%s','%s','%s','%s','%s','%s');
	    }
	    $olderror = $wpdb->show_errors(get_option('weblib_debugdb') != 'off');
	    $result = $wpdb->insert(WEBLIB_COLLECTION, $insertrec, $insertfmt);
	    $wpdb->show_errors($olderror);
	    $this->insert = false;
	    $this->dirty  = false;
	  } else {
	    $updaterec = array(
		'title'   => $this->record['title'],
		'author'  => $this->record['author'],
		'subject' => $this->record['subject'],
		'description' => $this->record['description'],
		'category' => $this->record['category'],
		'media' => $this->record['media'],
		'publisher' => $this->record['publisher'],
		'publocation' => $this->record['publocation'],
		'pubdate' => $this->record['pubdate'],
		'edition' => $this->record['edition'],
		'isbn' => $this->record['isbn'],
		'type'    => $this->record['type'],
		'thumburl' => $this->record['thumburl'],
		'callnumber'  => $this->record['callnumber']);
	    $updatefmt = array('%s','%s','%s','%s','%s','%s','%s','%s',
			       '%s','%s','%s','%s','%s','%s');
	    $where = array('barcode' => $this->thebarcode );
	    $wherefmt = '%s';
	    $olderror = $wpdb->show_errors(get_option('weblib_debugdb') != 'off');
	    //file_put_contents("php://stderr","*** WEBLIB_ItemInCollection::store(): updaterec = ".print_r($updaterec,true)."\n");
	    $wpdb->update(WEBLIB_COLLECTION, $updaterec, $where, $updatefmt, 
					   $wherefmt);
	    $wpdb->show_errors($olderror);
	    $this->dirty  = false;
	  }
	  return $this->thebarcode;
	}

        static function incrstring($string) {
	  if ($string == '') {return '0000000000000001';}
          $digits = str_split($string);
          $index = count($digits) - 1;
          $next = ord($digits[$index]) + 1;
	  if (chr($next) > '9' && chr($next) < 'A') $next = ord('A');
          while (chr($next) > 'Z' && $index >= 0) {
            $digits[$index] = '0';
            $index--;
	    if ($index >= 0) {
	      $next = ord($digits[$index]) + 1;
	      if (chr($next) > '9' && chr($next) < 'A') $next = ord('A');
	    } else {
	      $next = ord(chr('0')+1);
	    }
          }
	  if ($index >= 0) {
	    $digits[$index] = chr($next);
	    return implode($digits);
	  } else {
	    return chr($next).implode($digits);
	  }
        }
	static function AllBarCodes($orderby = 'barcode', $order = 'ASC') {
	  global $wpdb;
	  $olderror = $wpdb->show_errors(get_option('weblib_debugdb') != 'off');
	  $sql = $wpdb->prepare('SELECT barcode FROM '.
                                WEBLIB_COLLECTION.
                                ' order by %s %s',$orderby,$order);
          file_put_contents("php://stderr","*** WEBLIB_ItemInCollection::AllBarCodes: sql = $sql\n");
          $rawbarcodes = $wpdb->get_col($sql);
	  $wpdb->show_errors($olderror);
	  $barcodes = array();
	  foreach ($rawbarcodes as $bc) {
	    $barcodes[] = stripslashes($bc);
	  }
	  return $barcodes;
	}
	static function DeleteItemByBarCode($barcode) {
	  global $wpdb;
	  $olderror = $wpdb->show_errors(get_option('weblib_debugdb') != 'off');
	  $sql = $wpdb->prepare('DELETE FROM '.WEBLIB_COLLECTION.
				' where barcode = %s',$barcode);
	  $wpdb->query($sql);
	  $wpdb->show_errors($olderror);
	}
	static function DeleteKeywordsByBarCode($barcode) {
	  global $wpdb;
	  $olderror = $wpdb->show_errors(get_option('weblib_debugdb') != 'off');
	  $sql = $wpdb->prepare('DELETE FROM '.WEBLIB_KEYWORDS.
				' where barcode = %s',$barcode);
	  $wpdb->query($sql);
	  $wpdb->show_errors($olderror);
	}
	static function FindItemByTitle($pattern,$orderby = 'barcode', $order = 'ASC') {
	  global $wpdb;
	  $olderror = $wpdb->show_errors(get_option('weblib_debugdb') != 'off');
	  $sql = $wpdb->prepare(
			'SELECT barcode FROM '.
			WEBLIB_COLLECTION.
			' where title like %s order by '.$orderby.' '.$order,
			$pattern);
	  $rawbarcodes = $wpdb->get_col($sql);
	  $wpdb->show_errors($olderror);
	  $barcodes = array();
	  foreach ($rawbarcodes as $bc) {
	    $barcodes[] = stripslashes($bc);
	  }
	  return $barcodes;
	}
	static function FindItemByAuthor($pattern,$orderby = 'barcode', $order = 'ASC') {
	  global $wpdb;
	  $olderror = $wpdb->show_errors(get_option('weblib_debugdb') != 'off');
	  $sql = $wpdb->prepare(
			'SELECT barcode FROM '.
			WEBLIB_COLLECTION.
			' where author like %s order by '.$orderby.' '.$order,$pattern);
	  $rawbarcodes = $wpdb->get_col($sql);
	  $wpdb->show_errors($olderror);
	  $barcodes = array();
	  foreach ($rawbarcodes as $bc) {
	    $barcodes[] = stripslashes($bc);
	  }
	  return $barcodes;
	}
	static function FindItemBySubject($pattern,$orderby = 'barcode', $order = 'ASC') {
	  global $wpdb;
	  $olderror = $wpdb->show_errors(get_option('weblib_debugdb') != 'off');
	  $sql = $wpdb->prepare(
			'SELECT barcode FROM '.
			WEBLIB_COLLECTION.
			' where subject like %s order by '.$orderby.' '.$order,$pattern);
	  $rawbarcodes = $wpdb->get_col($sql);
	  $wpdb->show_errors($olderror);
	  $barcodes = array();
	  foreach ($rawbarcodes as $bc) {
	    $barcodes[] = stripslashes($bc);
	  }
	  return $barcodes;
	}
	static function FindItemByISBN($pattern,$orderby = 'barcode', $order = 'ASC') {
	  global $wpdb;
	  $olderror = $wpdb->show_errors(get_option('weblib_debugdb') != 'off');
	  $sql = $wpdb->prepare(
			'SELECT barcode FROM '.
			WEBLIB_COLLECTION.
			' where isbn like %s order by '.$orderby.' '.$order,$pattern);
	  $rawbarcodes = $wpdb->get_col($sql);
	  $wpdb->show_errors($olderror);
	  $barcodes = array();
	  foreach ($rawbarcodes as $bc) {
	    $barcodes[] = stripslashes($bc);
	  }
	  return $barcodes;
	}
        static function FindItemByKeyword($pattern,$orderby = 'barcode', $order = 'ASC') {
	  global $wpdb;
	  $olderror = $wpdb->show_errors(get_option('weblib_debugdb') != 'off');
	  $sql = $wpdb->prepare(
			'SELECT DISTINCT K.barcode FROM '.
			WEBLIB_KEYWORDS.' K, '.WEBLIB_COLLECTION.' C'.
			' where keyword like %s && K.barcode = C.barcode'.
			' order by C.'.$orderby.' '.$order,$pattern);
	  $rawbarcodes = $wpdb->get_col($sql); 
	  $wpdb->show_errors($olderror);
	  $barcodes = array();
	  foreach ($rawbarcodes as $bc) {
	    $barcodes[] = stripslashes($bc);
	  }
	  return $barcodes;
	}
	function addkeywordto($keyword) {
	  global $wpdb;
	  $olderror = $wpdb->show_errors(get_option('weblib_debugdb') != 'off');
	  $sql = $wpdb->prepare(
			'SELECT COUNT(barcode) FROM '.WEBLIB_KEYWORDS.
			' where keyword = %s AND barcode = %s',
			$keyword,$this->barcode());
	  $result = $wpdb->get_var($sql);
	  $wpdb->show_errors($olderror);
	  if ($result > 0) {
	    return false;
	  } else {
	    $insertrec = array('keyword' => $keyword, 'barcode' => $this->barcode());
	    $insertfmt = array('%s','%s');
	    $olderror = $wpdb->show_errors(get_option('weblib_debugdb') != 'off');
	    $wpdb->insert(WEBLIB_KEYWORDS, $insertrec, $insertfmt);
	    $wpdb->show_errors($olderror);
	    return true;
	  }
	}
	static function AddKeywordToItem($keyword, $barcode) {
	  global $wpdb;
	  $olderror = $wpdb->show_errors(get_option('weblib_debugdb') != 'off');
	  $sql = $wpdb->prepare(
			'SELECT COUNT(barcode) FROM '.WEBLIB_KEYWORDS.
			' where keyword = %s AND barcode = %s',
			$keyword,$barcode);
	  $result = $wpdb->get_var($sql);
	  $wpdb->show_errors($olderror);
	  if ($result > 0) {
	    return false;
	  } else {
	    $insertrec = array('keyword' => $keyword, 'barcode' => $barcode);
	    $insertfmt = array('%s','%s');
	    $olderror = $wpdb->show_errors(get_option('weblib_debugdb') != 'off');
	    $wpdb->insert(WEBLIB_KEYWORDS, $insertrec, $insertfmt);
	    $wpdb->show_errors($olderror);
	    return true;
	  }
	}
	function removekeywordfrom($keyword) {
	  global $wpdb;
	  $olderror = $wpdb->show_errors(get_option('weblib_debugdb') != 'off');
	  $sql = $wpdb->prepare(
			'SELECT COUNT(barcode) FROM '.WEBLIB_KEYWORDS.
			' where keyword = %s AND barcode = %s',
			$keyword,$this->barcode());
	  $result = $wpdb->get_var($sql);
	  $wpdb->show_errors($olderror);
	  if ($result == 0) {
	    return false;
	  } else {
	    $olderror = $wpdb->show_errors(get_option('weblib_debugdb') != 'off');
	    $sql = $wpdb->prepare('DELETE FROM '.WEBLIB_KEYWORDS.
				  ' where keyword = %s AND barcode = %s',
				  $keyword,$this->barcode());
	    $wpdb->query($sql);
	    $wpdb->show_errors($olderror);
	    return true;
	  }
	}
	static function RemoveKeywordFromItem($keyword, $barcode) {
	  global $wpdb;
	  $olderror = $wpdb->show_errors(get_option('weblib_debugdb') != 'off');
	  $sql = $wpdb->prepare(
			'SELECT COUNT(barcode) FROM '.WEBLIB_KEYWORDS.
			' where keyword = %s AND barcode = %s',
			$keyword,$barcode);
	  $result = $wpdb->get_var($sql);
	  $wpdb->show_errors($olderror);
	  if ($result == 0) {
	    return false;
	  } else {
	    $olderror = $wpdb->show_errors(get_option('weblib_debugdb') != 'off');
	    $sql = $wpdb->prepare('DELETE FROM '.WEBLIB_KEYWORDS.
				  ' where keyword = %s AND barcode = %s',
				  $keyword,$barcode);
	    $wpdb->query($sql);
	    $wpdb->show_errors($olderror);
	    return true;
	  }
	}
	function keywordsof() {
	  global $wpdb;
	  $olderror = $wpdb->show_errors(get_option('weblib_debugdb') != 'off');
	  $sql = $wpdb->prepare('SELECT keyword FROM '.WEBLIB_KEYWORDS.
				' where barcode = %s',$this->barcode());
	  $rawkeywords = $wpdb->get_col($sql);
	  $wpdb->show_errors($olderror);
	  $keywords = array();
	  foreach ($rawkeywords as $k) {
	    $keywords[] = stripslashes($k);
	  }
	  return $keywords;
	} 
	static function KeywordsOfItem($barcode) {
	  global $wpdb;
	  $olderror = $wpdb->show_errors(get_option('weblib_debugdb') != 'off');
	  $sql = $wpdb->prepare('SELECT keyword FROM '.WEBLIB_KEYWORDS.
				' where barcode = %s',$barcode);
	  $rawkeywords = $wpdb->get_col($sql);
	  $wpdb->show_errors($olderror);
	  $keywords = array();
	  foreach ($rawkeywords as $k) {
	    $keywords[] = stripslashes($k);
	  }
	  return $keywords;
	}
	function checkout($patronid, $source, $duedate) {
	  $outitem = new WEBLIB_OutItem();
	  $outitem->set_barcode($this->barcode());
	  $outitem->set_title($this->title());
	  $outitem->set_source($source);
	  $outitem->set_type($this->type());
	  $outitem->set_patronid($patronid);
	  $outitem->set_dateout(date('Y-m-d',time()));
	  $outitem->set_datedue($duedate);
	  WEBLIB_Statistic::IncrementCheckoutCount($this->type(),date('Y',time()),date('m',time()));
	  return ($outitem->store());	
	}
	function hold($patronid, $source, $dueexpire) {
	  $holditem = new WEBLIB_HoldItem();
	  $holditem->set_barcode($this->barcode());
	  $holditem->set_title($this->title());
	  $holditem->set_source($source);
	  $holditem->set_type($this->type());
	  $holditem->set_patronid($patronid);
	  $holditem->set_dateheld(date('Y-m-d',time()));
	  $holditem->set_dateexpire($dueexpire);
	  return ($holditem->store());	
	}
	static function IsItemInCollection($barcode) {
	  global $wpdb;
	  $olderror = $wpdb->show_errors(get_option('weblib_debugdb') != 'off');
	  $sql = $wpdb->prepare('SELECT count(barcode) FROM '. WEBLIB_COLLECTION .
				' where barcode = %s',$barcode);
	  $answer =  $wpdb->get_var($sql) > 0;
	  $wpdb->show_errors($olderror);
	  return $answer;
	}
	static function upload_csv($filename,$use_csv_headers,$field_sep,
				   $enclose_char/*,$escape_char*/) {
	  //file_put_contents("php://stderr","*** WEBLIB_ItemInCollection::upload_csv('".$filename."',".$use_csv_headers.",'".$field_sep."','".$enclose_char."')\n");
	  global $wpdb;
	  $message = '';
	  $skipfirstline = false;    
	  $fpointer = fopen($filename,"r");
	  $firstline = fgetcsv($fpointer, 10*1024, $field_sep,$enclose_char/*,$escape_char*/);
	  fclose($fpointer);
	  $dataok = true;
	  $columns = array();
	  $generatebarcode = true;
	  if ($use_csv_headers) {
	    $skipfirstline = true;
	    foreach($firstline as $h) {
	      if (trim($h) != "") {
		$col = strtolower(trim($h));
	        if (in_array($col,array('barcode','title','author','subject',
					'description','category','media',
					'publisher','publocation','pubdate',
					'edition','isbn','type','thumburl',
					'keywords','callnumber'))) {
		  $columns[] = $col;
		  if ($col == 'barcode') $generatebarcode = false;
		} else {
		  $message .= '<p><span id="error">Undefined column: '.$h.'</span></p>';
		  $dataok = false;
		}
	      }
	    }
	    $manditorycolcount = 0;
	    foreach ($columns as $col) {
	      if (in_array($col,array('title','author','subject','type'))) 
		$manditorycolcount++;
	    }
	    if ($manditorycolcount < 4) {
	      $message .= '<p><span id="error">Some mandatory columns are missing!</span></p>';
	      $dataok = false;
	    }
	  } else {
	    switch (count($firstline)) {
	      case 4: 
		$columns = array('title','author','subject','type');
		break;
	      case 5:
		$columns = array('barcode','title','author','subject','type');
		$generatebarcode = false;
		break;
	      case 6:
		$columns = array('barcode','title','author','subject',
				'description','type');
		$generatebarcode = false;
		break;
	      case 7:
		$columns = array('barcode','title','author','subject',
				'description','category','type');
		$generatebarcode = false;
		break;
	      case 8:
		$columns = array('barcode','title','author','subject',
				'description','category','media','type');
		$generatebarcode = false;
		break;
	      case 9:
		$columns = array('barcode','title','author','subject',
				'description','category','media','publisher',
				'type');
		$generatebarcode = false;
		break;
	      case 10:
		$columns = array('barcode','title','author','subject',
				'description','category','media','publisher',
				'publocation','type');
		$generatebarcode = false;
		break;
	      case 11:
		$columns = array('barcode','title','author','subject',
				'description','category','media','publisher',
				'publocation','pubdate','type');
		$generatebarcode = false;
		break;
	      case 12:
		$columns = array('barcode','title','author','subject',
				'description','category','media','publisher',
				'publocation','pubdate','edition','type');
		$generatebarcode = false;
		break;
	      case 13:
		$columns = array('barcode','title','author','subject',
				'description','category','media','publisher',
				'publocation','pubdate','edition','isbn',
				'type');
		$generatebarcode = false;
		break;
	      case 14:
		$columns = array('barcode','title','author','subject',
				'description','category','media','publisher',
				'publocation','pubdate','edition','isbn',
				'type','thumburl');
		$generatebarcode = false;
		break;
	      case 15:
		$columns = array('barcode','title','author','subject',
				'description','category','media','publisher',
				'publocation','pubdate','edition','isbn',
				'type','thumburl','keywords');
		$generatebarcode = false;
		break;
	      case 16:
		$columns = array('barcode','title','author','subject',
				 'description','category','media','publisher',
				 'publocation','pubdate','edition','isbn',
				 'type','thumburl','callnumber','keywords');
	      default:
		$message .= '<p><span id="error">Bad number of columns: '.
				count($firstline).'</span></p>';
		$dataok = false;
	    }
	  }
	  if (!$dataok) return $message;
	  $fpointer = fopen($filename,"r");
	  if ($skipfirstline) 
		$dummy = fgetcsv($fpointer, 10*1024, $field_sep,
				 $enclose_char/*,$escape_char*/);
	  $rowcount = 0;
	  $numberfixedbc = 0;
	  if ($generatebarcode) {
	    $olderror = $wpdb->show_errors(get_option('weblib_debugdb') != 'off');
	    $lastbarcode = $wpdb->get_var('SELECT barcode FROM '.
                                          WEBLIB_COLLECTION.
                                          " order by barcode desc limit 1");
	    $wpdb->show_errors($olderror);
	  }
	  //file_put_contents("php://stderr","*** WEBLIB_ItemInCollection::upload_csv: columns is ".print_r($columns,true)."\n");
	  while ($line = fgetcsv($fpointer, 10*1024, $field_sep,
				$enclose_char/*,$escape_char*/)) {
            //file_put_contents("php://stderr","*** WEBLIB_ItemInCollection::upload_csv: line is ".print_r($line,true)." and count(columns) is ".count($columns)."\n");
	    if (count($line) != count($columns)) {
              $message .= '<p class="error">Data line has the wrong number of columns '.count($line).' instead of '.count($columns).' : '.$line[0].','.$line[1].','.$line[2].'...';
              if (count($line) > count($columns)) {
                $message .= ' (excess columns ignored)</p>';
              } else {
                $message .= ' line skipped</p>';
                continue;
              }
            }
	    $data = array();
	    $keywords = array();
	    foreach ($columns as $i => $col) {
	      if ($col == 'keywords') {
		$keywords = explode(',',$line[$i]);
	      } else {
		$data[$col] = $line[$i];
	      }
	    }
	    if ($generatebarcode) {
	      $lastbarcode = WEBLIB_ItemInCollection::incrstring($lastbarcode);
	      while (WEBLIB_ItemInCollection::IsItemInCollection($lastbarcode)) {
                $lastbarcode = WEBLIB_ItemInCollection::incrstring($lastbarcode);
              }
              $data['barcode'] = $lastbarcode;
	    } else {
	      if (!preg_match('/^[a-zA-Z0-9]+$/',$data['barcode'])) {
		$message .= '<p class="error">Replaced bad barcode: '.$data['barcode'].' with ';
		$lastbarcode = $wpdb->get_var('SELECT barcode FROM '.
                                              WEBLIB_COLLECTION.
                                              " order by barcode desc limit 1");
		$data['barcode'] = WEBLIB_ItemInCollection::incrstring($lastbarcode);
		$message .= $data['barcode'].'.</p>';
		$numberfixedbc++;
	      }
	    }		
	    //file_put_contents("php://stderr","*** WEBLIB_ItemInCollection::upload_csv: data is ".print_r($data,true)."\n");
            foreach ($data as $field => $value) {
              switch ($field) {
              case 'title': 
              case 'subject':
                if (strlen($value) > 128) {
                  $data[$field] = substr($value,0,128);
                }
                break;
              case 'author':
                if (strlen($value) > 64) {
                  $data[$field] = substr($value,0,64);
                }
                break;
              case 'category':
              case 'media':
              case 'publisher':
              case 'publocation':
              case 'edition':
              case 'callnumber':
                if (strlen($value) > 36) {
                  $data[$field] = substr($value,0,36);
                }
                break;
              case 'isbn':
                if (strlen($value) > 20) {
                  $data[$field] = substr($value,0,20);
                }
                break;
              case 'type':
                if (strlen($value) > 16) {
                  $data[$field] = substr($value,0,16);
                }
                break;
              case 'thumburl':
                if (strlen($value) > 256) {
                  $data[$field] = substr($value,0,256);
                }
                break;
              }
            }
	    $olderror = $wpdb->show_errors(get_option('weblib_debugdb') != 'off');
	    $result = $wpdb->insert(WEBLIB_COLLECTION,$data,'%s');
	    $wpdb->show_errors($olderror);
            if (!$result) continue;
	    if (count($keywords) > 0) {
	      foreach ($keywords as $keyword) {
		WEBLIB_ItemInCollection::AddKeywordToItem($keyword,
							  $data['barcode']);
	      }
	    }
	    $rowcount++;
	  }
	  fclose($fpointer);
	  $message .= '<p>'.$rowcount.' item'.($rowcount == 1?'':'s').' inserted.</p>';
	  if ($numberfixedbc > 0) {
	    $message .= '<p class="error">'.$numberfixedbc.' bad barcodes replaced.</p>';
	  }
	  return $message;
	}
	static function export_csv() {
	  global $wpdb;
	  $allcols = array('barcode','title','author','subject',
					'description','category','media',
					'publisher','publocation','pubdate',
					'edition','isbn','type','thumburl',
					'callnumber','keywords');
	  $csv = '';
	  $comma = '';
	  foreach ($allcols as $col) {
	    $csv .= $comma.'"'.WEBLIB_Patron::csv_quote($col).'"';
	    $comma = ',';
	  }
	  $csv .= "\n";
	  $olderror = $wpdb->show_errors(get_option('weblib_debugdb') != 'off');
	  $data = $wpdb->get_results('SELECT * FROM '.WEBLIB_COLLECTION,ARRAY_A);
	  $wpdb->show_errors($olderror);
	  foreach ($data as $row) {
	    $comma = '';
	    foreach ($allcols as $col) {
	      switch ($col) {
		case 'keywords':
		  $csv .= $comma.'"'.WEBLIB_Patron::csv_quote(
			stripslashes(
				implode(',',
					WEBLIB_ItemInCollection::KeywordsOfItem(
						$row['barcode'])
					)
				     )
			).'"';
		  break;
		default:
		  $csv .= $comma.'"'.WEBLIB_Patron::csv_quote(
					stripslashes($row[$col])).'"';
		  break;
	      }
	      $comma = ',';
	    }
	    $csv .= "\n";
	  }
	  return $csv;
	}
	static function fixBrokenBarcodes () {
	  global $wpdb;
	  $barcodes = WEBLIB_ItemInCollection::AllBarCodes();
	  $fixed = 0;
	  $updatefmt = array('%s');
	  $wherefmt = '%s';
	  foreach ($barcodes as $bc) {
	    if (!preg_match('/^[a-zA-Z0-9]+$/',$bc)) {
	      $where = array('barcode' => $bc);
	      $olderror = $wpdb->show_errors(get_option('weblib_debugdb') != 'off');
	      $lastbarcode = $wpdb->get_var('SELECT barcode FROM '.
                                            WEBLIB_COLLECTION.
                                            " order by barcode desc limit 1");
	      $wpdb->show_errors($olderror);
	      $newbc = WEBLIB_ItemInCollection::incrstring($lastbarcode);
	      $updaterec = array('barcode' => $newbc);
	      $olderror = $wpdb->show_errors(get_option('weblib_debugdb') != 'off');
	      $wpdb->update(WEBLIB_COLLECTION, $updaterec, $where, $updatefmt,$wherefmt);
	      $wpdb->update(WEBLIB_KEYWORDS, $updaterec, $where, $updatefmt,$wherefmt);
	      $wpdb->update(WEBLIB_OUTITEMS, $updaterec, $where, $updatefmt,$wherefmt);
	      $wpdb->update(WEBLIB_HOLDITEMS, $updaterec, $where, $updatefmt,$wherefmt);
	      $wpdb->show_errors($olderror);
	      $fixed++;
	    }	
	  }
	  return $fixed;
	}
}

class WEBLIB_OutItem {
	private $record;
	private $thetransaction = 0;
	private $insert = true;
	private $dirty = true;

	function __construct($transaction = 0) {
	  $this->thetransaction = $transaction;
	  if ($this->thetransaction == 0) {
	    $this->insert = true;
	    $this->dirty  = true;
	    $this->record = array(
		'barcode'   => '',
		'title'     => '',
		'source'    => '',
		'type'      => '',
		'patronid'  => 0,
		'dateout'   => '',
		'datedue'    => '');
	  } else {
	    global $wpdb;
	    $olderror = $wpdb->show_errors(get_option('weblib_debugdb') != 'off');
	    $sql = $wpdb->prepare(
		'SELECT barcode, title, source, type, patronid, dateout,'.
		'datedue FROM ' . 
		WEBLIB_OUTITEMS . ' where transaction = %d',
		$this->thetransaction);
	    $rawrecord = $wpdb->get_row($sql, 'ARRAY_A' );
	    $wpdb->show_errors($olderror);
	    $this->record = array();
	    foreach ($rawrecord as $k => $v) {
	      $this->record[$k] = stripslashes($v);
	    }
	    $this->insert = false;
	    $this->dirty  = false;
	  }
	}
	function __destruct() {
	  if ($this->dirty) $this->store();
	}
	function barcode() {return $this->record['barcode'];}
	function set_barcode($v) {$this->record['barcode'] = $v; $this->dirty = true;}
	function title() {return $this->record['title'];}
	function set_title($v) {$this->record['title'] = $v; $this->dirty = true;}
	function source() {return $this->record['source'];}
	function set_source($v) {$this->record['source'] = $v; $this->dirty = true;}
	function type() {return $this->record['type'];}
	function set_type($v) {$this->record['type'] = $v; $this->dirty = true;}
	function patronid() {return $this->record['patronid'];}
	function set_patronid($v) {$this->record['patronid'] = $v; $this->dirty = true;}
	function dateout() {return $this->record['dateout'];}
	function set_dateout($v) {$this->record['dateout'] = $v; $this->dirty = true;}
	function datedue() {return $this->record['datedue'];}
	function set_datedue($v) {$this->record['datedue'] = $v; $this->dirty = true;}
	function store($newtransaction = 0) {
	  if (!$this->dirty) return $this->thetransaction;
	  //file_put_contents("php://stderr","*** WEBLIB_OutItem::store: this->record = ".print_r($this->record,true)."\n");
	  if ($this->record['barcode'] == '' ||
	      $this->record['title'] == '' ||
	      $this->record['source'] == '' ||
	      $this->record['type'] == '' ||
	      $this->record['patronid'] == 0 ||
	      ! is_numeric($this->record['patronid']) ||
	      $this->record['dateout'] == '' ||
	      $this->record['datedue'] == '') return(-1);
	  global $wpdb;
	  if ($this->insert) {
	    if ($newtransaction != 0) {
	      $insertrec = array(
		'transaction' => $newtransaction,
		'barcode' => $this->record['barcode'],
		'title' => $this->record['title'],
		'source' => $this->record['source'],
		'type' => $this->record['type'],
		'patronid' => $this->record['patronid'],
		'dateout' => $this->record['dateout'],
		'datedue' => $this->record['datedue']);
	      $insertfmt = array('%d','%s','%s','%s','%s','%d','%s','%s');
	    } else {
	      $insertrec = array(
		'barcode' => $this->record['barcode'],
		'title' => $this->record['title'],
		'source' => $this->record['source'],
		'type' => $this->record['type'],
		'patronid' => $this->record['patronid'],
		'dateout' => $this->record['dateout'],
		'datedue' => $this->record['datedue']);
	      $insertfmt = array('%s','%s','%s','%s','%d','%s','%s');
	    }
	    $olderror = $wpdb->show_errors(get_option('weblib_debugdb') != 'off');
	    $wpdb->insert(WEBLIB_OUTITEMS, $insertrec, $insertfmt);
	    $wpdb->show_errors($olderror);
	    $this->thetransaction = $wpdb->insert_id;
	    $this->insert = false;
	    $this->dirty  = false;
	  } else {
	    $updaterec = array(
		'barcode' => $this->record['barcode'],
		'title' => $this->record['title'],
		'source' => $this->record['source'],
		'type' => $this->record['type'],
		'patronid' => $this->record['patronid'],
		'dateout' => $this->record['dateout'],
		'datedue' => $this->record['datedue']);
	    $updatefmt = array('%s','%s','%s','%s','%d','%s','%s');
	    $where = array('transaction' => $this->thetransaction );
	    $wherefmt = '%d';
	    $olderror = $wpdb->show_errors(get_option('weblib_debugdb') != 'off');
	    $wpdb->update(WEBLIB_OUTITEMS, $updaterec, $where, $updatefmt,
					    $wherefmt);
	    $wpdb->show_errors($olderror);
	    $this->dirty  = false;
	  }
	  return $this->thetransaction;
	}
	function indatabase() {
	  if ($this->insert) return false;
	  else 		     return true;
	}
	function delete() {
	  if ($this->insert) return true;
	  $this->dirty = false;
	  WEBLIB_OutItem::DeleteOutItemByTransaction($this->thetransaction);
	  $this->insert = true;
	  return true;
	}
	function clean() {$this->dirty = false;}
	function transaction() {return $this->thetransaction;}
	function isdirty() {return $this->dirty;}
	function checkin($dailyfine) {
	  $daysoverdue = (mysql2date('U',$this->datedue) - time()) / (24*60*60);
	  if ($daysoverdue > 0) { /* item is overdue! */
	    $fine = $dailyfine * $daysoverdue;
	    $patron = new WEBLIB_Patron($this->patronid());
	    $patron->set_outstandingfines($patron->set_outstandingfines()+$fine);
	    $patron->store();
	  }
	  $this->delete();
	}
	static function DeleteOutItemByTransaction($transaction) {
	  global $wpdb;
	  $olderror = $wpdb->show_errors(get_option('weblib_debugdb') != 'off');
	  $sql = $wpdb->prepare('DELETE FROM '.WEBLIB_OUTITEMS.
			 ' where transaction = %d',$transaction);
	  $wpdb->query($sql);
	  $wpdb->show_errors($olderror);
	}
	static function DeleteCheckoutsByPatronId($patronid) {
	  global $wpdb;
	  $olderror = $wpdb->show_errors(get_option('weblib_debugdb') != 'off');
	  $sql = $wpdb->prepare('DELETE FROM '.WEBLIB_OUTITEMS.
				' where patronid = %d',$patronid);
	  $wpdb->query($sql);
	  $wpdb->show_errors($olderror);
	}
	static function DeleteOutItemByBarcode($barcode) {
	  global $wpdb;
	  $olderror = $wpdb->show_errors(get_option('weblib_debugdb') != 'off');
	  $sql = $wpdb->prepare('DELETE FROM '.WEBLIB_OUTITEMS.
			 ' where barcode = %s',$barcode);
	  $wpdb->query($sql);
	  $wpdb->show_errors($olderror);
	}
	static function RenewByBarcodeAndPatronID($barcode,$patronid = '*') {
	  $outitem = WEBLIB_OutItem::OutItemByBarcode($barcode);
	  if ($outitem == null) {
	    return __('The item is not checked out!','weblibrarian');
	  }
	  if ($patronid != '*' && $outitem->patronid() != $patronid) {
	    return sprintf(__('You cannot renew %s!','weblibrarian'),
                           $outitem->title());
	  }
	  $numberofholds = WEBLIB_HoldItem::HoldCountsOfBarcode($outitem->barcode());
	  if ($numberofholds > 0) {
            return sprintf(_n('You cannot renew %s, it has %d hold.',
                              'You cannot renew %s, it has %d holds.',
                              $numberofholds,'weblibrarian'),
                           $outitem->title(),$numberofholds);
	  }
	  $type = new WEBLIB_Type($outitem->type());
	  $currentdue = strtotime($outitem->datedue());
	  $originalout = strtotime($outitem->dateout());
	  $newduedate = $currentdue + ($type->loanperiod() * 24 * 60 * 60);
	  $totalloandays = ($newduedate - $originalout) / (24 * 60 * 60); 
	  $renewals = $totalloandays / $type->loanperiod();
	  unset($type);
	  if ($renewals > 3) {
	    return sprintf(__('Maximum number of renewals reached for %s!',
                              'weblibrarian'),$outitem->title());
	  } else {
	    $outitem->set_datedue($duedate);
	    $outitem->store();
	  }
	  return sprintf(__('%s Renewed.','weblibrarian'),$outitem->title());	  
	}
	static function AllOutItems() {
	  global $wpdb;
	  $olderror = $wpdb->show_errors(get_option('weblib_debugdb') != 'off');
	  $transactions = $wpdb->get_col('SELECT transaction FROM '.
						WEBLIB_OUTITEMS.
						' order by transaction');
	  $wpdb->show_errors($olderror);
	  return $transactions;
	}
	static function RemoveOrphanCheckouts() {
	  global $wpdb;
	  $transactions = WEBLIB_OutItem::AllOutItems();
	  $orphans = array();
	  foreach ($transactions as $trans) {
	    $out = new WEBLIB_OutItem($trans);
	    if (!WEBLIB_ItemInCollection::IsItemInCollection($out->barcode())) {
	      $orphans[] = array('transaction' => $trans,
				 'barcode' => $out->barcode(),
				 'patronid' => $out->patronid(),
				 'title' => $out->title(),
				 'dateout' => $out->dateout(),
				 'datedue' => $out->datedue());
	      $out->delete();
	    } else if (!WEBLIB_Patron::ValidPatronID($out->patronid())) {
	      $orphans[] = array('transaction' => $trans,
				 'barcode' => $out->barcode(),
				 'patronid' => $out->patronid(),
				 'title' => $out->title(),
				 'dateout' => $out->dateout(),
				 'datedue' => $out->datedue());
	      $out->delete();
	    }
	  }
	  return $orphans;
        }
	static function OutItemsOfPatron($patronid) {
	  global $wpdb;
	  $olderror = $wpdb->show_errors(get_option('weblib_debugdb') != 'off');
	  $sql = $wpdb->prepare('SELECT transaction FROM '.
				WEBLIB_OUTITEMS.
				' where patronid = %d order by transaction',
				$patronid);
	  $transactions = $wpdb->get_col($sql);
	  $wpdb->show_errors($olderror);
	  return $transactions;
	}
	static function OutItemByBarcode($barcode) {
	  global $wpdb;
	  $olderror = $wpdb->show_errors(get_option('weblib_debugdb') != 'off');
	  $sql = $wpdb->prepare('SELECT transaction FROM '.
				WEBLIB_OUTITEMS.
				' where barcode = %s order by transaction',
				$barcode);
	  $transactions = $wpdb->get_col($sql);
	  $wpdb->show_errors($olderror);
	  if ( empty($transactions) ) return null;
	  else return new WEBLIB_OutItem($transactions[0]);
	}
	static function DueDate($barcode) {
	  global $wpdb;
	  $olderror = $wpdb->show_errors(get_option('weblib_debugdb') != 'off');
	  $sql = $wpdb->prepare('SELECT datedue FROM '.
				WEBLIB_OUTITEMS.
				' where barcode = %s',$barcode);
	  $duedates = $wpdb->get_col($sql);
	  $wpdb->show_errors($olderror);
	  if ( empty($duedates) ) return '';
	  else return $duedates[0];
	}
	static function OutItemsByTitle($pattern) {
	  global $wpdb;
	  $olderror = $wpdb->show_errors(get_option('weblib_debugdb') != 'off');
	  $sql = $wpdb->prepare('SELECT transaction FROM '.
				WEBLIB_OUTITEMS.
				' where title like %s order by transaction',
				$pattern);
	  $transactions = $wpdb->get_col($sql);
	  $wpdb->show_errors($olderror);
	  if ( empty($transactions) ) return null;
	  else return $transactions[0];
	}

}

class WEBLIB_HoldItem {
	private $record;
	private $thetransaction = 0;
	private $insert = true;
	private $dirty = true;

	function __construct($transaction = 0) {
	  $this->thetransaction = $transaction;
	  if ($this->thetransaction == 0) {
	    $this->insert = true;
	    $this->dirty  = true;
	    $this->record = array(
		'barcode'   => '',
		'title'     => '',
		'source'    => '',
		'type'      => '',
		'patronid'  => 0,
		'dateheld'   => '',
		'dateexpire'    => '');
	  } else {
	    global $wpdb;
	    $olderror = $wpdb->show_errors(get_option('weblib_debugdb') != 'off');
	    $sql = $wpdb->prepare(
		'SELECT barcode, title, source, type, patronid, dateheld,'.
		'dateexpire FROM ' . 
		WEBLIB_HOLDITEMS . ' where transaction = %d',
		$this->thetransaction);
	    $rawrecord = $wpdb->get_row($sql, 'ARRAY_A' );
	    $wpdb->show_errors($olderror);
	    $this->record = array();
	    foreach ($rawrecord as $k => $v) {
	      $this->record[$k] = stripslashes($v);
	    }
	    $this->insert = false;
	    $this->dirty  = false;
	  }
	}
	function __destruct() {
	  if ($this->dirty) $this->store();
	}
	function barcode() {return $this->record['barcode'];}
	function set_barcode($v) {$this->record['barcode'] = $v; $this->dirty = true;}
	function title() {return $this->record['title'];}
	function set_title($v) {$this->record['title'] = $v; $this->dirty = true;}
	function source() {return $this->record['source'];}
	function set_source($v) {$this->record['source'] = $v; $this->dirty = true;}
	function type() {return $this->record['type'];}
	function set_type($v) {$this->record['type'] = $v; $this->dirty = true;}
	function patronid() {return $this->record['patronid'];}
	function set_patronid($v) {$this->record['patronid'] = $v; $this->dirty = true;}
	function dateheld() {return $this->record['dateheld'];}
	function set_dateheld($v) {$this->record['dateheld'] = $v; $this->dirty = true;}
	function dateexpire() {return $this->record['dateexpire'];}
	function set_dateexpire($v) {$this->record['dateexpire'] = $v; $this->dirty = true;}
	function store($newtransaction = 0) {
	  //file_put_contents("php://stderr","*** WEBLIB_HoldItem::store(): this->dirty is $this->dirty\n");
	  if (!$this->dirty) return $this->thetransaction;
	  //file_put_contents("php://stderr","*** WEBLIB_HoldItem::store: this->record = ".print_r($this->record,true)."\n");
	  if ($this->record['barcode'] == '' ||
	      $this->record['title'] == '' ||
	      $this->record['source'] == '' ||
	      $this->record['type'] == '' ||
	      $this->record['patronid'] == 0 ||
	      ! is_numeric($this->record['patronid']) ||
	      $this->record['dateheld'] == '' ||
	      $this->record['dateexpire'] == '') return(-1);
	  global $wpdb;
	  if ($this->insert) {
	    if ($newtransaction != 0) {
	      $insertrec = array(
		'transaction' => $newtransaction,
		'barcode' => $this->record['barcode'],
		'title' => $this->record['title'],
		'source' => $this->record['source'],
		'type' => $this->record['type'],
		'patronid' => $this->record['patronid'],
		'dateheld' => $this->record['dateheld'],
		'dateexpire' => $this->record['dateexpire']);
	      $insertfmt = array('%d','%s','%s','%s','%s','%d','%s','%s');
	    } else {
	      $insertrec = array(
		'barcode' => $this->record['barcode'],
		'title' => $this->record['title'],
		'source' => $this->record['source'],
		'type' => $this->record['type'],
		'patronid' => $this->record['patronid'],
		'dateheld' => $this->record['dateheld'],
		'dateexpire' => $this->record['dateexpire']);
	      $insertfmt = array('%s','%s','%s','%s','%d','%s','%s');
	    }
	    $olderror = $wpdb->show_errors(get_option('weblib_debugdb') != 'off');
	    $wpdb->insert(WEBLIB_HOLDITEMS, $insertrec, $insertfmt);
	    $wpdb->show_errors($olderror);
	    $this->thetransaction = $wpdb->insert_id;
	    $this->insert = false;
	    $this->dirty  = false;
	  } else {
	    $updaterec = array(
		'barcode' => $this->record['barcode'],
		'title' => $this->record['title'],
		'source' => $this->record['source'],
		'type' => $this->record['type'],
		'patronid' => $this->record['patronid'],
		'dateheld' => $this->record['dateheld'],
		'dateexpire' => $this->record['dateexpire']);
	    $updatefmt = array('%s','%s','%s','%s','%d','%s','%s');
	    $where = array('transaction' => $this->thetransaction );
	    $wherefmt = '%d';
	    $olderror = $wpdb->show_errors(get_option('weblib_debugdb') != 'off');
	    $wpdb->update(WEBLIB_HOLDITEMS, $updaterec, $where, $updatefmt,
					    $wherefmt);
	    $wpdb->show_errors($olderror);
	    $this->dirty  = false;
	  }
	  return $this->thetransaction;
	}
	function indatabase() {
	  if ($this->insert) return false;
	  else 		     return true;
	}
	function delete() {
	  //file_put_contents("php://stderr","*** WEBLIB_HoldItem::delete: this->record = ".print_r($this->record,true)."\n");
	  //file_put_contents("php://stderr","*** WEBLIB_HoldItem::delete: this->insert = ".$this->insert."\n");
	  if ($this->insert) return true;
	  $this->dirty = false;
	  WEBLIB_HoldItem::DeleteHeldItemByTransaction($this->thetransaction);
	  $this->insert = true;
	  return true;
	}
	function clean() {$this->dirty = false;}
	function transaction() {return $this->thetransaction;}
	function isdirty() {return $this->dirty;}
	function checkout($duedate) {
	  $item = new WEBLIB_ItemInCollection($this->barcode());
	  $outtrans = $item->checkout($this->patronid(), $this->source(), $duedate, $whocheckedout);
	  $this->delete();
	  $item->clean();
	  return($outtrans);
	}
	static function DeleteHeldItemByTransaction($transaction) {
	  //file_put_contents("php://stderr","*** WEBLIB_HoldItem::DeleteHeldItemByTransaction($transaction)\n");
	  global $wpdb;
	  $olderror = $wpdb->show_errors(get_option('weblib_debugdb') != 'off');
	  $sql = $wpdb->prepare('DELETE FROM '.WEBLIB_HOLDITEMS.
				' where transaction = %d',$transaction);
	  $status = $wpdb->query($sql);
	  $wpdb->show_errors($olderror);
	  //file_put_contents("php://stderr","*** WEBLIB_HoldItem::DeleteHeldItemByTransaction: query status is $status\n");
	}
	static function DeleteHeldItemByBarcodeAndPatronId($barcode,$patronid) {
	  global $wpdb;
	  $olderror = $wpdb->show_errors(get_option('weblib_debugdb') != 'off');
	  $sql = $wpdb->prepare('DELETE FROM '.WEBLIB_HOLDITEMS.
				' where barcode = %s && patronid = %d',
				$barcode,$patronid);
	  $status = $wpdb->query($sql);
	  $wpdb->show_errors($olderror);
	}
	static function DeleteHoldsByPatronId($patronid) {
	  global $wpdb;
	  $olderror = $wpdb->show_errors(get_option('weblib_debugdb') != 'off');
	  $sql = $wpdb->prepare('DELETE FROM '.WEBLIB_HOLDITEMS.
				' where patronid = %d',$patronid);
	  $status = $wpdb->query($sql);
	  $wpdb->show_errors($olderror);
	}
	static function DeleteHeldItemByBarcode($barcode) {
	  global $wpdb;
	  $olderror = $wpdb->show_errors(get_option('weblib_debugdb') != 'off');
	  $sql = $wpdb->prepare('DELETE FROM '.WEBLIB_HOLDITEMS.
				' where barcode = %s',$barcode);
	  $status = $wpdb->query($sql);
	  $wpdb->show_errors($olderror);
	}
	static function AllHeldItems() {
	  global $wpdb;
	  $olderror = $wpdb->show_errors(get_option('weblib_debugdb') != 'off');
	  $transactions = $wpdb->get_col('SELECT transaction FROM '.
						WEBLIB_HOLDITEMS.
						' order by transaction');
	  $wpdb->show_errors($olderror);
	  return $transactions;
	}
	static function RemoveOrphanHolds() {
	  global $wpdb;
	  $transactions = WEBLIB_HoldItem::AllHeldItems();
	  $orphans = array();
	  foreach ($transactions as $trans) {
	    $hold = new WEBLIB_HoldItem($trans);
	    if (!WEBLIB_ItemInCollection::IsItemInCollection($hold->barcode())) {
	      $orphans[] = array('transaction' => $trans,
				 'barcode' => $hold->barcode(),
				 'patronid' => $hold->patronid(),
				 'title' => $hold->title(),
				 'dateheld' => $hold->dateheld(),
				 'dateexpire' => $hold->dateexpire());
	      $hold->delete();
	    } else if (!WEBLIB_Patron::ValidPatronID($hold->patronid())) {
	      $orphans[] = array('transaction' => $trans,
				 'barcode' => $hold->barcode(),
				 'patronid' => $hold->patronid(),
				 'title' => $hold->title(),
				 'dateheld' => $hold->dateheld(),
				 'dateexpire' => $hold->dateexpire());
	      $hold->delete();
	    }
	  }
	  return $orphans;
        }
	static function HeldItemsOfPatron($patronid) {
	  global $wpdb;
	  $olderror = $wpdb->show_errors(get_option('weblib_debugdb') != 'off');
	  $sql = $wpdb->prepare(
			'SELECT transaction FROM '.
			WEBLIB_HOLDITEMS.
			' where patronid = %d order by transaction',
			$patronid);
	  $transactions = $wpdb->get_col($sql);
	  $wpdb->show_errors($olderror);
	  return $transactions;
	}
	static function HeldItemsByBarcode($barcode) {
	  global $wpdb;
	  $olderror = $wpdb->show_errors(get_option('weblib_debugdb') != 'off');
	  $sql = $wpdb->prepare('SELECT transaction FROM '.
				WEBLIB_HOLDITEMS.
				' where barcode = %s order by transaction',
				$barcode);
	  $transactions = $wpdb->get_col($sql);
	  $wpdb->show_errors($olderror);
	  return $transactions;
	}
	static function PatronAlreadyHolds($patronid,$barcode) {
	  global $wpdb;
	  $olderror = $wpdb->show_errors(get_option('weblib_debugdb') != 'off');
	  $sql = $wpdb->prepare('SELECT count(transaction) FROM '.
				WEBLIB_HOLDITEMS.
				' where patronid = %d and barcode = %s',
				$patronid,$barcode);
	  $count = $wpdb->get_var($sql);
	  $wpdb->show_errors($olderror);
	  return ($count > 0);
	}
	static function HoldCountsOfBarcode($barcode) {
	  global $wpdb;
	  $olderror = $wpdb->show_errors(get_option('weblib_debugdb') != 'off');
	  $sql = $wpdb->prepare('SELECT COUNT(transaction) FROM '.
				WEBLIB_HOLDITEMS.
				' where barcode = %s',$barcode);
	  $count = $wpdb->get_var($sql);
	  $wpdb->show_errors($olderror);
	  return $count;
	}
	static function HeldItemsByTitle($pattern) {
	  global $wpdb;
	  $olderror = $wpdb->show_errors(get_option('weblib_debugdb') != 'off');
	  $sql = $wpdb->prepare('SELECT transaction FROM '.
				WEBLIB_HOLDITEMS.
				' where title like %s order by transaction',
				$pattern);
	  $transactions = $wpdb->get_col($sql);
	  $wpdb->show_errors($olderror);
	  return $transactions;
	}
	static function ClearExpiredHolds() {
	  global $wpdb;
	  $olderror = $wpdb->show_errors(get_option('weblib_debugdb') != 'off');
	  $result = $wpdb->query('DELETE FROM '.WEBLIB_HOLDITEMS.
				' WHERE dateexpire < CURDATE()'); 
	  $wpdb->show_errors($olderror);
	  return $result;
	}
}

class WEBLIB_Statistic {
	private $thetype = '';
	private $theyear = '0000';
	private $themonth = '01';
	private $thecount = 0;
	private $insert = true;
	private $dirty = true;

	function __construct($type, 
			     $year = '',
			     $month = '') {
	  $this->thetype = $type;
	  if ($year == '') {$year = date('Y',time());}
	  $this->theyear = $year;
	  if ($month == '') {$year = date('m',time());}
	  $this->themonth = $month;
	  if ($this->themonth < 1 || $this->themonth > 12) {
	    trigger_error("Illegal month: $themonth\n", E_USER_ERROR);
	  }
	  if (!Type::KnownType($this->thetype)) {
	    trigger_error("Illegal type: $thetype\n", E_USER_ERROR);
	  }
	  global $wpdb;
	  $olderror = $wpdb->show_errors(get_option('weblib_debugdb') != 'off');
	  $sql = $wpdb->prepare('SELECT count FROM '.WEBLIB_STATISICS.
	  			' WHERE type = %s AND year = %d AND month = %d',
				$this->thetype, $this->theyear, 
				$this->themonth);
	  $this->thecount = $wpdb->get_var($sql);
	  $wpdb->show_errors($olderror);
	  if ($this->thecount == '') {
	    $this->thecount = 0;
	    $this->insert = true;
	    $this->dirty = true;
	  } else {
	    $this->insert = false;
	    $this->dirty = false;
	  }
	}
	function __destruct() {
	  if ($this->dirty) $this->store();
	}
	function type() {return $this->thetype;}
	function year() {return $this->theyear;}
	function month() {return $this->themonth;}
	function count() {return $this->thecount;}
	function increment() {$this->thecount++; $dirty = true;}
	function store() {
	  if (!$this->dirty) return;
	  global $wpdb;
	  if ($this->insert) {
	    $insertrec = array(
		'type' => $this->thetype,
		'year' => $this->theyear,
		'month' => $this->themonth,
		'count' => $this->thecount);
	    $insertfmt = array('%s','%d','%d','%d');
	    $olderror = $wpdb->show_errors(get_option('weblib_debugdb') != 'off');
	    $wpdb->insert(WEBLIB_STATISICS, $insertrec, $insertfmt);
	    $wpdb->show_errors($olderror);
	    $this->insert = false;
	    $this->dirty  = false;
	  } else {
	    $updaterec = array('count' => $this->thecount);
	    $updatefmt = array('%d');
	    $where = array(
		'type' => $this->thetype,
		'year' => $this->theyear,
		'month' => $this->themonth);
	    $wherefmt = array('%s','%d','%d');
	    $olderror = $wpdb->show_errors(get_option('weblib_debugdb') != 'off');
	    $wpdb->update(WEBLIB_STATISICS, $updaterec, $where, $updatefmt, 
				$wherefmt);
	    $wpdb->show_errors($olderror);
	    $this->dirty  = false;
	  }
	}
	static function MonthTotal($year,$month) {
	  global $wpdb;
	  $olderror = $wpdb->show_errors(get_option('weblib_debugdb') != 'off');
	  $sql = $wpdb->prepare('SELECT sum(count) FROM '.WEBLIB_STATISICS.
				' WHERE year = %d AND month = %d',
				$year, $month);
	  $count = $wpdb->get_var($sql);
	  $wpdb->show_errors($olderror);
	  if ($count == '') $count = 0;
	  return $count;
	}
	static function AnnualTotal($year) {
	  global $wpdb;
	  $olderror = $wpdb->show_errors(get_option('weblib_debugdb') != 'off');
	  $sql = $wpdb->prepare('SELECT sum(count) FROM '.WEBLIB_STATISICS.
				' WHERE year = %d',$year);
	  $count = $wpdb->get_var($sql);
	  $wpdb->show_errors($olderror);
	  if ($count == '') $count = 0;
	  return $count;
	}
	static function AnnualTotalByType($year,$type) {
	  global $wpdb;
	  $olderror = $wpdb->show_errors(get_option('weblib_debugdb') != 'off');
	  $sql = $wpdb->prepare('SELECT sum(count) FROM '.WEBLIB_STATISICS.
				' WHERE year = %d AND type = %s',
				$year, $type);
	  $count = $wpdb->get_var($sql);
	  $wpdb->show_errors($olderror);
	  if ($count == '') $count = 0;
	  return $count;
	}
	static function TypeCount($type,$year,$month) {
	  global $wpdb;
	  $olderror = $wpdb->show_errors(get_option('weblib_debugdb') != 'off');
	  $sql = $wpdb->prepare('SELECT count FROM '.WEBLIB_STATISICS.
				' WHERE type = %s AND year = %d AND month = %d',
				$type, $year, $month);
	  $count = $wpdb->get_var($sql);
	  $wpdb->show_errors($olderror);
	  if ($count == '') $count = 0;
	  return $count;
	}
	static function AllYears() {
	  global $wpdb;
	  $olderror = $wpdb->show_errors(get_option('weblib_debugdb') != 'off');
	  $result =  $wpdb->get_col('SELECT DISTINCT year FROM '.WEBLIB_STATISICS);
	  $wpdb->show_errors($olderror);
	  return $result;
	}
	static function IncrementCheckoutCount($type,$year,$month) {
	  //file_put_contents("php://stderr","*** WEBLIB_Statistic::IncrementCheckoutCount('$type',$year,$month)\n");
	  global $wpdb;
	  $olderror = $wpdb->show_errors(get_option('weblib_debugdb') != 'off');
	  $sql = $wpdb->prepare('SELECT count FROM '.WEBLIB_STATISICS.
	  			' WHERE type = %s AND year = %d AND month = %d',
				$type, $year, $month);
	  $count = $wpdb->get_var($sql);
	  $wpdb->show_errors($olderror);
	  if ($count == '') {
	    $count = 1;
	    $insertrec = array(
		'type' => $type,
		'year' => $year,
		'month' => $month,
		'count' => $count);
	    $insertfmt = array('%s','%d','%d','%d');
	    $olderror = $wpdb->show_errors(get_option('weblib_debugdb') != 'off');
	    $wpdb->insert(WEBLIB_STATISICS, $insertrec, $insertfmt);
	    $wpdb->show_errors($olderror);
	  } else {
	    $count++;
	    $updaterec = array('count' => $count);
	    $updatefmt = array('%d');
	    $where = array(
		'type' => $type,
		'year' => $year,
		'month' => $month);
	    $wherefmt = array('%s','%d','%d');
	    $olderror = $wpdb->show_errors(get_option('weblib_debugdb') != 'off');
	    $wpdb->update(WEBLIB_STATISICS, $updaterec, $where, $updatefmt, 
				$wherefmt);
	    $wpdb->show_errors($olderror);
	  }
	}
}

class WEBLIB_Type {
	private $thetype = '';
	private $theloanperiod = 14;
	private $insert = true;
	private $dirty  = true;

	function __construct($type) {
	  //file_put_contents("php://stderr","*** Type->__construct($type)\n");
	  $this->thetype = $type;
	  if ($this->thetype == '') {
	    trigger_error("Illegal type: $thetype\n", E_USER_ERROR);
	  }
	  global $wpdb;
	  $olderror = $wpdb->show_errors(get_option('weblib_debugdb') != 'off');
	  $sql = $wpdb->prepare('SELECT loanperiod FROM '.WEBLIB_TYPES.
	  			' WHERE type = %s',$this->thetype);
	  //file_put_contents("php://stderr","*** Type->__construct(): sql = $sql\n");
	  $this->theloanperiod = $wpdb->get_var($sql);
	  $wpdb->show_errors($olderror);
	  //file_put_contents("php://stderr","*** Type->__construct(): this->theloanperiod = $this->theloanperiod\n");
	  if ($this->theloanperiod == '') {
	    $this->theloanperiod = 14;
	    $this->insert = true;
	    $this->dirty = true; 
	  } else {
	    $this->insert = false;
	    $this->dirty = false; 
	  }
	  //file_put_contents("php://stderr","*** Type->__construct(): this->insert = $this->insert\n");
	}
	function __destruct() {
	  if ($this->dirty) $this->store();
	}
	function type() {return $this->thetype;}
	function loanperiod() {return $this->theloanperiod;}
	function set_loanperiod($days) {
	  //file_put_contents("php://stderr","*** Type->set_loanperiod($days)\n");
	  //file_put_contents("php://stderr","*** Type->set_loanperiod(): this->dirty = $this->dirty\n");
	  if ($days < 1) {
	    trigger_error("Illegal loan period: $days\n", E_USER_ERROR);
	  }
	  $this->theloanperiod = $days;
	  $this->dirty = true;
	  //file_put_contents("php://stderr","*** Type->set_loanperiod(): this->dirty = $this->dirty\n");
	}
	function store() {
	  //file_put_contents("php://stderr","*** Type->store(): this->dirty = $this->dirty\n");
	  if (!$this->dirty) return;
	  global $wpdb;
	  //file_put_contents("php://stderr","*** Type->store(): this->insert = $this->insert\n");
	  if ($this->insert) {
	    $insertrec = array(
	      'type' => $this->thetype,
	      'loanperiod' => $this->theloanperiod);
	    $insertfmt = array('%s','%d');
	    $olderror = $wpdb->show_errors(get_option('weblib_debugdb') != 'off');
	    $wpdb->insert(WEBLIB_TYPES, $insertrec, $insertfmt);
	    $wpdb->show_errors($olderror);
	    $this->insert = false;
	    $this->dirty  = false;
	  } else {
	    $updaterec = array('loanperiod' => $this->theloanperiod);
	    //file_put_contents("php://stderr","*** Type->store(): updaterec".'['."'loanperiod'".'] = '.$updaterec['loanperiod']."\n");
	    $updatefmt = array('%d');
	    $where = array('type' => $this->thetype);
	    //file_put_contents("php://stderr","*** Type->store(): where".'['."'type'".'] = '.$where['type']."\n");
	    $wherefmt = array('%s');
	    $olderror = $wpdb->show_errors(get_option('weblib_debugdb') != 'off');
	    $wpdb->update(WEBLIB_TYPES, $updaterec, $where, $updatefmt,
				$wherefmt);
	    $wpdb->show_errors($olderror);
	    //file_put_contents("php://stderr","*** Type->store(): wpdb->last_query = $wpdb->last_query\n");
	    //file_put_contents("php://stderr","*** Type->store(): wpdb->last_result = $wpdb->last_result\n");
	    $this->dirty  = false;
	  }
	}
	static function KnownType($type) {
	  global $wpdb;
	  $olderror = $wpdb->show_errors(get_option('weblib_debugdb') != 'off');
	  $sql = $wpdb->prepare('SELECT count(type) FROM '.WEBLIB_TYPES.
				' WHERE type = %s',$type);
	  $result =  ($wpdb->get_var($sql) > 0);
	  $wpdb->show_errors($olderror);
	  return $result;
	}
	static function AllTypes() {
	  global $wpdb;
	  $olderror = $wpdb->show_errors(get_option('weblib_debugdb') != 'off');
	  $rawtypes = $wpdb->get_col('SELECT type FROM '.WEBLIB_TYPES);
	  $wpdb->show_errors($olderror);
	  $thetypes = array();
	  foreach ($rawtypes as $atype) {
	    $thetypes[] = stripslashes($atype);
	  }
	  return $thetypes;
	}
}

?>
