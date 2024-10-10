/* -*- javascript -*- 
     Copyright 2013 Deepwoods Software
     All Rights Reserved
     System        : AWSFUNCTIONS_JS : 
     Object Name   : $RCS_FILE$
     Revision      : $REVISION$
     Date          : Mon Jun 3 09:29:32 2013
     Created By    : Robert Heller, Deepwoods Software
     Created       : Mon Jun 3 09:29:32 2013

     Last Modified : <160706.1420>
     ID            : $Id$
     Source        : $Source$
     Description	
     Notes
*/
log('*** AWSFunctions.js loading');

function AWSRequest(params,ajaxCallback) {
    params['action'] = 'AWSXmlGet';
    jQuery(document).ready(function($) {
       jQuery.post(admin_js.ajax_url, params, ajaxCallback);
   });
}

function AWSGotoFirstPage() 
{
  AWSSearch(1);
}

function AWSGotoPrevPage()
{
  var page = (document.getElementById('amazon-page-current').value*1) - 1;
  if (page < 1) page = 1;
  AWSSearch(page);
}

function AWSGotoNextPage() 
{
  var page = (document.getElementById('amazon-page-current').value*1) + 1;
  if (page > document.getElementById('amazon-page-N').value) 
	page = document.getElementById('amazon-page-N').value;
  if (page > 400) page = 400;
  AWSSearch(page);
}

function AWSGotoLastPage() 
{
  var page = document.getElementById('amazon-page-N').value;
  if (page > 400) page = 400;
  AWSSearch(page);
}

function AWSGotoPage()
{
  var page = document.getElementById('amazon-page-current').value;
  if (page < 1) page = 1;
  if (page > 400) page = 400;
  AWSSearch(page);
}

function AWSSearch(page) 
{
  var params = {"Operation": "ItemSearch",
		"ResponseGroup": "Small"};
  params["SearchIndex"] = document.getElementById('SearchIndex').value;
  params[document.getElementById('FieldName').value] = 
			document.getElementById('SearchString').value;
  params["ItemPage"] = page;

  document.getElementById('amazon-search-workstatus').innerHTML = '<p>'+admin_js.loading+'...</p>';
  document.getElementById('amazon-result-list').innerHTML = '';
  document.getElementById('amazon-item-lookup-display').innerHTML = '';
  AWSRequest(params,AWSSearchCallback);
}

function AWSSearchCallback(response) 
{
    var listout = '';
    
    if (response != null)
    {
	var ErrorsElts = response.getElementsByTagName('Error');
	if (ErrorsElts != null && ErrorsElts.length > 0) {
            var ierr;
            var WorkStatus = document.getElementById('amazon-search-workstatus');
            WorkStatus.innerHTML = '';
            for (ierr = 0; ierr < ErrorsElts.length; ierr++) {
                var theError = ErrorsElts[ierr];
                var theMessage = theError.getElementsByTagName('Message')[0].childNodes[0].nodeValue;
                WorkStatus.innerHTML += '<p class="error">'+theMessage+'</p>';
            }
            return;
	}
        var CurrentPage = response.getElementsByTagName('ItemPage')[0].childNodes[0].nodeValue;
        /*log("*** AWSSearchCallback: CurrentPage = '"+CurrentPage+"'");*/
        var TotalResults = response.getElementsByTagName('TotalResults')[0].childNodes[0].nodeValue;
        /*log("*** AWSSearchCallback: TotalResults = '"+TotalResults+"'");*/
        var TotalPages   = response.getElementsByTagName('TotalPages')[0].childNodes[0].nodeValue;
        /*log("*** AWSSearchCallback: TotalPages = '"+TotalPages+"'");*/
        
        document.getElementById('amazon-page-current').value = CurrentPage;
        document.getElementById('amazon-page-N').value = TotalPages;

        var items = response.getElementsByTagName('Item');
        listout += '<table class="form-table">';
	var j;
        for (j = 0; j < items.length; j++)
        {
            var title = items[j].getElementsByTagName('Title')[0].childNodes[0].nodeValue;
            var asin  = items[j].getElementsByTagName('ASIN')[0].childNodes[0].nodeValue;
            listout += '<tr><td valign="top" width="68%">'+title+' (ASIN: '+asin+')</td>';
            listout += '<td valign="top" width="16%"><a href="#amazon-item-lookup-display" class="button" onclick="AWSLookupItem('+
            "'"+asin+"'"+');">'+admin_js.lookupItem+'</a></td>';
            listout += '<td valign="top" width="16%"><a href="#" class="button" onclick="AWSInsertItem('+
            "'"+asin+"'"+');">'+admin_js.insertItem+'</a></td>';
            listout += '</tr>';
        }
        listout += '</table>';
        document.getElementById('amazon-result-list').innerHTML = listout;
        var tr1 = admin_js.totalResultsFount.replace(/%d/,TotalResults);
        document.getElementById('amazon-search-workstatus').innerHTML = '<p>'+tr1+'</p>';
    } else document.getElementById('amazon-search-workstatus').innerHTML = '<p>'+admin_js.nodata+'</p>'
}

function AWSLookupItem(asin)
{
  var params = {"Operation": "ItemLookup",
		"IdType": "ASIN",
		"ResponseGroup": "Large"};
  params["ItemId"] = asin;

  document.getElementById('amazon-item-lookup-workstatus').innerHTML = '<p>'+admin_js.loading+'...</p>';
  document.getElementById('amazon-item-lookup-display').innerHTML = '';
  AWSRequest(params,AWSLookupCallback);
}

function AWSInsertItem(asin)
{
  var params = {"Operation": "ItemLookup",
		"IdType": "ASIN",
		"ResponseGroup": "Large"};
  params["ItemId"] = asin;
  document.getElementById('amazon-item-lookup-workstatus').innerHTML = '<p>'+admin_js.loading+'...</p>';

  AWSRequest(params,AWSInsertCallback);
}

function AWSFixName(name)
{
  var nameparts = name.split(',');
  var propername = nameparts[0];
  var extraparts = '';
  var j;
  for (j = 1; j < nameparts.length; j++)
  {
    extraparts += ', ' + nameparts[j];
  }
  var firstlast = propername.split(' ');
  switch (firstlast.length) {
    case 1: return propername+extraparts;
    case 2: return firstlast[1]+', '+firstlast[0]+extraparts;
    case 3: return firstlast[2]+', '+firstlast[0]+' '+firstlast[1]+extraparts;
  }
}

function AWSFixTitle(thetitle)
{
  if (/^the /i.test(thetitle)) 
  {
    return thetitle.replace(/^the /i,"")+", The";
  } else if (/^a  /i.test(thetitle))
  {
    return thetitle.replace(/^a /i,"")+", A";
  } else {
    return thetitle;
  }
}

function AWSInsertCallback(response)
{
    if (response != null)
    {
	var ErrorsElts = response.getElementsByTagName('Error');
	if (ErrorsElts != null && ErrorsElts.length > 0) {
            var ierr;
            var WorkStatus = document.getElementById('amazon-search-workstatus');
            WorkStatus.innerHTML = '';
            for (ierr = 0; ierr < ErrorsElts.length; ierr++) {
                var theError = ErrorsElts[ierr];
                var theMessage = theError.getElementsByTagName('Message')[0].childNodes[0].nodeValue;
                WorkStatus.innerHTML += '<p class="error">'+theMessage+'</p>';
            }
            return;
	}
	var item = response.getElementsByTagName('Item')[0];
        var win = opener || parent || top;
	var smallimage = item.getElementsByTagName('SmallImage')[0];
	if (smallimage != null)
	{
            var smallimageURL = smallimage.getElementsByTagName('URL')[0].childNodes[0].nodeValue;
            win.WEBLIB_InsertThumb(smallimageURL);
	}
        win.WEBLIB_InsertTitle('');
        win.WEBLIB_ClearAuthor();
        win.WEBLIB_ClearDescription();
        win.WEBLIB_ClearMedia();
        win.WEBLIB_InsertPublisher('');
        win.WEBLIB_InsertPubLocation('');
        win.WEBLIB_ClearDate();
	win.WEBLIB_InsertEdition('');
        win.WEBLIB_InsertISBN('');
	var ItemAttributesList = item.getElementsByTagName('ItemAttributes');
	//log("*** AWSInsertCallback: ItemAttributesList.length is "+ItemAttributesList.length);
	var k;
	for (k = 0; k < ItemAttributesList.length; k++)
	{
            var ItemAttributes = ItemAttributesList[k];
            //log("*** AWSInsertCallback: (k="+k+"): ItemAttributes.childNodes.length is "+ItemAttributes.childNodes.length);
            var j;
            for (j = 0; j < ItemAttributes.childNodes.length; j++)
            {
                var attribute = ItemAttributes.childNodes[j];
                var value = attribute.childNodes[0].nodeValue;
                if (value != null)
                {
                    //log("*** AWSInsertCallback: (j="+j+") attribute.tagName is "+attribute.tagName+" value is '"+value+"'");
                    switch (attribute.tagName) {
                        case 'Editor':
                        case 'Artist':
                        case 'Actor':
                        case 'Director':
                        case 'Foreword':
                        case 'Contributor':
                        case 'Author':
                        var thename = AWSFixName(value);
                        win.WEBLIB_AddAuthor(thename);
                        break;
                        case 'Creator':
                        var thename = AWSFixName(value);
                        var role = attribute.attributes.getNamedItem("role");
                        if (role == null) attribute.attributes.getNamedItem("Role");
                        if (role != null) thename += ' ('+role+')';
                        win.WEBLIB_AddAuthor(thename);
                        break;
                        case 'Title':
                        win.WEBLIB_InsertTitleIfBlank(AWSFixTitle(value));
                        break;
                        case 'ReleaseDate':
                        case 'PublicationDate':
                        win.WEBLIB_InsertDateIfBlank(value);
                        break;
                        case 'Studio':
                        case 'Label':
                        case 'Publisher':
                        win.WEBLIB_InsertPublisherIfBlank(value);
                        break;
                        case 'ISBN':
                        win.WEBLIB_InsertISBN(value);
                        break;
                        case 'Edition':
                        win.WEBLIB_InsertEdition(value);
                        break;
                        case 'Binding':
                        case 'Format':
                        case 'ProductGroup':
                        win.WEBLIB_AddToMedia(value);
                        break;
                        case 'Height':
                        case 'Width':
                        case 'Length':
                        case 'Weight':
                        var units = attribute.attributes.getNamedItem("units");
                        if (units == null) attribute.attributes.getNamedItem("Units");
                        if (units != 'pixels') {
                            win.WEBLIB_AddToDescription(attribute.tagName+' '+value+' '+units+"\n");
                        } else {
                            win.WEBLIB_AddToDescription(attribute.tagName+' '+value+"\n");
                        }
                        break;
                        default:
                        var insertval = attribute.tagName;
                        insertval += ' ';
                        var ia;
                        for (ia = 0; ia < attribute.attributes.length; ia++)
                        {
                            var attr = attribute.attributes.item(ia);
                            insertval += attr.name+'="'+attr.value+'" ';
                        }
                        insertval += "\n";
                        win.WEBLIB_AddToDescription(insertval);
                        break;
                    }
                }
            }
	}
	var keywords = item.getElementsByTagName('Keywords');
        win.WEBLIB_ClearKeywords();
	for (k = 0; k < keywords.length; k++)
	{
            var keyword = keywords[0];
            for (j = 0; j < keyword.childNodes.length; j++)
            {
                win.WEBLIB_InsertKeyword(keyword.childNodes[j].nodeValue);
            }
	}
	win.WEBLIB_WriteKeywords('itemedit');
	document.getElementById('amazon-item-lookup-workstatus').innerHTML = '<p>'+admin_js.formInsertionComplete+'</p>';
    } else document.getElementById('amazon-item-lookup-workstatus').innerHTML = '<h1>'+admin_js.nodata+'</h1>';
}

function AWSLookupCallback(response)
{
  var outHTML = '';
  if (response != null)
  {
      var ErrorsElts = response.getElementsByTagName('Error');
      if (ErrorsElts != null && ErrorsElts.length > 0) {
	  var ierr;
	  var WorkStatus = document.getElementById('amazon-search-workstatus');
	  WorkStatus.innerHTML = '';
	  for (ierr = 0; ierr < ErrorsElts.length; ierr++) {
              var theError = ErrorsElts[ierr];
              var theMessage = theError.getElementsByTagName('Message')[0].childNodes[0].nodeValue;
              WorkStatus.innerHTML += '<p class="error">'+theMessage+'</p>';
	  }
	  return;
      }
      var item = response.getElementsByTagName('Item')[0];
      var title = item.getElementsByTagName('Title')[0].childNodes[0].nodeValue;
      var asin  = item.getElementsByTagName('ASIN')[0].childNodes[0].nodeValue;
      outHTML += '<h3>'+title;
      outHTML += '<img class="WEBLIB_AWS_addinsertbutton"';
      outHTML += ' src="'+admin_js.WEBLIB_BASEURL+'/images/update_field.png" width="16" height="16"';
      outHTML += ' alt="'+admin_js.insertTitle+'"';
      outHTML += ' title="'+admin_js.insertTitle+'"';
      outHTML += ' onclick="AWSWEBLIB_InsertTitle('+"'"+QuoteString(title)+"'"+');" />';
      outHTML += ' ('+asin;
      outHTML += '<img class="WEBLIB_AWS_addinsertbutton"';
      outHTML += ' src="'+admin_js.WEBLIB_BASEURL+'/images/update_field.png" width="16" height="16"';
      outHTML += ' alt="'+admin_js.insertISBN+'"' ;
      outHTML += ' title="'+admin_js.insertISBN+'"' ;
      outHTML += ' onclick="AWSWEBLIB_InsertISBN('+"'"+QuoteString(asin)+"'"+');" />';
      outHTML += ")</h3>\n";
      
      var smallimage = item.getElementsByTagName('SmallImage')[0];
      if (smallimage != null)
      {
	  var smallimageURL = smallimage.getElementsByTagName('URL')[0].childNodes[0].nodeValue;
	  var smallimageHeight = smallimage.getElementsByTagName('Height')[0].childNodes[0].nodeValue;
	  var smallimageWidth = smallimage.getElementsByTagName('Width')[0].childNodes[0].nodeValue;
          
	  outHTML += '<img src="'+smallimageURL+'" height="'+smallimageHeight+
          '" width="'+smallimageWidth+'" border="0">';
          outHTML += '<img class="WEBLIB_AWS_addinsertbutton" src="'+admin_js.WEBLIB_BASEURL+'/images/update_field.png" width="16" height="16" class="WEBLIB_AWS_addinsertbutton" alt="'+admin_js.insertThumbnail+'" title="'+admin_js.insertThumbnail+'" onclick="AWSWEBLIB_InsertThumb('+"'"+QuoteString(smallimageURL)+"'"+');" />';
          
          
      }
      var mediumimage = item.getElementsByTagName('MediumImage')[0];
      if (mediumimage != null)
      {
	  var mediumimageURL = mediumimage.getElementsByTagName('URL')[0].childNodes[0].nodeValue;
	  var mediumimageHeight = mediumimage.getElementsByTagName('Height')[0].childNodes[0].nodeValue;
	  var mediumimageWidth = mediumimage.getElementsByTagName('Width')[0].childNodes[0].nodeValue;
          
	  outHTML += '<img src="'+mediumimageURL+'" height="'+mediumimageHeight+
          '" width="'+mediumimageWidth+'" border="0">';
          outHTML += '<img class="WEBLIB_AWS_addinsertbutton" src="'+admin_js.WEBLIB_BASEURL+'/images/update_field.png" width="16" height="16" class="WEBLIB_AWS_addinsertbutton" alt="'+admin_js.insertThumbnail+'" title="'+admin_js.insertThumbnail+'" onclick="AWSWEBLIB_InsertThumb('+"'"+QuoteString(mediumimageURL)+"'"+');" />';
          
          
      }
      var largeimage = item.getElementsByTagName('LargeImage')[0];
      if (largeimage != null)
      {
	  var largeimageURL = largeimage.getElementsByTagName('URL')[0].childNodes[0].nodeValue;
	  var largeimageHeight = largeimage.getElementsByTagName('Height')[0].childNodes[0].nodeValue;
	  var largeimageWidth = largeimage.getElementsByTagName('Width')[0].childNodes[0].nodeValue;
          
	  outHTML += '<img src="'+largeimageURL+'" height="'+largeimageHeight+
          '" width="'+largeimageWidth+'" border="0">';
          outHTML += '<img class="WEBLIB_AWS_addinsertbutton" src="'+admin_js.WEBLIB_BASEURL+'/images/update_field.png" width="16" height="16" class="WEBLIB_AWS_addinsertbutton" alt="'+admin_js.insertThumbnail+'" title="'+admin_js.insertThumbnail+'" onclick="AWSWEBLIB_InsertThumb('+"'"+QuoteString(largeimageURL)+"'"+');" />';
          
          
      }
      var ItemAttributesList = item.getElementsByTagName('ItemAttributes');
      outHTML += '<ul>';
      var k;
      for (k = 0; k < ItemAttributesList.length; k++)
      {
	  var ItemAttributes = ItemAttributesList[k];
	  outHTML += '<table class="form-table">';
	  for (j = 0; j < ItemAttributes.childNodes.length; j++)
	  {
              var attribute = ItemAttributes.childNodes[j];
              var value = attribute.childNodes[0].nodeValue;
              if (value != null)
              {
                  outHTML += '<tr>';
                  outHTML += '<th valign="top" width="20%">'+attribute.tagName+'</th>';
                  outHTML += '<td valign="top" width="80%">'+attribute.childNodes[0].nodeValue+'</td>';
                  outHTML += '<td>';
                  switch (attribute.tagName) {
                      case 'Editor':
                      case 'Artist':
                      case 'Actor':
                      case 'Director':
                      case 'Foreword':
                      case 'Contributor':
                      case 'Author':
                      var thename = AWSFixName(value);
                      outHTML += '<img src="'+admin_js.WEBLIB_BASEURL+'/images/update_field.png" width="16" height="16" class="WEBLIB_AWS_addinsertbutton" alt="'+admin_js.addToAuthor+'" title="'+admin_js.addToAuthor+'" onclick="AWSWEBLIB_AddAuthor('+"'"+QuoteString(thename)+"'"+');" />';
                      break;
                      case  'Creator':
                      var thename = AWSFixName(value);
                      var role = attribute.attributes.getNamedItem("role");
                      if (role == null) attribute.attributes.getNamedItem("Role");
                      if (role != null) thename += ' ('+role+')';
                      outHTML += '<img src="'+admin_js.WEBLIB_BASEURL+'/images/update_field.png" width="16" height="16" class="WEBLIB_AWS_addinsertbutton" alt="'+admin_js.addToAuthor+'" title="'+admin_js.addToAuthor+'" onclick="AWSWEBLIB_AddAuthor('+"'"+QuoteString(thename)+"'"+');" />';
                      break;
                      case 'ReleaseDate':
                      case 'PublicationDate':
                      outHTML += '<img src="'+admin_js.WEBLIB_BASEURL+'/images/update_field.png" width="16" height="16" class="WEBLIB_AWS_addinsertbutton" alt="'+admin_js.insertAsDate+'" title="'+admin_js.insertAsDate+'"  onclick="AWSWEBLIB_InsertDate('+"'"+QuoteString(value)+"'"+');" />';
                      break;
                      case 'Studio':
                      case 'Label':
                      case 'Publisher':
                      outHTML += '<img src="'+admin_js.WEBLIB_BASEURL+'/images/update_field.png" width="16" height="16" class="WEBLIB_AWS_addinsertbutton" alt="'+admin_js.insertAsPublisher+'" title="'+admin_js.insertAsPublisher+'" onclick="AWSWEBLIB_InsertPublisher('+"'"+QuoteString(value)+"'"+');" />';
                      break;
                      case 'ISBN':
                      outHTML += '<img src="'+admin_js.WEBLIB_BASEURL+'/images/update_field.png" width="16" height="16" class="WEBLIB_AWS_addinsertbutton" alt="'+admin_js.insertISBN+'" title="'+admin_js.insertISBN+'" " onclick="AWSWEBLIB_InsertISBN('+"'"+QuoteString(value)+"'"+');" />';
                      break;
                      case 'Edition':
                      outHTML += '<img src="'+admin_js.WEBLIB_BASEURL+'/images/update_field.png" width="16" height="16" class="WEBLIB_AWS_addinsertbutton" alt="'+admin_js.insertEdition+'" title="'+admin_js.insertEdition+'" onclick="AWSWEBLIB_InsertEdition('+"'"+QuoteString(value)+"'"+');" />';
                      break;
                      case 'Binding':
                      case 'Format':
                      case 'ProductGroup':
                      outHTML += '<img src="'+admin_js.WEBLIB_BASEURL+'/images/update_field.png" width="16" height="16" class="WEBLIB_AWS_addinsertbutton" alt="'+admin_js.addToMedia+'" title="'+admin_js.addToMedia+'" onclick="AWSWEBLIB_AddToMedia('+"'"+QuoteString(value)+"'"+');" />';
                      break;
                      case 'Height':
                      case 'Width':
                      case 'Length':
                      case 'Weight':
                      var units = attribute.attributes.getNamedItem("units");
                      if (units != 'pixels') {
                          var temp = value+' '+units;
                      } else {
                          var temp = value;
                      }
                      outHTML += '<img src="'+admin_js.WEBLIB_BASEURL+'/images/update_field.png" width="16" height="16" class="WEBLIB_AWS_addinsertbutton" alt="'+admin_js.addToDescription+'" title="'+admin_js.addToDescription+'" onclick="AWSWEBLIB_AddToDescription('+"'"+QuoteString(temp)+"'"+');" />';
                      break;
                      case 'Title':
                      outHTML += '<img src="'+admin_js.WEBLIB_BASEURL+'/images/update_field.png" width="16" height="16" class="WEBLIB_AWS_addinsertbutton" alt="'+admin_js.insertTitle+'" title="'+admin_js.insertTitle+'" onclick="AWSWEBLIB_InsertTitle('+"'"+QuoteString(value)+"'"+');" />';  
                      break;
                      default:
                      outHTML += '<img src="'+admin_js.WEBLIB_BASEURL+'/images/update_field.png" width="16" height="16" class="WEBLIB_AWS_addinsertbutton" alt="'+admin_js.addToDescription+'" title="'+admin_js.addToDescription+'" onclick="AWSWEBLIB_AddToDescription('+"'"+QuoteString(value)+"'"+');" />';
                      break;
                  }
                  outHTML += '</td></tr>';
              }
	  }
	  outHTML += '</table>';
      }
      outHTML += '</ul>';
      var keywords = item.getElementsByTagName('Keywords');
      var needcomma = false;
      outHTML += '<p>';
      for (k = 0; k < keywords.length; k++)
      {
	  var keyword = keywords[0];
	  var j;
	  for (j = 0; j < keyword.childNodes.length; j++)
	  {
              if (needcomma) outHTML += ', ';
              outHTML += '<a href="" onclick="AWSWEBLIB_InsertKeyword('+"'"+QuoteString(keyword.childNodes[j].nodeValue)+"'"+');return false;">';
              outHTML += keyword.childNodes[j].nodeValue;
              outHTML += '<img src="'+admin_js.WEBLIB_BASEURL+'/images/update_field.png" width="16" height="16" class="WEBLIB_AWS_addinsertbutton" alt="'+admin_js.addToKeywords+'" />';
              outHTML += '</a>';
              needcomma = true;
	  }
      }
      outHTML += '</p>'
      document.getElementById('amazon-item-lookup-display').innerHTML = outHTML;
      document.getElementById('amazon-item-lookup-workstatus').innerHTML = '<p>'+admin_js.lookupComplete+'</p>';
  } else document.getElementById('amazon-item-lookup-workstatus').innerHTML = '<h1>'+admin_js.nodata+'</h1>';
}



function AWSWEBLIB_InsertTitle(value) {
  var win = opener || parent || top;
  win.WEBLIB_InsertTitle(value);
}

function AWSWEBLIB_InsertISBN(value) {                                         
    var win = opener || parent || top;
    win.WEBLIB_InsertISBN(value);
}                               

function AWSWEBLIB_InsertThumb(value) {
    var win = opener || parent || top;
    win.WEBLIB_InsertThumb(value);
}

function AWSWEBLIB_AddAuthor(value) {
    var win = opener || parent || top;
    win.WEBLIB_AddAuthor(value);
}

function AWSWEBLIB_InsertDate(value) {
    var win = opener || parent || top;
    win.WEBLIB_InsertDate(value);
}

function AWSWEBLIB_InsertPublisher(value) {
    var win = opener || parent || top;
    win.WEBLIB_InsertPublisher(value);
}

function AWSWEBLIB_InsertEdition(value) {
    var win = opener || parent || top;
    win.WEBLIB_InsertEdition(value);
}

function AWSWEBLIB_AddToMedia(value) {
    var win = opener || parent || top;
    win.WEBLIB_AddToMedia(value);
}

function AWSWEBLIB_AddToDescription(value) {
    var win = opener || parent || top;
    win.WEBLIB_AddToDescription(value);
}

function AWSWEBLIB_InsertKeyword(value) {
    var win = opener || parent || top;
    win.WEBLIB_InsertKeyword(value);
}

jQuery(function() {
       jQuery("#SearchString").bind('keypress', function(e) {
              //log("*** #SearchString keypress");
              //log("*** #SearchString keypress: e.keyCode is "+e.keyCode);
              //log("*** #SearchString keypress: e.which is "+e.which);
              if(e.keyCode==13 || e.which==13) {
                  AWSSearch(1);
              }
          });
           
       jQuery("#amazon-page-current").bind('keypress', function(e) {
              //log("*** #amazon-page-current keypress");
              //log("*** #amazon-page-current keypress: e.keyCode is "+e.keyCode);
              //log("*** #amazon-page-current keypress: e.which is "+e.which);
              if(e.keyCode==13 || e.which==13) {
                  AWSGotoPage();
              }
          });
});
                                        
