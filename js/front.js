function log (message) {
  try {
    console.log(message);
  } catch(err) { 
    /*alert(message);*/
  }
}

log('*** front.js loading');

function PlaceHold(barcode) {
    jQuery(document).ready(function($) {
        var data = {
            'action': 'PlaceHoldOnItem',
            'barcode': barcode
        };
        jQuery.post(front_js.ajax_url, data, function(response) {
	  // hold-count-<barcode>
	  var message = response.getElementsByTagName('message');
	  if (message.length > 0) {
              var messageText = message[0].childNodes[0].nodeValue;
              var elt = document.getElementById('ajax-message');
              if (elt) elt.innerHTML = '<p><span id="error">'+messageText+'</span></p>';
	  }
	  var result = response.getElementsByTagName('result');
	  if (result.length > 0) {
              var barcode = result[0].getElementsByTagName('barcode')[0].childNodes[0].nodeValue;
              var holdcount = result[0].getElementsByTagName('holdcount')[0].childNodes[0].nodeValue;
              var spanelt = document.getElementById('hold-count-'+barcode);
              if (holdcount != 1) {
                  spanelt.innerHTML = holdcount+' '+front_js.holds;
              } else {
                  spanelt.innerHTML = holdcount+' '+front_js.hold;
              }
	  }
        },'xml');
    });
}

jQuery(document).ready( function($) {
	// check all checkboxes
	$('tbody').children().children('.check-column').find(':checkbox').click( function(e) {
		if ( 'undefined' == e.shiftKey ) { return true; }
		if ( e.shiftKey ) {
			if ( !lastClicked ) { return true; }
			checks = $( lastClicked ).closest( 'form' ).find( ':checkbox' ).filter( ':visible:enabled' );
			first = checks.index( lastClicked );
			last = checks.index( this );
			checked = $(this).prop('checked');
			if ( 0 < first && 0 < last && first != last ) {
				sliced = ( last > first ) ? checks.slice( first, last ) : checks.slice( last, first );
				sliced.prop( 'checked', function() {
					if ( $(this).closest('tr').is(':visible') )
						return checked;

					return false;
				});
			}
		}
		lastClicked = this;

		// toggle "check all" checkboxes
		var unchecked = $(this).closest('tbody').find(':checkbox').filter(':visible:enabled').not(':checked');
		$(this).closest('table').children('thead, tfoot').find(':checkbox').prop('checked', function() {
			return ( 0 === unchecked.length );
		});

		return true;
	});

	$('thead, tfoot').find('.check-column :checkbox').on( 'click.wp-toggle-checkboxes', function( event ) {
		var $this = $(this),
			$table = $this.closest( 'table' ),
			controlChecked = $this.prop('checked'),
			toggle = event.shiftKey || $this.data('wp-toggle');

		$table.children( 'tbody' ).filter(':visible')
			.children().children('.check-column').find(':checkbox')
			.prop('checked', function() {
				if ( $(this).is(':hidden,:disabled') ) {
					return false;
				}

				if ( toggle ) {
					return ! $(this).prop( 'checked' );
				} else if ( controlChecked ) {
					return true;
				}

				return false;
			});

		$table.children('thead,  tfoot').filter(':visible')
			.children().children('.check-column').find(':checkbox')
			.prop('checked', function() {
				if ( toggle ) {
					return false;
				} else if ( controlChecked ) {
					return true;
				}

				return false;
			});
	});
});
function Renew(barcode) {
    jQuery(document).ready(function($) {
        var data = {
            'action': 'RenewItem',
            'barcode': barcode
        };
        jQuery.post(front_js.ajax_url, data, function(response) {
                    if (response != null)
                    {
                        
                        var message = response.getElementsByTagName('message');
                        if (message.length > 0) {
                            messageText = message[0].childNodes[0].nodeValue;
                            document.getElementById('ajax-message').innerHTML = '<p><span id="error">'+messageText+'</span></p>';
                        }
                        var result = response.getElementsByTagName('result');
                        if (result.length > 0) {
                            var barcode = result[0].getElementsByTagName('barcode')[0].childNodes[0].nodeValue;
                            var duedate = result[0].getElementsByTagName('duedate')[0].childNodes[0].nodeValue;
                            var spanelt = document.getElementById('due-date-'+barcode);
                            spanelt.className = spanelt.className.replace(/overdue/i,"");
                            spanelt.innerHTML = duedate;
                        }
                    }
                },'xml');
        });
}

