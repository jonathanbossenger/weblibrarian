<!DOCTYPE html 
     PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
     "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">

<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en"><head>
<title>Login</title>



<link rel="shortcut icon" href="/NoAuth/images//favicon.png" type="image/png" />
<link rel="stylesheet" href="/NoAuth/css/web2/main-squished.css" type="text/css" media="all" />
<link rel="stylesheet" href="/NoAuth/css/print.css" type="text/css" media="print" />



<script type="text/javascript" src="/NoAuth/js/util.js"></script>
<script type="text/javascript" src="/NoAuth/js/titlebox-state.js"></script>
<script type="text/javascript" src="/NoAuth/js/ahah.js"></script>
<script type="text/javascript" src="/NoAuth/js/prototype/prototype.js"></script>
<script type="text/javascript" src="/NoAuth/js/scriptaculous/scriptaculous.js?load=effects,controls"></script>
<script type="text/javascript" src="/NoAuth/RichText/fckeditor.js"></script>
<script type="text/javascript"><!--
    doOnLoad(loadTitleBoxStates);
    doOnLoad(function () { focusElementById('user') });


    function ReplaceAllTextareas() {
        var sAgent = navigator.userAgent.toLowerCase();
        if (!FCKeditor_IsCompatibleBrowser() ||
            sAgent.indexOf('iphone') != -1 ||
            sAgent.indexOf('ipad') != -1 ||
            sAgent.indexOf('android') != -1 )
            return false;

        // replace all content and signature message boxes
        var allTextAreas = document.getElementsByTagName("textarea");

        for (var i=0; i < allTextAreas.length; i++) {
            var textArea = allTextAreas[i];
            if ( (textArea.getAttribute('class') == 'messagebox')
              || (textArea.getAttribute('className') == 'messagebox')) {
                // Turn the original plain text content into HTML 

                textArea.value = textArea.value.replace(
                    /&/g, "&amp;"
                ).replace(
                    /</g, "&lt;"
                ).replace(
                    />/g, "&gt;"
                ).replace(
                    /\n/g, "\n<br />"
                );

                var FCKeditorEncoded = document.createElement('input');
                FCKeditorEncoded.setAttribute('type', 'hidden');
                FCKeditorEncoded.setAttribute('name', 'FCKeditorEncoded');
                FCKeditorEncoded.setAttribute('value', '1');
                textArea.parentNode.appendChild(FCKeditorEncoded);

                var typeField = document.createElement('input');
                typeField.setAttribute('type', 'hidden');
                typeField.setAttribute('name', textArea.name + 'Type');
                typeField.setAttribute('value', 'text/html');
                textArea.parentNode.appendChild(typeField);

                var oFCKeditor = new FCKeditor( textArea.name, '100%', '200' );
                oFCKeditor.BasePath = ''+"/NoAuth/RichText/";
                oFCKeditor.ReplaceTextarea();
            }
        }
    }
    doOnLoad(ReplaceAllTextareas);
--></script>


<!--[if lt IE 8]>
<link rel="stylesheet" href="/NoAuth/css/web2/msie.css" type="text/css" media="all" />

<![endif]-->
<!--[if lt IE 7]>
<link rel="stylesheet" href="/NoAuth/css/web2/msie6.css" type="text/css" media="all" />
<![endif]-->


</head>
  <body id="comp-NoAuth-Login">

<div id="logo">
<a href="http://bestpractical.com"><img
    src="/NoAuth/images/bplogo.gif"
    alt="Best Practical Solutions, LLC corporate logo"
    width="177"
    height="33" /></a>
    <span class="rtname">RT for deepsoft.com</span>
</div>


<div id="quickbar">
  <div id="quick-personal">
    <span class="hide"><a href="#skipnav">Skip Menu</a> | </span>
    Not logged in.

</div>






</div>

<div id="body" class="login-body">




<div id="login-box">
<div class="">
  <div class="titlebox" id="">
  <div class="titlebox-title">
    <span class="left">
      	Login</span>
    <span class="right">
	
	3.8.13
    </span>
  </div>
  <div class="titlebox-content " id="TitleBox--_NoAuth_Login.html------TG9naW4_---0">




<form id="login" name="login" method="post" action="/NoAuth/Login.html">

<div class="input-row">
    <span class="label">Username:</span>
    <span class="input"><input name="user" value="" id="user" /></span>
</div>

<div class="input-row">
    <span class="label">Password:</span>
    <span class="input"><input type="password" name="pass" autocomplete="off" /></span>
</div>

<input type="hidden" name="next" value="93d52e352eeffbc1e469b0bb25ef5bc8" />

<div class="button-row">
    <span class="input"><input type="submit" class="button" value="Login" /></span>
</div>


</form>
    <hr class="clear" />
  </div>
</div>




</div>


</div><!-- #login-box -->
</div><!-- #login-body -->
</div>
<div id="footer">
  <p id="time">
    <span>Time to display: 0.004249</span>
  </p>
  <p id="bpscredits">
    <span>
&#187;&#124;&#171; RT 3.8.13 Copyright 1996-2010 <a href="http://www.bestpractical.com?rt=3.8.13">Best Practical Solutions, LLC</a>.
</span>
</p>
  <p id="legal">
Distributed under version 2 <a href="http://www.gnu.org/copyleft/gpl.html"> of the GNU GPL.</a><br />
To inquire about support, training, custom development or licensing, please contact <a href="mailto:sales@bestpractical.com">sales@bestpractical.com</a>.<br />
  </p>

</div>

  </body>
</html>



