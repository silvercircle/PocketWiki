<?php if (!defined('PmWiki')) exit();
/*  Original breakpage.php copyright 2004 Patrick R. Michaud (pmichaud@pobox.com)
    Modified breakpage2.php copyright 2006 Hans Bracker.
    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published
    by the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.  

    This program adds (:comment breakpage:) markup to PmWiki.  A page containing
    one or more (:comment breakpage:) directives is displayed in smaller chunks,
    with a "Page 1 2 3 ..." navigation at the end to provide access
    to the other chunks of the page.

    (:comment breakpage:) is only processed this way if ?action=browse.
    If ?action=edit, then (:comment breakpage:) is converted to a visual
    indicator of a page break; for all other actions (:comment breakpage:)
    is converted to a null string.
    
    Modified to use (:comment breakpage:) instead of (:breakpage:),
    which will result in invisible markers in case page breaks are disabled.
    Added $EnablePageBreaks variable.
    This recipe is working in close integration with commentboxstyled.php.
*/
//define the breakpage2 version number
$RecipeInfo['BreakPage']['Version'] = '2006-10-28';


SDV($EnablePageBreaks, 1);

function PageBreak2($pagename,$text) {
  $p = explode('(:comment breakpage:)',$text);
  $n = @$_REQUEST['p'];
  if ($n<1) $n=1;  if ($n>count($p)) $n=count($p);
  $out[] = "<div class='breaklist'>Page";
  for($i=1;$i<=count($p);$i++) {
    if ($i==$n) $out[] = " $n";
    else $out[] = FmtPageName(" <a href='\$PageUrl?p=$i'>$i</a>",$pagename);
  }
  $out[] = '</div>';
  return $p[$n-1].Keep(implode('',$out));
}

if ($EnablePageBreaks==1) {
    if ($action=='browse') 
      Markup('breakpage2','>include','/^.*\\(:comment breakpage:\\).*$/se',
        "PageBreak2(\$pagename,PSS('$0'))");
    elseif ($action=='edit')
      Markup('breakpage2','>include','/\\(:comment breakpage:\\)/',
        "\n<div class='breakpage'>&mdash; Page Break &mdash;</div>\n");
    else
      Markup('breakpage2','directives','/\\(:comment breakpage:\\)/','');
}
