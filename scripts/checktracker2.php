#!/usr/bin/php
<?php
// I will cron this several times per day.
// cron is set to run every 15 min.
// I look for zeros in 'tracker' every interval.
// For every 'ip', 'agent' and 'site' that has isJavaScript equal to zero.
// This script looks at the 'tracker' table and adds any isJavaScript==0 entries to 'bots' and 'bots2'.
/*
 In bots robots is an ored integer value of BOTS_ROBOTS=1, BOTS_SITEMAP=2, BOTS_SITECLASS=4, BOTS_CRON_ZERO=0x100,
 and site is a comma seperated list of siteName.
 
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

 In bots2 site is just one siteName and which is single integer value of BOTS_ROBOTS=1, BOTS_SITEMAP=2, BOTS_SITECLASS=4, BOTS_CRON_ZERO=0x100.
 
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

CREATE TABLE `tracker` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `botAs` varchar(100) DEFAULT NULL,
  `site` varchar(25) DEFAULT NULL,
  `page` varchar(255) NOT NULL DEFAULT '',
  `finger` varchar(50) DEFAULT NULL,
  `nogeo` tinyint(1) DEFAULT NULL,
  `browser` varchar(50) DEFAULT NULL,
  `ip` varchar(40) DEFAULT NULL,
  `count` int DEFAULT 1,
  `agent` text,
  `referer` varchar(255) DEFAULT '',
  `starttime` datetime DEFAULT NULL,
  `endtime` datetime DEFAULT NULL,
  `difftime` varchar(20) DEFAULT NULL,
  `isJavaScript` int DEFAULT '0',
  `error` varchar(256) DEFAULT NULL,
  `lasttime` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `site` (`site`),
  KEY `ip` (`ip`),
  KEY `lasttime` (`lasttime`),
  KEY `starttime` (`starttime`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3;
*/

define("CHECKTRACKER_VERSION", "checktracker-2.10"); // Fixed tracker updates to use count and lasttime.

$DEBUG = true; // Misc errors
$DEBUG_IP_AGENT_EMPTY = true; // Agent or IP were empty.
//$DEBUG_NO_RECORDS = true; // The selection for this 15 minutes was empty.
$DEBUG_NOT_EMPTY = true; // The tracker difftime was not empty.
//$DEBUG_NEW_RECORD_ADDED = true; // A new record was inserted/updated to bots and bots2
//$DEBUG_TRACKER = true; // Update tracker record with robots.php and sitemap.php values.
$DEBUG_ZERO = true; // Tracker record has isjavaScript zero.
$DEBUG_EXIT = true; // Info at end, counters 
$DEBUG_DELETE = true; // Deleted item from bots

$_site = require_once "/var/www/vendor/bartonlp/site-class/includes/siteload.php";
//$_site = require_once "/var/www/site-class/includes/autoload.php";

// BLP 2024-01-28 - these two are not necessary because we are using the low level database class.
// However, if I were to use Database I would need these.
$_site->ip = '195.252.232.86'; 
$_site->agent = 'checktracker2.php';

require_once(SITECLASS_DIR . "/defines.php");

// Use the low level database functions so I don't need to set up noTrack etc.

$S = new dbPdo($_site);

$db = "barton";

$meIp = null;
$ipAr = [];
$S->sql("select myIp from $db.myip");
while($ip = $S->fetchrow('num')[0]) {
  $ipAr[] = $ip;
}

$ipAr[] = DO_SERVER;

foreach($ipAr as $v) {
  $meIp .= "'$v',";
}
$meIp = rtrim($meIp, ',');

$bot = CHECKTRACKER | TRACKER_BOT; // CHECKTRACKER = 0x8000, TRACKER_BOT = 0x200 
$trackerMask = 0xffff; // BLP 2025-03-03 - Only look at the values from SiteClass, tracker.php and beacon.php. Not robots.php or sitemap.php.
$cronZero = BOTS_CRON_ZERO; // 0x100

$zeroCount = 0; // number of tracker records that had isJavaScript equal to zero.
$botAddedCount = 0; // number of records added to bots and bots2
$diffNotEmptyCount = 0; // number of tracker records within 15 minute interval that had a difftime that was no zero.
$allDiffNotEmptyCount = 0; // number of all tracker records where difftime was not zero.
$botsDeletedCount = 0; // number of records deleted from bots table.
$bots2DeletedCount = 0; // number of records deleted from the bots2 table.

// Look at the tracker table and find any isJavaScript equal zero that are not me. These are curl or the like.
// This means no javascript or header image info (normal or noscript) and no csstest via
// .htaccess RewriteRule.
// BLP 2025-03-03 - we use $trackerMask which is ~0xffff to remove any high order bits from
// robots.php or sitemap.php (0x30000).

$sqlLoop = "select id, ip, agent, site, page, botAs, difftime, isjavascript from $db.tracker ".
           "where ip not in($meIp) and (isJavaScript & $trackerMask) = 0 " . // $trackerMask is 0xffff inverted via ~
           "and lasttime>=now() - interval 15 minute order by ip";

if(!$S->sql($sqlLoop)) {
  if($DEBUG_NO_RECORDS) error_log("checktracker: No records, line=" . __LINE__);
  echo "No records\n";
  exit();
}

$r = $S->getResult();

// Loop through all of the records in tracker for the last 15 minutes. The 15 minutes is due to
// crontab where we have a CRON setting for each 1/4 hour. The crontab calls this file.

while([$id, $ip, $agent, $site, $page, $botAs, $diff, $trjava] = $S->fetchrow($r, 'num')) { // process the 'tracker' records.
  // $trjava could have the high order bits set for robots.php and sitemap.php.
  
  if($diff) {
    // BLP 2023-10-15 - This person spent time at our site. Therefore, we leave the isJavaScript
    // value zero.
    
    if($DEBUG_NOT_EMPTY)
      error_log("checktracker DIFF_NOT_EMPTY: id=$id, ip=$ip, site=$site, page=$page, agent=$agent, diff=$diff, line=". __LINE__);

    ++$diffNotEmptyCount;
    continue; // This record has a difftime. 
  }

  // BLP 2025-03-03 - $trjava can only have the robots.php and sitemap.php bits. The lower 0xffff
  // is ZERO due to the $trackerMask.
  
  if($DEBUG_ZERO) 
    error_log("checktracker ZERO: id=$id, ip=$ip, site=$site, page=$page, agent=$agent, line=" . __LINE__);
  
  // BLP 2025-01-11 - isJavaScript is always zero see the $sql above!

  ++$zeroCount; // number of tracker isJavaScript with zero.
  
  // Now before we do anything else add the 0x100 to bots and bots2

  $sit = $site; // BLP 2023-10-05 - set $sit, the value we will write to the bots table, to the value from the tracker table. We will add to it later.

  if(empty($ip) || ($x = preg_match("~^ *$~", $agent)) === 1) {
    $msg .= $ip ? "ip=$ip, " : "ip=empty, ";
    $msg = ($x === 0 ? "agent=$agent, " : "agent=empty, ");
    $msg = rtrim($msg, ", ");
    
    if($DEBUG_IP_AGENT_EMPTY) error_log("checktracker: id=$id, $msg, line=". __LINE__);
    continue;
  }

  // bots is more difficult because site and robots are a list and ored.

  if($S->sql("select site, robots from $db.bots where ip='$ip' and agent='$agent'")) {
    [$s, $rob] = $S->fetchrow('num');

    $rob |= BOTS_CRON_ZERO; // BLP 2025-03-05 - 
    
    // BLP 2023-10-05 - If the site is not already in the list add it at the start followed by a
    // comma.
    
    if(!str_contains($s, $site)) { // $s is haystack, $site is the needle. $s from bots $site if from tracker.
      $sit = "$site, $s"; // $sit now is $site (from tracker) a comma and the value from bots.
    }
  } else {
    $rob = $trjava >> 16 | BOTS_CRON_ZERO; // BLP 2025-03-05 - add BOTS_CRON_ZERO
    
    $botAs = ((($rob & BOTS_ROBOTS) !== 0) ? BOTAS_ROBOT . "," : '').((($rob & BOTS_SITEMAP) !== 0) ? BOTAS_SITEMAP. "," : '').
             ((($rob & BOTS_SITECLASS) !== 0) ? BOTAS_SITECLASS . "," : '').((($rob & BOTS_CRON_ZERO) !== 0) ? BOTAS_ZBOT . "," : '');
    $botAs = rtrim($botAs, ',');
    
    if($DEBUG_NEW_RECORD_ADDED)
      error_log("checktracker NEW RECORD ADDED to bots and bots2, NO RECORD in bots table: id=$id, ip=$ip, site=$site, page=$page, botAs=$botAs, rob=$rob, java=$java, agent=$agent, line=".__LINE__);
  }

  // If we failed to read from bots then this is an insert, otherwise the key
  // exists so do an update.

  $S->sql("insert into $db.bots (ip, agent, count, robots, site, creation_time, lasttime) ".
          "values('$ip', '$agent', 1, $rob, '$sit', now(), now()) ".
          "on duplicate key update site='$sit', count=count+1, robots=$rob, lasttime=now()");

  // Do bots2. This should just be an insert.
  // We ignore duplicate key errors (should not be any)
  // Here we use $site from tracker and $cronZero because these are singular values.
  
  $S->sql("insert into $db.bots2 (ip, agent, page, date, site, which, count, lasttime) ".
          "values('$ip', '$agent', '$page', current_date(), '$site', $cronZero, 1, now()) ".
          "on duplicate key update count=count+1, lasttime=now()");

  ++$botAddedCount;

  // Make the TRACKER_ZERO (0) into a CHECKTRACKER|TRACKER_BOT (0x8200)

  $trjava = $bot; // $bot is CHECKTRACKER|TRACKER_BOT
  
  if($botAs) {
    if(!str_contains($botAs, BOTAS_ZERO)) { // BLP 2025-02-16 - 
      $botAs .= ",".BOTAS_ZERO;
    }
  } else {
    $botAs = BOTAS_ZERO;
  }
  
  $trjava |= ($rob & 3) << 16; 
  
  if($DEBUG_TRACKER)
    error_log("checktracker update tracker: id=$id, ip=$ip, site=$site, page=$page, botAs=$botAs, java=". dechex($trjava). ", agent=$agent, line=". __LINE__);
  
  $S->sql("update $db.tracker set botAs='$botAs', isJavaScript='$trjava', lasttime=now(), count=count+1 where ip='$ip' and agent='$agent'");

  //********
  // Now look at all tracker entries to see if this ip has ever been not zero.
  // $trackerMask is inverted via ~. Its value is ~0x10000. This masks out robots.php and
  // sitemap.php values.
  
  $sql = "select agent, isJavaScript, difftime from $db.tracker where ip='$ip' and site='$site' and (isJavaScript & '$trackerMask') != 0 order by lasttime";

  // We have never seen this ip without zero
  
  if(!$S->sql($sql)) continue;

  $rr = $S->getResult();

  // We have seen this ip and its value was not zero.
  
  while([$agent2, $java, $diff] = $S->fetchrow($rr, 'num')) {
    if($java & TRACKER_BOT) continue; // Marked as a bot so do next.

    // We have found a non zero entry for this ip and it was not marked as TRACKER_BOT. Does it have a difftime?

    if(!empty($diff)) {
      // Yes it has a difftime so someone has spend a little time on our site.

      if($DEBUG_NOT_EMPTY) error_log("checktracker diff not empty found in all: id=$id, ip=$ip, site=$site, page=$page, agent=$agent2, line=". __LINE__);
      ++$allDiffNotEmptyCount;
      
      // Now the bots table has a primary key of ip and agent(256).
      // The 'site' is not a single value it can have multiple sites seperated by commas,
      // and robots can have values that were ored in.
      
      if(!$S->sql("select robots, site from $db.bots where ip='$ip' and agent='$agent2'")) continue; // Did not find a record get next

      // Get the robots and the posibly multiple sites into $botsSites
      
      [$robots, $botsSites] = $S->fetchrow('num');

      // Remove the BOTS_CRON_ZERO (0x100) from the robots

      $robots &= ~BOTS_CRON_ZERO; // remove 0x100;

      if(!$robots) {
        // If $robots is empty then we can delete this record
          
        if(!$S->sql("delete from $db.bots where ip='$ip' and agent='$agent2'")) {
          error_log("checktracker Did not delete: id=$id, ip=$ip, site=$site, page=$page, agent=$agent2 from bots, line=". __LINE__);
        } else {
          ++$botsDeletedCount;
          if($DEBUG_DELETE) error_log("checktracker delete from bots because robots is empty: id=$id, ip=$ip, site=$site, page=$page, agent=$agent2, line=". __LINE__);
        }
      } else {
        // Remove the the $site we found in tracker from the $botsSites from the bots table.

        $botsSite = preg_replace("~(.*?)$site(?:,?)(.*?)$~", "$1$2", $botsSites); // remove the site and posibly a comma and maybe another set to site

        if(empty($botsSite)) $botsSite = $site;
        
        if(!$S->sql("update $db.bots set robots='$robots', site='$botsSite', lasttime=now() where ip='$ip' and agent='$agent2'")) {
          error_log("checktracker Error, Did not update: id=$id, ip=$ip, site=$botsSite, page=$page, agent=$agent2 in bots, line=". __LINE__);
        } else {
          if($DEBUG) error_log("checktracker update: id=$id, ip=$ip, bots with robots=$robots and site=$site is now $botsSite, line=". __LINE__);
        }
      }

      // bots2 primary key(ip, agent(256), date, site, which).
      // We may get multiple date and which fields
      
      $S->sql("select date, which from $db.bots2 where ip='$ip' and agent='$agent2' and site='$site'");

      while([$date, $which] = $S->fetchrow('num')) {
        // if which is not BOTS_CRON_ZERO continue.
        
        if($which != BOTS_CRON_ZERO) continue; // This is robot(1), Sitemap(2) or BOT(4)

        if(!$S->sql("delete from $db.bots2 where ip='$ip' and agent='$agent2' and date='$date' and site='$site' and which='$which'")) {
          error_log("checktracker Error, Did not delete: id=$id, ip=$ip, site=$site, $agent2, $date, $which from bots2, line=". __LINE__);
        } else {
          if($DEBUG) error_log("checktracker delete bots2 record: id=$id, ip=$ip, site=$site, date=$date, which=$which, agent=$agent2, line=". __LINE__);
        }
        ++$bots2DeletedCount;
      }
      continue;
    }
  }
}
echo "Done. Number of isJavaScript equal zero: $zeroCount.
Update for bots & bots2=$botAddedCount.
Bots records deleted: $botsDeletedCount.
Bots2 records deleted: $bots2DeletedCount.
Found difftime that was not empty: $diffNotEmptyCount.
All diff not empty: $allDiffNotEmptyCount.\n";

if(($botsDeletedCount || $bots2DeletedCount || $diffNotEmptyCount || $allDiffNotEmptyCount)) {
  $rest = <<<EOF
\nBots records deleted: $botsDeletedCount.
Bots2 records deleted: $bots2DeletedCount.
Found difftime that was not empty: $diffNotEmptyCount.
All diff not empty: $allDiffNotEmptyCount.
EOF;
}

// BLP 2025-02-20 - $zeroCount was $newCount.
if($DEBUG_EXIT) error_log("checktracker: Done. Number of isJavaScript equal zero: $zeroCount.$rest");
