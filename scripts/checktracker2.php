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

// Use absolute path as .htaccess does not have the environment variable SITELOADNAME.

$_site = require_once("/var/www/vendor/bartonlp/site-class/includes/siteload.php");
//vardump("site", $_site);

require_once(SITECLASS_DIR . "/defines.php");

//$S = new dbMysqli($_site); // If using mysqli
$S = new dbPdo($_site); // If using PDO

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
// This means no javascript of header image info (script, normal or noscript) and no csstest via
// .htaccess RewriteRule.

$bot = CHECKTRACKER | TRACKER_BOT; // CHECKTRACKER = 0x8000, TRACKER_BOT = 0x200 
$zero = TRACKER_ZERO; // 0
$cronZero = BOTS_CRON_ZERO; // 0x100
$newCount = 0;
$count = 0;
$ncnt = 0;

$sql = "select id, ip, agent, site, page, botAs, isJavaScript, difftime from $db.tracker ".
       "where ip not in($meIp) and isJavaScript='$zero' " . 
       "and lasttime>=now() - interval 15 minute order by ip";

if(!$S->sql($sql)) {
  error_log("checktracker2.php: No records");
  echo "No records\n";
  exit();
}

$r = $S->getResult();

// Loop through all of toady's tracker records looking for isJavaScript=0.
// This is just for the last 15 minutes

while([$id, $ip, $agent, $site, $page, $botAs, $java, $diff] = $S->fetchrow($r, 'num')) { // process the 'tracker' records.
  if($diff) { // BLP 2023-10-15 - This person spent time at our site
    error_log("checktracker2.php ip=$ip, site=$site, page=%page, java=$java, diff=$diff NOT EMPTY");
    continue; // This record has a difftime. 
  }

  // Now before we do anything else add the 0x100 to bots and bots2

  ++$newCount;
  
  $rob = $cronZero; // BLP 2023-10-05 - set robots to BOTS_CRON_ZERO
  $sit = $site; // BLP 2023-10-05 - set $sit (the value we will write to the table) to the value from the tracker table.

  // bots is more difficult because site and robots are a list and ored.

  if(empty($ip) || ($x = preg_match("~^ *$~", $agent)) == 1) {
    $msg = ($x == 0 ? $agent : "Agent empty") . ", ";
    $msg .= $ip ?? "Ip empty";
    $msg = rtrim($msg, ", ");
    
    error_log("checktracker2.php: id=$id,  $msg");
    continue;
  }

  if($S->sql("select site, robots from $db.bots where ip='$ip' and agent='$agent'")) {
    [$s, $b] = $S->fetchrow('num');

    // BLP 2023-10-05 - I think we want to always or in $b
    
    $rob |= $b;

    // BLP 2023-10-05 - If the site is not already in the list add it at the start followed by a
    // comma.
    
    if(!str_contains($s, $site)) {
      $sit = "$site, $s"; // BLP 2023-10-05 - $sit now is $site (from tracker) a comma and the value from bots.
    }
  }

  // BLP 2023-10-05 - if we failed to read from bots then this is an insert, otherwise the key
  // exists so do an update.
  
  $S->sql("insert into $db.bots (ip, agent, count, robots, site, creation_time, lasttime) ".
          "values('$ip', '$agent', 1, $cronZero, '$site', now(), now()) ".
          "on duplicate key update site='$sit', count=count+1, robots=$rob, lasttime=now()");

  // Do bots2. This should just be an insert.
  // We ignore duplicate key errors (should not be any)
  
  $S->sql("insert into $db.bots2 (ip, agent, page, date, site, which, count, lasttime) ".
          "values('$ip', '$agent', '$page', current_date(), '$site', $cronZero, 1, now()) ".
          "on duplicate key update count=count+1, lasttime=now()");

  // BLP 2023-10-15 - make the TRACKER_ZERO (0) into a CHECKTRACKER|TRACKER_BOT (0x8200)

  // BLP 2023-11-06 - update botAs
  
  if(empty($botAs)) {
    $botAs = BOTAS_ZERO;
  } else {
    $botAs .= ",".BOTAS_ZERO;
  }
  
  $S->sql("update $db.tracker set botAs='$botAs', isJavaScript='$bot', lasttime=now() where ip='$ip' and agent='$agent'");
  
  //$tmp = dechex($bot); error_log("checktracker2.php: id=$id, ip=$ip, update tracker set isJavaScript to $tmp");
    
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
          error_log("checktracker2.php **** Error: Did not delete $ip, $agent2 from bots");
        } else {
          error_log("checktracker2.php delete from bots because robots is empty: ip=$ip, agent=$agent2, site=$site");
        }
      } else {
        $sql2 = "select site from $db.bots where ip='$ip' and agent='$agent2' and site='$botsSites'";

        // Remove the the site we found in tracker from the $botsSites from the bots table.

        $botsSite = preg_replace("~(.*?)$site(?:,?)(.*?)$~", "$1$2", $botsSites); // remove the site and posibly a comma and maybe another set to site

        if(empty($botsSite)) $botsSite = $site;
        
        if(!$S->sql("update $db.bots set robots='$robots', site='$botsSite', lasttime=now() where ip='$ip' and agent='$agent2'")) {
          error_log("checktracker2.php **** Error: Did not update $ip, $botsSite, $agent2 in bots");
        } else {
          error_log("checktracker2.php: update $ip, bots with robots=$robots and site=$site is now $botsSite");
        }
      }

      // bots2 primary key(ip, agent(256), date, site, which).
      // We may get multiple date and which fields
      
      $sql1 = "select date, which from $db.bots2 where ip='$ip' and agent='$agent2' and site='$site'";
      
      $S->sql($sql1);

      while([$date, $which] = $S->fetchrow('num')) {
        // if which is not BOTS_CRON_ZERO continue.
        
        if($which != BOTS_CRON_ZERO) continue; // This is robots(1), Sitemap(2) or BOTS(4)

        if(!$S->sql("delete from $db.bots2 where ip='$ip' and agent='$agent2' and date='$date' and site='$site' and which='$which'")) {
          error_log("checktracker2.php **** Error: Did not delete $ip, $agent2, $date, $site, $which from bots2");
        } else {
          error_log("checktracker2.php delete bots2 record for ip=$ip, agent=$agent2, date=$date, site=$site, which=$which");
        }
      }
      ++$count;
      continue;
    }
    ++$ncnt;
  }
}
echo "Done: insert/updates=$ncnt\n";
error_log("checktracker2.php: Done. Added $newCount to bots&bots2. Update/delete for bots&bots2=$count. tracker isJavaScript not zero=$ncnt");
