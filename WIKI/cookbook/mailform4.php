<?php if (!defined('PmWiki')) exit();

/*
  Mailform4 (alias Mailform2²) by Markus Konrad <mako@mako-home.de>
  PmWiki module for mail forms.
  More information at http://www.pmwiki.org/wiki/Cookbook/Mailform4
  
  Based on Mailform2, created by Joachim Durchholz (jo@durchholz.org).
  See http://www.pmwiki.org/wiki/Cookbook/Mailform2
  
  This code is licensed under the
  GNU General Public License version 2.0
  as distributed with PmWiki.
*/

$RecipeInfo['Mailform4']['Version'] = '2010-07-22';

Markup('mailform4-show-msgs', 'directives', "/\\(:mailform4-show-msgs:\\)/e", "Mailform4ShowMsgs()");

/******************************************************************************/
/* DEFAULT VARIABLE VALUES                                                    */
/******************************************************************************/

SDVA(
  $Mailform4,
  array(
    'recipient' => '',
    'subject' => 'contact form message',
    'mailform4_url' => 'http://www.pmwiki.org/wiki/Cookbook/Mailform4',
    'website' => $ScriptUrl,
    'ip' => $_SERVER['REMOTE_ADDR'],
    'datetime' => date('Y-m-d H:i:s')
  )
);

SDVA($Mailform4ValidFields,
  array(
    'sender' => array('email', 3, 120),
    'text' => array('string', 3, null)
  )
);

SDV($Mailform4Disabled, 0);

SDV($Mailform4MailTemplate, 'Mailform4/MailTemplate');
SDV($Mailform4FormTemplate, 'Mailform4/FormTemplate');

SDV($HandleActions['mailform4'], 'Mailform4Handler');


/******************************************************************************/
/* MAIN CODE                                                                  */
/******************************************************************************/

if ($Lang == 'de')
  XLPage('de','Site.XLPage-Mailform4-de');


if (!isset($_SESSION['Mailform4']))
{
  $_SESSION['Mailform4'] = array('errors' => null, 'info' => null);
}

$Mailform4Msgs = &$_SESSION['Mailform4'];


Mailform4SetDefaultPVs();

function Mailform4SetDefaultPVs()
{
  global $Mailform4, $FmtPV;

  foreach ($Mailform4 as $field => $val)
  {
    $FmtPV['$Mailform4' . ucfirst($field)] = '"' . $val . '"';
  }
}

function Mailform4Sanitise($str)
{
  return preg_replace ('[\\0-\\37]', '', $str);
}

function Mailform4ValueIsEmail($field, $v, $min, $max)
{
  global $Mailform4Msgs;
  
  $ok = Mailform4ValueIsString($field, $v, $min, $max);
	$ok = $ok && preg_match("/^[a-zA-Z0-9]{1}[_a-zA-Z0-9-]*(\.[_a-zA-Z0-9-]+)*@([a-zA-Z0-9-\.]+)*([a-zA-Z0-9-]{2,})\.[a-zA-Z]{2,}$/", $v);
	
	if (!$ok)
	  $Mailform4Msgs['error'][] = ucfirst($field) . ': ' . XL('Please enter a valid email address.');
	
	return $ok;
}

function Mailform4ValueIsNumber($field, $v, $min, $max)
{
  global $Mailform4Msgs;
  
  $ok = is_numeric($v);
  
  if ($min !== null)
    $ok = $ok && ($v >= $min);
  if ($max !== null)
    $ok = $ok && ($v <= $max);

	if (!$ok)
	  $Mailform4Msgs['error'][] = ucfirst($field) . ': ' . sprintf(XL('Please enter a valid number between %d and %d.'), $min, $max);
	
	return $ok;
}

function Mailform4ValueIsString($field, $v, $min, $max)
{
  global $Mailform4Msgs;
  
  $len = strlen($v);
  if ($min !== null)
    $ok = ($len >= $min);
  if ($max !== null)
    $ok = $ok && ($len <= $max);
  
	if (!$ok)
	  $Mailform4Msgs['error'][] = ucfirst($field) . ': ' . sprintf(XL('Please enter a string that is between %d and %d characters long.'), $min, $max);

  return $ok;
}

function Mailform4Handler($pagename)
{
  global $Mailform4, $Mailform4ValidFields;
  global $Mailform4Disabled, $Mailform4MailTemplate;
  global $Mailform4Msgs;
  global $EnablePostCaptchaRequired, $ScriptUrl, $FmtPV;
  
  if ($Mailform4Disabled)
  {
    $Mailform4Msgs['error'][] = XL('Sending mails has been disabled.');
  }
  else if (isset($_GET['success']) && $_GET['success'] == "1")
  {
    $Mailform4Msgs['info'][] = XL('Thank you! Your mail has been successfully sent!');
  }
  else
  {
    $error = false;
    
    $fields = array_merge($Mailform4, $Mailform4ValidFields);
    $submittedValues = array();
    foreach ($fields as $field => $val)
    {
      if (isset($Mailform4ValidFields[$field]))
      {
        if (isset($_POST['mailform4'][$field]) && strlen(trim($_POST['mailform4'][$field])) > 0)
        {
          $submitted = trim(Mailform4Sanitise($_POST['mailform4'][$field]));
        }
        else
        {
          if ($val[1] != null)
          {
            $Mailform4Msgs['error'][] = ucfirst($field) . ': ' . XL('Please enter a value.');
            $error = true;
          }
          
          $submitted = '';
        }
        
        if ($submitted != '')
        {
          $chkFunc = 'Mailform4ValueIs' . ucfirst($val[0]);
          $error = $error || !($chkFunc($field, $submitted, $val[1], $val[2]));
        }
        
        $val = $submitted;
      }
      
      $submittedValues[$field] = $val;
      $FmtPV['$Mailform4' . ucfirst($field)] = '"' . $val . '"';
    }
    
    
    if ($EnablePostCaptchaRequired && !IsCaptcha())
    {
      $Mailform4Msgs['error'][] = XL('Invalid CAPTCHA code. Please try again.');
      $error = true;
    }
    
   
    if (!$error)
    {
      $msgTpl = ReadPage('Mailform4/MailTemplate');
      $msgTpl = $msgTpl['text'];
      foreach ($submittedValues as $field => $val)
      {
        $msgTpl = str_replace('%' . strtoupper($field) . '%', $val, $msgTpl);
      }
      
      if (!mail(
          $Mailform4['recipient'],
          $Mailform4['subject'],
          $msgTpl,
          'From: ' . $FmtPV['$Mailform4Sender']))
      {
        $Mailform4Msgs['error'][] = XL('Failure sending email.');
      }
      else
      {
        Redirect($pagename, '$PageUrl?action=mailform4&success=1');
        exit;
      }
    }
  }
  
  $GLOBALS['action'] = 'browse';
  HandleDispatch($pagename, $GLOBALS['action']);
}

/**
 * Display error- or success-messages that have been saved in the session
 */
function Mailform4ShowMsgs()
{
  global $Mailform4Msgs;
  
	$out = '';
	  
	if (isset($Mailform4Msgs['error']))
	{
  	//var_dump($Mailform4Msgs['error']);exit;
	  $out .= Mailform4ShowMsgType('error', $Mailform4Msgs['error']);
	}
	  
	if (isset($Mailform4Msgs['info']))
	{
	  //var_dump($Mailform4Msgs['info']);exit;
	  $out .= Mailform4ShowMsgType('info', $Mailform4Msgs['info']);
	}
	  
	return Keep($out);
}


function Mailform4ShowMsgType($type, &$msgs)
{
  $out = '';
	  
  if (is_array($msgs) && count($msgs) > 0)
  {
    $out .= '<div class="' . $type . '_msg">';
	    
    foreach ($msgs as $m)
    {
      $out .= $m . '<br />';
    }
	    
    $out .= '</div>';
  }
	  
  $msgs = null;
  
  return $out;
}
