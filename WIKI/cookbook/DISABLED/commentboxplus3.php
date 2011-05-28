<?php if (!defined('PmWiki')) exit();
/*
    commentboxplus3.php
    Copyright 2006 Stefan Schimanski, an adaption of commentboxplus2.php by
    Copyright 2005, 2006 Hans Bracker, an adaptation of commentbox.php by
    John Rankin, copyright 2004, 2005 John Rankin john.rankin@affinity.co.nz
    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published
    by the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    Adds (:commentbox:) and (:commentboxchrono:) markups.
    Put (:commentbox:) at the top of a page, or a GroupHeader,
    latest entries appear at top, underneat the commentbox,
    i.e. in reverse chronological order.

    Put (:commentboxchrono:) at the bottom of a page, or a GroupFooter,
    latest entries appear at bottom, above the commentbox,
    i.e. in chronological order.

    (:commentbox SomePageName:) or (:commentboxchrono SomePageName:) will post the comment
    on page 'SomePageName' instead of the page where the commentbox is.

    Adds commentbox with chronologfical entries automatically to pages
    which contain 'Journal' or 'Diary' in their name.

    If you use forms markup instead of (:commentbox:) or
    (:commentboxchrono:) markup, add name=cboxform to (:input form ....:),
    which functions as an identifier for the internal authentication check.

    You can hide the commentbox for users who have no page edit permissions
    with conditional markup of the EXACT form:
    (:if auth edit:)(:commentbox:)(:if:)
    and set $MPAnchorFmt = "(:commentbox:)(:if:)";
    if comments should be placed underneath the commentbox
    or (for commentboxchrono)
    (:if auth edit:)(:commentboxchrono:)(:if:)
    and set $MPAnchorChronoFmt = "(:if auth edit:)(:commentboxchrono:)";
    if comments should be placed above the commentbox
    Otherwise users can post comments even if they don't have page edit permission.

    You can also set $MPAnchorFmt to some different string to use as an anchor
    for positioning the comments. For instance
    $MPAnchorFmt = "[[#comments]]";
    and place [[#comments]] somewhere on the page.
    The comment posts will appear beneath this anchor.
    bur for chronological posts setting $MPAnchorChronoFmt = "[[#comments]]";
    the posts will appear ABOVE $MPAnchorChronoFmt.
    
    This version of commentbox adds a website field to the form. 
    There is no special check if entry is indeed a website.
    If url approval is in place the generated link will be checked by url approval.
    
    This version canot be run with commentbox.php or commentboxplus.php at the same time.


*/
//define the commentboxstyled version number
define(COMMENTBOXSTYLED_VERSION, '2006-10-24');

# you may wish to put the style declarations into pub/css/local.css
# and set $CommentStyles = 0; or delete $HTMLStylesFmt definitions below.
SDV($CommentStyles, 1);

# enable page breaks after a number of posts: set in config.php $EnablePageBreaks = 1;
# requires installing pagebreak2.php. See Cookbook.BreakPage
SDV($EnablePageBreaks, 0); # default is No Page Breaks
# with $EnablePageBreaks = 1; set number of posts per page: for instance $PostsPerPage = 50;
SDV($PostsPerPage,20); # default is 20 posts.

# The form check will display a warning message if user has not provided content and name.
# Set to false if no javascript form check is required.
SDV($EnableCommentFormCheck, true);
SDV($NoCommentMessage, '$[Please enter a comment to post]');
SDV($NoAuthorMessage, '$[Please enter your name as author]');
SDV($NoCodeMessage, '$[Please enter the code number]');

# Set $EnableAccessCode to true if you want your users to enter a random
# generated access code number in order to post.
# This may stop robot scripts from posting.
SDV($EnableAccessCode, false);

# Set $EnablPostToAnyPage = 1; if you want users to be able to post comments 
# to other pages than the current one, in conjunction with the use of  
# the markup (:commentbox GroupName.PageName:) or a postto form field.
# Be aware there is a security risk in this, as users could write markup to post to 
# edit protected or admin only pages as in the Site group. To counteract this risk
# include this script (or set $EnablePostToAnyPage =1;) ONLY FOR THOSE PAGES(s) a 
# commentbox form shall be used, AND PROTECT THESE PAGES by restricting edit access to them!
SDV($EnablePostToAnyPage, false);

if($CommentStyles == 1) {
$HTMLStylesFmt['commentbox'] = "
/* styling of commentbox entries */
.messagehead, .journalhead {
            margin:1em 0 0 0;
            padding:0 0 0 3px;
            border:1px solid #999;
            }
.messageitem, .journalitem {
            margin:0;
            padding:3px;
            border-left:1px solid #999;
            border-right:1px solid #999;
            border-bottom:1px solid #999;
            }
.messagehead { background:#e5e5ff; }
/*use the following if message head should be same as message item */
/* .messagehead { background:#eef; border-bottom:none; } */
.messageitem { background:#eef; }
.journalhead { background:#ffb; }
.journalitem { background:#ffc; }

.diaryhead h4 { border-bottom:1px solid #999;
            margin-bottom:1px; }
* html .diaryhead h4 { margin-bottom:0; }
.diaryitem {background:#ffc;
            margin:0;
            padding:3px;
            border-left:1px solid #999;
            border-right:1px solid #999;
            border-bottom:1px solid #999;
            }
.messagehead h5, .messagedate h4, .journalhead h5, .journaldate h4,
.diaryhead h4 { margin:0.25em 0 0 0; }

.commentbutton { margin:0 0 0 5px;
                padding:0 3px }
.commenttext { width:100%;
               margin:0 0 3px 0 }
em.access { font-style:normal; color:#FF2222;}
";}

if($EnableCommentFormCheck==1) {
$HTMLHeaderFmt['checkform'] = "
<script type='text/javascript' language='JavaScript1.2'>
  // <![CDATA[
  function checkform ( form ) {
      if (form.text && form.text.value == \"\") {
        window.alert( '$NoCommentMessage' );
        form.text.focus();
        return false ;
      }
      if (form.author && form.author.value == \"\") {
        window.alert( '$NoAuthorMessage' );
        form.author.focus();
        return false ;
      }
      if (form.access && form.access.value == \"\") {
        window.alert( '$NoCodeMessage' );
        form.access.focus();
        return false ;
      }
      return true ;
  }
  // ]]>
</script>
";}

SDV($DiaryBoxFmt,"<div id='diary'><form class='inputform' action='\$PageUrl' method='post' onsubmit='return checkform(this);'>
    <input type='hidden' name='n' value='\$FullName' />
    <input type='hidden' name='action' value='comment' />
    <input type='hidden' name='accesscode' value='\$AccessCode' />
    <input type='hidden' name='csum' value='$[Entry added]' />
    <table width='90%'><tr>
    <th class='prompt' align='right' valign='top'>$[New entry]&nbsp;</th>
    <td><textarea class='inputtext commenttext' name='text' rows='6' cols='50'></textarea><br />".
    ($EnableAccessCode ? "</td></tr>
    <tr><th class='prompt' align='right' valign='top'>$[Enter code] <em class='access'>\$AccessCode</em></th>
    <td><input type='text' size='4' maxlength='3' name='access' value='' class='inputbox' /> "
        : "<input type='hidden' name='access' value='\$AccessCode' /><br />").
    "<input class='inputbutton commentbutton' type='submit' name='post' value=' $[Post] ' />
    <input class='inputbutton commentbutton' type='reset' value='$[Reset]' /></td></tr></table></form></div>");

SDV($CommentBoxFmt,"
    <div id='message'><form name='cbox' class='inputform' action='\$PageUrl' method='post' onsubmit='return checkform(this);'>
    <input type='hidden' name='n' value='\$FullName' />
    <input type='hidden' name='action' value='comment' />
    <input type='hidden' name='order' value='\$Chrono' />
    <input type='hidden' name='postto' value='\$PostTo' />".
    ($EnablePostToAnyPage ? " <input type='hidden' name='postto' value='\$PostTo' />" : "").
    "<input type='hidden' name='accesscode' value='\$AccessCode' />
    <input type='hidden' name='csum' value='$[Comment added]' />
    <table width='90%'><tr>
    <th class='prompt' align='right' valign='top'>$[Add Comment]&nbsp;</th>
    <td><textarea class='inputtext commenttext' name='text' id='text' rows='6' cols='40'></textarea></td>
    </tr><tr>
    <th class='prompt' align='right' valign='top'>$[Sign as Author]&nbsp;</th>
    <td><input class='inputbox commentauthorbox' type='text' name='author' value='\$Author' size='40' /></td>
    </tr>
    <tr>
    <th class='prompt' align='right' valign='top'>$[Website]&nbsp;</th>
    <td><input class='inputbox' type='text' name='website' value='' size='40' /></td>
    </tr>
    <tr>
    <th class='prompt' align='right' valign='top'>$[Email] ($[for] <a href='http://www.gravatar.com'>Gravatar</a>)</th>
    <td><input class='inputbox' type='text' name='email' value='' size='40' /> 
    ". ($EnableAccessCode ? "</td>
    </tr>
    <tr>
    <th class='prompt' align='right' valign='top'>$[Enter code] <em class='access'>\$AccessCode</em></th>
    <td><input type='text' size='4' maxlength='3' name='access' value='' class='inputbox' /> "
    : "<input type='hidden' name='access' value='\$AccessCode' />").
    "<input class='inputbutton commentbutton' type='submit' name='post' value=' $[Post] ' />
    <input class='inputbutton commentbutton' type='reset' value='$[Reset]' /></td></tr></table><br /></form></div>");

# date and time formats
SDV($JournalDateFmt,'%d %B %Y');
SDV($JournalTimeFmt,'%H:%M');

# journal and diary patterns as part of page name
SDV($JournalPattern,'/Journal$/');
SDV($DiaryPattern,'/Diary$/');


if ($action == 'comment') {
    if (auditJP($MaxLinkCount))
    SDV($HandleActions['comment'],'HandleCommentPost');
    else Redirect($pagename);
}
else if ($action=='print' || $action=='publish')
    Markup('cbox','<block','/\(:commentbox(chrono)?:\)/','');
else {
    Markup('cbox','<links','/\(:commentbox(chrono)?(?:\\s+(\\S.*?))?:\)/e',
        "'<:block>'.Keep(str_replace(array('\$Chrono','\$PostTo','\$AccessCode'),
        array('$1','$2',RandomAccess()),
        FmtPageName(\$GLOBALS['CommentBoxFmt'],\$pagename)))");

    Markup('dbox','<block','/\(:diarybox:\)/e',
        "'<:block>'.str_replace('\$AccessCode',RandomAccess(),
        FmtPageName(\$GLOBALS['DiaryBoxFmt'],\$pagename))");
    if (preg_match($JournalPattern,$pagename) ||
        preg_match($DiaryPattern,$pagename)) {
            $GroupHeaderFmt .= '(:if auth edit:)(:diarybox:)(:if:)(:nl:)';
            if (!PageExists($pagename)) $DefaultPageTextFmt = '';
    }
}

function RandomAccess() {
  return rand(100,999);
}
# provide {$AccessCode} page variable:
$FmtPV['$AccessCode'] = RandomAccess();

function auditJP($MaxLinkCount) {
  SDV($MaxLinkCount, 1);
  if (!(@$_POST['access'] && ($_POST['access']==$_POST['accesscode'])
     && @$_POST['post'])) return false;
  preg_match_all('/https?:/',$_POST['text'],$match);
  return (count($match[0])>$MaxLinkCount) ? false : true;
}

function HandleCommentPost($pagename) {
  global $_GET,$_POST,$JournalPattern,$DiaryPattern,$Author;
  global $AuthFunction, $oAuthFunction, $EnablePostToAnyPage;
  if (!@$_POST['post'] || @$_POST['text']=='') Redirect($pagename);
  if (@$_POST['author']=='') $Author = 'anon';
  if (isset($_GET['message'])) { $message = $_GET['message']; echo $message; }
  if (@$_POST['postto'] && $EnablePostToAnyPage==1) {
      SDV($EditRedirectFmt, $pagename);
      $pagename = MakePageName($pagename, $_POST['postto']);
  }

  $_POST['text'] = preg_replace('/\\(:/', '(&#x3a;', $_POST['text']);

  SDV($AuthFunction,'PmWikiAuth');
  $oAuthFunction = $AuthFunction;
  $AuthFunction = 'BypassAuth';
  $page = RetrieveAuthPage($pagename, "read");
  if(get_magic_quotes_gpc()==1) $page['text'] = addslashes($page['text']);
  $HandleCommentFunction = (preg_match($JournalPattern,$pagename)) ? 'Journal' :
    ((preg_match($DiaryPattern,$pagename)) ? 'Diary'   : 'Message');
  $HandleCommentFunction = 'Handle' . $HandleCommentFunction . 'Post';
  $HandleCommentFunction($pagename, $page['text']);
  HandleEdit($pagename);
  exit;
}

function BypassAuth($pagename,$level,$authprompt=true) {
    global $AuthFunction,$oAuthFunction;
    if ($level=='edit') $AuthFunction = $oAuthFunction;
    return $oAuthFunction($pagename,"read",$authprompt);
}

function FormatDateHeading($txt,$datefmt,$fmt) {
  return str_replace($txt,strftime($datefmt,time()),$fmt);
}

## Journal entry
function HandleJournalPost($pagename,$pagetext) {
   global $_POST,$JournalDateFmt,$JournalTimeFmt,$JPItemStartFmt,$JPItemEndFmt,$JPDateFmt,$JPTimeFmt,
            $Author;
   SDV($JPDateFmt,'>>journaldate<<(:nl:)!!!!$Date');
   SDV($JPTimeFmt,"\n>>journalhead<<\n!!!!!&ndash; \$Time &ndash;\n");
   SDV($JPItemStartFmt,">>journalitem<<\n");
   SDV($JPItemEndFmt,"");
   $date = FormatDateHeading('$Date',$JournalDateFmt,$JPDateFmt);
   $time = $date . FormatDateHeading('$Time',$JournalTimeFmt,$JPTimeFmt);
   $entry = $time.$JPItemStartFmt.$_POST['text'].$JPItemEndFmt;
   $_POST['text'] = (strstr($pagetext, $date)) ?
        str_replace($date, $entry, $pagetext) :
        "$entry\n>><<\n\n" . $pagetext;
}

## Diary entry
function HandleDiaryPost($pagename,$pagetext) {
   global $_POST,$JournalDateFmt,$DPDateFmt,$DPItemStartFmt,$DPItemEndFmt,$DPItemFmt;
   SDV($DPDateFmt,">>diaryhead<<\n!!!!\$Date ");
   SDV($DPItemStartFmt,"\n>>diaryitem<<\n");
   SDV($DPItemEndFmt,"");
   $date = FormatDateHeading('$Date',$JournalDateFmt,$DPDateFmt);
   $entry = $date.$DPItemStartFmt.$_POST['text'].$DPItemEndFmt;
   $_POST['text'] = (strstr($pagetext, $date)) ?
        str_replace($date, $entry, $pagetext) :
        "$entry\n>><<\n\n" . $pagetext;
}

##  Comment entry
function HandleMessagePost($pagename,$pagetext) {
   global $_POST,$JournalDateFmt,$JournalTimeFmt,$MPDateFmt,$MPTimeFmt,$MPAuthorLink,
        $MPItemFmt,$MPItemStartFmt,$MPItemEndFmt,$MPDateTimeFmt,$MultipleItemsPerDay,$Author,
        $EnablePostAuthorRequired, $CommentboxMessageFmt,$PageUrl,$EnablePageBreaks,$PostsPerPage,
        $MPAnchorFmt,$MPAnchorChronoFmt;
    $id = StringCount($pagename,">>messagehead<<")+1;
    $website = @$_POST['website'];
    $email = @$_POST['email'];
    if(@$_POST['email']=='') $gravatar = "";
    else {
      $url = "http://www.gravatar.com/avatar.php?gravatar_id=".
	md5($email)."&amp;size=20&amp;foo=.png";
      $gravatar = "%lfloat% $url\n\n";
    }
    $weblink = '&mdash; [-[[(http://)'.$website.']]-]';
    if(@$_POST['website']=='') $weblink = '';

if ($EnablePageBreaks==1) {
   $r = fmod($id-1,$PostsPerPage);
   if($r==0 && $id>1)  SDV($MPItemEndFmt, "\n>><<\n\n(:comment breakpage:)");
   else SDV($MPItemEndFmt, "\n>><<");
   }
else SDV($MPItemEndFmt,"\n>><<");

   SDV($MPDateFmt,'>>messagedate<<(:nl:)!!!!$Date');
   SDV($MPTimeFmt,"(:nl:)>>messagehead<<\n$gravatar!!!!!\$Author  &mdash; [-at \$Time-] $weblink \n");
   SDV($MPItemStartFmt,">>messageitem<<\n");
   SDV($MPDateTimeFmt,"(:nl:)>>messagehead<<\n$gravatar!!!!!\$Author  &mdash;  [-\$Date, \$Time-] $weblink \n");
   SDV($MultipleItemsPerDay,0); # set to 1 to have date above for multiple entries per day
   SDV($MPAuthorLink, 1); # set to 0 to disable author name as link
   SDV($MPAnchorFmt,"(:commentbox:)");
   SDV($MPAnchorChronoFmt,"(:commentboxchrono:)");
   $name = @$_POST['author'];
   if (@$_POST['author']=='') $_POST['author'] = 'anon';
  # disable anonymous posts, but this looses also any message content:
  # if($EnablePostAuthorRequired == 1 && $name=='') Redirect($pagename);
   if($name=='') $name = 'anonymous';
   else $name = ($MPAuthorLink==1) ? '[[~' . $name . ']]' : $name;
   if ($MultipleItemsPerDay) {
        $date = FormatDateHeading('$Date',$JournalDateFmt,$MPDateFmt);
        $entry = '[[#comment'.$id.']]';
        $entry .= str_replace('$Author',$name,
            FormatDateHeading('$Time',$JournalTimeFmt,$MPTimeFmt));
   } else {
        $date = '';
        $entry = '[[#comment'.$id.']]';
        $entry .= FormatDateHeading('$Date',$JournalDateFmt,
            str_replace('$Author',$name,
            FormatDateHeading('$Time',$JournalTimeFmt,$MPDateTimeFmt)));
   }
   $entry.= $MPItemStartFmt.$_POST['text'].$MPItemEndFmt;
   $order= @$_POST['order'];
   if ($order=='') { # order is not chrono, latest top
#       if (strstr($pagetext,'(:commentbox:)(:if:)')) {
#         $pos = strpos($pagetext,'(:commentbox:)(:if:)');
#         $len = strlen('(:commentbox:)(:if:)');
      if (strstr($pagetext,$MPAnchorFmt)) {
         $pos = strpos($pagetext,$MPAnchorFmt);
         $len = strlen($MPAnchorFmt);
         $before = substr($pagetext,0,$pos+$len)."\n";
         $after  = substr($pagetext,$pos+$len);
      }
      else {
         $before = '';
         $after  = $pagetext;
      }
      $entry = "$date$entry";
      $after = ($MultipleItemsPerDay && strstr($after, $date)) ?
            str_replace($date, $entry, $after) : "$entry$after";
   }
   else { # order is chrono, latest last
      $entry .= "\n";
#      if (strstr($pagetext,'(:if auth edit:)(:commentboxchrono:)')) {
#         $pos = strpos($pagetext,'(:if auth edit:)(:commentboxchrono:)');
      if (strstr($pagetext,$MPAnchorChronoFmt)) {
         $pos = strpos($pagetext,$MPAnchorChronoFmt);
         $before = substr($pagetext,0,$pos);
         $after  = substr($pagetext,$pos);
      }
      else {
         $before = $pagetext;
         if ($before[strlen($before)-1]!='\n') $before .="\n";
         $after  = '';
      }
      $before .= ($MultipleItemsPerDay && strstr($before, $date)) ?
            substr($entry,1) : "$date$entry";
   }
   $_POST['text'] = "$before\n$after";
}

# add page variable {$PostCount},
# counts message items per page
$FmtPV['$PostCount'] = 'StringCount($pn,">>messagehead<<")';
function StringCount($pagename,$find) {
   $page = ReadPage($pagename, READPAGE_CURRENT);
   $n = substr_count($page['text'], $find);
   if ($n==0) return '';  #suppressing 0
   return $n;
}

