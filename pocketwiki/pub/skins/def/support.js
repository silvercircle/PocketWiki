function switchCSS(styleName)
{
    jQuery('link[@rel*=style][title]').each(function(i) {
        this.disabled = true;
        if (this.getAttribute('title') == styleName) this.disabled = false;
    });
    createCookie('wp_stylesheet', styleName, 365);
}

function createCookie(name,value,days) {
  if (days) {
    var date = new Date();
    date.setTime(date.getTime()+(days*24*60*60*1000));
    var expires = "; expires="+date.toGMTString();
  }
  else var expires = "";
  document.cookie = name+"="+value+expires+"; path=/";
}

function readCookie(name) {
  var nameEQ = name + "=";
  var ca = document.cookie.split(';');
  for(var i=0;i < ca.length;i++) {
    var c = ca[i];
    while (c.charAt(0)==' ') c = c.substring(1,c.length);
    if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length,c.length);
  }
  return null;
}

function setInitialFontSize(newSize, newStyle) {
    if(jQuery.browser.msie) {
        document.styleSheets[0].addRule("#wikitext", "font-size:" + parseInt(newSize) + textSizeUnit + " !important;");
        if(newStyle == 'serif') {
            document.styleSheets[0].addRule('#wikitext', 'font-family: Constantia,Georgia,serif !important;');
        }
        else {
            document.styleSheets[0].addRule('#wikitext', 'font-family: Verdana,helvetica,sans-serif !important;');
        }
    }
    else {
        document.styleSheets[0].insertRule("#wikitext {font-size:" + parseInt(newSize) + textSizeUnit + " !important;}", 0);
        if(newStyle == 'serif') {
            document.styleSheets[0].insertRule('#wikitext {font-family: Constantia,Georgia,serif !important;}', 0);
        }
        else {
            document.styleSheets[0].insertRule('#wikitext {font-family: Verdana,helvetica,sans-serif !important;}', 0);
        }
    }
    return false;
}

function setTextStyle(newSize) {
    if(textstyle == 'serif') {
        jQuery('#wikitext').css('cssText', 'font-family: Constantia,Georgia,serif !important;font-size: ' + parseInt(newSize) + textSizeUnit + ' !important;');
    }
    else {
        jQuery('#wikitext').css('cssText', 'font-family: Verdana,helvetica,sans-serif !important; font-size: ' + parseInt(newSize) + textSizeUnit + ' !important;');
    }
    jQuery('#fontsize').text(newSize + textSizeUnit);
    createCookie('pm_textstyle', textstyle, 500);
    createCookie('pm_textsizestyle', newSize, 500);
    return false;
}
