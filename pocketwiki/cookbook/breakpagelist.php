<?php if (!defined('PmWiki')) exit();

/* breakpagelist.php for PmWiki 2, to break a long pagelist display into several subpages, 
   and display links to all subpages as a series of page numbers.
   Copyright 2009 Hans Bracker
   This program is free software; you can redistribute it and/or modify
   it under the terms of the GNU General Public License as published
   by the Free Software Foundation; either version 2 of the License, or
   (at your option) any later version.

   Usage: see pmwiki.org Cookbook/BreakPageList
*/

$RecipeInfo['BreakPageList']['Version'] = '2009-08-26';

## Default settings:
SDVA($BPLConfig, array(
	'slice' => 20,    //sets number of results to be displayed per page; normally set with (:pagelist ... count=nn :)
	'byitem' => false, //sets url arg as item numbers, defaultis false = page numbers
	'urlkey' => 'p',   //sets key used for url parameter
	'navtype' => 3,       //sets nav link type
	//sets various text strings
	'results' => 'Results',
	'page'    => 'Page',
	'of'      => 'of',
	'goto'    => 'Go to page',
	'next'    => 'Next',
	'prev'    => 'Previous',
));	

## Markup (:bplinit args.... :)
#Markup('bplinit','directives','/\\(:bplinit\\s*(.*?)\\s*:\\)/e',
#		"BPLInit(\$pagename, PSS('$1'))");
function BPLInit($pagename, $arg) {
	global $FPLTemplateFunctions, $BPLConfig;
	$FPLTemplateFunctions['FPLBreakPageList'] = 350; # eg before FPLTemplateSliceList()
	$arg = ParseArgs($arg); unset($arg['#']);
	foreach ($arg as $k => $v) $BPLConfig[$k] = $v;
} //}}}	

# generate navigation links to page chunks
function FPLBreakPageList($pagename, $matches, &$opt, $tparts) {
	global $BPLConfig;
	if (!$opt['start'] == 'BPL') return;
	//initialise 
	$max = count($matches);
	$slice = ($opt['count']) ? $opt['count'] : $BPLConfig['slice'];
	$key = $BPLConfig['urlkey'];
	$byitem = $BPLConfig['byitem'];
	//get any BPLvar from $opt
	foreach($BPLConfig as $k => $val)
		if ($opt['BPL'.$k]) $BPLConfig[$k] = $opt['BPL'.$k];
	//get url arg and calculate
	$p = @$_REQUEST[$key];
	$p = intval($p);
	if($slice==0) $slice=1; //prevent div by zero error
	if ($byitem) $p = floor(($p-1)/$slice)+1;
	$last = ceil($max/$slice);
	if ($last<=0) $last=1;
	if ($p<1) $p=1;
	if ($p>$last) $p=$last;		
	$from = ($p-1)*$slice+1;
	$to = $from+$slice-1;
	if ($to>$max) $to = $max;
	
	//set $opt arguments for next FPL functions (slicing and formatting)
	foreach ($BPLConfig as $k => $val) 
			$opt['BPL'.$k] = $val;	
	$opt['count'] = $from."..".$to;	
	$opt['BPLfrom'] = $from;
	$opt['BPLto'] = $to;
	$opt['BPLmax'] = $max;
	$opt['BPLlast'] = $last;
	$opt['BPLcurr'] = $p;
	$opt['BPLprev'] = ($p>1) ? $p-1 : NULL;
	$opt['BPLnext'] = ($p+1 <= $last) ? $p+1 : NULL;
	$opt['BPLnav'] = &$nav;
	$opt['BPLnumlinks'] = &$numlinks;
	$opt['BPLprevlink'] = &$prevlink;
	$opt['BPLnextlink'] = &$nextlink;	
#$BPLConfig['right2left'] = 1;
#$BPLConfig['navtype'] =10;
	//type selection: $dots=1 show last and first pagelink and dots
	//$d equals the number links either side of current, less 1 if $dots
	switch ($BPLConfig['navtype']) {
		case 0: $d=0;  $dots=0; break;
		case 1: $d=2;  $dots=1; break;
		case 2: $d=3;  $dots=1; break;
		case 3: $d=4;  $dots=1; break;
		case 4: $d=5;  $dots=1; break;
		case 5: $d=5;  $dots=0; break;
		case 10: $d=10; $dots=0; break;
		default: $d=4;  $dots=1; break;
	}
	//make prev and next nav links
	$info = ($last == 1) ? " $max {$BPLConfig['results']} &ndash; {$BPLConfig['page']}" :
		" $max {$BPLConfig['results']} &ndash; {$BPLConfig['page']} $p {$BPLConfig['of']} $last &ndash; {$BPLConfig['goto']} ";
	if ($BPLConfig['right2left']) {
		$info = ($last == 1) ? " {$BPLConfig['page']} &ndash; {$BPLConfig['results']} $max " :
			" {$BPLConfig['goto']} &ndash; $last {$BPLConfig['of']} $p  {$BPLConfig['page']} &ndash; {$BPLConfig['results']} $max ";
	}
	$prev = ($byitem) ? ($p-2)*$slice+1 : $p-1;
	$next = ($byitem) ? $p*$slice+1 : $p+1;	
	$prevlink = ($p-1 > 0) ? " [[$pagename?$key=$prev|{$BPLConfig['prev']}]]" : " ";
	$nextlink = ($p+1 <= $last) ? " [[$pagename?$key=$next|{$BPLConfig['next']}]]" : " ";
	//make number nav links
	$numlnk = array(); $firstlnk = ''; $lastlnk = '';
	
	if ($dots==1 && $p>1)
		$numlnk[] = " [[$pagename?$key=1|1]]";

	for($i = $p-$d; $i <= $p+$d; $i++) {
		if ($i<1) continue; if ($i>$last) break;
		if ($i==$p) $numlnk[] = " $p";
		else if ($dots==1 AND (($i==$p-$d && $i>1) OR ($i==$p+$d && $i<$last)) )
			$numlnk[] = " ...";
		elseif($i>$dots && $i<=$last-$dots) {
			$num = ($byitem) ? ($i-1)*$slice+1 : $i;
			$numlnk[] = " [[$pagename?$key=$num|$i]]";
		}
	}
	if ($dots==1 && $p<$last) {
		$num = ($byitem) ? ($last-1)*$slice+1 : $last;
		$numlnk[] = " [[$pagename?$key=$num|$last]]";
	}
	//assemble nav links
	if ($BPLConfig['right2left'])	$numlnk = array_reverse($numlnk);
	$numlinks = implode("",$numlnk);
	$nav = $info.$prevlink.$numlinks.$nextlink;
	if ($BPLConfig['right2left']) $nav = $nextlink.$numlinks.$prevlink.$info;

} //}}}

## Markup  expression {(sum arg1 arg2 arg3 ...)}
$MarkupExpr['sum'] = 'BPLSum($pagename, $args)'; 
function BPLSum($pagename, $args ) {
	(int)$sum = 0;
	foreach($args as $aa) {
		$a = str_replace('+',' +', $aa);
		$a = str_replace('-',' -', $a);
		$b = explode(' ',$a);
		foreach($b as $bb)
			$sum += floatval($bb);
	}	
	return $sum;
}
//EOF