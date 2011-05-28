<?php if (!defined('PmWiki')) exit();

/*
  Part of UserAuth2. Copyright 2007 Thomas Pitschel, pmwiki (at) sigproc (dot) de

  These are some helper functions for preventing brute force attacks on the login.
  Two functions, ua2MayAttemptToLogin and ua2LogFailedLoginAttempt, may be employed
  by the login system. At the start one would call ua2MayAttemptToLogin to check whether
  a login is allowed, then, on finding that the login fails, one registers this login
  attempt with ua2LogFailedLoginAttempt.

  Logins will be allowed if the number of failed logins from this client ip (or using
  the specified username) does not exceed a certain limit ($FailedLoginsLimitIp/
  $FailedLoginsLimitUser) within a certain time frame ($FailedLoginsTimeframeIp/
  $FailedLoginsTimeframeUser).

*/

// Since we include this file within a function, no "global" variables can here be defined.
// Look/put in userauth2.php instead.

function ua2MayAttemptToLogin($username, $ip, $now) {
  // Returns true if login attempt might proceed, false if limit is reached

  global $FailedLoginsLogDir, 
         $FailedLoginsLimitUser,
         $FailedLoginsLimitIp,
         $FailedLoginsTimeframeUser,
         $FailedLoginsTimeframeIp;

  if (!file_exists($FailedLoginsLogDir)) {
    mkdirp($FailedLoginsLogDir);
    copy('local/.htaccess', $FailedLoginsLogDir.'/.htaccess');
  }

  if (!ua2MayAttemptToLogin_wrk($FailedLoginsLogDir.'/_'.sha1($username), $now, 
                                $FailedLoginsLimitUser, $FailedLoginsTimeframeUser)) return false;
  if (!ua2MayAttemptToLogin_wrk($FailedLoginsLogDir.'/_'.sha1($ip), $now, 
                                $FailedLoginsLimitIp, $FailedLoginsTimeframeIp)) return false;
  return true;
}

function ua2MayAttemptToLogin_wrk($filename, $now, $limit, $duration) {
  // Returns true if attempt yet accepted, false if limit is reached.
  if (file_exists($filename))
    $line = trim(file_get_contents($filename));
  else
    return true;
  $arr = explode(" ", $line);
  $i = 1;
  $mayPass = true;
  foreach($arr as $stamp) {
    if ($i >= $limit) { $mayPass = false; break; }
    if ($stamp < $now - $duration) break;
    $i++;
  }
  return $mayPass;
}

function ua2LogFailedLoginAttempt($username, $ip, $now) {
  global $FailedLoginsLogDir, $FailedLoginsLimitUser, $FailedLoginsLimitIp;

  ua2LogFailedLoginAttempt_wrk($FailedLoginsLogDir.'/_'.sha1($username), $now, $FailedLoginsLimitUser);
  ua2LogFailedLoginAttempt_wrk($FailedLoginsLogDir.'/_'.sha1($ip      ), $now, $FailedLoginsLimitIp);
}

function ua2LogFailedLoginAttempt_wrk($filename, $now, $limit) {
  if (file_exists($filename))
    $line = trim(file_get_contents($filename));
  else
    $line = '';
  $line = substr($line, 0, $limit * 12 + 12);
  $fp = @fopen($filename, "w");
  if ($fp) { fwrite($fp, "$now $line"); fclose($fp); }
}


