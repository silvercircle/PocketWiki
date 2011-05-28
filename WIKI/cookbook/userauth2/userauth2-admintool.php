<?php if (!defined('PmWiki')) exit(); 
      if (!constant('USERAUTH2_VERSION') >= 2.0) exit();

/*

 UserAuth Admin Tool 2

 -- Copyright --

 Copyright 2005 by James McDuffie (ttq3sxl02@sneakemail.com)
       and 2007 by Thomas Pitschel

 -- License --

 GNU GPL

 -- Description --

 This module adds the ability to use a web based administrative tool to
 edit user and group permissions. The admin tool can only be accessed by
 who have been granted the 'ad' level. For the single create/delete/edit actions
 certain further levels will be needed, see userauth2.php for details.
   
 -- Installation Instructions --

 Done automatically by userauth2.php.

 -- History --

 2.0 - major adaption from userauth-tool.php version 0.5 to make it usable
       with UserAuth 2.

 -- Configuration --

*/

define(UA_ADMINTOOL, '2.0');  // for cmslike.php
define(UA2_ADMINTOOL, '2.0'); 

SDV($PermissionsHelpPage,         'UserAuth2.EditUserQuickReference');

// Also could be defined by userauth-pwchange.php
SDV($InappropriateInputFmt,       '$[Insecure or inappropriate input supplied.]'); 
SDV($InsecureInputFmt,            $InappropriateInputFmt);
SDV($UnknownAdminActionFmt,       '$[Unknown admin operation provided.]');
SDV($PasswordMismatchFmt,         '$[The submitted passwords do not match.]');
SDV($InvalidUserStringFmt,        '$[Invalid user string(s) provided.]');
SDV($InvalidGroupStringFmt,       '$[Invalid group string provided.]');
SDV($InvalidChosenUserStringFmt,  '$[The user name provided is not a valid choice or exists already.]');
SDV($InvalidChosenGroupStringFmt, '$[The group name provided is not a valid choice or exists already.]');
SDV($EditLockPlacedFmt,           '$[Cannot proceed. This record is locked by someone else for editing. Please try again later.]');
SDV($UserAlreadyExistsFmt,        '$[User already exists.]');
SDV($GroupAlreadyExistsFmt,       '$[Group already exists.]');
SDV($MissingInputsFmt,            '$[Some REQUEST parameters were missing.]');
SDV($UserNotExistsFmt,            '$[User does not exist.]');
SDV($GroupNotExistsFmt,           '$[Group does not exist.]');
SDV($ParentMustExistFmt,          '$[The chosen parent must exist and must either equal you or have you as (indirect) parent.]');
SDV($NotAllowedToCreateUserFmt,   '$[Not allowed to create (this type of) users/groups.]');
SDV($NotAllowedToEditUserFmt,     '$[Not allowed to edit permissions of users/groups.]');
SDV($NotAllowedOnThisUserFmt,     '$[Not allowed to edit permissions/profile of this user.]');
SDV($NotAllowedOnThisGroupFmt,    '$[Not allowed to edit the permissions of this group.]');
SDV($ProblemsSavingSettingsFmt,   '$[Settings could not be saved. Please contact the system administrator.]');
SDV($NotAllowedToSetIpPermFmt,    '$[Not allowed to set ip related permission settings.]');

SDV($OnCreateUserFunc, ''); // By default do nothing with the empty perm record and profile of a new user.
  // If desired, set to a function with declaration
  //   foobar(&$permrecord, &$profile, $curr_admin, $created_user, $groupaction = false)
  // which modifies the perm record/profile as you want, e.g. setting perms to personal pages of the new user.
  // The function must return.

// Example 
function UA2onCreateGrantProfilesAccess(&$permrecord, &$profile, $curr_admin, $created_user, $groupaction = false) {
  if (!$groupaction) {
    $UsersGroup = "Profiles";
    $permrecord['perms']['admin'] = array("rd_$UsersGroup.$created_user",
                                          "ed_$UsersGroup.$created_user",
                                          "hi_$UsersGroup.$created_user",
                                          "up_$UsersGroup.$created_user");
  }
}

// Customizable configuration variables
SDV($AdminToolAction,      'admin');
SDV($AdminActionReqKey,    'aa');

// Configuration that your probably do not want to change
$HandleActions[$AdminToolAction] = 'HandleAdminTool'; // this sets the
               // main (and only) entry point into the admin functions
$AdminToolMainUrl = $_SERVER['SCRIPT_URL'] . "?action=" . $AdminToolAction;


$BooleanRegExp    = '[01]{1}';
$IdentifierRegExp = '[a-zA-Z][a-zA-Z0-9\_]*';
$PermholderRegExp = '[a-zA-Z0-9\_\.]+'; // can start with digit (for ip ranges), but need to be non-empty
$EmptyOrIdentRegExp = '(' . $IdentifierRegExp . ')|()';
$PasswordRegExp   = '[a-zA-Z\,\.\;\:\-\_\=\)\(\/\&\%\$\+]*';
$IpRangeRegExp    = '[0-9][0-9\.]*';
$IpRangeListRegExp= '[0-9\.\ \,]*';
$AbltsRegExp      = '[\#,\$,\{,\},\@\_\*\.\-a-zA-Z0-9\ \,]*';

$AllowedReqVarValuesRegExp = array(
  'action'                => $IdentifierRegExp,
  $AdminActionReqKey      => $IdentifierRegExp,
  'tool_perform'          => $BooleanRegExp,
  'tool_cancel'           => $IdentifierRegExp,
  'tool_confirm'          => $IdentifierRegExp,
  'tool_username'         => $PermholderRegExp,
  'tool_descript'         => '.*',
  'tool_parent'           => $IdentifierRegExp,
  'tool_loginfromipsonly' => $IpRangeListRegExp,
  'tool_ablts_arr_(.*?)'  => $AbltsRegExp,
  'tool_newgranter_name'  => $EmptyOrIdentRegExp,
  'tool_newgranter_ablts' => $AbltsRegExp
);
$AllowedReqVarValuesHelp = array(
  'tool_perform'          => $InappropriateInputFmt,
  'tool_confirm'          => $InappropriateInputFmt,
  'tool_username'         => '$[Valid user names contain only letters and digits and start with a letter, or are a ip range.]',
  'tool_descript'         => '$[In descriptions almost all characters are allowed.]',
  'tool_parent'           => '$[Valid user names contain only letters and digits and start with a letter.]',
  'tool_loginfromipsonly' => '$[The IP range list may contain only digits, dots, commata and spaces.]',
  'tool_ablts_arr_(.*?)'  => "\$[The permission table may contain only letters, digits, commas and spaces, and the characters] '@', '-', '_', '.', '*'.",
  'tool_newgranter_name'  => '$[The new granter field must either contain a valid user name or be left empty.]',
  'tool_newgranter_ablts' => "\$[The permission table may contain only letters, digits, commas and spaces, and the characters] '#', '$', '{', '}', '@', '-', '_', '.', '*'."
);

function removeNewlines($str) {
  $search  = array("\r\n", "\n", "\r");
  return str_replace($search, '', $str);
}

function fetchReqVariables(&$resArr) {
  // In lieu of the various possible script attacks we afford ourselves
  // here a separate fetching function for central checking of input
  // vars.
  // If insecure input is detected, then it redirects deirectly to a
  // separate page with a msg indicating this.
  // Otherwise the input values are checked against the allowed patterns
  // in $AllowedReqVarValuesRegExp, and appropriate messages are generated 
  // in $AdminToolMessages (to be displayed later on the web dialogs).
  global $AllowedReqVarValuesRegExp,
         $AllowedReqVarValuesHelp,
         $pagename,
         $InsecureInputFmt, 
         $AdminToolMessages;

  $AdminToolMessages = '';

  foreach($_REQUEST as $key => $value) {
    $value = removeNewlines($value);
    if (isset($AllowedReqVarValuesRegExp[$key])) { // check for exact match of the key
      if (!isSecure($key) || !isSecure($value)) {
        PrintAdminToolPageAndExit( $pagename, $InsecureInputFmt );
      }
      if (preg_match('/^' . $AllowedReqVarValuesRegExp[$key] . '$/', $value)) { // check validity of value
        $resArr[$key] = $value;
      } else {
        $AdminToolMessages .= $AllowedReqVarValuesHelp[$key] . "<br>\n";
      }
    } else { // check for match with reg exps
      foreach($AllowedReqVarValuesRegExp as $allowed_key => $allowedValueRegExp)
        if (preg_match("/^$allowed_key$/", $key, $matches)) {
          if (!isSecure($key) || !isSecure($value)) {
            PrintAdminToolPageAndExit( $pagename, $InsecureInputFmt );
          }
          //appendToUA2ErrorLog("$key matched on $allowed_key with value $value.\n");
          if (preg_match('/^' . $allowedValueRegExp . '$/', $value)) {
            //appendToUA2ErrorLog("Value matched as well.\n");
            $arrname = $matches[0];
            $arrkey  = $matches[1];
            $arrname = substr($arrname, 0, strlen($arrname)-strlen($arrkey)-1);
            $resArr[$arrname][$arrkey] = $value;
          } else {
            $AdminToolMessages .= '$[Error]: ' . $key . $AllowedReqVarValuesHelp[$allowed_key] . "<br>\n";
          }
        }
    }
  }
}

function HandleAdminTool($pagename) {
  // This is the main entry point called by the pmwiki engine.
  // If we find we are in the middle of an admin operation (the
  // AdminActionReqKey is non-empty), then redirect to the various
  // specific handlers. Otherwise go to the action choice page.
  
  global $Action; // pmwiki variable
  global $LackProperAbilitiesFmt, // defined in userauth2.php
         $AdminActionReqKey,
         $UnknownAdminActionFmt,
         $NotAllowedOnThisUserFmt,
         $NotAllowedOnThisGroupFmt,
         $AdminActionsArr,
         $AdminToolMessages,
         $UserInstanceVars,
         $AuthRequiredFmt,
         $reqVars;

  $AdminToolMessages = ''; // Will carry information about invalid user input.
                           // If it stays empty, then allow changes to commit.

  $Action = 'UserAuth Administration';

  $reqVars = array();
  fetchReqVariables($reqVars);
  //foreach($reqVars as $key => $val) appendToUA2ErrorLog("REQ: $key => '$val'\n");
  //foreach($_REQUEST as $key => $val) appendToUA2ErrorLog("REQUEST: $key => '$val'\n");

  $admin_action = $reqVars[$AdminActionReqKey];
  $tool_username = $reqVars['tool_username'];

  //appendToUA2ErrorLog("Admin tool called with admin action '$admin_action'.\n");
 
  if ((strlen($admin_action) != 0) && !in_array($admin_action, $AdminActionsArr))
    PrintAdminToolPageAndExit( $pagename, $UnknownAdminActionFmt );

  if ( !HasCurrentUserPermForAction('', 'admin') ) {
      // This is just a basic entrance check, still independent of the specific admin action
      // or the position of the curr admin and manipulated user to each other. If this fails 
      // however, tell already so:
    if ($UserInstanceVars->isAuthenticated()) {
      PrintEmbeddedPageAndExit( $pagename, $LackProperAbilitiesFmt );
    } else {
      // Set redirection memory:
      $UserInstanceVars->setTargetUrl('http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
      // Send to login page:
      RedirectToLoginPage($AuthRequiredFmt); // won't return
    }
  }

  // Otherwise indulge into "details":
  if ($admin_action == 'adduser') {
    HandleAddEditUser($pagename, $admin_action, 'add', $tool_username, false);
  } elseif ($admin_action == 'edituser') {
    HandleAddEditUser($pagename, $admin_action, 'edit', $tool_username, false);
  } elseif ($admin_action == 'addgroup') {
    HandleAddEditUser($pagename, $admin_action, 'add', $tool_username, true);
  } elseif ($admin_action == 'editgroup') {
    HandleAddEditUser($pagename, $admin_action, 'edit', $tool_username, true);
  } elseif ($admin_action == 'deluser') {
    HandleDelUser($pagename, $admin_action, $tool_username, false);
  } elseif ($admin_action == 'delgroup') {
    HandleDelUser($pagename, $admin_action, $tool_username, true);
//  } elseif ($admin_action == 'report') {
//    HandleUserReport($pagename);
  } 

  PrintAdminToolPageAndExit( $pagename, GetActionChoicePage($pagename), false );
}

function HandleAddEditUser($pagename, $admin_action, $method, $tool_username, $groupaction) {
  // This function has a dual use: if the request var key 'tool_perform'
  // is set to false (0), then we first have to ask the user for the desired perm 
  // changes, so print the perm record form then.
  // If tool_perform is set to true (1), then commit the changes the user just
  // communicates to us (first to its perm record, then to disk) - but
  // not if tool_cancel is true.
  //
  // Have to check against all sorts of innocent or bad behaviour to 
  // prevent someone is accumulating permissions he is not entitled to. 
  // In particular, ["innocent"]
  // - the current admin may only set the parent to values \leq him
  // - the curr admin may edit only perm tables of patrons \leq him.
  // Besides, ["bad"] we have to defend against form manipulation
  // (injecting some colourful request variables etc.)
  global $AdminToolMainUrl,
         $UserInstanceVars,
         $InvalidUserStringFmt,
         $InvalidGroupStringFmt,
         $InvalidChosenUserStringFmt,
         $InvalidChosenGroupStringFmt,
         $EditLockPlacedFmt,
         $UserAlreadyExistsFmt,
         $GroupAlreadyExistsFmt,
         $MissingInputsFmt,
         $UserNotExistsFmt,
         $GroupNotExistsFmt,
         $ParentMustExistFmt,
         $NotAllowedOnThisUserFmt,
         $NotAllowedOnThisGroupFmt,
         $ProblemsSavingSettingsFmt, 
         $NotAllowedToSetIpPermFmt,
         $NotAllowedToCreateUserFmt,
         $OnCreateUserFunc,
         $UA2AllowMultipleGranters,
         $AdminToolMessages, 
         $reqVars;

  $curr_admin    = $UserInstanceVars->GetUsername();

  if (isset($reqVars['tool_cancel'])) {
    $UserInstanceVars->releaseLock($tool_username, $groupaction); // LOCK1 released here
    RedirectToURL($AdminToolMainUrl); // exits
  }
	 
  if($method == 'add') {
    if (($reqVars['tool_perform'] != '1') || (strlen($AdminToolMessages) > 0)) {
      PrintAdminToolPageAndExit( $pagename,
        GetAddEditUserForm($pagename, $method, $tool_username, $groupaction, true),
        false
      );
    }

    if (!isValidChosenPermHolderString($tool_username, $groupaction))
      PrintAdminToolPageAndExit( $pagename, 
        ($groupaction ? $InvalidChosenGroupStringFmt : $InvalidChosenUserStringFmt) 
      );

    if (!mayCurrAdminActOnPermHolder('admin', 'createuser', $tool_username, $groupaction))
      PrintAdminToolPageAndExit( $pagename, $NotAllowedToCreateUserFmt );
   
    $tool_parent = $reqVars['tool_parent'];

    if (!isValidUserString($tool_parent))
      // obvious injection (or perm record inconsisteny, since parent must exist) 
      PrintAdminToolPageAndExit( $pagename,
        ($groupaction ? $InvalidGroupStringFmt : $InvalidUserStringFmt) 
      );

    if (!doesUserExist($tool_parent, false, false) ||
        !isEqualOrPatronOf($curr_admin, $tool_parent, false)) {
      $AdminToolMessages .= $ParentMustExistFmt . "\n";
      PrintAdminToolPageAndExit( $pagename,
        GetAddEditUserForm($pagename, $method, $tool_username, $groupaction, true),
        false
      );
    }

    if (!$UserInstanceVars->acquireLock($tool_username, $groupaction) || // LOCK2
        doesPermHolderExist($tool_username, $groupaction, false))
      PrintAdminToolPageAndExit( $pagename, 
        ($groupaction ? $GroupAlreadyExistsFmt : $UserAlreadyExistsFmt) 
      );
 
    $permrecord = newEmptyPermRecord($tool_parent, $groupaction);
    if (!$groupaction)
      $profile    = newEmptyProfile();

    if (function_exists($OnCreateUserFunc))
      $OnCreateUserFunc($permrecord, $profile, $curr_admin, $tool_username, $groupaction);

    $res = savePermHolderRecord($tool_username, $permrecord, $groupaction);
    if (!$groupaction)
      $res = $res && saveUserProfile($tool_username, $profile);

    $UserInstanceVars->releaseLock($tool_username, $groupaction); // LOCK2 released here

    $UserInstanceVars->writePermUpdateTimestamp($curr_admin);

    if (!$res) 
      PrintAdminToolPageAndExit( $pagename, $ProblemsSavingSettingsFmt );

    appendToUA2ErrorLog("Have created new user '$tool_username'.\n");

    // The creation of a new user (at the bottom of the hierarchy tree) can not have 
    // influenced the permission of the remaining users, no perm cache reset is necessary.

    RedirectToURL($AdminToolMainUrl);
  }

  if ($method == 'edit') {
    // first see whether the lock relevant inputs are available and are valid:
    if (!isValidPermHolderString($tool_username, $groupaction))
      PrintAdminToolPageAndExit( $pagename, 
        ($groupaction ? $InvalidGroupStringFmt : $InvalidUserStringFmt) 
      );

    if (! doesPermHolderExist($tool_username, $groupaction, false)) 
      // injection, since we generate edit links only for existing users
      PrintAdminToolPageAndExit( $pagename, 
        ($groupaction ? $GroupNotExistsFmt : $UserNotExistsFmt) 
      );

    //appendToUA2ErrorLog("Well, basic data was correct for user $tool_username...\n");

    // If we are not expected to commit changes, then print the edit dialog first:
    if (($reqVars['tool_perform'] != '1') || (strlen($AdminToolMessages) > 0)) {
      if (!$UserInstanceVars->acquireLock($tool_username, $groupaction)) // LOCK1
        PrintAdminToolPageAndExit( $pagename, $EditLockPlacedFmt );

      //appendToUA2ErrorLog("AdminToolMessages contains " . strlen($AdminToolMessages) . " chars.\n");

      PrintAdminToolPageAndExit( 
        $pagename, 
        GetAddEditUserForm($pagename, $method, $tool_username, $groupaction, true),
        false 
      );
    } 

    //appendToUA2ErrorLog("Seems we shall save the changes for user $tool_username...\n");

    // first make sure we are allowed to edit the user's perm record:
    if (!mayCurrAdminActOnPermHolder('admin', $admin_action, $tool_username, $groupaction))
      // injection, since we generate edit links only for authorized users
      PrintAdminToolPageAndExit( $pagename, 
        ($groupaction ? $NotAllowedOnThisGroupFmt : $NotAllowedOnThisUserFmt) 
      );
   
    // then check the inputs 

    // for tool_description nothing to do

    $tool_parent = $reqVars['tool_parent'];

    if (!isValidUserString($tool_parent) ||
        !doesUserExist($tool_parent, false, false)) 
      // obvious injection (or perm record inconsisteny, since parent must exist) 
      PrintAdminToolPageAndExit( $pagename, 
        ($groupaction ? $InvalidGroupStringFmt : $InvalidUserStringFmt) 
      );

    if (!isEqualOrPatronOf($curr_admin, $tool_parent, false)) 
      $AdminToolMessages .= "The parent set for this user must be you or one of your dependents.<br>\n";

    if (isset($reqVars['tool_loginfromipsonly']) &&
        ($groupaction ||
         !mayCurrAdminActOnPermHolder('admin', 'setipperms', $tool_username, false)
        )
       )
      // injection, since we generate ip fields (which generate the "tool_loginfromipsonly" key)
      // only for authorized users (which are not groups!) 
      PrintAdminToolPageAndExit( $pagename, $NotAllowedToSetIpPermFmt ); 

    if (!$groupaction && mayCurrAdminActOnPermHolder('admin', 'setipperms', $tool_username, false)) { // corresponds #101
      if (!isset($reqVars['tool_loginfromipsonly'])) 
        PrintAdminToolPageAndExit( $pagename, $MissingInputsFmt );

      $tool_loginFromIpranges_arr = array();
      if (strlen($reqVars['tool_loginfromipsonly']) > 0) {
        $alleged_ipranges_arr = preg_split('/[\s,]+/', $reqVars['tool_loginfromipsonly'], -1, PREG_SPLIT_NO_EMPTY);
        foreach($alleged_ipranges_arr as $alleged_iprange) {
          if (isValidIpRange($alleged_iprange)) // if valid then add
            $tool_loginFromIpranges_arr[] = $alleged_iprange;
          else                                  // otherwise tell the user about problems
            $AdminToolMessages .= "'$alleged_iprange' is not a valid ip range.<br>\n";
        }
      }
    }
  
    if (!isset($reqVars['tool_ablts_arr']))
      PrintAdminToolPageAndExit( $pagename, $MissingInputsFmt );

    // check perm tables for existing granters:
    $tool_perms = array();
    foreach($reqVars['tool_ablts_arr'] as $granter => $abltsstring) {
      //appendToUA2ErrorLog("For $tool_username considering: $granter\n");
      if (!doesUserExist($granter, false, false) || 
          !isPatronOf($granter, $tool_username, $groupaction))
               // || !isEqualOrPatronOf($curr_admin, $granter, false)) is left out since we want to display all tables
               // (The actual check whether a granting is allowed is done at #200.)
        // if perm table is from a nonexistent or unauthorized granter, discard it silently
        // (silently, because these may be the remainders of some old permission settings)
        continue;
       
      if (strlen($abltsstring) > 0)
        $alleged_permtable = preg_split('/[\n\r,]+/', $abltsstring, -1, PREG_SPLIT_NO_EMPTY);
      else 
        $alleged_permtable = array();
      if (!isConsistentPermTable($alleged_permtable, $granter))
        $AdminToolMessages .= "The permission table for granter $granter contains errors. (Probably "
                              . "forgot a comma.)<br>\n";
      else {
        $tool_perms[$granter] = $alleged_permtable;
        //appendToUA2ErrorLog("For $tool_username granting: $granter\n");
      }
    }

    // consider new granter:
    $tool_newgranter_name = $reqVars['tool_newgranter_name'];
    if ($UA2AllowMultipleGranters && (strlen($tool_newgranter_name) > 0)) { // we are supposed to add a new patron
      if (!isValidUserString($tool_newgranter_name, false) ||
          !doesUserExist($tool_newgranter_name, false, false) ||
          !isPatronOf($tool_newgranter_name, $tool_username, $groupaction) ||
          !isEqualOrPatronOf($curr_admin, $tool_newgranter_name, false)) {
        $AdminToolMessages .= "Granters of permissions you set for a user must be patrons of this user "
          . "and must be you or one of your dependents. Therefore, allowed granters are: "
          . implode(', ', getAllPatronsEqualOrBelow($curr_admin, $tool_username, $groupaction)) 
          . ".<br>\n";
      } else {
        // if everything fine, go on with checking the perm table:
        $alleged_permtable = preg_split('/[\n\r,]+/', $reqVars['tool_newgranter_ablts'], -1, PREG_SPLIT_NO_EMPTY);
        if (!isConsistentPermTable($alleged_permtable, $tool_newgranter_name))
          $AdminToolMessages .= "The permission table for granter $tool_newgranter_name contains errors.<br>\n";
        else
          $tool_perms[$tool_newgranter_name] = $alleged_permtable;    
      }
    }

    // If there have been problems, go to edit page again, displaying the messages:
    if (strlen($AdminToolMessages) > 0)
      PrintAdminToolPageAndExit(
        $pagename,
        GetAddEditUserForm($pagename, $method, $tool_username, $groupaction, false),
        false
      );

    // Otherwise commit changes if allowed (save them), and go back to main admin page:
    // transfer and save data:
    $permrecord = loadPermHolderRecord($tool_username, $groupaction);
      // load default values, possibly to be overwritten by the client input if allowed
    
    $permrecord['description'] = $reqVars['tool_descript'];
    $permrecord['parent'] = $tool_parent;
    if (!$groupaction) 
      $permrecord['loginFromIpsOnly'] = $tool_loginFromIpranges_arr; // empty if no restrictions, see #100
    foreach($tool_perms as $granter => $permtable) {
      if (isEqualOrPatronOf($curr_admin, $granter, false)) // we can already assume (see #200) that the granter exists 
                                                           // and is a patron of the user, so check only if 
                                                           // the current admin is patron of or equal to the granter.
      {                                                    // Otherwise just ignore.
        $permrecord['perms'][$granter] = $tool_perms[$granter]; // ... and thus may overwrite the perm table
        if (empty($tool_perms[$granter]) && ($granter !== $tool_parent) ) 
          // obviously the client wants this perm table to be deleted, so do it unless it is the parent's table
          unset($permrecord['perms'][$granter]);
      }
    }

    $res = savePermHolderRecord($tool_username, $permrecord, $groupaction);
    //appendToUA2ErrorLog("Saving perm record on edit for user $tool_username...\n");

    $UserInstanceVars->releaseLock($tool_username, $groupaction); // LOCK1 released here

    $UserInstanceVars->writePermUpdateTimestamp($curr_admin);
   
    // reset perm cache of curr_admin:
    $UserInstanceVars->clearPermCache();

    if (!$res) 
      PrintAdminToolPageAndExit( $pagename, $ProblemsSavingSettingsFmt );
  
    appendToUA2ErrorLog("Have saved perm record of user '$tool_username'.\n");

    RedirectToURL($AdminToolMainUrl);
  }
}

function HandleDelUser($pagename, $admin_action, $tool_username, $groupaction) {
  global $AdminToolMainUrl, 
         $reqVars,
         $GroupNotExistsFmt,
         $UserNotExistsFmt,
         $EditLockPlacedFmt,
         $UserInstanceVars;

  // admin_action == 'deluser' or == 'delgroup'

  if (isset($reqVars['tool_confirm']) && $reqVars['tool_confirm']=="Yes") {
    if (!isValidPermHolderString($tool_username, $groupaction) ||
        !doesPermHolderExist($tool_username, $groupaction, false))
      PrintAdminToolPageAndExit( $pagename, 
        ($groupaction ? $GroupNotExistsFmt : $UserNotExistsFmt) 
      );

    if (!mayCurrAdminActOnPermHolder('admin', $admin_action, $tool_username, $groupaction))
      PrintAdminToolPageAndExit( $pagename,
        ($groupaction ? $NotAllowedOnThisGroupFmt : $NotAllowedOnThisUserFmt)
      );
    
    if (!$UserInstanceVars->acquireLock($tool_username, $groupaction)) // LOCK3 acquired here ...
      PrintAdminToolPageAndExit( $pagename, $EditLockPlacedFmt );

    // if everything is fine, delete user and go to main admin page
    delPermHolder($tool_username, $groupaction);
    appendToUA2ErrorLog("Deleted user '$tool_username'.\n");

    $UserInstanceVars->releaseLock($tool_username, $groupaction); // ... and immediately again released here

    $UserInstanceVars->writePermUpdateTimestamp($curr_admin); // take care change is noticed by others
   
    // reset perm cache of curr_admin:
    $UserInstanceVars->clearPermCache();
    RedirectToURL($AdminToolMainUrl);
  }

  if (isset($reqVars['tool_confirm'])) { // both Yes and No case covered, but only No case important
    RedirectToURL($AdminToolMainUrl);
  }

  // if confirmation not yet given, get it:
  PrintAdminToolPageAndExit( 
    $pagename, 
    GetDelUserConfirmPage($pagename, $tool_username, $groupaction),
    false 
  );
}

function HandleUserReport($pagename) {
  global $AdminToolMainUrl, $UA2UserPermDir;

  $report_html .= "
    <h3>$[User Administration Report]</h3>

    <table style='padding:0px; margin:10px' border='1'>
    <tr><th align='center' colspan='3'>$[Users]</th></tr>
    <tr><th>$[User ID]</th><th>$[Full Name]</th><th>$[Abilities]</th></tr>";

    $userRecords = getAllUserPermRecords();   
    foreach($userRecords as $username => $permrecord) {
      $user_fullname = 'stbi';
      $user_abilities = '';
      foreach($permrecord as $patron => $permtable) {
        $user_abilities .= "Granted by $patron: <br> <br>\n";
        $user_abilities .= implode(", ", $permtable);  
      }
      $report_html .= "
        <tr><td>$username</td><td>$user_fullname</td><td>$user_abilities</td></tr>";
    }

    $report_html .= "    
      </table><br>
      <table style='padding:0px; margin:10px' border='1'>
      <tr><th align='center' colspan='4'>$[User Groups]</th></tr>
      <tr><th>$[Group ID]</th><th>$[Full Name]</th><th>$[Abilities]</th><th>$[Members]</th></tr>";

    $groupRecords = getAllGroupPermRecords();   
    foreach($groupRecords as $groupname => $permrecord) {
      $group_abilities = '';
      foreach($permrecord as $patron => $permtable) {
        $group_abilities .= "Granted by $patron: <br> <br>\n";
        $group_abilities .= implode(", ", $permtable);
      }
      $report_html .= "
        <tr><td>$groupname</td><td> n/a </td><td>$group_abilities</td><td>group_members stbi</td></tr>";
    }
    
    $report_html .= "    
      </table>
      <br>
      <form name='navigation' action='?action=admin' method='post'>
      <input class='userauthbutton' type='submit' value='$[Admin Main]' />
      </form>";

  PrintAdminToolPageAndExit( $pagename, $report_html );
}

function getActionTarget($action, $admin_action = '', $user_concerned = '') {
  global $AdminActionReqKey;

  $target = $_SERVER['SCRIPT_URL'] . "?action=$action";
  if (strlen($admin_action) > 0)
    $target .= "&" . $AdminActionReqKey . "=" . $admin_action;
  if (strlen($user_concerned) > 0)
    $target .= "&tool_username=" . $user_concerned;

  return $target;
}

function getAdminActionTarget($admin_action, $user_concerned = '') {
  global $AdminToolAction; // usually 'admin'
  getActionTarget($AdminToolAction, $admin_action, $user_concerned);
}

function getActionLink($caption, $action, $admin_action = '', $user_concerned = '') {
  $target = getActionTarget($action, $admin_action, $user_concerned);
  $res = "<a class='wikilink' href='$target'>$caption</a>";

  return $res;
}

function getAdminActionLink($caption, $admin_action, $user_concerned = '') {
  global $AdminToolAction; // usually 'admin'
  return getActionLink($caption, $AdminToolAction, $admin_action, $user_concerned);
}

function getPerUserActionChoiceHtml($user, $parent, $groupaction = false) {
  if ($groupaction) $actionsuffix = 'group';
  else              $actionsuffix = 'user';

  if (($user != 'admin') && (strlen($parent) > 0))
    $parentStr = '(' . (doesUserExist($parent, false, false) ? $parent : "<del>$parent</del>") . ')';
  else 
    $parentStr = '';

  $choice_html = "\n<tr>";
  $choice_html .=   "<td>$user $parentStr</td>";

  if (mayCurrAdminActOnPermHolder('admin', 'edit' . $actionsuffix, $user, $groupaction))
    $choice_html .= "<td>" . getAdminActionLink('$[Edit]', 'edit' . $actionsuffix, $user) . "</td>\n";
  else 
    $choice_html .= "<td></td>\n";

  if (mayCurrAdminActOnPermHolder('admin', 'del' . $actionsuffix, $user, $groupaction))
    $choice_html .= "<td>" . getAdminActionLink('$[Delete]', 'del' . $actionsuffix, $user) . "</td>\n";
  else
    $choice_html .= "<td></td>\n";

  if (!$groupaction) {
    if (mayCurrAdminActOnPermHolder('profile', '', $user, false)) 
      $choice_html .= "<td>" . getActionLink('$[Profile]', 'profile', '', $user) . "</td>\n";
    else 
      $choice_html .= "<td></td>\n";
    if (mayCurrAdminActOnPermHolder('pwchange', '', $user, false) ||
        mayCurrAdminActOnPermHolder('pwset',    '', $user, false)) 
      $choice_html .= "<td>" . getActionLink('$[Password]', 'pwchange', '', $user) . "</td>\n";
    else 
      $choice_html .= "<td></td>\n";
  }

  $choice_html .= "</tr>\n";

  return $choice_html;
}

function getCreateUserHtml($groupaction = false) {
  $colspan = ($groupaction ? 3 : 5); // users have two additional columns for profile and passwd
  $caption = ($groupaction ? 'Group' : 'User');
  return "\n<tr><td colspan='$colspan' align='center'>" 
         . ($groupaction ? getAdminActionLink('$[Add group]', 'addgroup') : getAdminActionLink('$[Add user]', 'adduser'))
         . "</tr>\n";
}

function getShowAdminReportButtonHtml() {
  return "
    <form name='navigation' action='" . getAdminActionTarget('report') . "' method='post'>
    <input class='userauthbutton' type='submit' value='$[Show Admin Report]' />
    </form>";
}

function GetActionChoicePage($pagename) {
  // This prints us the table from which to choose what to do with the listed
  // users and groups. We make sure that the curr admin sees only users that
  // he can change the permissions of, namely his (indirect) children.
  global $GuestUsergrp, $LoggedInUsergrp, $UserInstanceVars;

  $curr_admin = $UserInstanceVars->GetUsername();

  $choice_html = "
    <h3>$[Admin Main]</h3>

    <table style='padding:0px; margin:0px' border=0><tr><td valign='top'>
    <table border=1><tr><th colspan='5' align='center'>$[Users]</th></tr>";

  $userlist = getAllUsers(false, true, true, false); // proper users only
  $userlist = retainUsersStrictlyBelow($curr_admin, $userlist, false, true); // retain orphans
  $userlist = sortUserList($userlist, $curr_admin);
  $choice_html .= getPerUserActionChoiceHtml($curr_admin, '', false);
  foreach($userlist as $user => $parent)
    $choice_html .= getPerUserActionChoiceHtml($user, $parent, false);

  if (mayCurrAdminActOnPermHolder('admin', 'createuser', NULL, NULL))
    $choice_html .= getCreateUserHtml(false);

  $choice_html .=
    "</table> <br>
     </td><td valign='top'>
     <table border=1><tr><th colspan='3' align='center'>$[Groups]</th>";

  $grouplist = getAllUsers(true, true, true, false); // groups and ipranges, including the special groups
  $grouplist = retainUsersStrictlyBelow($curr_admin, $grouplist, true, true); // retain orphans
  $grouplist = sortUserList($grouplist, $curr_admin);
  foreach($grouplist as $group => $parent) 
    $choice_html .= getPerUserActionChoiceHtml($group, $parent, true);

  if (mayCurrAdminActOnPermHolder('admin', 'creategroup', NULL, NULL))
    $choice_html .= getCreateUserHtml(true);

  $choice_html .= 
    "</table>
     </td></tr></table>
    
    <br>";

  $choice_html .= getShowAdminReportButtonHtml();

  return FmtPageName($choice_html, $pagename);
}

function getNonEditableAbilitiesHtml($patron, $existing_abilities) {
  $existing_abilities = str_replace('$', '&#36;', $existing_abilities); // wiki-escape the '{$AuthId}' string
  return "<tr>
            <td valign='top'>$[Permissions granted by]<br> $patron: (readonly)</td>
            <td>
<textarea tabindex='2' name='tool_ablts_arr_$patron' class='userauthinput' rows='10' cols='50' readonly>$existing_abilities
</textarea>
            </td>
          </tr>"; // readonly textarea
}

function getEditableAbilitiesHtml($patron, $existing_abilities) {
  $existing_abilities = str_replace('$', '&#36;', $existing_abilities);
  return "<tr>
            <td valign='top'>$[Permissions granted by]<br> $patron:</td>
            <td>
<textarea tabindex='2' name='tool_ablts_arr_$patron' class='userauthinput' rows='10' cols='50'>$existing_abilities
</textarea>
            </td>
          </tr>";
}

function getAbilitiesHtmlForNewGranter($new_granter_name, $new_granter_ablts) {
  $new_granter_ablts = str_replace('$', '&#36;', $new_granter_ablts);
  return "<tr>
            <td valign='top'>
<input tabindex='2' name='tool_newgranter_name' value='$new_granter_name' class='userauthinput'/><br>$[(new granter)]
            </td>
            <td>
<textarea tabindex='2' name='tool_newgranter_ablts' class='userauthinput' rows='10' cols='50'>$new_granter_ablts
</textarea>
            </td>
          </tr>\n";
}

function getUsernameCaptionHtml($user, $groupaction = false) {
  $res = "
    <tr>";
  if($groupaction) $res .= "<td>$[Groupname]:</td>";
  else             $res .= "<td>$[Username]:</td>";

  $res .= "
      <td><input name='tool_username' value='$user' type='hidden'/> 
        $user
      </td>
    </tr>\n";
  return $res;
}

function getLabeledTextFieldHtml($caption, $var_name, $default_val = '', $size = 20, $maxlength=20) {
  return "<tr><td>$caption: </td><td><input tabindex='1' name='$var_name' value='$default_val' size=$size maxlength=$maxlength class='userauthinput' type='text'/> </td></tr>\n";
}

function getGroupMembershipInfoHtml($groupsOfUser) {
  if (!$groupsOfUser) return "<tr><td>Member in groups:</td><td></td></tr>\n";
  return "<tr><td>Member in groups:</td><td>" . implode(", ", array_keys($groupsOfUser))
         . "&nbsp;</td></tr>\n";
}

function getHtmlVertSpaceInTable() {
//  return "<tr><td>&nbsp;</td><td>&nbsp;</td></tr>\n";
  return "<tr><td></td><td></td></tr>\n";
}

function GetAddEditUserForm($pagename, $method, $existing_user, $groupaction = false, $start = true) {
  // This prints us the permission edit dialog. We print all permissions
  // of the considered user, but make the ones belonging to patrons \leq than
  // the curr admin editable (the others can be viewed only).
  global $UserInstanceVars,
         $PermissionsHelpPage,
         $UnknownAdminActionFmt,
         $NotAllowedToCreateUserFmt,
         $NotAllowedToEditUserFmt,
         $UA2AllowMultipleGranters,
         $AdminToolMessages,
         $reqVars;

  $curr_admin = $UserInstanceVars->GetUsername();

  // note we can assume that $existing_user is a valid user string,
  // and all values in reqVars are secure (though maybe still containing not allowed characters)

  $usrgrp = ($groupaction ? 'group' : 'user');
  $UsrGrp = ucfirst($usrgrp);

  $method_text = ucfirst($method);
  $commit_link_caption = $method_text;
  if ($commit_link_caption == "Edit")
    $commit_link_caption = "Save";

  $action_url = getAdminActionTarget($method . $usrgrp);

  $add_edit_form = '';
  $add_edit_form .= "
    <form name='addeditform' action='$action_url' method='post'>
       <table style='padding:0px; margin:10px'>\n\n";
  $add_edit_form .= "<h3>$method_text $UsrGrp</h3>";

  if (strlen($AdminToolMessages) > 0) {
    $add_edit_form .= "<tr><td colspan=2>$AdminToolMessages </td></tr><tr><td colspan=2>&nbsp;</td></tr>\n";
    $AdminToolMessages = '';
  }

  if ($method == 'add') {
    if (!HasCurrentUserPerm('', 'createuserperms')) 
      PrintAdminToolPageAndExit( $pagename, $NotAllowedToCreateUserFmt );

    // Print form asking for new user name:
    if ($reqVars['tool_parent'] == '') $reqVars['tool_parent'] = $curr_admin;
    $add_edit_form .= getLabeledTextFieldHtml('$[Username]', 'tool_username', $reqVars['tool_username']);
    $add_edit_form .= getLabeledTextFieldHtml('$[Parent]', 'tool_parent', $reqVars['tool_parent']);
  }

  if ($method == 'edit') {
    if (!HasCurrentUserPerm('', 'edituserperms')) 
      PrintAdminToolPageAndExit( $pagename, $NotAllowedToEditUserFmt );

    if ($start) {
      // if new edit session then load the data from file:
      $permrecord = loadPermHolderRecord($existing_user, $groupaction);
        // file not found check here?
      makeConsistent($permrecord, $groupaction);
        // break if not consistent here?
      // assemble reqVars array:
      $reqVars['tool_username'] = $existing_user;
      $reqVars['tool_descript'] = $permrecord['description'];
      $reqVars['tool_parent']   = $permrecord['parent'];
      if (!isset($permrecord['loginFromIpsOnly'])) 
        $permrecord['loginFromIpsOnly'] = array(); // for some reason the same action in makeConsistent() 
	                                           // does not yield the desired result, so it is repeated here
      if (!$groupaction) 
        $reqVars['tool_loginfromipsonly'] = join(", ", $permrecord['loginFromIpsOnly']);
      foreach($permrecord['perms'] as $granter => $permtable)
        $reqVars['tool_ablts_arr'][$granter] = join(",\n", $permtable);
    }

    $abilities_html = '';
    if (!isset($reqVars['tool_ablts_arr'][$reqVars['tool_parent']])) 
      $reqVars['tool_ablts_arr'][$reqVars['tool_parent']] = '';
    foreach($reqVars['tool_ablts_arr'] as $granter => $permtable) {
      $ablts_string = join(",\n", preg_split('/[\n\r,]+/', $reqVars['tool_ablts_arr'][$granter], -1, PREG_SPLIT_NO_EMPTY)); 
        // this construction reintroduces the new lines again that were removed at fetching
      if (isEqualOrPatronOf($curr_admin, $granter, false)) 
        $abilities_html .= getEditableAbilitiesHtml($granter, $ablts_string);
      else
        $abilities_html .= getNonEditableAbilitiesHtml($granter, $ablts_string);
    }
    // dont forget a new granter if present, though it may not exist or has a inconsistent perm table
    if ($UA2AllowMultipleGranters) {
      $tool_newgranter_name = $reqVars['tool_newgranter_name'];
      $tool_newgranter_ablts = $reqVars['tool_newgranter_ablts'];
      if (strlen($reqVars['tool_newgranter_name']) > 0) { 
        if (!isValidUserString($tool_newgranter_name)) {
          // make at least sure that it is a valid string, 
          // existence and patron check happens in makeConsistent() anyway. 
          $tool_newgranter_name = '';
          $tool_newgranter_ablts = '';
        }
       } else {
         $tool_newgranter_ablts = '';
      }
      $abilities_html .= getAbilitiesHtmlForNewGranter($tool_newgranter_name, $tool_newgranter_ablts);
    }

    $add_edit_form .= getUsernameCaptionHtml($existing_user, $groupaction);
    $add_edit_form .= getHtmlVertSpaceInTable();

    $add_edit_form .= getLabeledTextFieldHtml('$[Description]', 'tool_descript', $reqVars['tool_descript'], 50, 200);
    $add_edit_form .= getHtmlVertSpaceInTable();

    $add_edit_form .= getLabeledTextFieldHtml('$[Parent]', 'tool_parent', $reqVars['tool_parent']);
    $add_edit_form .= getHtmlVertSpaceInTable();

    $groupsOfUser = getAllGroupsOfUser($existing_user, $groupaction);
    $add_edit_form .= getGroupMembershipInfoHtml($groupsOfUser);
    $add_edit_form .= getHtmlVertSpaceInTable();

    if (!$groupaction && mayCurrAdminActOnPermHolder('admin', 'setipperms', $existing_user, false)) { // corresponds #101
      $add_edit_form .= getLabeledTextFieldHtml('$[IPs permitted at login]', 
                                                'tool_loginfromipsonly', 
                                                $reqVars['tool_loginfromipsonly'], 50, 200);
      $add_edit_form .= getHtmlVertSpaceInTable();
    }

    $add_edit_form .= $abilities_html;
    $add_edit_form .= getHtmlVertSpaceInTable();
  }

  if (($method=='add') || ($method=='edit')) {   
    $add_edit_form .= "
       <tr height=40px>
           <td align=left><input tabindex='2' class='userauthbutton' type='submit' value='$[".$commit_link_caption."]' /></td>
           <td align=left><input tabindex='2' class='userauthbutton' type='submit' name='tool_cancel' value='$[Cancel]' /></td>
           <td>&nbsp</td> 
       </tr>\n";
    $add_edit_form .= "
       </table>
       <input name='tool_perform' value='1' type='hidden'/>
    </form>
    <script language='javascript' type='text/javascript'><!--
       document.addeditform.elements[0].focus() //--></script>";
    $add_edit_form .= "\n<p><br><p>\n";
    $add_edit_form = FmtPageName($add_edit_form, $pagename);

    if ($method == 'edit')
      $helppn = $PermissionsHelpPage;

    if (PageExists($helppn)) {
      $helppage = RetrieveAuthPage($helppn, 'read', false, READPAGE_CURRENT);
      if ($helppage['text'])
        $add_edit_form .= MarkupToHTML($helppn, $helppage['text']);
    }

    return $add_edit_form;
  }

  // if none of the above cases matched, then it was an unknown action:    
  PrintAdminToolPageAndExit( $pagename, $UnknownAdminActionFmt );

/* 
  if($groupaction) {
    if(isset($existing_user)) {	  
      $user_list = implode(", ", $UserInfoObj->GetUsersInGroup($existing_user));
      $add_edit_form .= "
        <br>
    	$[Users in this User Group]:<br>
    	$user_list<br>";
    }
    $add_edit_form .= "
      <br>
      $AbilitiesHelpFmt";
  }
  else {
      $group_list = implode(", ", $UserInfoObj->GetAllUserGroups());
      $add_edit_form .= "
        <br>
    	$[Defined User Groups]:<br>
    	$group_list
    	<br><br>
        $AbilitiesHelpFmt
        <br>
        $GroupAbilitiesHelpFmt";
  }
*/

}


function GetDelUserConfirmPage($pagename, $username, $groupaction) {
  $action_url = ($groupaction ? getAdminActionTarget('delgroup') : getAdminActionTarget('deluser'));

  if ($groupaction) $confirm_form .= "<h3>$[Delete group]</h3>";
  else              $confirm_form .= "<h3>$[Delete user]</h3>";

  $confirm_form .= "
    $[Really delete] '$username'? <br> &nbsp; <br>
    <form name='del_confirm' action='$action_url' method='post'>
      <input name='tool_username' value='$username' type='hidden'/>
      <input class='userauthbutton' name='tool_confirm' type='submit' value='Yes' />
      <input class='userauthbutton' name='tool_confirm' type='submit' value='No' />
    </form>";
  
  return FmtPageName($confirm_form, $pagename);
}

function PrintAdminToolPageAndExit($pagename, $html, $mainpage_link = true) {
  global $AdminToolMainUrl,
         $AdminToolMessages;

  if (strlen($AdminToolMessages) > 0) {
    $AdminToolMessages .= "\n<p>\n\n";
  }

  $html = 
    FmtPageName("<h2>$[UserAuth II Administration]</h2>", $pagename) . 
    $AdminToolMessages .
    $html;

  if ($mainpage_link)
    $html .= "\n<br><br><a href='$AdminToolMainUrl'>Back</a> to UserAuth main page. <p>\n\n";

  $AdminToolMessages = '';

  PrintEmbeddedPageAndExit($pagename, $html);
}

