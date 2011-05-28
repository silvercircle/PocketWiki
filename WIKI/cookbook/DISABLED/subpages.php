<?php if (!defined('PmWiki')) exit();
/*
    subpage markup version 2.1.11

    Copyright 2006 John Rankin (john.rankin@affinity.co.nz)
    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published
    by the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.
	minor tweaks by Anke Wehner, August 2010
*/

SDV($EnableDisambiguation,2);
$oAsSpacedFunction = $AsSpacedFunction;
$AsSpacedFunction  = 'SpaceAfterComma';

function SpaceAfterComma($text) {
  global $oAsSpacedFunction;
  $text = preg_replace('/^(\d{4})(\d{2})(\d{2}(?:,|$))/','\1-\2-\3',$text);
  return str_replace(',',', ',$oAsSpacedFunction($text));
}

## [[,subpage]]
$HTMLStylesFmt['subpage'] = "
.subpage h1, h1.subpage { margin:0px; margin-top:1.2em; margin-bottom:8px; 
    color: #006633;
	font-size: 150%; }
p.subpage { float: right; }
";
#$SearchPatterns['normal'][] = '!,del-\d+$!';
$PageNameChars  = '-~,[:alnum:]';
$SubNamePattern = '[[:upper:]\\d][\\w]*(?:[-~]\\w+)*';
$NamePattern    = "$SubNamePattern(?:,$SubNamePattern)?";
Markup('[[,','<links','/\[\[,([^\|\]]+)(?:\|\s*([^\]]+))?\]\]/e',
    "'[[' . preg_replace('/,.*$/','',FmtPageName('\$Name',\$pagename)) .
    PSS(', $1 |') . (('$2'=='') ? '$1' : '$2') . ']]'");
## escaped `SubPages
Markup('`subpage', '<`wikiword',
  "/`(($GroupPattern([\\/.]))?$NamePattern)/e",
  "Keep('$1')");
if ($EnableDisambiguation==1)
    Markup('[[(,)','<links','/\[\[([^,#\]\(\|]+),\s*([^\|\]]+)\]\]/e',
    "SubpageMismatch(PSS('$2')) ? '$0' : '[[$1(, $2)]]'");
elseif ($EnableDisambiguation==2)
    Markup('[[(,)','<links','/\[\[([^,#\]\(\|]+),\s*([^\|\]]+)\]\]/e',
    "SubpageMismatch(PSS('$2')) ? '$0' : '[[$1, $2 | $2]] ([[$1]])'");
if (preg_match('/^([^\/.]+[\/.]([^,]+)),[^,]+$/',$pagename,$parent_child)) {
    SDV($SubpageTitleFmt, '(:title $SubpageTitle:)$[Subpage of]');
    $FmtPV['$SubpageTitle'] = 
        '$AsSpacedFunction(preg_replace("/^[^,]+,/",",",$name))';
    $GroupHeaderFmt = 
        "!%block class=subpage%" . FmtPageName($SubpageTitleFmt,$pagename) . 
        " [[$parent_child[1] |".$AsSpacedFunction($parent_child[2])."]](:nl:)" . 
        $GroupHeaderFmt;
    if (IsEnabled($EnablePGCust,1) && file_exists("local/$parent_child[1].php"))
        include_once("local/$parent_child[1].php");
}

function SubpageMismatch($txt) { return strstr($txt, '-&gt;'); }

function SubpageToggle($pagename, $opt) {
  global $SubpageToggleFmt, $SubpageToggleOpt, $PDFCheckboxFmt, $PDFTypesetFmt;
  if (isset($SubpageToggleFmt)) 
      return Keep(FmtPageName($SubpageToggleFmt, $pagename));
  SDVA($SubpageToggleOpt,
        array('subpage' => (@$_GET['subpage']=='show') ? 'hide' : 'show',
              'show'    => FmtPageName('$[Show subpages]', $pagename), 
              'hide'    => FmtPageName('$[Hide subpages]', $pagename),
              'reverse' => FmtPageName('$[Reverse order]', $pagename),
              'print'   => FmtPageName('$[Print]', $pagename)));
  $opt = array_merge($SubpageToggleOpt, (array)$opt);
  $out[] = FmtPageName("class='publish' action='\$ScriptUrl' method='get'>",
    $pagename);
  $out[] = FmtPageName("<input type='hidden' name='n' value='\$FullName' />", 
    $pagename);
  $out[] = "<input type='hidden' name='subpage' value=\"{$opt['subpage']}\" />";
  if (@$opt['action']=='print') $out[] = "<input type='checkbox' 
    name='action' value='print' /> {$opt['print']} ";
  elseif (@$opt['action']=='publish') {
    SDV($PrintTagFmt,"<form class='publish' action='\$ScriptUrl' method='get'>
    <input type='hidden' name='n' value='\$FullName' />
    <input type='hidden' name='action' value='print' />
    <input type='hidden' name='ptype'  value='print' />
    <input type='hidden' name='subpage' value='show' />
    $PDFCheckboxFmt $PDFTypesetFmt $PDFOptionsFmt</form>");
    $pdf = Keep(FmtPageName($PrintTagFmt, $pagename));
  }
  $out[] = "<input class='pubbutton'
    type='submit' value=\"{$opt[$opt['subpage']]}\" />";
  if (@$_GET['subpage']=='hide')
    $out[] = "<input type='checkbox' name='reverse' /> {$opt['reverse']}";
  else
    $out[] = "<input type='hidden' name='reverse' value=\"{$_GET['reverse']}\" />".
      ($_GET['reverse'] ? ' '.$opt['reverse'] : '');
  return '<form '.Keep(implode('',$out))."</form>$pdf";
}

Markup('subpage', 'directives', '/^\(:subpage\s*(.*?):\)/e',
   $action=='print' ? "" : "SubpageToggle(\$pagename, ParseArgs(PSS('$1')))");
if (@$_GET['subpage']=='show') {
   if (@$_GET['reverse']) {
      Markup('switchsub', '<showsubpage',
      "/((?:\*\s*\[\[,[^\|\]]+(?:\|\s*[^\]]+)?\]\][^\n]*\n)+)".
      "(\n?\*\s*\[\[,[^\|\]]+(?:\|\s*[^\]]+)?\]\][^\n]*\n)/si", '$2$1');
	}
   Markup('showsubpage','fulltext',
   "/(\n?\*\s*(\[\[,([^\|\]]+)(?:\|\s*([^\]]+))?\]\])([^\n]*)\n)(.*)$/sei",
   "str_replace(PSS(',$3'),'#'.FmtPageName('\$Name',MakePageName(\$pagename,PSS('$3'))).
    (('$4'=='') ? '|'.PSS('$3') : ''),PSS('$1')).PSS('$6').".
   "'\n&gt;&gt;class=subpage&lt;&lt;'.".($action=='print' ? '' :
   "'\n%block class=subpage%[['.\$pagename.PSS(',$3?action=edit|edit ,$3]]').").
   "'\n![[#'.FmtPageName('\$Name',MakePageName(\$pagename,PSS('$3'))).']]'.PSS('$2').
    '\n(:include '.MakePageName(\$pagename,\$pagename.PSS(',$3')).':)'.
    '\n(:title {\$Title}:)'.
    '\n&gt;&gt;&lt;&lt;\n'");
} elseif (@$_GET['reverse'])
   Markup('switchsub', '>include',
   "/(?:\n?\*\s*\[\[,[^\|\]]+(?:\|\s*[^\]]+)?\]\][^\n]*\n)+/sei", 
   "implode('\n',array_reverse(explode('\n',PSS('$0')))).'\n'");

?>