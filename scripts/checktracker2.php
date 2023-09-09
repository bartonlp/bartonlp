#!/usr/bin/php
<?php
// I will cron this several times per day.
// cron is set to run every 15 min.
// I look for zeros in 'tracker' every interval.
// For every 'ip', 'agent' and 'site' that has isJavaScript equal to zero.
// This script looks at the 'tracker' table and adds any isJavaScript==0 entries to 'bots' and 'bots2'.
/*
CREATE TABLE `bots` (
  `ip` varchar(40) NOT NULL DEFAULT '',
  `agent` text NOT NULL,
  `count` int DEFAULT NULL,
  `robots` int DEFAULT '0',
  `site` varchar(255) DEFAULT NULL,
  `creation_time` datetime DEFAULT NULL,
  `lasttime` datetime DEFAULT NULL,
  PRIMARY KEY (`ip`,`agent`(254))
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
CREATE TABLE `bots2` (
  `ip` varchar(40) NOT NULL DEFAULT '',
  `agent` text NOT NULL,
  `page` text,
  `date` date NOT NULL,
  `site` varchar(50) NOT NULL DEFAULT '',
  `which` int NOT NULL DEFAULT '0',
  `count` int DEFAULT NULL,
  `lasttime` datetime DEFAULT NULL,
  PRIMARY KEY (`ip`,`agent`(254),`date`,`site`,`which`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
*/

$_site = require_once(getenv("SITELOADNAME"));
require_once(SITECLASS_DIR . "/defines.php");

$S = new Database($_site);
$db = $S->masterdb;

$meIp = null;
$ipAr = $S->myIp;

foreach($ipAr as $v) {
  $meIp .= "'$v',";
}
$meIp = rtrim($meIp, ',');

// Look at the tracker table and find any isJavaScript equal zero that are not me. These are curl or the like.
// This means no javascript of header image info (script, normal or noscript) and no csstest via
// .htaccess RewriteRule.

$zero = TRACKER_ZERO;
$cronZero = BOTS_CRON_ZERO;
$newCount = 0;
$count = 0;
$ncnt = 0;

$sql = "select id, ip, agent, site, page, botAs, isJavaScript, difftime from $db.tracker ".
       "where ip not in($meIp) and isJavaScript='$zero' " . 
       "and lasttime>=now() - interval 15 minute order by ip";
       //"and date(lasttime)>=current_date() - interval 1 day order by ip";
       //"order by ip";

if(!$S->query($sql)) {
  //error_log("checktracker2.php: No records");
  //echo "No records\n";
  exit();
}

$r = $S->getResult();

// Loop through all of toady's tracker records looking for isJavaScript=0.
// This is just for the last 15 minutes

while([$id, $ip, $agent, $site, $page, $botAs, $java, $diff] = $S->fetchrow($r, 'num')) { // process the 'tracker' records.
  if($diff) {
    error_log("checktracker2.php ip=$ip, site=$site, page=%page, java=$java, diff=$diff NOT EMPTY");
    continue; // This record has a difftime 
  }

  // Now before we do anything else add the 0x100 to bots and bots2

  ++$newCount;
  
  $rob = $cronZero;
  $sit = $site;

  // bots is more difficult because site and robots are a list and ored.
  
  if($S->query("select site, robots from $db.bots where ip='$ip' and agent='$agent'")) {
    [$s, $b] = $S->fetchrow('num');
    if(!str_contains($s, $site)) {
      $rob |= $b;
      $sit = "$site, $s";
    }
  }
  
  $S->query("insert into $db.bots (ip, agent, count, robots, site, creation_time, lasttime) ".
            "values('$ip', '$agent', 1, $cronZero, '$site', now(), now()) ".
            "on duplicate key update site='$sit', count=count+1, robots=$rob, lasttime=now()");

  // Do bots2. This should just be an insert.
  // We ignore duplicate key errors (should not be any)
  
  $S->query("insert into $db.bots2 (ip, agent, page, date, site, which, count, lasttime) ".
            "values('$ip', '$agent', '$page', current_date(), '$site', $cronZero, 1, now()) ".
            "on duplicate key update count=count+1, lasttime=now()");

  //error_log("Added $ip, $agent, $site to bots&bots2");

  if(empty($ip)) {
    error_log("checktracker2.php ip empty");
    continue;
  }

  if(empty($agent)) {
    error_log("checktracker2.php agent empty: $id, $ip, $site, $page, $java, $diff");
    continue;
  }

  //********
  // Now look at all tracker entries to see of this ip has ever been not zero.
  
  $sql = "select agent, isJavaScript, difftime from $db.tracker where ip='$ip' and site='$site' and isJavaScript!='$zero' order by lasttime";

  // We have never seen this ip without zero
  
  if(!$S->query($sql)) continue;

  $rr = $S->getResult();
  
  while([$agent2, $java, $diff] = $S->fetchrow($rr, 'num')) {
    if($java & TRACKER_BOT) continue; // Marked as a bot.

    // We have found a non zero entry for this ip. Does it have a difftime?

    if(!empty($diff)) {
      // Yes it has a difftime so someone has spend a little time on our site.

      // Now the bots table has a primary key of ip and agent(256).
      // The 'site' is not a single value it can have multiple sites seperated by commas,
      // and robots can have values that were ored in.
      
      $sql1 = "select robots, site from $db.bots where ip='$ip' and agent='$agent2'";
      
      if(!$S->query($sql1)) continue; // Did not find a record

      // Get the robots and the posibly multiple sites into $botsSites
      
      [$robots, $botsSites] = $S->fetchrow('num');

      // Remove the BOTS_CRON_ZERO (0x100) from the robots

      $robots &= ~BOTS_CRON_ZERO; // 0x100;

      if(!$robots) {
        // If $robots is empty then we can delete this record
          
        if(!$S->query("delete from $db.bots where ip='$ip' and agent='$agent2'")) {
          error_log("**** Error: Did not delete $ip, $agent2 from bots");
        } else {
          error_log("delete bots: ip=$ip, agent=$agent2, because robots=$robots, site=$site");
        }
      } else {
        $sql2 = "select site from $db.bots where ip='$ip' and agent='$agent2' and site='$botsSites'";

        // Remove the the site from tracker from the $botsSites from the bots table.

        $botsSite = preg_replace("~(.*?)$site(?:,?)(.*?)$~", "$1$2", $botsSites);

        if(!$S->query("update $db.bots set robots='$robots', site='$botsSite', lasttime=now() where ip='$ip' and agent='$agent2'")) {
          error_log("**** Error: Did not update $ip, $agent2 in bots");
        } else {
          error_log("update bots with robots=$robots and site($site) is now $botsSite");
        }
      }

      // bots2 primary key(ip, agent(256), date, site, which).
      // We may get multiple date and which fields
      
      $sql1 = "select date, which from $db.bots2 where ip='$ip' and agent='$agent2' and site='$site'";
      
      $S->query($sql1);

      while([$date, $which] = $S->fetchrow('num')) {
        // if which is not BOTS_CRON_ZERO continue.
        
        if($which != BOTS_CRON_ZERO) continue; // This is robots(1), Sitemap(2) or BOTS(4)

        if(!$S->query("delete from $db.bots2 where ip='$ip' and agent='$agent2' and date='$date' and site='$site' and which='$which'")) {
          error_log("**** Error: Did not delete $ip, $agent2, $date, $site, $which from bots2");
        } else {
          error_log("delete bots2 record for ip=$ip, agent=$agent2, date=$date, site=$site, which=$which");
        }
      }
      ++$count;
      continue;
    }
    ++$ncnt;
  }
}
//echo "Done: insert/updates=$ncnt\n";
//error_log("checktracker2.php: Done. Added $newCount to bots&bots2. Update/delete for bots&bots2=$count. tracker isJavaScript not zero=$ncnt");
