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
  Copyright 2008 Fritz Webering FritzWeberingATgmxDOTde
  Copyright 2004-2006 Patrick R. Michaud http://www.pmwiki.org
  Copyright 2006 Hagan Fox haganfoxATusersDOTsourceforge.net
*/
# Version date
$RecipeInfo['ThumbList']['Version'] = '20100315';
$FmtPV['$ThumbListVersion'] = "'TL-{$RecipeInfo['ThumbList']['Version']}'";


SDVA($ThumbList, array(
  'Px' => 128, 'HTMLpx'=> 1, 'TrailPx' => 64,'TableCols' => 0, 'tlmode'=> 0,
  'TitleFormat' => "?f: ?wx?h, ?kk (?t)", 'CaptionFormat' => '',
  'LinkRel' => '','BgColor' => "#ffffff",#for transparent pictures
  'LinkOriginal' => 0,# 1:always; -1:never; 0:when needed; 2:link to page
  'PrevLink' => '<<', 'NextLink' => '>>',
  'UseTmpl' => 0,
  'AllowedUploadPages' => '*', 'ShowErrors' => 1, 'AttachLinks' => 1,
  'FileExt' => 'jpg', 'FileListOrder' => 'name',
  'ImTypes' => array(1=>"gif",2=>"jpeg",3=>"png",15=>"wbmp",16=>"xbm"),
  'ImTypesRegExp' => "\\.(?:jpe?g|png|gif|jpe|wbmp|xbm)",
  'EnableMarkup'=> 1,
  'EnableThumbs'=> 1,
  'EnableMessages'=> 1,
  'PerPage' => 0, 'PerPageNav' => 2, # 0:none; 1:above; 2: below; 3:both
  # functions
  'fGetFileList'=> 'ThumbGetFileList','fOrderFileList'=> 'ThumbOrderFileList',
  'fGetFileStat'=> 'ThumbGetFileStat',
  'fEXIF'=> 'ThumbExif',
  'fPreChecks'=> null,
));
SDVA($ThumbList['EXIFvars'], array( # except ?T and ?C
  'M'=>'IFD0.Make', 'm'=>'IFD0.Model',
  'W'=>array('COMPUTED.Width', 'EXIF.ExifImageWidth'),
  'H'=>array('COMPUTED.Height', 'EXIF.ExifImageHeight'),
  'E'=>'EXIF.ExposureTime',  'F'=>'EXIF.FocalLength',
  'A'=>'COMPUTED.ApertureFNumber',  'I'=>'EXIF.ISOSpeedRatings',
));

SDVA($ThumbList['OrderFunctions'], array(
  'ext' => 'return strnatcmp($a["ext"], $b["ext"]);',
  'name' => 'return strnatcmp($a["name"], $b["name"]);',
  'random' => 'return mt_rand(-1,1);',
  'time' => 'return $a["stat"][9] - $b["stat"][9];',
  'size' => 'return $a["stat"][7] - $b["stat"][7];',
  'width' => 'return $a["getimagesize"][0] - $b["getimagesize"][0];',
  'height' => 'return $a["getimagesize"][1] - $b["getimagesize"][1];',
  'ratio' => '$c=$a["getimagesize"];$d=$b["getimagesize"]; return round(($c[0]/$c[1]-$d[0]/$d[1])*10000);',
));
SDVA($ThumbList['stat_dirlist'], array(
  'time'=>'stat','size'=>'stat','width'=>'getimagesize',
  'height'=>'getimagesize','ratio'=>'getimagesize',
));

SDVA($ThumbList['_tmpl'], array(
  'tableattributes' => array("border", "cellpadding", "cellspacing", "rules", "style", "bgcolor", "align"),
  'imgwrap' => '<img class="thumbs" src="?u" title="%1$s" alt="%1$s" %2$s />',
  'awrap' => "<a href='?L' class='thumblink' title='?f' %2\$s>%1\$s</a> ",
  'captionwrap'=>'<span class="caption">%s</span>',

  # %s= awrap, captionwrap, td attr, divattr
  'cellwrap'=> "<td class='thumbtd'><div class='img' %4\$s>%1\$s</div>%2\$s</td>",
  'inlinewrap'=>'%s%s',#%s= img/, <span class=caption>
  'inlinewrapall'=>'<span class="thumblist">%s</span>',

  'rowwrap'=> "<tr class='thumbtr'>%s</tr>",#%s= cellwrap
  'tablewrap'=> "<table %s>%s%s</table>",#%s= table attributes, [tcaption], tbody

  # by Fritz Webering
  'navwrap'=> '<div class="thumblist-navigation">%s</div>',
  'navpagelink' => '<a class="page-number" href="%s">%s</a>',          // %1$s = page URL, %2$s = page number
  'navpagelinksep' => ' ',          // separator between links
  'navpagecurrent' => '<span class="page-number current">%2$s</span>', // %1$s = page URL, %2$s = page number
  'navprevnext' => '<a class="next" href="%s">%s</a>',                 // %1$s = page URL, %2$s = link text
  'navdisabled' => '<span class="disabled">%2$s</span>',               // %1$s = page URL, %2$s = link text
));
SDVA($ThumbList['IMCLTpl'], array(
'default'=>'{%x} -size "{%W}x{%H}" "{%P}"[0] -resize "{%w}x{%h}" -background "{%c}" -flatten -unsharp 0 -quality "{%q}" "{%p}"',
'shadow' =>'{%x} -size "{%W}x{%H}" "{%P}"[0] -resize "{%w}x{%h}" -bordercolor "white" -border 3 -bordercolor grey60 -border 1 -background black \\( +clone -shadow 60x3+2+2 \\) +swap -background "{%c}" -flatten -resize "{%w}x{%h}!" -unsharp 0 -quality "{%q}" "{%p}"',
));

if(preg_match("/^(imgtpl|purgethumbs|createthumb)$/", $action)) {
  include_once(dirname(__FILE__) . "/thumblist2-actions.php" );
}

# Trying to recover existing deprecated configurations...
foreach($ThumbList as $k=>$v){$x = "Thumb$k";if(isset($$x)) $ThumbList[$k] = $$x;}
if(isset($ThumbListUseTmpl))$ThumbList['UseTmpl'] = $ThumbListUseTmpl;
if(isset($ImageTemplateFmt))$ThumbList['ImageTplFmt'] = $ImageTemplateFmt;
function percent2qm(&$x){$x = preg_replace("/([^%]|^)%([MmWHTEFAICGfwhbktUuLnN])/", "$1?$2", $x);}
# ...Do not rely on this, by 2008 it will be removed!


Markup('thumblist', '<split',
  '/\\(:thumb(list)?\\s*(.*?):\\)/ei',
  "FmtThumbList(\$pagename,PSS('$2'),'$1')");
Markup('thumbgallery', '<thumblist',
  '/\\(:thumb(gallery)\\s*(.*?):\\)(.*?)\\(:thumbgalleryend:\\)/esi',
  "FmtThumbList(\$pagename,PSS('$2'),'$1', PSS('$3'))");
function FmtThumbList($pagename, $args, $suffix='', $list='') {
  if(! function_exists('imagecreate') && ! $ThumbList['ImageMagickExe'])
    return ThumbReturn("PHP-GD image library not found. Exiting.", 2);
  global $UploadDir, $UploadPrefixFmt, $UploadUrlFmt, $TimeFmt,
    $EnableDirectDownload, $ThumbList, $ThumbList_ShowErrors,
    $HandleActions, $RecipeInfo, $GroupPattern, $NamePattern;
  static $ThumbGalNumBase = 0;
  $ThumbGalNumBase++;

  $opt = ParseArgs($args);
  if(@$opt['id'])$ThumbList['IDs'][$opt['id']] = $ThumbGalNumBase;
  if(function_exists($ThumbList['fPreChecks']))$ThumbList['fPreChecks']($opt);
  $currentpage = $pagename = MakePageName($pagename, $pagename);
  $captionfmt =IsEnabled($opt['captionfmt'], $ThumbList['CaptionFormat']);
  $titlefmt = IsEnabled($opt['titlefmt'], $ThumbList['TitleFormat']);
  percent2qm($captionfmt);
  percent2qm($titlefmt);
  $quiet = intval(@$opt['quiet']);#1:errors 2:attachlinks
  $ThumbList_ShowErrors = $ThumbList['ShowErrors'];
  if($quiet%2 == 1)$ThumbList_ShowErrors=0;

  if($suffix){if (@$opt[''][0]) $pagename = MakePageName($pagename, $opt[''][0]);}
  else {# "thumb" was used
    if (! @$opt[''][0]) return ThumbReturn("No file specified.", 1);
    if(preg_match("!^(.*)\\/([^\\/]+)$!", $opt[''][0], $m) ) {
      $pagename = MakePageName($pagename, $m[1]);
      $opt['name'] = $m[2];
    }
    else $opt['name'] = $opt[''][0];
    if($captionfmt)$opt['cols'] = $Width = 1;
    else $opt['cols'] = 0;
  }
  $pagelist = MatchPageNames($pagename, FmtPageName($ThumbList['AllowedUploadPages'], $currentpage));
  if( @$pagelist[0] == '' )
    return ThumbReturn("$pagename does not match \$ThumbList['AllowedUploadPages'] permissions.", 1);
  if ($pagename != $currentpage && !CondAuth($pagename,"read") )
    return ThumbReturn("No read permissions at $pagename.", 1);
  $opt['currentpage'] = $currentpage;  $opt['pagename'] = $pagename;

  $perpage = IsEnabled($opt['perpage'], $ThumbList['PerPage']);
  $perpagenav = IsEnabled($opt['perpagenav'], $ThumbList['PerPageNav']);
  $getpage = intval(@$_GET['page'])-1;
  if($getpage<0)$getpage=0;
  $supercache = intval(@$opt['supercache']);

  if($perpage>0)
    $ThumbGalNum = $opt['?G'] = 1000 + $ThumbGalNumBase*1000 + $getpage;
  else
    $ThumbGalNum = $opt['?G'] = ($supercache>999)? $supercache : $ThumbGalNumBase;  
  $ThumbGalNumGet = @isset($ThumbList['IDs']["{$opt['usetemplate']}"])
    ? $ThumbList['IDs']["{$opt['usetemplate']}"] : $ThumbGalNum;

  if(!@$_POST['preview'] && $supercache) {
    $output = ThumbGetCache($currentpage, $pagename, $ThumbGalNum);
    if($output) return ThumbReturn($output);
  }
  $thumbext = IsEnabled($opt['ext'], $ThumbList['FileExt']);
  if (intval(@$opt['px']) > 0 ) $Px = intval($opt['px']);
  elseif(intval(@$opt['width']))$Px = $Width = intval($opt['width']);
  elseif(intval(@$opt['height'])){$Px = $Height = intval($opt['height']);$Width=0;}
  elseif(@$ThumbList['Width'])$Px = $Width = $ThumbList['Width'];
  else $Px = $ThumbList['Px'];

  $linkorig = IsEnabled($opt['link'], $ThumbList['LinkOriginal']);
  $linkrel = htmlspecialchars(IsEnabled($opt['rel'], $ThumbList['LinkRel']), ENT_QUOTES);
  $linktarget = htmlspecialchars(IsEnabled($opt['target'], @$ThumbList['LinkTarget']), ENT_QUOTES);
  $usetpl =IsEnabled($opt['usetemplate'],$ThumbList['UseTmpl']);
  $trailstamp = intval($usetpl)>0? ThumbGetCache($currentpage, $pagename, $ThumbGalNum, 2) : 0;
  $trail = ''; $trailcache = ($trailstamp>0)? 0:1;
  $thumbcols = IsEnabled($opt['cols'], $ThumbList['TableCols']);
  $caption = @$opt['caption'];
  $enableexif = (function_exists($ThumbList['fEXIF'])
    && (preg_match("/([^\\?]|^)(\\?\\?)*\\?[MmWHTEFAICx]/", $titlefmt.$captionfmt)
        || @$opt['onlysetpagevars']>''));
  $imcl = (@$opt['imcl']>'' && isset($ThumbList['IMCLTpl'][$opt['imcl']]))? $opt['imcl'] : '';
  $tlmode = IsEnabled($opt['tlmode'], $ThumbList['tlmode']);

  $uploaddir = FmtPageName("$UploadDir$UploadPrefixFmt", $pagename);
  $uploadurl = FmtPageName(IsEnabled($EnableDirectDownload, 1)
      ? "$UploadUrlFmt$UploadPrefixFmt/"
      : "\$PageUrl?action=download&amp;upname=",
    $pagename);

  $filelist = array();
  if(!$suffix)
    $filelist[$opt['name']] = $ThumbList['fGetFileStat']($uploaddir, $opt['name'], $opt);
  elseif($suffix=='list' || $tlmode>0 )
    $filelist = $ThumbList['fGetFileList']($uploaddir, $opt);
  if($suffix=='gallery') {
    $tem = $ThumbList['EnableMarkup'];
    $rx = ($tem)? "(?:\"([^\"]*)\")? *(?:\\| *([^\n]*?) *)?" : " *(?:\\| *([^\\|\n]+?) *(?:\\| *(.*) *)?)?";
    preg_match_all("/^\s*(.+?{$ThumbList['ImTypesRegExp']})$rx$/im", $list, $m, PREG_SET_ORDER);
    for($i=0; $i<count($m); $i++) {
      if($tem)@list(, $_n, $_t, $_c) = $m[$i];
      else @list(, $_n, $_c, $_t) = $m[$i];

      $filelist[$_n] = $ThumbList['fGetFileStat']($uploaddir, $_n, $opt);
      $filelist[$_n]['_c'] = $_c; $filelist[$_n]['_t'] = $_t;
      if(!@$opt['order'] && $tlmode<=0) $opt['order'] = 'none';
    }
  }
  # rm blocked items by fGetFileStat
  foreach($filelist as $k=>$a)if(!is_array($a))unset($filelist[$k]);
  if(!count($filelist) ) return ThumbReturn("No pictures found.", 1);
  if(IsEnabled($opt['shuffle'], 0))$opt['order'] = 'random';
  if(@$opt['order'] == 'random') {
    ThumbCacheName($opt['currentpage'], $pagename, $opt['?G'], 1, 1);
    NoCache();
  }
  if(!@$opt['order'])$opt['order'] = $ThumbList['FileListOrder'];
  $filelist = $ThumbList['fOrderFileList']($filelist, $opt, $uploaddir);

  $start=1;$limit=count($filelist);#first:last
  if(@$opt['count']>'') {
    if(preg_match("/^(\\d+)..(\\d+)$/", $opt['count'], $m)) {
      $start= max(min($m[1],$m[2]),1);
      $limit= min(max($m[1],$m[2]),count($filelist))-$start+1;
    }
    elseif(preg_match("/^(\\d+)..$/", $opt['count'], $m))$start = $m[1];
    elseif(preg_match("/^(..)?(\\d+)$/", $opt['count'], $m)) {
      $start = 1; $limit= $m[2];
    }
  }
  if(intval(@$opt['start']>0)) $start=$opt['start'];
  if(intval(@$opt['limit']>0)) $limit=$opt['limit'];
  if($start>1||$limit>0) {
    $f2 = array_slice(array_keys($filelist), $start-1, $limit);
    $f3 = array();foreach($f2 as $v) {$f3[$v] = $filelist[$v];}
    if(count($f3)<1) return ThumbReturn("Count out of range.", 1);
    $filelist=$f3;
  }
  if($tlmode==2) {
    $r = '';
    foreach($filelist as $k=>$a) {
      $t=(@$a['_t']>'')?'"'.$a['_t'].'"':'';
      $r .= @"$k$t | {$a['_c']}\n";
    }
    return Keep(ThumbReturn("<pre>$r</pre>"));
  }

  $perpagetotalnum = count($filelist);
  $navlinks = ThumbNavLinks($currentpage, $perpage, $perpagetotalnum, $getpage);
  if($perpage>0 && $perpagetotalnum>$perpage) {
    $f2 = array_slice(array_keys($filelist), $getpage*$perpage, $perpage);
    $f3 = array();foreach($f2 as $v) {$f3[$v] = $filelist[$v];}
    if(count($f3)<1) return ThumbReturn($navlinks);
    $filelist=$f3;
  }
  
  $mybgcolor = preg_match("/^#([0-9a-f]{6}|none)$/i", @$opt['bgcolor'])
    ? $opt['bgcolor'] : $ThumbList['BgColor'];
  $htmlpx = IsEnabled($opt['htmlpx'], $ThumbList['HTMLpx']);
  if($thumbcols) {
    $mytabattr = "";
    foreach($ThumbList['_tmpl']['tableattributes'] as $k)
      if(isset($opt[$k])) $mytabattr.=" $k=\"".htmlspecialchars($opt[$k])."\"";
    $Px4 = $Px+4;
    $h = (@$Width)? '' : " height='$Px4'";
    $w = (@$Height)? '' : " width='$Px4'";
    $pad = (@$Width || @$Height)? '2px' : '%dpx';
    $class = @$opt['class']? $opt['class'] : 'thumbtable';
    $tableattributes = "class='$class'$mytabattr";
    if($caption)$caption = "<caption>$caption</caption>";
    $cellattributes = "$h$w";
    $celldivattributes = $htmlpx? " style='padding:$pad $pad;'":'';
    $_tmpl_item = $ThumbList['_tmpl']['cellwrap'];
  }
  else $_tmpl_item = $ThumbList['_tmpl']['inlinewrap'];

  $output = $notfound='';
  $items = array();# cells or thumbs
  foreach($filelist as $file=>$arr) {
    $filepath = "$uploaddir/$file";
    $info = (isset($arr['getimagesize']))? $arr['getimagesize'] : @getimagesize($filepath);
    if(!isset($ThumbList['ImTypes'][@$info[2]])) {
      $c = trim($arr['_c'])>'' ? Keep(" ({$arr['_c']}) ") : '';
      if($suffix!='list')$notfound .= "* [[Attach:$pagename/$file | $file$c]]\n";
      continue;
    }
    $_captionfmt = (@$arr['_c'])? $arr['_c']: $captionfmt;
    $_titlefmt = (@$arr['_t'])? $arr['_t']: $titlefmt;

    $picurl = PUE("$uploadurl$file");
    $stat = (isset($arr['stat']))? $arr['stat'] : stat($filepath);
    $replArr0 = array(
      "?1" => $_titlefmt,
      "?2" => htmlspecialchars($_titlefmt, ENT_QUOTES),
      "?3" => $_captionfmt,
      "?4" => htmlspecialchars($_captionfmt, ENT_QUOTES),
    );
    $replArr = array(
      "??" => "?",
      "?G" => $ThumbGalNum,
      "?P" => $pagename,
      "?p" => preg_replace("/^.*\\./", '', $pagename),
      "?f" => $file,
      "?i" => preg_replace("/\\.[^.]+$/", '', $file ),
      "?w" => $info[0],     "?h" => $info[1],
      "?r" => round($info[0]/$info[1],2), "?a" => $info[0]*$info[1],
      "?b" => $stat['size'],"?k" => round($stat['size']/1024),
      "?t" => strftime($TimeFmt, $stat['mtime']),
    );
    if($enableexif)$replArr = array_merge($replArr, $ThumbList['fEXIF']($filepath, $opt) );
    ## when inside an ImageTemplate, set pagevars for 1 pic and exit
    if(@$opt['onlysetpagevars']){ThumbSetPageVars($replArr); return;}
    
    if(($thumbcols && $info[0]>$info[1] && ! @$Height) || @$Width) {  # w > h
      $imgw = $Px; $imgh = round($Px * $info[1] / $info[0]);
    }
    else { $imgh = $Px; $imgw = round($Px * $info[0] / $info[1]);}

    if($imgh>=$info[1]) { # picture not bigger than thumb, display it
      if($imgh>=$info[1])list($imgw, $imgh)= $info;
      $thumburl = $picurl;
    }
    else {
      $thumbprefix = "th$imgh---".substr($mybgcolor, 1). "--";
      $thumbpath = "$uploaddir/$thumbprefix$file.$thumbext";
      if (file_exists($uploaddir."/th---$file.$thumbext"))
        $thumburl = PUE($uploadurl."th---$file.$thumbext");
      elseif(file_exists($thumbpath) && filemtime($thumbpath)>=$stat['mtime'])
         $thumburl = PUE("$uploadurl$thumbprefix$file.$thumbext");
      elseif(!$ThumbList['EnableThumbs'])$thumburl='';
      else {
        $thumburl = PUE(
          FmtPageName("\$PageUrl?action=createthumb&amp;imcl=$imcl&amp;upname=", $pagename)
          ."$thumbprefix$file.$thumbext");
        $opt['supercache'] = $supercache = 0;
        touch("$uploaddir/.$thumbprefix$file.$thumbext.lock");
        NoCache();
      }
    }
    $Mx = max($imgh, $imgw);
    $widthheight = $htmlpx ? "width='$imgw' height='$imgh'" : '';
    $ud = ($pagename == $currentpage)? '':"&amp;updir=$pagename";
    $linkurl = $picurl;
    if(preg_match("/^{$GroupPattern}[\\.\\/]{$NamePattern}$/", $linkorig))
      $linkurl = PUE(FmtPageName("\$PageUrl", $linkorig));
    elseif($linkorig==2)$linkurl = PUE(FmtPageName("\$PageUrl", $pagename));
    elseif($usetpl)$linkurl = PUE(FmtPageName("\$PageUrl?action=imgtpl&amp;G=$ThumbGalNumGet$ud&amp;upname=$file", $currentpage));
    $replArr= array_merge($replArr,  array("?U" => $picurl,"?u" => $thumburl, '?L' => $linkurl,
      '?n'=>" | ", '?N'=>" | "));
    $item = $thumburl?
      sprintf($ThumbList['_tmpl']['imgwrap'], strtr($_titlefmt, $replArr), $widthheight):
      $_titlefmt;
    $replArr['?n'] = $replArr['?N'] = '<br/>';
    if($linkorig!=-1 && ($linkorig>0 || $thumburl != $picurl || $usetpl )) {
      $rel = ($linkrel>'')? " rel='$linkrel'" : ' ';
      if($linktarget>'') $rel.= " target='$linktarget'";
      $item = sprintf($ThumbList['_tmpl']['awrap'], $item, $rel);
    }
    if($_captionfmt>'')$_captionfmt = sprintf($ThumbList['_tmpl']['captionwrap'], $_captionfmt);
    $_celldivattributes = sprintf(@$celldivattributes, round(($Mx-$imgh)/2)+2, round(($Mx-$imgw)/2)+2);
    $item = sprintf($_tmpl_item, $item, $_captionfmt, @$cellattributes, $_celldivattributes);

    $item = strtr( $item, $replArr0);
    $items[] = strtr( $item, $replArr);

    $trail.="$file\n";
    if($usetpl && $stat['mtime']>=$trailstamp && @$opt['order']!='random')$trailcache = 1;
  }
  if($thumbcols>0) {
    $output = '';
    for($i=0; $i<count($items); $i+=$thumbcols) {
      $output .= sprintf($ThumbList['_tmpl']['rowwrap'],
        implode('', array_slice($items, $i, $thumbcols)));
    }
    $output = sprintf($ThumbList['_tmpl']['tablewrap'], $tableattributes, $caption, $output);
  }
  else $output = sprintf($ThumbList['_tmpl']['inlinewrapall'], implode('', $items));

  if($perpage>0 && $perpagetotalnum>$perpage && $perpagenav>0) {
    if($perpagenav%2) $output = "$navlinks\n$output";
    if($perpagenav>1) $output = "$output\n$navlinks";
  }

  $output = ($ThumbGalNumBase==1? "<!-- TL2-{$RecipeInfo['ThumbList']['Version']} -->":'')
    . trim($output);

  if($ThumbList['AttachLinks'] && $quiet<2 && strlen($notfound))
    $output .= $notfound;

  if(!@$_POST['preview']) {
     if($supercache)ThumbSetCache($currentpage, $pagename, $ThumbGalNum, MarkupRestore($output));
    if($usetpl>0 && $trailcache)ThumbSetCache($currentpage, $pagename, $ThumbGalNum, $trail, 1);
  }
  if($tlmode>=0) return ThumbReturn($output);
}
function ThumbReturn($x,$err=0) {
  global $ThumbList_ShowErrors;
  if($ThumbList_ShowErrors==0 && $err==1) return;
  $a=$b=''; if($err){$a='<pre><b>ThumbList warning: </b>';$b='</pre>';}
  if(strpos($x,'<')!==false)$x = preg_replace('/<[^<]*?>/e',"Keep(PSS('$0'))",$x);
  return "<:block>$a$x$b";
}
# returns a list of all potential files from the upload directory
function ThumbGetFileList($uploaddir, $opt) {
  global $ThumbList;
  if (@$opt['skip']) $skipmatch=ThumbFilePattern($opt['skip'], 'i');
  if (@$opt['name']) $pattern = ThumbFilePattern($opt['name'], 'i');
  $filelist = array();
  $dirp = @opendir($uploaddir);
  if($dirp) {
    while (($file=readdir($dirp)) !== false) {
      if ($file{0} == '.') continue;
      if (!preg_match("/{$ThumbList['ImTypesRegExp']}$/i", $file)) continue;
      if (preg_match("/^th\\d*---/", $file))continue;
      if (@$skipmatch && preg_match(@$skipmatch, $file)) continue;
      if (@$pattern && !preg_match($pattern, $file)) continue;
      $filelist[$file] = $ThumbList['fGetFileStat']($uploaddir, $file, $opt);
    }
    closedir($dirp);
  }
  return $filelist;
}
function ThumbGetFileStat($uploaddir, $file, $opt) {
  global $ThumbList;
  $ext = preg_replace("/^.*\\.([^\\.]*)$/", '$1', $file);
  $r = array('name'=>$file, 'ext'=>$ext);
  foreach( (array)$ThumbList['stat_dirlist'] as $k=>$v )
    if(preg_match("/^-?$k$/", @$opt['order']) && function_exists($v) )
      $r[$v]=$v("$uploaddir/$file");
  return $r;
}
function ThumbOrderFileList($filelist, $opt, $uploaddir) {
  global $ThumbList;
  preg_match('/^(-)?(\\w*)$/', @$opt['order'], $m);
  @list(, $rev, $order) = (array)@$m;
  if($order!='none') {
    if(! @$ThumbList['OrderFunctions'][$order]) $order='name';
    usort($filelist, create_function('$a,$b', $ThumbList['OrderFunctions'][$order]) );
    $f2=array();foreach($filelist as $k=>$a)$f2[$a['name']]=$a; $filelist=$f2;
  }
  return ($rev=='-')? array_reverse($filelist, 1) : $filelist;
}
function ThumbFilePattern($x, $mod=''){
  return '/^'.str_replace(array("\\*","\\?","\\|"),array(".*",".","|"),preg_quote($x)).'$/'.$mod;
}
function ThumbCacheName($currentpage, $uploadpage, $n, $trail=0, $delete=0) {
  global $UploadDir, $UploadPrefixFmt;
  $t = $trail? '-trail' : '';
  $f = FmtPageName("$UploadDir$UploadPrefixFmt/.thumblist$t.$currentpage.$n.cache", $uploadpage);
  if(!$delete) return $f;
  if(file_exists($f)) @unlink($f);
}
function ThumbGetCache($currentpage, $uploadpage, $n, $trail=0) {
  global $PCache, $MessagesFmt, $ThumbList;
  $ptime = $PCache[$currentpage]['time'];
  $t = ($trail)? '-trail' : '';
  $cachefile = ThumbCacheName($currentpage, $uploadpage, $n, $trail);
  if(file_exists( $cachefile) && filemtime($cachefile) >= $ptime) {
    if($ThumbList['EnableMessages'])
      $MessagesFmt[] = "Getting cache for $currentpage, gallery$t $n<br />\n";
    if($trail==2) return filemtime($cachefile);
    if($handle = @fopen($cachefile, "r")) {
      $contents = @fread($handle, filesize($cachefile));
      fclose($handle);
    }
    return @$contents;
  }
}
function ThumbSetCache($currentpage, $uploadpage, $n, $html, $trail=0) {
  global $UploadDir, $UploadPrefixFmt, $MessagesFmt, $ThumbList;
  $t = ($trail)? '-trail' : '';
  if($ThumbList['EnableMessages'])
    $MessagesFmt[] = "Caching gallery$t $n for $currentpage<br />\n";
  $cachefile = ThumbCacheName($currentpage, $uploadpage, $n, $trail);
  if ($handle = @fopen($cachefile, 'w+')) {
    @fwrite($handle, $html);
    fclose($handle);
  }
}
function ThumbExif($filepath, $opt=null) {
  $e = $exif = array();
  if(!function_exists("exif_read_data")) return $e;
  global $TimeFmt, $ThumbList;
  $exif = @exif_read_data($filepath, 'EXIF', 1);
  $t = preg_replace('/^(\\d{4}):(\\d\\d):(\\d\\d.*)/', '$1-$2-$3', @$exif['EXIF']['DateTimeOriginal']);
  $e['?T'] = @$t? strftime($TimeFmt,strtotime($t)):'';
  $e['?C'] = trim(@$exif['COMMENT']['0'].' '.@$exif['COMPUTED']['UserComment']);
  foreach($ThumbList['EXIFvars'] as $k=>$a) {
    $tmp = '';
    foreach((array)$a as $kk) {
      list($k0, $k1) = explode('.', $kk);
      if(!isset($exif[$k0][$k1])) continue;
      $tmp = $exif[$k0][$k1]; break;
    }
    $e["?$k"] = $tmp;
  }
  return $e;
}
function ThumbNavLinks($pagename, $perpage, $total, $getpage) {
  if($perpage==0) return;
  global $ThumbList;
  $tmpl = $ThumbList['_tmpl'];
  $nbpages = ceil($total/$perpage); if($nbpages<2) return '';

  $prev = $getpage ;
  $next = $getpage +2;

  $url = FmtPageName("\$PageUrl?page=$prev", $pagename);
  $out = sprintf( ($prev>0? $tmpl['navprevnext'] : $tmpl['navdisabled']),
    $url, htmlspecialchars($ThumbList['PrevLink']) ) . $tmpl['navpagelinksep'] ;

  for($i=0; $i<$nbpages; $i++) {
    $j = $i+1;
    $url = FmtPageName("\$PageUrl?page=$j", $pagename);
    $out .= sprintf(($getpage == $i ? $tmpl['navpagecurrent'] : $tmpl['navpagelink']), $url, $j). $tmpl['navpagelinksep'];
  }
  $url = FmtPageName("\$PageUrl?page=$next", $pagename);
  $out .= sprintf( ($next<=$nbpages ? $tmpl['navprevnext'] : $tmpl['navdisabled']),
    $url, htmlspecialchars($ThumbList['NextLink']) );
  return sprintf($tmpl['navwrap'], $out);
}
