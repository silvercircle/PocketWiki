<?php
  if (!defined('PmWiki')) exit();
  if ((!constant('USERAUTH2_VERSION') >= 2.0) ||
      (defined('USER_AUTH_VERSION') && !constant('USER_AUTH_VERSION') >= 0.5)) 
    exit();


  /*

   User Session Variables Container Class, default UserInstanceVars class

   -- Copyright --
   Copyright 2004, by James McDuffie (ttq3sxl02@sneakemail.com)
         and 2007, by Thomas Pitschel

   -- License --
   GNU GPL

   -- Description --

   This class encapsulate handling of user variables that should be stored
   between successive calls to the wiki. Information such as the username
   and password for the current user are returned to UserAuth.

   This class implements the UserVariables class interface in by storing
   user information in the $_SESSION array.

   -- Change log --

   v0.1 - Initial version
   v0.2 - Dan Weber - Added cookie support
   v0.3 - Thomas Pitschel - Modified to make it suitable for UserAuth2.

  */

define(USERSESSIONVARS, '0.3');

if (!function_exists(file_put_contents)) {
  function file_put_contents($fn, $data) {
    $fp = @fopen($fn, 'w');
    if ($fp) {
      fputs($fp, $data, strlen($data)); 
      fclose($fp);
      return true;
    }
    return false;
  }
}

if (!function_exists(get_rand_session_id)) {
  function get_rand_session_id() {
    // generates a 256 bit hash string, from a good hardware source or from the Mersenne twister
    // if that is not available.
    // (adapted from post by Marc Seecof, http://de2.php.net/manual/en/function.mt-rand.php#83655 )

    $pr_bits = '';

    // Unix/Linux platform?
    // (for this source to work, /dev/urandom must be mentioned in the open_basedir directive)
    $fp = @fopen('/dev/urandom','rb');
    if ($fp !== FALSE) {
        $pr_bits .= @fread($fp,32);
        @fclose($fp);
    }

    // MS-Windows platform?
    if (@class_exists('COM')) {
        // http://msdn.microsoft.com/en-us/library/aa388176(VS.85).aspx
            $CAPI_Util = new COM('CAPICOM.Utilities.1');
            $pr_bits .= @$CAPI_Util->GetRandom(32,0);

            // if we ask for binary data PHP munges it, so we
            // request base64 return value.  We decode the value before hashing it below:
            if ($pr_bits) { $pr_bits = base64_decode($pr_bits); }
    }

    if (strlen($pr_bits) < 32) {
        //Abort("UserAuth2: Error: Random number generator using /dev/urandom is not working.");

        // better than abort, rather fall back to the supposedly worse generator:
        $x  = substr(str_pad(mt_rand(), 10, '0'), 0, 10); // length 10 chars
        $x .= substr(str_pad(mt_rand(), 10, '0'), 0, 10);
        $x .= substr(str_pad(mt_rand(), 10, '0'), 0, 10);
        return md5($x);

    }

    return md5(substr($pr_bits, 0, 16)).md5(substr($pr_bits, 16, 16));
  }
}

/*
//thp_session_register_destroy_callback($THP_SESSION, 'releaseAllLocks_callback');

function releaseAllLocks_callback(&$THP_SESSIONDATA) {
  if (isset($THP_SESSIONDATA['editlocks'])) {
    foreach($THP_SESSIONDATA['editlocks'] as $fn => $id) {
      if (file_exists($fn) && (file_get_contents($fn) == $THP_SESSIONDATA['editlocks'][$fn]))
        @unlink($fn);
    }
    unset($THP_SESSIONDATA['editlocks']);
    appendToUA2ErrorLog("Whole bunch of edit locks deleted as part of destruction callback.\n");
  }
  return true;
}
*/


Class UserSessionVars {

  // This class encapsulates all session related transactions. Since the
  // "authenticated" property is held in this object, access on the _SESSION
  // variable on ways other than over this class are discouraged.
  //
  // The "authenticated" property is propagated over the session variables,
  // so to get "complete" security make sure your session is not hijacked by
  // someone who steals the non-encrypted session ids. (These would enable the attacker
  // to act in place and with the power of the authenticated user, for as along
  // as this user stays logged in.) Use for example SSL to encrypt http traffic
  // (see "session handling" on www.php.net).
  //
  // Concerning the login via cookie, make sure your cookie is not stolen 
  // from your computer. The cookie is produced with some random string in it
  // which is used as key upon login-via-cookie authentication. This key is stored
  // in the user profile together with its creation date so as to supervise the
  // expiration. (Upon authentication it is just checked whether the stored random
  // key has not yet expired.) Thus the risk when your cookie is stolen is restricted 
  // to the time when the key is valid and to the amount of damage that can be inflicted
  // on your site with that user's privileges in that time. (It is thus BTW essential to 
  // ask for the old password if one is about to change the password, otherwise the above
  // reasoning does not make sense.)

  function UserSessionVars() {
    global $THP_SESSION;
    //session_start(); // done globally in userauth2.php

    if (!isset($_SESSION['cachestarttime'])) // on creation of the session, set start time:
      $_SESSION['cachestarttime'] = time();

    $this->assureCacheGetsUpdated();
      // Since we can expect the script to run through in less than some seconds, we
      // obviously have to check only once per client page request.
  }

  function GetUsername() {
    // A username != '' indicates that the user is authenticated. Thus, to transparently
    // login a user who wants to use cookie login, we on the fly check whether there
    // there is such a cookie, if a user name has not yet been set.
    global $THP_SESSION;
    global $UA2AllowCookieLogin, $UA2CookieExpireTime, $UA2CookiePrefix,
           $WrongPasswordFmt;

    if(@$_SESSION['username']) return $_SESSION['username'];

    if (!$UA2AllowCookieLogin) return false;
    if (!@$_COOKIE[$UA2CookiePrefix.'UA2Username']) return false;
    if (!@$_COOKIE[$UA2CookiePrefix.'UA2RandKey']) return false;
    $alleged_user = $_COOKIE[$UA2CookiePrefix.'UA2Username'];
    if (!isValidUserString($alleged_user)) return false; // dont trust the cookie
    if (!doesUserExist($alleged_user)) return false;
    // check whether given and stored cookie keys match: 
    $storedCookieKey = getCookieKey($alleged_user, $UA2CookieExpireTime);
    if ($storedCookieKey) {
      if ($storedCookieKey === $_COOKIE[$UA2CookiePrefix.'UA2RandKey']) {
        $_SESSION['username'] = $alleged_user;
        appendToUA2ErrorLog("Logged user '$alleged_user' in via authentication cookie.\n");
      } else 
        $this->setAuthMsg($WrongPasswordFmt);
    }

    return $_SESSION['username'];
  }

  function GetInstanceUsername() {
    return $this->GetUsername();
  }

  function SetUsername($username_loc) {
    global $THP_SESSION; 
    global $AuthId, $Author;
    $_SESSION['username'] = $username_loc;
    $AuthId = $username_loc; // make authentication status change immediately availabe to the engine
    $Author = $username_loc;
  }

  function isAuthenticated() {
    global $THP_SESSION;
    return strlen($this->GetUsername()) > 0;
  }

  function setAuthMsg($msg) {
    global $THP_SESSION;
    $_SESSION['auth_message'] = $msg;
  }

  function getAuthMsg() {
    global $THP_SESSION;
    return $_SESSION['auth_message'];
  }

  function isAuthMsgSet() {
    return @$_SESSION['auth_message'] && (strlen($_SESSION['auth_message']) > 0);
  }

  function GetProfile() {
    global $THP_SESSION;
    return $_SESSION['profile'];
  }

  function SetProfile($profile) {
    global $THP_SESSION;
    $_SESSION['profile'] = $profile;
  }

  function ClearInstanceVariables() {
    global $THP_SESSION;
    $this->releaseAllLocks();
    $this->clearPermCache();
    // Exactly don't clear the auth cookie here, since we want to carry our cookie authentication
    // over to the next session start.
    $this->SetUsername(''); // = "unauthenticated"
    $_SESSION = array(); // unset whole array
    // dont clear the THP_SESSION array here completely, since the session related data must be kept for destroying
    return true;
  }

  function setAuthCookie() {
    // Sets an authentication cookie for the (has-to-be-authenticated!) user
    // represented by this object.
    global $THP_SESSION;
    global $UA2CookieExpireTime, $UA2CookiePrefix;
  
    // get a random key and store it in the profile:
    $keycreatetime = time();
    $randKey = get_rand_session_id(); // defined in thp_sessions.php
    storeCookieKey($this->GetUsername(), $randKey, $keycreatetime);

    $expirationTime = $keycreatetime + $UA2CookieExpireTime;

    setCookie($UA2CookiePrefix.'UA2Username', $_SESSION['username'], $expirationTime);
    setCookie($UA2CookiePrefix.'UA2RandKey',  $randKey,              $expirationTime);
  }

  function clearAuthCookie() {
    global $THP_SESSION;
    global $UA2CookiePrefix;

    setCookie($UA2CookiePrefix.'UA2Username', '', time()-3600);
    setCookie($UA2CookiePrefix.'UA2RandKey', '', time()-3600);
    
    // also clear it in the profile (i.e. server-sidely)
    storeCookieKey($this->GetUsername(), '', time()-3600);
  }

  // Caching functions:
  // First expiration issues, for the sake that records might be changed by admins,
  // and this change should propagate into "real world" in a definite way. (don't
  // want to wait until the user logs in the next time)

  function assureCacheGetsUpdated() {
    global $THP_SESSION;
    global $UA2PermUpdateTimestampFile;

    if (file_exists($UA2PermUpdateTimestampFile)) {
      $lastpermupdate = file_get_contents($UA2PermUpdateTimestampFile);
      if ($lastpermupdate && ($lastpermupdate >= $_SESSION['cachestarttime'])) 
        $this->clearPermCache(); // this also updates the cache start time
    }
    // Note: if the time stamp file did not exist, this will leave the perm cache unupdated
  }

  function writePermUpdateTimestamp($updater) {
    global $THP_SESSION;
    global $UA2PermUpdateTimestampFile;

    return file_put_contents($UA2PermUpdateTimestampFile, time()) !== false;
  }

  // The following two functions implement the cache for all three types of records
  // and for the perm queries. (They should be considered private.)
  // The implementation currently mimicks a random-pick-out cache.
  function pushCacheItem($cache, $key, $item, $max_cache_size) {
    // pushes a currently non-cached item on the cache
    global $THP_SESSION;
    if (@$_SESSION[$cache] && (count($_SESSION[$cache]) >= $max_cache_size)) {
      $randarrkey = array_rand($_SESSION[$cache]);
      unset($_SESSION[$cache][$randarrkey]);
    }
    $_SESSION[$cache][$key] = $item;

    /* 
    // This would be lest-used-out, but PHP won't unshift associative arrays.
    // A lest-used-out cache would require two arrays per cache, one for
    // keys and one for values, and some tricky playing. It is not worth the effort.
    array_unshift($_SESSION[$cache], $key => $item);
    if (count($_SESSION[$cache]) > $max_cache_size)
      array_pop($_SESSION[$cache]);
    */
  }

  function fetchCacheItem($cache, $key) {
    // see whether item was cached and if so return it, otherwise return false
    global $THP_SESSION;
    if (@$_SESSION[$cache] && $_SESSION[$cache][$key]) {
      return $_SESSION[$cache][$key];

      /* // This would be for lest-used-out:
      // shift it to the top of the array:
      $buf = $_SESSION[$cache][$key];
      unset($_SESSION[$cache][$key]);
      array_unshift($_SESSION[$cache][$key], $buf);
      return $buf;
      */
    }
    return false; 
  }

  // The following three functions however are for public use:
  function isPermHolderRecordCached($user, $groupaction = false) {
    global $THP_SESSION;
    global $UA2EnablePermCaching; 
 
    if (!$UA2EnablePermCaching) return false;
    if ($groupaction && isIpRange($user)) 
      return isIpRangePermRecordCached($user);
    return isUserPermRecordCached($user, $groupaction);
  }

  function isIpRangePermRecordCached($iprange) {
    global $THP_SESSION;
    global $UA2EnablePermCaching; 
 
    if (!$UA2EnablePermCaching) return false;
    return isset($_SESSION['iprangerecords']) && 
           isset($_SESSION['iprangerecords'][$iprange]);
  }

  function isUserPermRecordCached($user, $groupaction = false) {
    global $THP_SESSION;
    global $UA2EnablePermCaching;

    if (!$UA2EnablePermCaching) return false;
    if ($groupaction) 
      return isset($_SESSION['grouppermrecords'][$user]);
    return isset($_SESSION['userpermrecords'][$user]);
  }
  
  // This function is the main entry point from everywhere else when
  // to get a perm holder record. Set cache_it to false if you do not
  // want the record which is loaded to be put in the cache. (This can 
  // be used for preventing the cache being flooded by a major but
  // singular record search etc.)
  // If assure_consistency is set to true, the function will try to make
  // the loaded record consistent if it is not, and return false if it
  // can't achieve it, e.g. parent is non-existent.
  function getPermHolderRecord($user, $groupaction = false, 
                               $use_cache = true, $cache_it = true, 
                               $assure_consistency = true) { 
    global $THP_SESSION;
    global $UA2EnablePermCaching, 
           $UA2MaxPermRecordCacheSize;

    if (!$UA2EnablePermCaching || !$use_cache) {
      $res = loadPermHolderRecord($user, $groupaction);
      return ($assure_consistency ? getConsistent($res, $groupaction) : $res);
    }

    if ($groupaction && isIpRange($user)) {
      $cache = 'iprangerecords';
      $cache_it = false; // See #006
    } else {
      if ($groupaction) $cache = 'grouppermrecords';
      else              $cache = 'userpermrecords';
    }
 
    $buf = $this->fetchCacheItem($cache, $user);
    if ($buf) return ($assure_consistency ? getConsistent($buf, $groupaction) : $buf); 
      // though we can almost assume that records in the cache are consistent

    // if not in cache, load it
    $buf = loadPermHolderRecord($user, $groupaction);
 
    // assure consistency:
    if ($assure_consistency && !makeConsistent($buf, $groupaction)) return false;

    //... and cache it (in consistent form) if asked for:
    if ($cache_it) 
      $this->pushCacheItem($cache, $user, $buf, $UA2MaxPermRecordCacheSize);

    //in any case return it:
    return $buf;
  }

  function getIpRangePermRecords() {
    global $THP_SESSION;
    global $UA2EnablePermCaching; 
  
    if (!$UA2EnablePermCaching) return loadIpRangePermRecords();

    if (!isset($_SESSION['iprangerecords']))
      $_SESSION['iprangerecords'] = loadIpRangePermRecords();
      // Because we would get a false here if at least some ip range records have been cache
      // (though we need all), make sure that either all or none ip range records are cached!
      // See #006.

    return $_SESSION['iprangerecords'];
  }
 
  function getFurtherGroupsForUser($user, $groupaction) {
    global $THP_SESSION;
    global $UA2EnablePermCaching, $UA2MaxPermRecordCacheSize; // "reuse/recycle" the maxPermRecordCache size here
    global $UA2_FurtherGroupsForUserFunc; //must have signature ($user, $groupaction) and return an unkeyed array of groups

    if (!$UA2_FurtherGroupsForUserFunc || !function_exists($UA2_FurtherGroupsForUserFunc)) return false;

    if (!$UA2EnablePermCaching) {
      $cache = 'furtherusergroups';
      $buf = $this->fetchCacheItem($cache, $user);
      if ($buf) return $buf;
      // if not yet in cache, calculate it, store it in cache, and return it:
      $buf = $UA2_FurtherGroupsForUserFunc($user, $groupaction);
      $this->pushCacheItem($cache, $user, $buf, $UA2MaxPermRecordCacheSize);
      return $buf;
    } else {
      return $UA2_FurtherGroupsForUserFunc($user, $groupaction);
    }
  }

  // The following functions implement the cache on the perm query level.
  // The data herein refers to the current user represented by this 
  // $UserInstanceVars object, so this cache has to be renewed when the
  // authentication status changes (login, logout, etc.).
  function pushPermQueryRes($page, $level, $authres) {
    // Pushes a (better non-cached, otherwise duplication) perm query result on the cache. 
    global $THP_SESSION;
    global $UA2MaxPermQueryCacheSize;

    $this->pushCacheItem('permqueries', "$level $page", $authres, $UA2MaxPermQueryCacheSize);
  }

  function isPermQueryResCached($page, $level) {
    global $THP_SESSION;
    return isset($_SESSION['permqueries']["$level $page"]);
  }

  function getPermQueryRes($page, $level) {
    // Expects the specified query to be existent in the cache, will otherwise return wrong result.
    global $THP_SESSION;
    return $this->fetchCacheItem('permqueries', "$level $page");
  }

  function clearPermQueryCache() {
    global $THP_SESSION;
    unset($_SESSION['permqueries']);
     // we use still the "old" loaded perm records, so dont update cache start time here.
  }

  function clearPermCache() {
    global $THP_SESSION;
    unset($_SESSION['userpermrecords']);
    unset($_SESSION['grouppermrecords']);
    unset($_SESSION['iprangerecords']);
    unset($_SESSION['furtherusergroups']);
    $this->clearPermQueryCache();
    $_SESSION['cachestarttime'] = time();
  }
 
  // perm holder record locking for edit:
  function acquireLock($user, $groupaction = false) {
    // Returns true on success, false if not able to acquire lock.
    global $THP_SESSION;
    global $UA2PermEditLockTimeout;

    $fn = getEditLockFilename($user, $groupaction);
    // If we have already locked it for ourselves, let proceed:
    if (@$_SESSION['editlocks'] && 
        $_SESSION['editlocks'][$fn] && 
        file_exists($fn) &&
        (file_get_contents($fn) == $_SESSION['editlocks'][$fn])) return true;
    // otherwise try to acquire lock: 1st, look whether its free and if not, whether it is fresh
    if (file_exists($fn) && 
        (file_get_contents($fn) > time() - $UA2PermEditLockTimeout)) return false; 
    // ok, either it did not exist or it was so old that we can ignore it, so we can set our lock now:
    $now = time(); // the time will serve us both as plain time info and as edit lock id for correct releasing 
    if (!file_put_contents($fn, $now)) return false;
    $_SESSION['editlocks'][$fn] = $now; // save the id in the session record
    appendToUA2ErrorLog("Edit lock file placed for user $user.\n");
    return true;
  }

  function isLocked($user, $groupaction = false) {
    global $THP_SESSION;
    global $UA2PermEditLockTimeout;

    $fn = getEditLockFilename($user, $groupaction);
    // If we have already locked it for ourselves, let proceed:
    if (@$_SESSION['editlocks'] && 
        $_SESSION['editlocks'][$fn] &&
        file_exists($fn) &&
        (file_get_contents($fn) == $_SESSION['editlocks'][$fn])) return false;
    if (file_exists($fn) &&
        (file_get_contents($fn) > time() - $UA2PermEditLockTimeout)) return true;
    return false;
  }

  function releaseLock($user, $groupaction = false) {
    global $THP_SESSION;
    $fn = getEditLockFilename($user, $groupaction);
    // make sure we delete only the lock file set by ourselves
    if (@$_SESSION['editlocks'] &&
        $_SESSION['editlocks'][$fn] &&
        file_exists($fn) &&
        (file_get_contents($fn) == $_SESSION['editlocks'][$fn])) {
      @unlink($fn);
      unset($_SESSION['editlocks'][$fn]);
      appendToUA2ErrorLog("Edit lock file for '$user' deleted.\n");
      return true;
    }
    return false;
  }

  function releaseAllLocks() {
    global $THP_SESSION;
    if (isset($_SESSION['editlocks'])) {
      foreach($_SESSION['editlocks'] as $fn => $id) {
        if (file_exists($fn) && (file_get_contents($fn) == $_SESSION['editlocks'][$fn]))
          @unlink($fn);
      }
      // Either the lock found in our record was ours and we have deleted it, or it wasn't and we left it.
      // In both cases it must leave our record, thus unset all:
      unset($_SESSION['editlocks']);
      appendToUA2ErrorLog("Possibly remaining edit locks deleted.\n");
    }
    return true;
  }

  // redirection facility:
  function getTargetUrl() {
    global $THP_SESSION;
    return $_SESSION['target_url'];
  }

  function setTargetUrl($target_url_loc) {
    // argument must be a fully qualified url
    global $THP_SESSION;
    $_SESSION['target_url'] = $target_url_loc;
  }

  function clearTargetUrl() {
    global $THP_SESSION;
    unset($_SESSION['target_url']);
  }

  function getPrevContentPage() {
    global $THP_SESSION;
    return $_SESSION['prev_contentpage'];
  }

  function setPrevContentPage($prevcont) {
    // argument must be a wiki page name and should be a content page
    global $THP_SESSION;
    $_SESSION['prev_contentpage'] = $prevcont;
  }
}


