function log (message) {
  try {
    console.log(message);
  } catch(err) { 
    /*alert(message);*/
  }
}

log('*** admin.js loading');

function QuoteString(s) {
    //log('*** QuoteString('+s+')');
    var result = s.replace(/"/g,'&quot;');
    //log('*** QuoteString: result ("s) is '+result);
    result = result.replace(/\\/g,'\\\\');
    //log('*** QuoteString: result \\s is '+result);
    result = result.replace(/\'/g,"\\'");
    //log('*** QuoteString: result \'s is '+result);
    return result;
}
                        
                     

function WEBLIB_WriteKeywords(name) {
  var keywordstextarea = document.getElementById(name+'-keyword-list');
  var keywordschecklist = document.getElementById(name+'-keywordchecklist');

  var keywords = keywordstextarea.value.split(',');
  keywordschecklist.innerHTML = "";
  var i;
  for (i = 0; i < keywords.length; i++) {
    var val,txt, button_id, id = name;
    val = keywords[i].trim();
    if (val == '') continue;
    button_id = id + '-check-num-' + val;
    txt = '<span><a id="' + button_id + 
	  '" class="ntdelbutton" onclick="WEBLIB_DeleteKeyword('+"'"+name+"','"+val+"'"+')">X</a>&nbsp;' + val + '</span> ';
    keywordschecklist.innerHTML += txt;
  }
} 

function UpdatePatronID(userid) {
    jQuery(document).ready(function($) {
        var data = {
            'action': 'UpdatePatronID',
            'userid': userid,
            'patronid': document.getElementById('patronid-'+userid).value
        };
        jQuery.post(admin_js.ajax_url, data, function(response) {
                    userid = response.getElementsByTagName('userid')[0].childNodes[0].nodeValue;
                    patronid = response.getElementsByTagName('patronid')[0].childNodes[0].nodeValue;
                    document.getElementById('displayed-patronid-'+userid).innerHTML = patronid;
                },'xml');
        });
    }

function FindPatron() {
    jQuery(document).ready(function($) {
        var data = {
            'action': 'FindPatron',
            'searchname' : document.getElementById('searchname').value
        };
        jQuery.post(admin_js.ajax_url, data, function(response) {
                    document.getElementById('weblib-patronlist').innerHTML = '';
                    if (response != null)
                    {
                        var patrons = response.getElementsByTagName('patron');
                        //log("*** FindPatron -- onreadystatechange: patrons.length = "+patrons.length);
                        switch (patrons.length) {
                            case 0:
                            document.getElementById('weblib-patronlist').innerHTML = '<p>'+admin_js.noMatchingPatrons+'</p>';
                            break;
                            case 1:
                            document.getElementById('patronid').value = 
                            patrons[0].getElementsByTagName('id')[0].childNodes[0].nodeValue;
                            break;
                            default:
                            //log("*** FindPatron -- onreadystatechange: patrons.length > 1");
                            var outHTML  = '<label for="searched-patronid" class="inputlab"></label>';
                            outHTML += '<select class="patrondroplist weblib-input-fill" id="searched-patronid">';
                            var j;
                            for (j = 0; j < patrons.length; j++) {
                                //log("*** FindPatron -- onreadystatechange: j = "+j);
                                var id = patrons[j].getElementsByTagName('id')[0].childNodes[0].nodeValue;
                                var name = patrons[j].getElementsByTagName('name')[0].childNodes[0].nodeValue;
                                //log("*** FindPatron -- onreadystatechange: id = "+id+", name = "+name);
                                outHTML += '<option value="'+id+'">'+name+'</option>';
                            }
                            outHTML += '</select>';
                            outHTML +=
                            '<input class="button" type="button" name="patronselect" value="'+admin_js.selectPatron+'" onclick="SelectPatron();" />';
                            //log("*** FindPatron -- onreadystatechange: outHTML = "+outHTML);
                            document.getElementById('weblib-patronlist').innerHTML = outHTML;
                            break;
                        }
                    }
                },'xml');
        });
}

function SelectPatron() {
  var searched_patronid = document.getElementById('searched-patronid');
  var id = searched_patronid.options[searched_patronid.selectedIndex].value;
  //log("*** SelectPatron: id = "+id);
  var select = document.getElementById('patronid');
  /*if (select.options[select.selectedIndex].value == id) return;*/
  select.options[select.selectedIndex].selected = false;
  select.selectedIndex = 0;
  var i;
  for (i = 1; i < select.length; i++) {
    //log("*** SelectPatron: select.options["+i+"].value = "+select.options[i].value);
    if (select.options[i].value == id) {
      select.options[i].selected = true;
      select.selectedIndex = i;
      break;
    }
  }
  document.getElementById('weblib-patronlist').innerHTML = '';
}

function Renew(barcode) {
    jQuery(document).ready(function($) {
        var data = {
            'action': 'RenewItem',
            'barcode': barcode
        };
        jQuery.post(admin_js.ajax_url, data, function(response) {
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

function PlaceHold(barcode) {
    // patronid
    var patronidSelect = document.getElementById('patronid');
    var selectedPatron = patronidSelect.selectedIndex;
    var patronid = patronidSelect.options[selectedPatron].value;
    jQuery(document).ready(function($) {
        var data = {
            'action': 'PlaceHoldOnItem',
            'barcode': barcode,
            'patronid' : patronid
        };
        jQuery.post(admin_js.ajax_url, data, function(response) {
	  // hold-count-<barcode>
	  var message = response.getElementsByTagName('message');
	  if (message.length > 0) {
              var messageText = message[0].childNodes[0].nodeValue;
              document.getElementById('ajax-message').innerHTML = '<p><span id="error">'+messageText+'</span></p>';
	  }
	  var result = this.responseXML.getElementsByTagName('result');
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



function WEBLIB_AddKeyword(name) {
  var keydiv = document.getElementById(name+'-keyword-div');
  var keywordstextarea = document.getElementById(name+'-keyword-list');
  var keyinput = document.getElementById(name+'-new-keyword-item_keyword');
  if (keywordstextarea.value != "") keywordstextarea.value += ',';
  keywordstextarea.value += keyinput.value.toUpperCase();
  WEBLIB_WriteKeywords(name);  
}

function WEBLIB_DeleteKeyword(name,keyword) {
  var keywordstextarea = document.getElementById(name+'-keyword-list');
  var ic1 = keywordstextarea.value.indexOf(','+keyword+',');
  var ic2 = keywordstextarea.value.indexOf(','+keyword);
  var ic3 = keywordstextarea.value.indexOf(keyword+',');
  if (ic1 >= 0) {
    keywordstextarea.value = keywordstextarea.value.substr(0,ic1) + 
			     keywordstextarea.value.substr(ic1+keyword.length+1);
  } else if (ic2 >= 0 && (ic2+keyword.length+1) == keywordstextarea.value.length) {
    keywordstextarea.value = keywordstextarea.value.substr(0,ic2);
  } else if (ic3 == 0) {
    keywordstextarea.value = keywordstextarea.value.substr(ic3+keyword.length+1);
  } else if (keywordstextarea.value == keyword) {
    keywordstextarea.value = '';
  }
  WEBLIB_WriteKeywords(name);  
}


function WEBLIB_InsertTitle(title) {
    document.getElementById('title').value = title;
}

function WEBLIB_InsertTitleIfBlank(title) {
    if (document.getElementById('title').value == '')
    {
        document.getElementById('title').value = title;
    }
}

function WEBLIB_InsertISBN(isbn) {
    document.getElementById('isbn').value = isbn;
}

function WEBLIB_InsertThumb(url) {
    document.getElementById('thumburl').value = url;
}

function WEBLIB_AddAuthor(thename) {
    if (document.getElementById('itemauthor').value == '')
    {
        document.getElementById('itemauthor').value = thename;
    } else {
        document.getElementById('itemauthor').value += ' and '+thename;
    }
}

function WEBLIB_ClearAuthor() {
    document.getElementById('itemauthor').value = '';
}


function WEBLIB_ClearDate() {
    document.getElementById('pubdate').value = '';
}

function WEBLIB_InsertDateIfBlank(value) {
    if (document.getElementById('pubdate').value == '') {
        WEBLIB_InsertDate(value);
    }
}

function WEBLIB_InsertDate(value) {
    var date;
    //log("*** AWSInsertCallback(): value = "+value);
    if (/[0-9][0-9][0-9][0-9]-[0-9][0-9]-[0-9][0-9]/.test(value)) {
        var dateval = value.split('-');
        //log("*** AWSInsertCallback(): dateval is "+dateval);
        date = new Date(dateval[0],dateval[1]-1,dateval[2],0,0,0);
    } else {
        date = new Date(value);
    }
    //log("*** AWSInsertCallback(): date.getMonth() = "+date.getMonth()+", date.getDate() = "+date.getDate()+", date.getYear() = "+date.getYear());
    var months = ['Jan','Feb','Mar','Apr','May','Jun',
                  'Jul','Aug','Sep','Oct','Nov','Dec'];
    document.getElementById('pubdate').value = 
    months[date.getMonth()]+'/'+date.getDate()+'/'+
    (date.getYear()+1900);
}

function WEBLIB_InsertPublisherIfBlank(value) {
    if (document.getElementById('publisher').value == '')
    {
        document.getElementById('publisher').value = value;
    }
}

function WEBLIB_InsertPublisher(value) {
    document.getElementById('publisher').value = value;
}

function WEBLIB_InsertEdition(value) {
    document.getElementById('edition').value = value;
}

function WEBLIB_AddToMedia(value) {
    if (document.getElementById('media').value == '')
    {
        document.getElementById('media').value = value;
    } else {
        document.getElementById('media').value += ','+value;
    }
}

function WEBLIB_ClearMedia() {
    document.getElementById('media').value = '';
}

function WEBLIB_AddToDescription(value) {
    document.getElementById('description').value += value+"\n";
}

function WEBLIB_ClearDescription() {
    document.getElementById('description').value = '';
}

function WEBLIB_ClearKeywords() {
    document.getElementById('itemedit-keyword-list').value = '';
}

function WEBLIB_InsertKeyword(keyword) {
    if (document.getElementById('itemedit-keyword-list').value != '') 
    {
        document.getElementById('itemedit-keyword-list').value += ',';
    }
    document.getElementById('itemedit-keyword-list').value += keyword;
}

function WEBLIB_InsertPubLocation(value) {
    document.getElementById('publocation').value = value;
}

log('*** admin.js: about to define resizable');

jQuery(function() {
  log('*** admin.js: jQuery: setting #resizable to be resizable');
  jQuery("#resizable").resizable({
       animate: true,     
       animateEasing: 'swing',
       imateDuration: 500
   });
  
  log('*** admin.js: jQuery: defining the resize propagation');
  
  jQuery("#resizable").resizable({
       resize: function(event, ui) {
           log('*** admin.js: jQuery: resizing the iframe');
           log('*** admin.js: jQuery: ui.size.height = '+ui.size.height);
           var h = ui.size.height-40;
           log('*** admin.js: jQuery: h = '+h);
           jQuery("#aws-formframe").css({ "height": h,"width":ui.size.width});
       }
   });
  });
    
log('*** admin.js: resizable defined');
