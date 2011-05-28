/* copyright Henrik Bechmann, Toronto, Canada, 2006. All rights reserved 
    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.
*/ 

function processquicktoc() {
 var theTOCElement=document.getElementById('quicktocframe');
 if (!theTOCElement) return;
 var theTOCHeading=document.getElementById('quicktocheading');
 var theElement=document.getElementById('quicktoccontents');
 if ((!theElement.style.display) || (theElement.style.display=='none')) {
  theElement.style.display='block';
  theTOCHeading.className='open';
  var theTOCstatus=theTOCElement.getAttribute("quicktocstatus");
  if ((!theTOCstatus) || (theTOCstatus=='empty')) {
   populatequicktoc(theTOCElement,theElement);
  }
  document.getElementById('quicktocmsg').innerHTML='';
 } else {
  theElement.style.display='none';
  theTOCHeading.className='closed';
  document.getElementById('quicktocmsg').innerHTML='';
 }
}
function populatequicktoc(theTOCElement,theContentsElement) {
 var theWikiTextElement = document.getElementById('wikitext');
 var theList=theWikiTextElement.childNodes,theHeadings=new Array();
 var theHeadingElements='H1,H2,H3,H4,H5,H6';
 var theLength=theList.length,theTag;
 var theIndex;
 var theHTML='';
 function HeadingInfo() {
  this.element=null;
  this.depth=null;
  this.id=null;
  this.text=null;
  this.html=null;
  this.indent=null;
 }
 var theIndent=6,theMargin,theStyle;
 for (var i=0;i<theLength;i++) {
  theTag=theList[i].tagName;
  theIndex=theHeadingElements.indexOf(theTag);
  if (theIndex>-1) {
   var theHeading = new HeadingInfo;
   theHeading.element=theList[i];
   theHeading.depth=((theIndex+3)/3);
   theIndent=Math.min(theIndent,theHeading.depth);
   theHeading.id=theHeading.element.id;
   theHeadings.push(theHeading);
  }
 }
 theLength=theHeadings.length;
 for (var i=0;i<theLength;i++) {
  var theHeading=theHeadings[i];
  theHeading.text=getelementtext(theHeading.element);
  if (!theHeading.id) {
   theHeading.id="tempid-"+i;
   theHeading.element.setAttribute("id",theHeading.id);
  }
  theMargin=theHeading.depth-theIndent;
 theStyle="margin-left:"+ (5*theMargin)+"px;";
  if (theMargin==0) {
   theStyle+="font-weight:bold;";
  }
  theHeading.html='<div class="quicktocitem" style="' + theStyle + '"><a href="#'+theHeading.id+'">'+theHeading.text+'</a></div>';
  theHTML+=theHeading.html;
 }
 theContentsElement.innerHTML=theHTML;
 theTOCElement.setAttribute("quicktocstatus","loaded")
}
function getelementtext(theElement) {
 var theChildren=theElement.childNodes;
 var theText='';
 var theLength=theChildren.length;
 for (var i=0;i<theLength;i++) {
  if (theChildren[i].nodeType==3) {
   theText+=theChildren[i].nodeValue;
  } else {
   theText+=getelementtext(theChildren[i]);
  }
 }
 return theText;
}
