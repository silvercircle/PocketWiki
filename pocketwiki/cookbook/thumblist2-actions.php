<?php if (!defined('PmWiki')) exit();
/**
  A thumbnail gallery generator for PmWiki
  Written by (c) Petko Yotov 2006-2010

  This script is POSTCARDWARE, if you like it or use it,
  you can send me a postcard. Details at
  http://galleries.accent.bg/Cookbook/Postcard

  This text is written for PmWiki; you can redistribute it and/or modify
  it under the terms of the GNU General Public License as published
  by the Free Software Foundation; either version 3 of the License, or
  (at your option) any later version. See pmwiki.php for full details
  and lack of warranty.

  Copyright 2006-2010 Petko Yotov http://5ko.fr
  Copyright 2004-2006 Patrick R. Michaud http://www.pmwiki.org
  Copyright 2006 Hagan Fox (haganfoxATusersDOTsourceforge.net)

  This file is automatically included by thumblist2.php when needed
  and should be in the same directory.
*/
# This file was last modified: 20100215

SDVA($ThumbList, array(
'Quality' => 90,
'ImageTplFmt' => '(:include {*$:ImageTemplate} {$FullName}-ImageTemplate {$Group}.ImageTemplate {$SiteGroup}.ImageTemplate:)',
'NoImageTplFmt' => '!! {*$UpFile}
%p center% {*$PrevLink} [[{*$FullName}?action=browse | $[Back to gallery] ]] {*$NextLink}\\\\
Attach:{*$UpDirUpFile}
(:title {*$FullName} / {*$UpFile}:)',
'UseTmpl' => 0,
'PurgeRedirectFmt' => '{$PageUrl}?action=upload',
'ImageMagickExe' => '', 'DefaultCLTpl' => 'default',
'fCreateIM'=> 'ThumbCreateIM','fCreateGD'=> 'ThumbCreateGD','fProcessGD'=> '',
'AutoPurgeThumbsDays'=> 0,
'MaxThumbs'=> 3000,
'DirThumbsRatio'=> 3,
'AutoPurgeRatio'=> 0.3,
'AutoPurgeDelay'=> 1800,
'AutoPurgeLock'=> "$WorkDir/.thumblist.lock",
'Sharpen' => 16,
));

SDVA($HandleActions, array('imgtpl'=>'HandleImageTemplate',
  'purgethumbs'=>"HandlePurgeThumbnails",'createthumb'=>"HandleCreateThumb"));
SDVA($HandleAuth, array('purgethumbs' => 'edit', 'imgtpl'=>'read', 'createthumb'=>'read'));
SDV($RobotActions['imgtpl'], 1);
SDV($RobotActions['createthumb'], 1);


function HandleCreateThumb($pagename, $auth="read") {
  global $UploadDir, $UploadPrefixFmt, $ThumbList;
  if(!$ThumbList['EnableThumbs'])Abort("Thumbnail creation is disabled.");
  $fCreate = $ThumbList['ImageMagickExe'] ? $ThumbList['fCreateIM'] : $ThumbList['fCreateGD'];

  $page = RetrieveAuthPage($pagename,$auth,1, READPAGE_CURRENT);#will ask for pw if needed
  $uploaddir = FmtPageName("$UploadDir$UploadPrefixFmt", $pagename);

  $t = $_REQUEST['upname'];
  $pt = "$uploaddir/$t";
  $lock = "$uploaddir/.$t.lock";
  if(!file_exists($lock)) {
    if(file_exists($pt)){HandleDownload($pagename);}
    else Abort("?Lock file not found.");
    exit;
  }

  ThumbAutoPurge($uploaddir);

  if(preg_match( "/^th(\\d+)---(?:([0-9a-f]{6}|none)--)?(.+)\\.(jpg|png)$/i", $t, $m ) ) {
    $f = "$uploaddir/{$m[3]}";
    if(!file_exists($f) ) Abort("?Source file $f not found.");
    $info = @getimagesize($f);
    list($imgw, $imgh, $t) = $info;
    $nh = $m[1]; $nw = round($imgw * $nh / $imgh);

    if(!file_exists($pt) || filemtime($f)>filemtime($pt))#source newer than thumb
      $fCreate($f,$pt,$imgw,$imgh,$nw,$nh,$t, "#{$m[2]}", @$_REQUEST['imcl']);
    if(!file_exists($pt)) Abort("Thumbnail $pt was not created for $f.");
    @unlink($lock);
    HandleDownload($pagename);
    exit;
  }
  Abort("?Unrecognized format: ThumbList upname='$t'.");
}
function HandlePurgeThumbnails($pagename, $auth='edit') {
  RetrieveAuthPage($pagename, $auth, true, READPAGE_CURRENT);
  global $UploadDir, $UploadPrefixFmt, $ThumbList, $Now;
  $uploaddir = FmtPageName("$UploadDir$UploadPrefixFmt", $pagename);
  if ($dirp = @opendir($uploaddir)) {
    while (($file=readdir($dirp)) !== false) {
      if (!preg_match("/^(th\\d+---|\\.thumblist)/", $file)) continue;
      $days = floatval(IsEnabled($_REQUEST['days'], $ThumbList['AutoPurgeThumbsDays']));
      if($days>0 && filemtime("$uploaddir/$file")>$Now-$days*86400) continue;
      unlink("$uploaddir/$file");
    }
    closedir($dirp);
  }
  if(@$_REQUEST['redirect']>'')Redirect(MakePageName($pagename, $_REQUEST['redirect']));
  else Redirect($pagename, $ThumbList['PurgeRedirectFmt']);
}
function ThumbCreateGD($filepath,$thumbpath,$imgw,$imgh,$nw,$nh,$t,$bgc,$cl='') {
  global $ThumbList;
  if(!isset($ThumbList['ImTypes'][$t])) return;
  if($bgc == "#none")$bgc="#ffffff";# TODO
  $rr = hexdec(substr($bgc, 1, 2) );
  $gg = hexdec(substr($bgc, 3, 2) );
  $bb = hexdec(substr($bgc, 5, 2) );
  $gd2 = function_exists('imagecreatetruecolor');
  $imcopy = ($gd2)?'imagecopyresampled':'imagecopyresized';
  $imcreate=($gd2)?'imagecreatetruecolor':'imagecreate';

  $fcreate = "imagecreatefrom".$ThumbList['ImTypes'][$t];
  $img = $fcreate($filepath);
  if (!@$img){return;}

  $nimg = $imcreate($nw,$nh);
  if($t != 2)imagefill($nimg, 0, 0, imagecolorallocate($nimg, $rr, $gg, $bb));
  $imcopy($nimg,$img,0,0,0,0,$nw,$nh,$imgw,$imgh);
  imagedestroy($img);
  if(function_exists('imageconvolution')) {
    $amount = $ThumbList['Sharpen'];
    imageconvolution($nimg, array(array(-1,-1,-1),array(-1,$amount,-1),array(-1,-1,-1)),$amount-8,0);
  }
  if(function_exists($ThumbList['fProcessGD'])) $nimg = $ThumbList['fProcessGD']($nimg);
  if(preg_match("/\\.png$/", $thumbpath))imagepng($nimg,$thumbpath,1);
  else imagejpeg($nimg,$thumbpath,$ThumbList['Quality']);
  imagedestroy($nimg);
}
function ThumbCreateIM($filepath,$thumbpath,$imgw,$imgh,$nw,$nh,$t,$bgc,$cl='') {
  global $ThumbList;
  if($bgc == "#")$bgc="#ffffff";
  if($bgc == "#none")$bgc="none";
  $replArr = array(
    'x'=>$ThumbList['ImageMagickExe'], 'c'=>$bgc, 'q'=>$ThumbList['Quality'],
    'p'=>escapeshellcmd($thumbpath), 'P'=>escapeshellcmd($filepath),
    'w'=>$nw, 'W'=>$imgw, 'h'=>$nh, 'H'=>$imgh,);
  $cl = IsEnabled($ThumbList['IMCLTpl'][$cl], $ThumbList['IMCLTpl'][$ThumbList['DefaultCLTpl']]);
  foreach($replArr as $k=>$v) {
    $cl = preg_replace("/\\{%$k\\}/", $v, $cl);
    $cl = preg_replace("/\\{%$k([+-]\\d+)\\}/e", "$v$1", $cl);
  }
  $cl = preg_replace("/\\{%RAND([+-]\\d+)([+-]\\d+)([\\*\\/]\\d+)?\\}/e", "mt_rand($1, $2)$3", $cl);

  $r = exec($cl, $o, $status);
  if(intval($status)!=0)
    Abort("convert returned <pre>$r\n".print_r($o, true)
    ."'</pre> with a status '$status'.<br/> Command line was '$cl'.");
}
## partly based on Hagan Fox's "HandleImageLink" from thumblink.php
function HandleImageTemplate($pagename) {
  global $FmtV, $FmtPV, $PageStartFmt, $PageEndFmt, $ThumbList, $MetaRobots;
  $ThumbList['UseTmpl'] = 0;
  SDV($MetaRobots, 'index,follow');
  
  SDV($HandleImageTplFmt,array(&$PageStartFmt, '$PageText', &$PageEndFmt));
  $pagename = MakePageName($pagename, $pagename);
  PCache($pagename, RetrieveAuthPage($pagename, 'read',1, READPAGE_CURRENT));
  $uname=htmlspecialchars(@$_REQUEST['upname'], ENT_QUOTES);
  $pname=preg_replace("/^.*[\\.\\/]/", '', MakePageName($pagename, str_replace(".", "", $uname)));
  $udir =htmlspecialchars(@$_REQUEST['updir'], ENT_QUOTES);
  if($udir == '')$udir = $pagename;
  $ud = ($pagename == $udir)? '':"&updir=$udir";
  $G = intval(@$_REQUEST['G']);
  $a = explode("\n", ThumbGetCache($pagename, $udir, $G, 1));
  $key = @array_search($uname, $a);
  if($key!==false)$FmtPV['$CurrentThumbIndex'] = "'$key'";
  if(strlen(@$a[$key-1])) {
    $xx = $a[$key-1];
    $x = PUE($xx);
    $FmtPV['$PrevFile'] = "'$xx'";
    $FmtPV['$PrevLinkUrl'] = "'$pagename?action=imgtpl&G=$G$ud&upname=$x'";
    $FmtPV['$PrevLink'] = "'[[$pagename?action=imgtpl&G=$G$ud&upname=$x| {$ThumbList['PrevLink']} ]]'";
    $FmtPV['$PrevThumb'] = "'[[$pagename?action=imgtpl&G=$G$ud&upname=$x|(:thumb \"$udir/$xx\" px={$ThumbList['TrailPx']} link=-1:)]]'";
  }
  else $FmtPV['$PrevLinkUrl'] = "'$pagename?action=browse'";
  if(strlen(@$a[$key+1])) {
    $xx = $a[$key+1];
    $x = PUE($xx);
    $FmtPV['$NextFile'] = "'$xx'";
    $FmtPV['$NextLinkUrl'] = "'$pagename?action=imgtpl&G=$G$ud&upname=$x'";
    $FmtPV['$NextLink'] = "'[[$pagename?action=imgtpl&G=$G$ud&upname=$x| {$ThumbList['NextLink']} ]]'";
    $FmtPV['$NextThumb'] = "'[[$pagename?action=imgtpl&G=$G$ud&upname=$x|(:thumb \"$udir/$xx\" px={$ThumbList['TrailPx']} link=-1:)]]'";
  }
  else $FmtPV['$NextLinkUrl'] = "'$pagename?action=browse'";
  $FmtPV['$UpFile'] = "'$uname'";
  $FmtPV['$UpFilePage'] = "'$pname'";
  $FmtPV['$UpDir'] = "'$udir'";
  $FmtPV['$UpDirUpFile'] = "'$udir/$uname'";
  FmtThumbList($pagename, "\"$udir/$uname\" exif=1 onlysetpagevars=1");
  $FmtV['$PageText'] = MarkupToHTML($pagename,$ThumbList['ImageTplFmt']);
  if(!trim($FmtV['$PageText']))
  {  $FmtV['$PageText'] = MarkupToHTML($pagename,$ThumbList['NoImageTplFmt']);}
  PrintFmt($pagename, $HandleImageTplFmt);
}
function ThumbSetPageVars($replArr) {
  global $FmtPV;
  foreach($replArr as $k=>$v)
    $FmtPV["\$ThumbList_". substr($k, 1)] = "'".htmlspecialchars($v, ENT_QUOTES)."'";
}

function ThumbAutoPurge($d) {
  global $UploadDir, $UploadPrefixFmt, $ThumbList, $Now;
  if(!$ThumbList['AutoPurgeDelay'])return;
  if(file_exists($ThumbList['AutoPurgeLock']) &&
    filemtime($ThumbList['AutoPurgeLock'])>=$Now-$ThumbList['AutoPurgeDelay'] ) return;
  touch($ThumbList['AutoPurgeLock']);

  ThumbPurgePurge(ThumbAutoList($UploadDir),
    $ThumbList['MaxThumbs'], $ThumbList['AutoPurgeRatio']);
  $origlist = ThumbAutoList($d, "/^(?!th\\d+---).*{$ThumbList['ImTypesRegExp']}$/i");
  ThumbPurgePurge(ThumbAutoList($d), count($origlist)*$ThumbList['DirThumbsRatio'],
    $ThumbList['AutoPurgeRatio']);
}

function ThumbAutoList($dir, $pat="/^th\\d+---/") {
  $a = array();
  $dirp = @opendir($dir);
  if (!$dirp) return $a;
  while (($file=readdir($dirp)) !== false) {
    if ($file[0]=='.') continue;
    if (is_dir("$dir/$file")) $a+=ThumbAutoList("$dir/$file", $pat);
    elseif(preg_match( $pat, $file) )
      $a["$dir/$file"]=filemtime("$dir/$file");
  }
  closedir($dirp);
  return $a;
}

function ThumbPurgePurge($list, $max, $ratio) {
  if(count($list)==0||count($list)<$max)return;
  if($max)$max = $max*(1-$ratio)+1;
  $numdel = count($list)-$max;
  arsort($list);
  $list = array_slice(array_keys($list), $numdel);
  $dirs = array();# to clear supercache
  foreach($list as $f) {
    @unlink($f);
    $dirs[preg_replace("/\\/[^\\/]+$/", '', $f)]=1;
  }
  foreach($dirs as $d=>$x) {
    $cachelist = ThumbAutoList($d, "/^\\.thumblist(?!-trail).*\\.cache$/i");
    ThumbPurgePurge($cachelist, 0, 1);
  }
}
