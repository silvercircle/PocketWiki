<?php if (!defined('PmWiki')) exit();
/*  Copyright 2009 Hans Bracker. 
    This file is toggle.php; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published
    by the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.
    
    (:toggle id=divname :) creates a toggle link, which can show or hide 
    a division or other object on the page, for instance a div created with
    >>id=divisionname<< 
    text can be hidden/shown 
    >><< 
    Necessary parameters: (:toggle id=divname:) 
    Alternative: (:toggle divname:)
    Alternative with options: 
    (:toggle hide divname:) initial hide
    (:toggle hide divname button:) initial hide, button
    (:toggle name1 name2:) toggle between name1 and name2
    Optional parameters:
    init=hide  hides the division initially (default is show)
    show=labelname  label of link or button when div is hidden (default is Show)
    hide=labelname label of link or button when div is shown (default is Hide)
    label=labelname label of link or button for both toggle states
    id2=objname second object (div), for toggling betwen first and second object
    set=1 sets a cookie to remember toggle state
*/ 
# Version date
$RecipeInfo['Toggle']['Version'] = '2009-07-23';

# declare $Toggle for (:if enabled $Toggle:) recipe installation check
global $Toggle; $Toggle = 1;

Markup('toggle', 'directives',
  '/\\(:toggle\\s*(.*?):\\)/ei',
  "ToggleMarkup(\$pagename, PSS('$1'))");
  
# all in one function
function ToggleMarkup($pagename,$args) {
   # javascript for toggling and cookie setting
   global $HTMLFooterFmt, $HTMLStylesFmt, $ToggleConfig, $UploadUrlFmt, $UploadPrefixFmt;
	SDVA($ToggleConfig, array(
		'init' => 'show',       //show div 
		'show' => XL("Show"),  //link text 'Show'
		'hide' => XL("Hide"),  //link text 'Hide'
		'ttshow' => XL("Show"),  //tooltip text 'Show'
		'tthide' => XL("Hide"),  //tooltip text 'Hide'
		'set' => false,         //set no cookie to remeber toggle state
		'id' => '',            //no default div name
		'id2' => '',           //no default div2 name
		'set' => false,         //set no cookie to remeber toggle state
		'printhidden' => true,  // hidden divs get printed
	));   

	$HTMLStylesFmt['toggle'] = "@media print {.toggle{display:none}} .toggle img {border:none;} ";
   
  $HTMLFooterFmt['toggleobj'] = "";
   $args = ParseArgs($args); 
	//get parameters without keys
	if (is_array($args[''])) {
	   while (count($args[''])>0) {
			$par = array_shift($args['']);
	   	if ($par=='button') $args['button'] = 1;
	   	elseif ($par=='hide') $args['init'] = 'hide';
	   	elseif ($par=='show') $args['init'] = 'show';
	   	elseif (!isset($args['id'])) $args['id'] = $par;
	   	elseif (!isset($args['id2'])) $args['id2'] = $par;	   
		}
	}
   $args = array_merge($ToggleConfig, $args);

 	$id = (isset($args['div'])) ? $args['div'] : $args['id'];
 	$id2 = (isset($args['div2'])) ? $args['div2'] : $args['id2'];
	if ($id=='') return "//!Error:// no object id specified!"; 
	$tog = $args['init'];
	$ts = array();
	if (isset($args['label'])) 
		$ts['show'] = $ts['hide'] = $args['label'];
	else {
		$ts['show'] = (isset($args['lshow'])) ? $args['lshow'] : $args['show'];
		$ts['hide'] = (isset($args['lhide'])) ? $args['lhide'] : $args['hide'];	
	}
	$ipat = "/\.png|\.gif|\.jpg|\.jpeg|\.ico/";
	foreach ($ts as $k => $val) {
		//check for image, make image tag
		if (preg_match($ipat, $val)) {
			$prefix = (strstr($val, '/')) ? '/' : $UploadPrefixFmt; 
			$path = FmtPageName($UploadUrlFmt.$prefix, $pagename);
			$ts[$k] = "<img src=$path/$val title={$args['tt'.$k]}&nbsp;$id />";
			$args['button'] = '';
		}
		//apostrophe encoding
		else $ts[$k] = str_replace("'","&rsquo;",$val);
	}	
	$show = $ts['show']; $hide = $ts['hide'];

   //check cookie  if set=1
   if($args['set']==1) { 
      global $CookiePrefix, $SkinName;
      $cookie = $CookiePrefix.$SkinName.'_toggle_'.$id;
      if (isset($_COOKIE[$cookie])) $tog = $_COOKIE[$cookie];
   }      
   
   //toggle state 
	if ($tog=='show') { 
		$style = 'block';
		$altstyle = 'none';
		$label = $hide;
		$tog = 'hide';
	} else {
		$style = 'none';
		$altstyle = 'block';
		$label = $show;
		$tog = 'show';
	}

	//set initial toggle link or button (later it is build with javascript)
	$act = "javascript:toggleObj('{$id}','{$tog}','{$show}','{$hide}','{$id2}','{$args['set']}','{$cookie}','{$args['button']}')";
	$out = "<span id=\"{$id}-tog\" class=\"toggle\">";
	if ($args['button']==1 || $args['button']=='button')
		$out .= "<input type=\"button\" class=\"inputbutton togglebutton\" value=\"$label\" onclick=\"{$act}\" />";
	else  $out .= "<a class=\"togglelink\" href=\"{$act}\">{$label}</a>";
	
	$out.= "<style type='text/css'> #{$id} { display:{$style}; } ";
	if ($args['printhidden']==1) $out.= " @media print { #{$id} {display:block} } ";
	if ($id2) { 
		$out.= " #{$id2} { display:{$altstyle}; } ";
		if ($args['printhidden']==1) $out.= " @media print { #{$id2} {display:block} } ";
	}
	
	$out .= "</style></span>";
   return Keep($out);
}
#EOF