<?php if (!defined('PmWiki')) exit();

global $WikiLibDirs, $HTMLStylesFmt, $FmtPV, $SiteGroup, $action, $pagename;

// remove SideBar when...
/*
if ($action   == "edit" ||
    $action   == "diff" ||
    $pagename == "$SiteGroup.Search" ||
    $action   == 'search' ||
    $action   == 'login')
{
    SetTmplDisplay('PageLeftFmt',0);
    $HTMLStylesFmt['noleft'] = "td#wiki-page{
                                margin:0;border-left:0;width:100%;
                                } td#wiki-left {width:0;}";
}
*/
//Directive for info

 Markup('noinfo',
	'directives',
	'/\\(:noinfo:\\)/ei',
	"SetTmplDisplay('PageInfoFmt',0)"); 

// Charset & lang & search

$FmtPV['$Lang'] = '$GLOBALS["XLLangs"][0]';
$FmtPV['$CharSet'] = '$GLOBALS["Charset"]';
$FmtPV['$Search'] = 'str_replace(")","",
str_replace("(","",str_replace("&quot;","",
str_replace("&#039;" ,"",htmlspecialchars(
stripmagic(strip_tags(XL(Search,$fmt))),
ENT_QUOTES)))))'; 
//long one. (to prevent XSS attacks) 
//remember to protect your translation files (XLPages)

// Some date formats

$FmtPV['$Today'] = 'strftime("%a, %d %b %y .", time() )';
$FmtPV['$LastModifiedDate'] = 'strftime("%a, %d %b %y", $page["time"])';

//Includes green.d in WikiLibDirs

$PageStorePath = dirname(__FILE__)."/def.d/\$FullName";
$where = count($WikiLibDirs);
if ($where>1) $where--;
array_splice($WikiLibDirs, $where, 0,
             array(new PageStore($PageStorePath)));



