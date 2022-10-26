#!/usr/bin/php
<?php
// I will cron this several times per day.
// cron is set to run every 15 min.
// I look for zeros in 'tracker' every interval.
// For every 'ip', 'agent' and 'site' that has isJavaScript equal to zero.
// I 'order' the return by 'ip' address so if there are multiple 'ip' occrances they are one after
// the other.
// I then set $last to the current 'ip' address at the end of the process. I use $last to
// tell if the previous 'ip' is the same as the current 'ip'. If it is I update the bots2 record
// with plus one for the count.
// This script looks at the 'tracker' table and adds any isJavaScript==0 entries to 'bots' and 'bots2'.
// The idea is that once we have been able to run javascript or get the image info via tracker.php (normal, script, and
// noscript) we can then figure out if the ip is really a zero or not. If we find
// that it is, and the javascript or image has values, this is NOT a zero.
// BLP 2022-07-29 - on insert update the bots field in daycounts table.

$_site = require_once(getenv("SITELOADNAME"));
require_once(SITECLASS_DIR . "/defines.php");

$S = new Database($_site);
$db = $S->masterdb;

$myIps = implode(",", $S->myIp);

// Look at the tracker table and find any isJavaScript equal zero that are not me. These are curl or the like.
// This means no javascript of header image info (script, normal or noscript) and no csstest via
// .htaccess RewriteRule.

$sql = "select ip, agent, site, finger, botAs from $db.tracker ". // BLP 2022-06-13 - add finger
       "where ip not in ('$myIps') and isJavaScript = " . TRACKER_ZERO . 
       " and lasttime >= now() - interval 15 minute order by ip"; // interval = 15 min

if($S->query($sql) == 0) {
  error_log("checktracker2.php: No records with isJavaScript=0 found");
  exit();
}

// Loop through all of toady's tracker records looking for isJavaScript=0.

$r = $S->getResult(); // save the above sql so we can do other thing inside the loop
$count = $insertCnt = 0;
$last = '';

while([$ip, $agent, $trackerSite, $finger, $botAs] = $S->fetchrow($r, 'num')) { // process the 'tracker' records. // BLP 2022-06-13 - add finger
  if($finger) {
    error_log("checktracker2.php: Because finger=$finger not a bot: $ip, $agent");
    continue; // This record has a finger
  }
  
  ++$count;
  
  $agent = $S->escape($agent);

  // Now look in the bots table to see if there is a record that matches the ip and agent

  $sql = "select site, lasttime from $db.bots where lasttime>=current_date() and ip='$ip' and agent='$agent'";

  // Is there a record?

  if($S->query($sql)) {
    // Yes there is a bots record that matches ip and agent (should be only one because ip and
    // agent are primary keys).

    [$botsSite, $lasttime] = $S->fetchrow('num'); // get the 'bots' record

    // Now look to see if $trackerSite is in $botsSite. $trackerSite will be a single item while
    // $botsSite may have multiple items seperated by commas.
    // If yes then add $trackerSite before $botsSite. Otherwise set $botsSite alone.
    
    $botsSite = strpos($botsSite, $trackerSite) === false ? "$botsSite, $trackerSite" : $botsSite;

    $sql = "update $db.bots set site='$botsSite', count=count+1, robots=robots|" . BOTS_CRON_ZERO . ", lasttime=now() ".
           "where ip='$ip' and agent='$agent'";

    $S->query($sql);
  } else {
    // There was NO record for this 'ip' and 'agent' so insert a new record in bots.

    $sql = "insert into $db.bots (ip, agent, count, robots, site, creation_time, lasttime) ".
           "values('$ip', '$agent', 1, " . BOTS_CRON_ZERO . ", '$trackerSite', now(), now())";
    try {
      $S->query($sql);
    } catch(Exception $e) {
      // This should NOT happen but sometime does?
      error_log("checktracker2.php: insert into bots failed (it should not). $ip, $agent, sql($sql), " . __LINE__);
    }      
    ++$insertCnt;

    // If we inserted this then we should add 1 to the daycounts for this date as zeros are not
    // counted because we don't have enough information untill now.

    $S->query("insert into $db.daycounts (site, `date`, bots, lasttime) values('$trackerSite', now(), bots=bots+1, now()) ".
              "on duplicate key update bots=bots+1, lasttime=now()");

    error_log("updated daycounts $trackerSite, $ip, botAs=$botAs, bots+1");
  }

  // bots2 primary key is 'ip, agent, date, site, which'.
  
  $sql = "select ip, count from $db.bots2 where ip='$ip' and agent='$agent' and date=current_date() and which=" . BOTS_CRON_ZERO;

  // If the query returns a value then we update the bots2 record.
  
  if($S->query($sql)) {
    // preset $updateCounter, then check $last (the previous 'ip' address) with $ip (the current
    // 'ip' address. If $last equals $ip then set $updateCounter to count+1.
    
    $updateCounter = '';

    if($last == $ip) {
      $updateCounter = "count=count+1, ";
    }
    $sql = "update $db.bots2 set {$updateCounter}lasttime=now() where ip='$ip'";

    $S->query($sql);
  } else {
    // The query did not return results so we must insert a new bot2 record.
    
    $sql = "insert into $db.bots2 (ip, agent, date, site, which, count, lasttime) ".
           "values('$ip', '$agent', now(), '$trackerSite', " . BOTS_CRON_ZERO . ", 1, now())";

    $S->query($sql);
  }
  $last = $ip; // Update $last with the current 'ip'
}

error_log("checktracker2.php: count=$count, bots updates=" . $count-$insertCnt . ", insert=$insertCnt");
