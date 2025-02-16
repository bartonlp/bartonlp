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
  `id` int NOT NULL AUTO_INCREMENT,
  `botAs` varchar(30) DEFAULT NULL,
  `site` varchar(25) DEFAULT NULL,
  `page` varchar(255) NOT NULL DEFAULT '',
  `finger` varchar(50) DEFAULT NULL,
  `nogeo` tinyint(1) DEFAULT NULL,
  `browser` varchar(50) DEFAULT NULL,
  `ip` varchar(40) DEFAULT NULL,
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
) ENGINE=MyISAM AUTO_INCREMENT=6716225 DEFAULT CHARSET=utf8mb3;  
*/

define("CHECKTRACKER_VERSION", "checktracker-2.4"); // BLP 2025-02-16 - Get $trbot from tracker. Fixed rob = $trbot >> 16 not $bot.
                                                    // Fixed $botAS so not multiple 'zero' entries.

$DEBUG = true;
//$DEBUG_NO_RECORDS = true;
//$DEBUG_NOT_EMPTY = true;
$DEBUG_NEW_RECORD_ADDED = true;
$DEBUG_TRACKER = true;

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

// Look at the tracker table and find any isJavaScript equal zero that are not me. These are curl or the like.
// This means no javascript or header image info (normal or noscript) and no csstest via
// .htaccess RewriteRule.

$bot = CHECKTRACKER | TRACKER_BOT; // CHECKTRACKER = 0x8000, TRACKER_BOT = 0x200 
$zero = TRACKER_ZERO; // 0
$cronZero = BOTS_CRON_ZERO; // 0x100
$newCount = 0;
$count = 0;
$ncnt = 0;

$sql = "select id, ip, agent, site, page, botAs, difftime, isjavascript from $db.tracker ".
       "where ip not in($meIp) and isJavaScript='$zero' " . 
       "and lasttime>=now() - interval 15 minute order by ip";

if(!$S->sql($sql)) {
  if($DEBUG_NO_RECORDS) error_log("checktracker: No records");
  echo "No records\n";
  exit();
}

$r = $S->getResult();

// Loop through all of toady's tracker records looking for isJavaScript=0.
// This is just for the last 15 minutes

while([$id, $ip, $agent, $site, $page, $botAs, $diff, $trbot] = $S->fetchrow($r, 'num')) { // process the 'tracker' records.
  if($diff) { // BLP 2023-10-15 - This person spent time at our site
    if($DEBUG_NOT_EMPTY)
      error_log("checktracker: ip=$ip, site=$site, page=%page, diff=$diff NOT EMPTY");

    continue; // This record has a difftime. 
  }

  // BLP 2025-01-11 - isJavaScript is always zero see the $sql above!
  
  // Now before we do anything else add the 0x100 to bots and bots2

  ++$newCount;
  
  $sit = $site; // BLP 2023-10-05 - set $sit, the value we will write to the table bots table, to the value from the tracker table. We will add to it later.

  // bots is more difficult because site and robots are a list and ored.

  if(empty($ip) || ($x = preg_match("~^ *$~", $agent)) === 1) {
    $msg = ($x === 0 ? $agent : "Agent empty") . ", ";
    $msg .= $ip ?? "Ip empty";
    $msg = rtrim($msg, ", ");
    
    if($DEBUG) error_log("checktracker: id=$id,  $msg");
    continue;
  }

  if($S->sql("select site, robots from $db.bots where ip='$ip' and agent='$agent'")) {
    [$s, $rob] = $S->fetchrow('num');

    // BLP 2023-10-05 - If the site is not already in the list add it at the start followed by a
    // comma.
    
    if(!str_contains($s, $site)) { // $s is haystack, $site is the needle.
      $sit = "$site, $s"; // $sit now is $site (from tracker) a comma and the value from bots.
    }
  } else {
    $rob = $trbot >> 16; // BLP 2025-02-16 - 
    
    $botAs = ((($rob & BOTS_ROBOTS) !== 0) ? "robot," : '').((($rob & BOTS_SITEMAP) !== 0) ? "sitemap," : '').
             ((($rob & BOTS_SITECLASS) !== 0) ? "BOT," : '').((($rob & BOTS_CRON_ZERO) !== 0) ? "zero," : '');
    $botAs = rtrim($botAs, ',');
    
    //error_log("checktracker: ip=$ip, \$rob= $rob, \$botAs=$botAs, line=".__LINE__);

    if($DEBUG_NEW_RECORD_ADDED)
      error_log("checktracker: NEW RECORD ADDED to bots, NO RECORD in bots table for ip=$ip, botAs=$botAs, rob=$rob, java=$java, agent=$agent, line=".__LINE__);
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

  // Make the TRACKER_ZERO (0) into a CHECKTRACKER|TRACKER_BOT (0x8200)

  if($botAs) {
    if(!str_contains($botAs, BOTAS_ZERO)) { // BLP 2025-02-16 - 
      $botAs .= ",".BOTAS_ZERO;
    }
  } else {
    $botAs = BOTAS_ZERO;
  }
  
  $trbot |= ($rob & 3) << 16; // BLP 2025-01-11 - $bot starts out as CHECKTRACKER | TRACKER_BOT. $java is the value from the bots table changed into isJavaScript values.
  
  if($DEBUG_TRACKER)
    error_log("checktracker: update tracker, ip=$ip, site=$site, page=$page, botAs=$botAs, java=". dechex($trbot). ", agent=$agent");
  
  $S->sql("update $db.tracker set botAs='$botAs', isJavaScript='$trbot', lasttime=now() where ip='$ip' and agent='$agent'");
  
  //********
  // Now look at all tracker entries to see if this ip has ever been not zero.
  
  $sql = "select agent, isJavaScript, difftime from $db.tracker where ip='$ip' and site='$site' and isJavaScript!='$zero' order by lasttime";

  // We have never seen this ip without zero
  
  if(!$S->sql($sql)) continue;

  $rr = $S->getResult();

  // We have seen this ip and its value was not zero.
  
  while([$agent2, $java, $diff] = $S->fetchrow($rr, 'num')) {
    if($java & TRACKER_BOT) continue; // Marked as a bot so do next.

    // We have found a non zero entry for this ip and it was not marked as TRACKER_BOT. Does it have a difftime?

    if(!empty($diff)) {
      // Yes it has a difftime so someone has spend a little time on our site.

      // Now the bots table has a primary key of ip and agent(256).
      // The 'site' is not a single value it can have multiple sites seperated by commas,
      // and robots can have values that were ored in.
      
      $sql1 = "select robots, site from $db.bots where ip='$ip' and agent='$agent2'";
      
      if(!$S->sql($sql1)) continue; // Did not find a record get next

      // Get the robots and the posibly multiple sites into $botsSites
      
      [$robots, $botsSites] = $S->fetchrow('num');

      // Remove the BOTS_CRON_ZERO (0x100) from the robots

      $robots &= ~BOTS_CRON_ZERO; // remove 0x100;

      if(!$robots) {
        // If $robots is empty then we can delete this record
          
        if(!$S->sql("delete from $db.bots where ip='$ip' and agent='$agent2'")) {
          error_log("checktracker: Error, Did not delete $ip, $agent2 from bots");
        } else {
          if($DEBUG) error_log("checktracker: delete from bots because robots is empty, ip=$ip, agent=$agent2, site=$site");
        }
      } else {
        $sql2 = "select site from $db.bots where ip='$ip' and agent='$agent2' and site='$botsSites'";

        // Remove the the site we found in tracker from the $botsSites from the bots table.

        $botsSite = preg_replace("~(.*?)$site(?:,?)(.*?)$~", "$1$2", $botsSites); // remove the site and posibly a comma and maybe another set to site

        if(empty($botsSite)) $botsSite = $site;
        
        if(!$S->sql("update $db.bots set robots='$robots', site='$botsSite', lasttime=now() where ip='$ip' and agent='$agent2'")) {
          error_log("checktracker: Error, Did not update $ip, $botsSite, $agent2 in bots");
        } else {
          if($DEBUG) error_log("checktracker: update $ip, bots with robots=$robots and site=$site is now $botsSite");
        }
      }

      // bots2 primary key(ip, agent(256), date, site, which).
      // We may get multiple date and which fields
      
      $sql1 = "select date, which from $db.bots2 where ip='$ip' and agent='$agent2' and site='$site'";
      
      $S->sql($sql1);

      while([$date, $which] = $S->fetchrow('num')) {
        // if which is not BOTS_CRON_ZERO continue.
        
        if($which != BOTS_CRON_ZERO) continue; // This is robot(1), Sitemap(2) or BOT(4)

        if(!$S->sql("delete from $db.bots2 where ip='$ip' and agent='$agent2' and date='$date' and site='$site' and which='$which'")) {
          error_log("checktracker: Error, Did not delete $ip, $agent2, $date, $site, $which from bots2");
        } else {
          if($DEBUG) error_log("checktracker: delete bots2 record for ip=$ip, agent=$agent2, date=$date, site=$site, which=$which");
        }
      }
      ++$count;
      continue;
    } 
    ++$ncnt;
  }
}
echo "Done: insert/updates=$ncnt\n";
if($DEBUG) error_log("checktracker: Done. Added $newCount to bots&bots2. Update/delete for bots&bots2=$count. tracker isJavaScript not zero=$ncnt");
