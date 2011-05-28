<?php if (!defined('PmWiki')) exit();

/*
  UserAuth2 (Version 2.x.x) - A user-based authorization and authentication module.

  Permission checking library.

  Copyright 2007 Thomas Pitschel, pmwiki (at) sigproc (dot) de
 
  The aim was to factor out functionality that is not bound to an (interactive)
  client access session via web browser, but is rather low level and concentrating
  all the checking logic, perm record syntax and perm setup policy in it. Thus
  session related stuff, cookies, forms and markup are all left out.

  This all is in lieu of including the authentication mechanism also in a non-interactive
  scenario, e.g. when accessing the wiki by api or email posting.

  getCurrClientIp() has been left as the ip ranges check is deeply intertwined with
  the other perm checking stages. It does no harm as also other interface mechanisms
  might have an ip associated with it on the one hand, or on the other hand the ip
  can always be set to some non-harmful dummy value.

  This file does not contain any global statements with external relevance and can 
  thus be included somewhere else in any order.

  28.11.2007 ThP

  -- License --

  GNU General Public License. 

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

*/

/*

  Vars/funcs that have to be set during runtime, i.e. before actually calling one of the
  functions:

  $UA2EnablePermCaching
  $UA2UserPermDir
  $UA2GroupPermDir
  $UA2IpRangesDir
  $UA2ProfileDir
  $LoginPage
  $HomePage
  $GuestUsergrp
  $LoggedInUsergrp
  $UA2LoggedInUsersReplacements
  $HandleAuth
  $UA2AlwaysAllowedLevels
  $UA2LevelToAbbr
  $UA2PageRelatedLevelAbbr
  $UserInstanceVars
    -> isAuthenticated();
    -> GetUsername();
    -> isPermQueryResCached($page, $level);
    -> getPermQueryRes($page, $level);
    -> pushPermQueryRes($page, $level, $res);
    -> isUserPermRecordCached($user, $groupaction);
    -> getPermHolderRecord($user, $groupaction);
    -> getIpRangePermRecords();
  appendToUA2ErrorLog(msg);
  flushUA2ErrorLog();
  getCurrClientIp();

*/

//===========================================================================
//=============== Default config usually not necessary to be changed ========
SDVA($HandleAuth, array(
 // action => level
  'admin'            => 'admin',
  'pwchange'         => 'pwchange',
  'pwset'            => 'pwset',
  'profile'          => 'profile',
  'diff'             => 'history', // this is a redefinition of the diff action to make it separately grantable
 // we have to take up the following (to pmwiki irrelevant) action-level pairs since otherwise, CheckUserPerms
 // will complain about unknown actions/levels
  'edituserperms'    => 'edituserperms',
  'createuserperms'  => 'createuserperms',
  'setipperms'       => 'setipperms',
));
SDVA($UA2AlwaysAllowedLevels, array(
  'logout',
  'ALWAYS',
  'zap'
));
SDVA($UA2LevelToAbbr, array(
 // level => abbreviation (used in perm table)
 // (must be bijection)
 // page related:
  'read'             => 'rd',
  'edit'             => 'ed',
  'upload'           => 'up',
  'history'          => 'hi',
 // non page related:
  'admin'            => 'ad', // may use admin tool (e.g. to change user rights) to the well-defined extent (see spec)
  'pwchange'         => 'pw', // user may change his own password
  'pwset'            => 'ps', // may set passwords for users below
  'profile'          => 'pr', // may view and alter his profile (excluding his password)
  'createuserperms'  => 'cu', // may create and delete users/groups
  'edituserperms'    => 'eu', // may edit users'/groups' permissions
  'setipperms'       => 'ip', // may change ip related permissions
));
// which levels make only sense in connection with a page: (the other ones are usually user related)
SDVA($UA2PageRelatedLevelAbbr, array(
  'rd', 'ed', 'up', 'hi'
));
// Whether the 'attr' level should be denied for everyone. If false, it is granted to admin only by default,
// and further recipients have to be privileged by introducing a level abbreviation and then using 
// standard granting procedures. (The default true is for performance reasons.)
SDV($UA2DenyAttrLevel, true);

//================================================================================
//============ Init some statistics vars, other stuff ============================

$UA2StatUncachedRecordLoadsCount = 0; // both totals over complete script run
$UA2StatUncachedPermQueryCount = 0;

if (!function_exists(microtime_float)) {
  function microtime_float() {
    list($usec, $sec) = explode(" ", microtime());
    return ((float)$usec + (float)$sec);
  }
}

//================================================================
//======== Permission checking ===================================
//
// The following functions are status-checkers, i.e. they take no action
// on the web-app state whatsoever. They however depend on the web-app state
// via the authentication status of the current user.

function HasCurrentUserPermForAction($page, $action) {
  global $HandleAuth;
  if (!isset($HandleAuth[$action])) {
    appendToUA2ErrorLog("Warning: Current user asking for permission for unknown action '$action'. Refused.\n");
    return false;
  }
  return HasCurrentUserPerm($page, $HandleAuth[$action]);
}

function HasCurrentUserPerm($page, $level) {
  // This is the (query-level) cached version of the function 
  // HasCurrentUserPermUncached() below.
  global $UserInstanceVars, $UA2EnablePermCaching;

  $UA2StartTime = microtime_float();

  if (!$UA2EnablePermCaching) {
    $res = HasCurrentUserPermUncached($page, $level);
    //appendToUA2ErrorLog("Executed in " . (microtime_float() - $UA2StartTime)*1000 . " msecs (uncached).\n");
    //flushUA2ErrorLog();
    return $res;
  }

  // look at the cache first:
  if ($UserInstanceVars->isPermQueryResCached($page, $level)) {
    $res = $UserInstanceVars->getPermQueryRes($page, $level); 
    //appendToUA2ErrorLog("Executed in " . (microtime_float() - $UA2StartTime)*1000 . " msecs (cached).\n");
    //flushUA2ErrorLog();
    return $res;
  }
  
  // or recalculate if not had been cached:
  $res = HasCurrentUserPermUncached($page, $level);

  // in any case cache it:
  $UserInstanceVars->pushPermQueryRes($page, $level, $res);

  //appendToUA2ErrorLog("Executed in " . (microtime_float() - $UA2StartTime)*1000 . " msecs (uncached).\n");
  //flushUA2ErrorLog();
  return $res;
}

function HasCurrentUserPermUncached($page, $level) {
  global $UserInstanceVars, $UA2StatUncachedPermQueryCount;

  $UA2StatUncachedPermQueryCount += 1;

  // First check by the current user's personal permission table:
  // (if here the username is undefined since not yet logged in, the
  //  parameter will be at least "as empty" such that #001 fails)
  if (CheckUserPerms($UserInstanceVars->GetUsername(), $page, $level)) 
    return true;

  // Otherwise try ip based (silent) granting:
  $ipRangePermRecords = $UserInstanceVars->getIpRangePermRecords();
  if ($ipRangePermRecords) 
    foreach($ipRangePermRecords as $ipRange => $permrecord) {
      if (ipMatches($ipRange, getCurrClientIp()) &&
          CheckUserPermsWithRecord($page, $level, $permrecord))
        return true;
    }
  
  return false;
}

//  The following functions perform web-app-state blind permission
//  checking: all what determines their result is fed through the parameters.

function CheckUserPermsForAction($user, $page, $action, $groupaction = false) {
  global $HandleAuth;
  if (!isset($HandleAuth[$action])) {
    appendToUA2ErrorLog("Warning: '$user' asking for permission for unknown action '$action'. Refused.\n");
    return false;
  }
  return CheckUserPerms($user, $page, $HandleAuth[$action], $groupaction);
}

function CheckUserPerms($user, $page, $level, $groupaction = false, $origuser = false) {
  global $UA2ChUsPmCallingStack;

  if (!$origuser) $origuser = $user; // #011

  // Make sure we dont cycle: (stop defensively if so) 
  // (important for complex group dependencies)
  if (isset($UA2ChUsPmCallingStack[$user])) // this works also for the initial case when arr still unset
    return false;
 
  $UA2ChUsPmCallingStack[$user] = 1;
  $res = CheckUserPermsWorker($user, $page, $level, $groupaction, $origuser);
  unset($UA2ChUsPmCallingStack[$user]);
  if (empty($UA2ChUsPmCallingStack)) {
    // Remember to switch off perm caching to be sure that the following lines are executed
    if (false) { // show PERM results at the bottom of wiki output?
      global $HTMLFooterFmt;
      $HTMLFooterFmt[] = "Perm for user '$user', page '$page', level '$level': " . 
                         ($res ? "passed.\n" : "failed.\n") . "<br>\n";
    }
    if (false) // log PERM results?
    appendToUA2ErrorLog("Perm for user '$user', page '$page', level '$level': " .
                        ($res ? "passed.\n" : "failed.\n"));
    flushUA2ErrorLog(); // since every permission check passes this point exactly once
                        // (after having ascended from the recursion), do it here
  }
  return $res;
}

function CheckUserPermsWorker($user, $page, $level, $groupaction, $origuser) {
  // This function checks the permissions solely due to the
  // identification as given by the user name. If $user == '' or $user == false, 
  // then this counts as not logged in. If non-empty, expects a valid user string 
  // as input. Argument $page can be the empty string if $level is not page-related.
  // (Note that even when using CleanUrls, i.e. enabled path info, this function
  // expects (and is indeed delivered so by the engine) page names with a DOT as
  // group/page part separator.)
  global $LoginPage, $HomePage, $GuestUsergrp, $LoggedInUsergrp,
         $UserInstanceVars, $UA2AlwaysAllowedLevels, $UA2AllLevels, $HandleAuth, $UA2DenyAttrLevel,
         $UA2LoggedInUsersReplacements, $UA2LevelToAbbr, $UA2PageRelatedLevelAbbr;

  if (!isset($UA2AllLevels))
    $UA2AllLevels = array_merge(array_values($HandleAuth), $UA2AlwaysAllowedLevels);

  if (!in_array($level, $UA2AllLevels)) {
    appendToUA2ErrorLog("Warning: Someone asking for permission for unknown level '$level'. Refused.\n");
    return false;
  }

  //appendToUA2ErrorLog("CheckUserPerms user $user page $page level $level...\n");

  // First the hardwired permissions:
  if ($level == 'attr' && $UA2DenyAttrLevel) return false; // frequently occurring legacy level silently refused
  if (!$groupaction && ($user == 'admin')) return true;
  if (($level == 'read') && ($page == $LoginPage)) return true;
  //if (($level == 'read') && ($page == $HomePage)) return true; // see #010 in docu
  if (in_array($level, $UA2AlwaysAllowedLevels)) return true;
  if (!$groupaction) { // see #004 in docu
    if (@in_array($UA2LevelToAbbr[$level], $UA2PageRelatedLevelAbbr) && // see #301, and #301 in docu
        CheckUserPerms($GuestUsergrp, $page, $level, true, $origuser)) return true; // #011 in docu
    if ((strlen($user) > 0) &&  // See #001
        CheckUserPerms($LoggedInUsergrp, $page, $level, true, $origuser)) return true;
  }

  if (strlen($user) == 0) return false;
  if (! doesUserExist_expected($user, $groupaction)) return false; 
    // here, for groups, return silently since someone could have deleted a group and the
    // perm tables haven't been updated by the admin yet; for users, chances are that 
    // we are checking for the parent of an orphaned user, so this is told in the error log

  // Extension to cater for additional external group membership schemes:
  $usrgrps = $UserInstanceVars->getFurtherGroupsForUser($user, $groupaction);
  if ($usrgrps) {
    foreach ($usrgrps as $g)
      if ($g && CheckUserPerms($g, $page, $level, true, $origuser)) return true;
  }

  // Then by saved record: (note that in any case the result that is obtained
  // via getPermHolderRecord is cached in the UserInstance, be it the guest user
  // logged in user record or the record of the user himself)
  $permrecord = $UserInstanceVars->getPermHolderRecord($user, $groupaction);
  if (!$permrecord) return false; // when problems loading perm record, stop defensively

  if (($user == $LoggedInUsergrp) && function_exists($UA2LoggedInUsersReplacements))
    $UA2LoggedInUsersReplacements($permrecord, $origuser);

  return CheckUserPermsWithRecord($page, $level, $permrecord);
}

function CheckUserPermsWithRecord($page, $level, $permrecord) {
  // In this function the deeper internal logic of the delegation
  // scheme is concentrated. Two versions: 
  // (1) everyone can grant to everyone as much rights as he possesses 
  //   himself (a strategic world, google rank world), or 
  // (2) everyone in the line between admin and subject only can grant the 
  //   rights he possess himself (hierarchical world). 
  // Implemented is version (2).
 
  $parent = $permrecord['parent'];
  if (strlen($parent) == 0) return false; // empty parent would be a serious
                                          // inconsistency, so deny all

  $patronArr = getAllPatrons($parent); // is a proper user
  if ($patronArr === false) return false; // the parent or one of its patrons did not exist, so our
                                          // subject is an orphan => deny all.

  foreach($permrecord['perms'] as $granter => $permtable) {
    // First check if granter is patron of $subject: (BTW, make sure that all granters are proper users)
    // (take this out if you want version (1))
 
    if (($granter != 'admin') && !isInArrOfPatrons($granter, $patronArr)) continue;
      // This is equivalent to if (!isEqualOrPatronOf($granter, $parent)) continue;, only that
      // here we dont recalculate the patron array again and again.

    // Then check if granter has enough rights:
    if (! CheckUserPerms($granter, $page, $level)) continue;

    // Finally let's see how much he grants:
    if (CheckUserPermsWithTable($page, $level, $permtable, $granter))
      return true;
  }
  return false;
}

function CheckUserPermsWithTable($page, $level, $permtable, $granter) {
  // We expect here perm tables with each of the group membership specs
  // appearing before any elementary entry (see #002)

  // Honour group memberships on the fly, and transparently to the effects of
  // the elementary entries:
  $result = false; // default "deny all"
  foreach($permtable as $permentry) {
    if ($permentry{0} == '#') continue;       // this was a comment
    if (!$result && ($permentry{0} == '@')) { // this was a group membership spec
      $usergroup = substr($permentry, 1);     //   and it is worth considering
      if (!isPatronOf($granter, $usergroup, true)) continue; // (see docu #201)
      if (CheckUserPerms($usergroup, $page, $level, true)) $result = true; // see #205
    } 
    if ($permentry == '*') $result = true;
    if (entryApplicable($permentry, $page, $level)) {
      if ($permentry{0} == '-') 
        $result = false;
      else
        $result = true;
    }
  }

  return $result;
}

function entryApplicable($entry, $page, $level) {
  // Allowed parameters for $page: "group.page" or "group.*"
  // $entry must be of form "ad", "ed_group.page", "-ed_group.*", etc.
  //
  // Return values:
  // queried page:            entry:                     Result:
  // mygroup.mypage           mygroup.mypage             match
  // mygroup.mypage           *.mypage                   match
  // mygroup.mypage           mygroup.*                  match
  // mygroup.mypage           *.*                        match
  // mygroup.*                mygroup.*                  match
  // mygroup.*                *.*                        match
  // mygroup.*                mygroup.*d*                not match
  // mygroup.*                mygroup.anypage            not match
  //
  // When adding new features to the perm table syntax, make sure you update
  // isConsistentPermTable() also.
  global $UA2LevelToAbbr, $UA2PageRelatedLevelAbbr;

  if (strlen($entry) < 2) return false;
  if ($entry{0} == '-') $entry = substr($entry, 1);
  $levelspec = substr($entry, 0, 2); // first two characters
  $pagespec  = substr($entry, 3);    // everything after third character
  $levAbbr = $UA2LevelToAbbr[$level];
  if (!$levAbbr) 
    appendToUA2ErrorLog("Warning: Someone passed invalid permission level '$level' as argument. Refused.\n"); 
  if (!($pagespec && ($levelspec == 'xx') &&              // a joker level entry must be page related and must match
        in_array($levAbbr, $UA2PageRelatedLevelAbbr))) {  // a page related query;
    if ($levAbbr !== $levelspec) return false;            // otherwise compare levels as usual
  }
  if ($pagespec) { // if the entry is indeed page related (non-empty)
    if (! isValidQueriedPagename($page)) return false; // then be cautious
    return pagePatternMatches($pagespec, $page);
  }
  return true;
}

function getPagePart($page) {
  return substr($page, strpos($page, '.') + 1);
}

function getGroupPart($page) {
  return substr($page, 0, strpos($page, '.'));
}

function isGroupReference($page) {
  // returns true if $page is of form "group.*"
  return getPagePart($page) === '*';
} 

function pagePatternMatches($pattern, $pagename) {
  // $pattern must be of the form 'a??.d*e*' for example, $pagename must be
  // a valid queried page name. A '*' will match any number of any allowed characters,
  // the '?' will match exactly one allowed character.

  // At this point, all variables (only {$AuthId} at the moment) must be replaced already,
  if (strpos($pattern, '{') !== false) return false; // otherwise deny silently.

  if (strpos($pagename, '*') !== false) { // if is a group query then 
    if (isGroupReference($pattern)) { // if the patterns ends on '.*'
      $pagename = getGroupPart($pagename);  // then go for it, using only the group parts
      $pattern = getGroupPart($pattern);
    } else                                 // otherwise it cant be counted as a group related specifier
      return false;
  }
  // assemble reg exp out of page name pattern:
  $search = array('.', '?', '*');
  $replace= array('\.', '.', '(.*)');
  $regexp = str_replace($search, $replace, $pattern);
  // and fire:
  return preg_match('/^'.$regexp.'$/', $pagename); // case-sensitive
}

function replaceAuthIdInRecord(&$permrecord, $origuser) {
  // replaces all occurences of "{$AuthId}" in the perm tables of the given record by the
  // upper-cased version of the given user name.
  $newperms = array();
  foreach($permrecord['perms'] as $granter => $permtable) {
    $newperms[$granter] = str_replace('{$AuthId}', ucfirst($origuser), $permtable);
  }
  $permrecord['perms'] = $newperms;
}

//====================================================
//======== Validity checks ===========================

function isValidUserString($user, $groupaction = false) {
  // user string may contain only letters, digits and underscore, and
  // must begin with a letter; ensure that it is a valid file name on your
  // system!
  return preg_match('/^[a-zA-Z][a-zA-Z0-9\_]*$/', $user);
    // (for both users and groups the same requirement)
}

function isValidChosenUserString($user, $groupaction = false) {
  // This function does not decide regarding whether the user already exists!
  global $GuestUsergrp, $LoggedInUsergrp;

  // first, the string must be a valid choice:
  if (!isValidUserString($user, $groupaction)) return false;

  // since the edit lock files are constructed by prepending a dot to the
  // user name, some choices are rather forbidden as user/group names:
  if (strcasecmp($user, 'htaccess') == 0) return false;
  if (strcasecmp($user, 'htpasswd') == 0) return false;
  if (strcasecmp($user, 'htgroup') == 0) return false;

  // some are just inappropriate (for example to similar to special names):
  if ($groupaction && ($user === $GuestUsergrp)) return true; // exact matches to guest or logged in user are ok
  if ($groupaction && ($user === $LoggedInUsergrp)) return true; 
  if (strcasecmp($user, $GuestUsergrp) == 0) return false;    // mimicks of these are forbidden
  if (strcasecmp($user, $LoggedInUsergrp) == 0) return false;
  if (strcasecmp($user, 'admin') == 0) return false; // admin user and its mimicks are tabu

  return true;
}

function isValidIpRange($ipRange) {
  // This function is needed for deciding whether
  // to save a group into the group perms or ip ranges folder.
  // Returns true if argument is of the form 
  // 192.
  // 192.168.
  // 192.168.1.
  // 192.168.1.10
  // false otherwise. 
  if (!preg_match('/^[0-9\.]+$/', $ipRange)) return false;
  $buf = explode(".", $ipRange);
  if (count($buf) < 2) return false;
  if (count($buf) > 4) return false;
  $flag = false; // the construction with the flag makes any empty
                 // byte field which is not the last to cause a neg answer
  foreach($buf as $byteVal) {
    if ($flag) return false;
    if (strlen($byteVal) == 0)
      $flag = true;
    else
      if (($byteVal < 0) || ($byteVal > 255)) return false;
  }
  return true;
}

function isIpRange($alleged_iprange) {
  // This is the meager version of the function above.
  // It expects a valid perm holder string, i.e. valid ip range or
  // valid user/group string.
  if (($alleged_iprange{0} >= '0') && ($alleged_iprange{0} <= '9'))
    return true;
  return false;
}

function isValidPermHolderString($user, $groupaction = false) {
  return isValidUserString($user, $groupaction) ||
         ($groupaction && isValidIpRange($user));
}

if (!function_exists(lcfirst)) {
function lcfirst($str) { // like ucfirst()
  if (strlen($str) == 0) return '';
  return strtolower($str{0}) . substr($str, 1);
}
}

function isValidChosenPermHolderString($user, $groupaction = false) {
  if (doesPermHolderExist(ucfirst($user), $groupaction, false) ||
      doesPermHolderExist(lcfirst($user), $groupaction, false))
    return false; // see #012 in docu

  return isValidChosenUserString($user, $groupaction) ||
         ($groupaction && isValidIpRange($user));
}

function isValidFullPagename($pagename) {
  // Just for reference, to explicitize that valid pages contain only
  // letters and digits, and group and page part must be non-empty.
  return preg_match('/^[a-z0-9]+\.[a-z0-9]+$/i', $pagename);
}

function isValidQueriedPagename($pagename) {
  // Returns true if the argument is valid when provided to CheckUserPerms etc.
  // as $page argument. Currently only full qualified pages (MyGroup.MyPage) or
  // group queries (MyGroup.*) are permitted.
  return preg_match('/^[a-z0-9]+\.\*|([a-z0-9]+)$/i', $pagename);
}

function isValidPagenamePattern($pattern) {
  // For validity check of page specifiers in page related perm entries.
  // Returns true if it contains exactly one dot, and group and page part are non-empty,
  // consist of letters, digits, '*' and '?' only and each dont start with a digit.
  // Examples are: 'a.b', '*a*.h12???', '*.*', 'dfsf.*', '*.df' and so on.
  $pattern = str_replace('{$AuthId}', 'Max', $pattern);
  return preg_match('/^[a-z0-9\?\*]+\.[a-z0-9\?\*]+$/i', $pattern);
  //return preg_match('/^[a-z\?\*][a-z0-9\?\*]*\.[a-z\?\*][a-z0-9\?\*]*$/i', $pattern); // old
}

function isContentPage($pagename) {
  global $LoginPage;

  return isValidFullPagename($pagename) &&
         ($pagename != $LoginPage);
}

//=====================================================
//======== File handling ==============================

function getUserPermFilename($user) {
  global $UA2UserPermDir;

  return "$UA2UserPermDir/$user";
}

function getGroupPermFilename($group) {
  global $UA2GroupPermDir;

  return "$UA2GroupPermDir/$group";
}

function getIpRangePermFilename($iprange) {
  global $UA2IpRangesDir;

  return "$UA2IpRangesDir/$iprange";
}

function getPermHolderFilename($user, $groupaction = false) {
  // Generalized version of the above three. Expects $user to be
  // either a valid ip range or valid user/group string.
  if ($groupaction) {
    if (isIpRange($user)) 
      return getIpRangePermFilename($user);
    else
      return getGroupPermFilename($user);
  }
  return getUserPermFilename($user);
}

function getEditLockFilename($user, $groupaction = false) {
  // Generalized version.
  // Practically, these hidden files are automatically ignored when reading users/groups.
  if ($groupaction) {
    if (isIpRange($user))
      return getIpRangePermFilename('.' . $user);
    else
      return getGroupPermFilename('.' . $user);
  }
  return getUserPermFilename('.' . $user);
}

if (!function_exists('doesUserExist')) {
function doesUserExist($user, $groupaction = false, $use_cache = true) {
  global $UserInstanceVars;

  if (!$groupaction && ($user == 'admin')) return true;

  if ($use_cache && $UserInstanceVars->isUserPermRecordCached($user, $groupaction))
    return true;

  if ($groupaction) return doesGroupExist($user);

  return file_exists(getUserPermFilename($user));
}
}

function doesUserExist_expected($user, $groupaction = false) {
  // This is the same version as before, but with error reporting
  // included for cases where we expect the user to exist, but it
  // doesn't. For groups we go silent, since it is easier to forget
  // to delete all group references and it is less critical. (On the
  // other hand a user without parent is a orphan in the system,
  // can be manipulated by the admin only.)

  if (doesUserExist($user, $groupaction)) return true;
  if (!$groupaction)
    appendToUA2ErrorLog("Warning: Expected user '$user' did not exist. (could be ghost parent of an orphan)\n");
  return false;
}

function doesGroupExist($group) {
  return file_exists(getGroupPermFilename($group));
}

function doesIpRangeExist($iprange) {
  return file_exists(getIpRangePermFilename($iprange));
}

function doesGenGroupExist($group) {
  return doesGroupExist($group) || doesIpRangeExist($group);
}

function doesPermHolderExist($user, $groupaction = false, $use_cache = true) {
  // This is a permrecord-cached version valid for all three types
  // of "users" (user, group, iprange).
  // Expects $user to be a valid perm holder string, i.e. either
  // a valid ip range, or a valid user or group name. Distinction
  // between ip range and the others is made via the first character
  // in $user, distinction between user and group name is made via 
  // $groupaction.
  global $UserInstanceVars;

  if ($groupaction && isIpRange($user)) {
    if ($use_cache && $UserInstanceVars->isIpRangeRecordCached($user, $groupaction))
      return true;
    return doesIpRangeExist($user);
  }

  return doesUserExist($user, $groupaction, $use_cache);
}

function loadUserPermRecord($user, $groupaction = false) {
  if (strlen($user) == 0) return false;

  //appendToUA2ErrorLog("Loading perm record for $user.\n");

  if ($groupaction)
    return loadPermRecord_(getGroupPermFilename($user));
  else
    return loadPermRecord_(getUserPermFilename($user));
 
  return false;
}

function loadIpRangePermRecord($iprange) {
  if (strlen($iprange) == 0) return false;

  //appendToUA2ErrorLog("Loading perm record for $iprange.\n");

  return loadPermRecord_(getIpRangePermFilename($iprange));
}

function loadPermHolderRecord($holder, $groupaction) {
  // Expects a valid holder string.
  if ($groupaction && isIpRange($holder))
    return loadIpRangePermRecord($holder);
  else
    return loadUserPermRecord($holder, $groupaction);
  return false;
}

function loadPermRecord_($file) {
  global $UA2StatUncachedRecordLoadsCount;
  $UA2StatUncachedRecordLoadsCount += 1;
  $fp = @fopen($file, 'r');
  //if ($fp && flock($fp, LOCK_SH)) { // old, left uninterpreted on FAT systems anyway
  if ($fp) { // if user exists, load record
    $sz = filesize($file);
    $permrecord = unserialize(fgets($fp, $sz));
    // flock($fp, LOCK_UN); // old
    fclose($fp);
    return $permrecord;
  }
  else
    appendToUA2ErrorLog("Could not open permission record at $file for reading.\n");
  return false;
}

function saveUserPermRecord($user, $record, $groupaction = false) {
  if ($groupaction)
    return savePermRecord_(getGroupPermFilename($user), $record);
  else
    return savePermRecord_(getUserPermFilename($user), $record);
}

function saveGroupPermRecord($group, $record) {
  return savePermRecord_(getGroupPermFilename($group), $record);
}

function saveIpRangePermRecord($iprange, $record) {
  return savePermRecord_(getIpRangePermFilename($iprange), $record);
}

function savePermHolderRecord($holder, $record, $groupaction) {
  // This is the most generalized form, with holder being
  // either a normal user (groupaction==false) or a normal group
  // or ip range (identified from the holder string; both with 
  // groupaction==true).
  if (strlen($holder) == 0) { 
    appendToUA2ErrorLog("Warning: Tried to save to empty user name. Prevented.\n");
    return false;
  }
  if ($groupaction && isIpRange($holder))
    return saveIpRangePermRecord($holder, $record);
  else
    return saveUserPermRecord($holder, $record, $groupaction);
}

function savePermRecord_($file, $record) {
  $fp = @fopen($file, "w");
  // if ($fp && flock($fp, LOCK_EX)) { // old
  if ($fp) {
    fputs($fp, serialize($record) . "\n");
    //flock($fp, LOCK_UN); // old
    fclose($fp);
    return true;
  }
  return false;
}

function delUser($user, $groupaction = false) {
  global $UA2ProfileDir;

  if ($groupaction) 
    @unlink(getGroupPermFilename($user));
  else {
    @unlink(getUserPermFilename($user));
    @unlink("$UA2ProfileDir/$user");
  }
}

function delPermHolder($holder, $groupaction) {
  if ($groupaction && isIpRange($holder))
    @unlink(getIpRangePermFilename($holder));
  else
    delUser($holder, $groupaction);
}

function loadIpRangePermRecords() {
  global $UA2IpRangesDir;
  
  $res = array();
  $dirp = @opendir($UA2IpRangesDir);
  if (!$dirp) return $res;
  while (($file=readdir($dirp)) !== false) {
    if ($file[0]=='.') continue;
    $res[$file] = loadIpRangePermRecord($file);
  }
  closedir($dirp);
  return $res;
}

function getAllUserPermRecords() {
  global $UA2UserPermDir;

  $res = array();
  $dirp = @opendir($UA2UserPermDir);
  if (!$dirp) return $res;
  while (($user=readdir($dirp)) !== false) {
    if ($user[0]=='.') continue;
    $res[$user] = loadPermRecord_(getUserPermFile($user));
  }
  closedir($dirp);
  return $res;
}

function getAllGroupPermRecords() {
  global $UA2GroupPermDir;

  $res = array();
  $dirp = @opendir($UA2GroupPermDir);
  if (!$dirp) return $res;
  while (($group=readdir($dirp)) !== false) {
    if ($group[0]=='.') continue;
    $res[$group] = loadPermRecord_(getGroupPermFile($group));
  }
  closedir($dirp);
  return array_merge($res, loadIpRangePermRecords());
}

function loadUserProfile($user) {
  global $UA2ProfileDir;

  $filename = "$UA2ProfileDir/$user";

  $fp = @fopen($filename, 'r');
  if ($fp) { // if user exists, load profile
    $sz = filesize($filename);
    $profile = unserialize(fgets($fp, $sz));
    fclose($fp);
    return $profile;
  }
  else
    appendToUA2ErrorLog("Could not open profile at $filename for reading.\n");
  return false;
}

function saveUserProfile($user, $profile) {
  global $UA2ProfileDir;
  
  $filename = "$UA2ProfileDir/$user";

  $fp = @fopen($filename, 'w');
  if ($fp) { // if successful
    fputs($fp, serialize($profile) . "\n");
    fclose($fp);
    return true;
  }
  else 
    appendToUA2ErrorLog("Could not open profile at $filename for writing.\n");
  return false;
}

//==============================================================
//======== Ip address related helpers ==========================

function ipMatches($ipRange, $client_ip) {
  // Checks whether the given client ip matches the ip range given in $ipRange.
  // For $ipRange strings as "192.168.1.", "134.121.34.2", or "10.1." are allowed
  // (take care to use the final dot for actual ranges!).
  // $ipRange may be a comma-separated list of those strings in the future.
  if (count(explode(".", $ipRange)) == 4) // if iprange is a full ip address,
      // set an end marker to avoid 192.168.1.112 matching iprange = 192.168.1.1 (e.g.)
    return (strpos("x" . $client_ip . ",", $ipRange . ",", 1) == 1);
  return (strpos("x" . $client_ip, $ipRange, 1) == 1);
  // Note the begin marker "x" and testing on == 1 is also for the reason to avoid
  // matching the range "92." on an ip address "192.168.1.1".
}

function ipMatchesIpRangeArr($ipRangeArr, $client_ip) {
  // Returns true is the specified ip matches at least one of the
  // given ip ranges in $ipRangeArr.
  foreach($ipRangeArr as $ipRange)
    if (ipMatches($ipRange, $client_ip))
      return true;
  return false;
}

function currClientIpMatchesRange($ipRange) {
  return (boolean)ipMatches($ipRange, getCurrClientIp());
}

//==============================================================
//======== Structural functions on the perm record and related 

// ------------ initialization and cleaning ------------------

function newEmptyProfile() {
  $prof['password'] = ''; // empty password as default
  return $prof;
}

function newEmptyPermRecord($parent, $groupaction = false) {
  $res['parent'] = $parent;
  if (!makeConsistent($res, $groupaction)) return false;
  return $res;
}

function getConsistent($permrecord, $groupaction = false) {
  if (!makeConsistent($permrecord, $groupaction)) return false;
  return $permrecord;
}

function makeConsistent(&$permrecord, $groupaction = false) {

  $parent = $permrecord['parent'];
  if ((strlen($parent) == 0) || !doesUserExist($parent)) return false; 

  // set loginFromIpsOnly field to a definite value if is not set,
  // but only if it is a proper user; for groups delete it:
  if ($groupaction)
    unset($permrecord['loginFromIpsOnly']);
  else
    if (!isset($permrecord['loginFromIpsOnly']))
      $permrecord['loginFromIpsOnly'] = array();

  // same with the perms array: have at least an empty perm table for the parent
  if (!isset($permrecord['perms']))
    $permrecord['perms'][$parent] = array();

  // remove all non-authorized permission granters (and check perm tables on the fly):
  if (!empty($permrecord['perms'])) {
    $patronArr = getAllPatrons($parent);
    if ($patronArr === false) return false;
    foreach($permrecord['perms'] as $granter => $permtable) { // traversing a changing array works
      if (($granter != admin) &&
          (!doesUserExist($granter) ||
           !isInArrOfPatrons($granter, $patronArr)
          )
         ) {
        appendToUA2ErrorLog("Warning: '$granter' granting though not patron or non-existent. I keep deleting him.\n");
        unset($permrecord['perms'][$granter]); 
      }
      if (!isConsistentPermTable($permtable, $granter)) return false;
    }
  }

  return $permrecord;
}

// ------------- patron related -------------------------------

function isEqualOrPatronOf($alleged_patron, $dependent, $groupaction = false) {
  // Returns whether $alleged_patron is equal to or a patron of $dependent.
  // $alleged_patron must be proper user, $dependent may be user or group
  if ($alleged_patron == 'admin') return true;
  $patronArr = getAllPatrons($dependent, $groupaction);
  return isInArrOfPatrons($alleged_patron, $patronArr);
} 

function isPatronOf($alleged_patron, $dependent, $groupaction = false) {
  // Returns whether $alleged_patron is a (strict) patron of $dependent.
  // $alleged_patron must be proper user, $dependent may be user or group
  if (!$groupaction && ($dependent == $alleged_patron)) return false;
  return isEqualOrPatronOf($alleged_patron, $dependent, $groupaction);
}

function getAllPatrons($user, $groupaction = false) {
  // Returns an associative array of $user and all its patrons, excluding 'admin'.
  // Entries are of form $patron => $thePatronsParent, so (provided consistent
  // perm records) the first included entry is xxx => admin and the last included
  // is $user => yyy.
  global $UserInstanceVars; // we make use of almost arbitrary caching here
                            // (data need not necessarily have to do with the
                            // user represented by the user instance)
  // fill up result array from bottom-up:
  // (Due to some practical consideration we do not include the root 
  // parent 'admin' in the array.)
  if (!$groupaction && ($user == 'admin')) return array();
  $permrecord = $UserInstanceVars->getPermHolderRecord($user, $groupaction);
  if ($permrecord === false) return false;
  $parent = $permrecord['parent'];
  $res = getAllPatrons($parent); // parent is definitely a user
  if ($res === false) return false;
  $res[$user] = $parent;
  return $res;
}

function getAllPatronsStrictlyBelow($patron, $user, $groupaction = false) {
  // Returns the array of patrons of $user where all entries higher than $patron
  // are removed.
  $buf = getAllPatrons($user, $groupaction);
  if ($buf === false) return false;
  $res = array();
  $pointer = $user;
  while (($pointer != false) && isset($buf[$pointer])) {
    array_unshift($res, $buf[$pointer]); // unshift the parent of the currently considered patron:w
    $pointer = $buf[$pointer];
  }
  // now we have got an array of parents, in descending order starting with admin, so remove
  // the ones that are above $patron now:
  while (array_shift($res) !== $patron) ;
  return $res;
}

function getAllPatronsEqualOrBelow($patron, $user, $groupaction = false) {
  $res = getAllPatronsStrictlyBelow($patron, $user, $groupaction);
  if ($res === false) return false;
  array_unshift($res, $patron);
  return $res;
}

function isInArrOfPatrons($patron, $patronArr) {
  return isset($patronArr[$patron]);
}

// ------------- lists ----------------------------------------

function getAllUsers($groupaction = false, $use_cache = true, 
                     $cache_it = true, $assure_consistency = true) {
  // Returns an array of all users (or groups and ip ranges) in the
  // form $user => $theUsersParent.
  global $UA2GroupPermDir, $UA2UserPermDir, $UserInstanceVars;

  $res = array();
  if ($groupaction) $dirp = @opendir($UA2GroupPermDir);
  else              $dirp = @opendir($UA2UserPermDir);
  if (!$dirp) return false;
  while (($user=readdir($dirp)) !== false) {
    if ($user[0]=='.') continue;
    $permrecord = $UserInstanceVars->getPermHolderRecord(
                    $user, $groupaction, $use_cache, $cache_it, $assure_consistency);
    $res[$user] = $permrecord['parent'];
  }
  closedir($dirp);
  if ($groupaction) {
    $buf = $UserInstanceVars->getIpRangePermRecords();
    foreach($buf as $iprange => $permrecord)
      $res[$iprange] = $permrecord['parent'];
  }
  return $res;
}

function isOrphan($user, $groupaction = false) {
  global $UserInstanceVars;
  $precord = $UserInstanceVars->getPermHolderRecord($user, $groupaction, false, false, false);
    // a possibly inconsistent record (the third 'false'), since parent might not exist
  return !doesUserExist($precord['parent']);
}

function retainUsersStrictlyBelow($patron, $userList, $groupaction = false, $retain_orphans = false) {
  foreach($userList as $user => $anything) {
    if ((!$retain_orphans || !isOrphan($user, $groupaction)) &&
        !isPatronOf($patron, $user, $groupaction))
      unset($userList[$user]);
  }
  return $userList;
}

function sortUserList($userList, $rootparent) {
  // This code runs in quadratic time in the number of entries in userList.
  ksort($userList, SORT_STRING);
  $res = array();
  $buf[] = $rootparent;
  $processedParent = array_shift($buf);
  while ($processedParent) {
    foreach($userList as $user => $maybeRecord) {
      if (is_array($maybeRecord) && isset($maybeRecord['parent']))
        $userParent = $maybeRecord['parent'];
      else
        $userParent = $maybeRecord;
      if ($userParent == $processedParent) { // if we have found a direct dependent, push it:
        $res[$user] = $maybeRecord;
        $buf[] = $user;
        unset($userList[$user]);
      }
    }
    $processedParent = array_shift($buf);
  }
  return array_merge($res, $userList); // include the remainders "manually" (may be orphans)
}

function getUserListStrictlyBelow($patron) {
  $buf = getUserListEqualOrBelow($patron);
  unset($buf[$patron]);
  return $buf;
}

//---------------- group membership related ------------------------

function getAllGroupsOfUser($user, $groupaction = false) {
  // Returns an ass. array xx => 1 containing all groups xx the user is member of.
  // (The construction with the associative array avoids duplicates.)
  global $UA2AllGrpsCallingStack, $UserInstanceVars, $GuestUsergrp, $LoggedInUsergrp;

  // Make sure we dont cycle:
  if (isset($UA2AllGrpsCallingStack[$user]))
    return array();

  $permrecord = $UserInstanceVars->getPermHolderRecord($user, $groupaction);
  if (!$permrecord) return false;
  $res = array();
  foreach($permrecord['perms'] as $granter => $permtable) 
    foreach($permtable as $permentry) {
      if ($permentry{0} == '@') {
        $group = substr($permentry, 1);
        $res[$group] = 1;
        $UA2AllGrpsCallingStack[$group] = 1;
        $res = array_merge($res, getAllGroupsOfUser($group, true));
        unset($UA2AllGrpsCallingStack[$group]);
      } else break; // to conform with the optimization hack used on evaluation
    }
  if (empty($UA2AllGrpsCallingStack) && !$groupaction) {
    $res[$GuestUsergrp] = 1;
    $res[$LoggedInUsergrp] = 1;
  }
  return $res;
}

function IsUserGroupMember($group, $user, $groupaction = false) {
  // Returns true if $user (which means a group if $groupaction is true) is
  // member of group $group.
  $allGroups = getAllGroupsOfUser($user, $groupaction);
  return isset($allGroups[$group]);
}

function IsCurrentUserGroupMember($group) {
  global $UserInstanceVars;
 
  if (! isValidUserString($group, true)) return false; // on bad input silently deny
  if (! $UserInstanceVars->isAuthenticated()) return false;
  return IsUserGroupMember($group, $UserInstanceVars->GetUsername(), false);
}

function IsCurrentUserGroupMemberOld($group) {
  if ($group{0} == '@')
    return IsCurrentUserGroupMember(substr($group, 1));
  return false;
}

//=============================================================
//======= Perm table related stuff ============================

function isConsistentPermTable(&$permtable, $granter) {
  // Expects an array of alleged perm entries, and returns true if
  // they are all make sense, or if the errors are correctable. (Modifies
  // the argument then.)
  global $UA2LevelToAbbr,
         $UA2AbbrToLevel,
         $UA2PageRelatedLevelAbbr;

  if (!isset($UA2AbbrToLevel)) // make it on the fly if not exists
    foreach($UA2LevelToAbbr as $level => $abbr) 
      $UA2AbbrToLevel[$abbr] = $level;

  foreach($permtable as $permentry) {
    if ($permentry == '*') continue;
    if ($permentry{0} == '#') continue;
    if ($permentry{0} == '@') {
      if (!isValidUserString(substr($permentry, 1), true)) return false;
      if (!isPatronOf($granter, substr($permentry, 1), true)) {
        appendToUA2ErrorLog("Warning: '$granter' granting membership to group '".substr($permentry, 1)
                            ."' though not patron of this group.\n");
      }
    } else {
      if ($permentry{0} == '-') $permentry = substr($permentry, 1);
      $entrylevel = substr($permentry, 0, 2);
      if ($entrylevel == 'xx') continue;
      if (!isset($UA2AbbrToLevel[$entrylevel])) return false; // entry level does not exist
      if (in_array($entrylevel, $UA2PageRelatedLevelAbbr)) {
        if ($permentry{2} != '_') return false;
        $entrypage  = substr($permentry, 3);
        if (!isValidPagenamePattern($entrypage)) return false;  // bad pagename pattern
      }
    }
  }

  return true;
}

//============================================================
//======= Needed by userauth2-admintool.php and -profile.php

function mayCurrAdminActOnPermHolder($action, $admin_action, $tool_username, $groupaction = false) {
  // This is one of the main places where the logic concerning the _granting policy_
  // is concentrated. Here admin actions get mapped to (permtable checkable) permission levels.
  // Expects an existing user $tool_username, unless on create action (where NULL or the new perm holder
  // name is expected).
  global $UserInstanceVars,
         $GuestUsergrp,
         $LoggedInUsergrp;

  $curr_admin = $UserInstanceVars->GetUsername();
  if (strlen($curr_admin) == 0) return false; // see #301 also, and #301 in docu

  // action is the pmwiki action as communicated via ?action=
  // admin_action is the UA action as communicated via &aa=

  // all of these permission queries are independent of a possible page concerned,
  // thus first argument in hasCurr..() is ''.
  if ($action == 'profile') {
    // the user himself or all his patrons may change his profile if they are entitled:
    if (!isEqualOrPatronOf($curr_admin, $tool_username, $groupaction)) return false;
    if (!HasCurrentUserPermForAction('', 'profile')) return false;
    return true;
  } else
  if ($action == 'pwchange') {
    // only the user himself can change his password:
    if ($tool_username != $curr_admin) return false;
    // but only if he is entitled to do so:
    if (!HasCurrentUserPermForAction('', 'pwchange')) return false;
    return true;
  } else
  if ($action == 'pwset') {
    // setting passwords only for users strictly below the current admin:
    if (!isPatronOf($curr_admin, $tool_username, $groupaction)) return false;
    if (!HasCurrentUserPermForAction('', 'pwset')) return false;
    return true;
  } else
  if ($action == 'admin') {
    // (a) The creation or deletion of guest user or logged in user group is allowed only to 'admin', while
    //     editing is just permitted according to the usual patron principle.
    // (b) Edit and delete actions only strict patrons are entitled to execute (in particular no "self edit" ;)
    //     But this obviously need not apply for creation.
    // (c) creation and deletion of users/groups are subsumed under one level
    // (d) For any action on ip ranges the 'setipperms' level must have been granted

    if (($tool_username == $GuestUsergrp) ||            // (a)
        ($tool_username == $LoggedInUsergrp)) {                   
      if (($admin_action == 'creategroup') || 
          ($admin_action == 'delgroup')) {
        if ($curr_admin != 'admin')
          return false;
      }
    }

    if ($groupaction && (strlen($tool_username) > 0) && isIpRange($tool_username) &&
        !HasCurrentUserPerm('', 'setipperms')) return false; // (d)

    if ($admin_action == 'createuser') {
      return HasCurrentUserPerm('', 'createuserperms'); // (c)
    } else
    if ($admin_action == 'creategroup') {
      return HasCurrentUserPerm('', 'createuserperms'); // (c)
    } else
    if ($admin_action == 'deluser') {
      if (!isPatronOf($curr_admin, $tool_username, $groupaction)) return false; // (b)
      return HasCurrentUserPerm('', 'createuserperms'); // (c)
    } else
    if ($admin_action == 'delgroup') {
      if (!isPatronOf($curr_admin, $tool_username, $groupaction)) return false; // (b)
      return HasCurrentUserPerm('', 'createuserperms'); // (c)
    } else
    if ($admin_action == 'edituser') {
      if (!isPatronOf($curr_admin, $tool_username, $groupaction)) return false; // (b)
      return HasCurrentUserPerm('', 'edituserperms');
    } else
    if ($admin_action == 'editgroup') {
      if (!isPatronOf($curr_admin, $tool_username, $groupaction)) return false; // (b)(a)
      return HasCurrentUserPerm('', 'edituserperms');
    } else
    if ($admin_action == 'setipperms') {
      if (!isPatronOf($curr_admin, $tool_username, $groupaction)) return false; // (b)
      return HasCurrentUserPerm('', 'setipperms');
    }
  }

  appendToUA2ErrorLog("Warning: Unknown action/admin_action pair: '$action/$admin_action'. Refused.\n");
  return false;
}


