<?php if (!defined('PmWiki')) exit(); 
      if (!constant('USERAUTH_VERSION') >= 2.0) exit();

/*

 UserAuth Password Change Interface

 -- Copyright --

 Copyright 2005 by James McDuffie (ttq3sxl02@sneakemail.com)
               and Thomas Pitschel

 -- License --

 GNU GPL

 -- Description --

 This module allows a user to change his userauth password using a form.
 To get to the form you need to append ?action=pwchange on to the url for
 any page. A form will show up allowing the user to submit a new password.

 The user must have a 'pwchange' ability defined for him in order to be
 able to change his password. 
 The 'pwchange' ability will be preferably set for the user defined by the 
 $LoggedInUsername variable in userauth2.php. This user is a global user
 that defines abilities for all logged in users.
 
 -- Installation Instructions --

 Done automatically by userauth2.php.

 -- History --

 0.1 - Initial version
 0.2 - Added $[internationalization] substitutions
 0.3 - Added default password, if not already set
 2.0 - Adaption to UserAuth 2 by ThP

 -- Configuration --

*/

define(UA2_PWCHANGE, '2.0'); 

/*
// default password
SDV($DefaultPasswords['pwchange'], '');
*/

// Customizable configuration variables
SDV($NoUserGivenFmt,               '$[No user is given for which password could be changed.]');
SDV($OldPasswordMismatchFmt,       '$[The old password did not match the current password.]');
SDV($NewPasswordMismatchFmt,       '$[The two submitted new passwords do not match.]');
SDV($PasswordChangeSuccessFmt,     '$[Password was changed successfully.]');

function HandlePasswordChange($pagename) {
  global $LackProperAbilitiesFmt;
  global $NoUserGivenFmt,
         $OldPasswordMismatchFmt,
         $NewPasswordMismatchFmt, 
         $PasswordChangeSuccessFmt,
         $UserNotExistsFmt,
         $InsecureInputFmt,
         $UserInstanceVars;

  if (isset($_REQUEST['tool_username']))
    $user = $_REQUEST['tool_username'];
  else 
    $user = $UserInstanceVars->GetUsername();

  if ($user == '') {
    PrintEmbeddedPageAndExit( $pagename, $NoUserGivenFmt );
  }

  //appendToUA2ErrorLog("Ok, entered password changing handler for user '$user'.\n");

  // first do some validity checks:
  if (!isSecure($_REQUEST['tool_username']) || 
      !isSecure($_REQUEST['oldpassword']) || 
      !isSecure($_REQUEST['newpassword1']) || 
      !isSecure($_REQUEST['newpassword2'])) {
    PrintEmbeddedPageAndExit( $pagename, $InsecureInputFmt );
  }

  //appendToUA2ErrorLog("Input is clean. \n");

  if (!isValidUserString($user) || !doesUserExist($user, false, false)) {
    PrintEmbeddedPageAndExit( $pagename, $UserNotExistsFmt );
  }

  //appendToUA2ErrorLog("User exists. \n");

  if( !mayCurrAdminActOnPermHolder('pwchange', '', $user, false) &&
      !mayCurrAdminActOnPermHolder('pwset',    '', $user, false) ) {
    PrintEmbeddedPageAndExit( $pagename, $LackProperAbilitiesFmt );
  }

  //appendToUA2ErrorLog("User '$user' may change password. \n");

  // if we are just starting, present the form first:
  if (!isset($_REQUEST['perform_change'])) {
    PrintEmbeddedPageAndExit( $pagename, GetPasswordChangeForm($user) );
  }

  // Otherwise perform change/set:
  // for a change, the old password must be given first and match:
  if ((strlen($_REQUEST['oldpassword']) > 0) ||
      !mayCurrAdminActOnPermHolder('pwset',    '', $user, false) ) {
    // check old password:
    $resFmt = checkPasswordForUser($user, $_REQUEST['oldpassword']);
    if ($resFmt != 'success') {
      PrintEmbeddedPageAndExit( $pagename, $OldPasswordMismatchFmt );
    }
    //appendToUA2ErrorLog("Old password matched.\n");
  }

  // Ensure that the repeated password matches
  if($_REQUEST['newpassword1'] != $_REQUEST['newpassword2']) {
    PrintEmbeddedPageAndExit( $pagename, $NewPasswordMismatchFmt );
  }

  // The user has passed the abilities check already and the 
  // repeated password check so now get the password changed
  $resFmt = setPasswordForUser($user, $_REQUEST['newpassword1']);
  if ($resFmt == 'success') {
    appendToUA2ErrorLog("Password changed for user '$user'.\n");
    PrintEmbeddedPageAndExit( $pagename, $PasswordChangeSuccessFmt );
  } else
    PrintEmbeddedPageAndExit( $pagename, $resFmt );

}

function GetPasswordChangeForm($user) {
  global $pagename;

  $pwchange_form .= "
     <h3>Changing password for user $user</h3>
     <form name='authform' action='{$_SERVER['REQUEST_URI']}' method='post'>
       <input name='tool_username' value='$user' type='hidden'/>
       <input name='perform_change' value='1' type='hidden'/>
       <table class='userauthtable' style='padding:0px; margin:0px'>

       <tr>
           <td>$[Enter old password]<sup>*</sup>:</td>
           <td><input tabindex='1' name='oldpassword' class='userauthinput' type='password'/></td>
       </tr>

       <tr>
           <td>$[Enter new password]:</td>
           <td><input tabindex='2' name='newpassword1' class='userauthinput' type='password'/></td>
       </tr>

       <tr>
           <td>$[Repeat new password]:</td>
           <td><input tabindex='3' name='newpassword2' class='userauthinput' type='password' /></td>
       </tr>
       <tr height=40px>
           <td align=left><input tabindex='4' class='userauthbutton' type='submit' value='$[Change]' /></td>
           <td>&nbsp</td> 
       </tr>
       </table>
       </form>
       <script language='javascript' type='text/javascript'><!--
          document.authform.oldpassword.focus() //--></script>
       <br>(*) Leave empty when initially setting a password.";

  return FmtPageName($pwchange_form, $pagename);
}

