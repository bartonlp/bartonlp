#!/usr/bin/php
<?php
// Check for visability change without any other beacon indicator.
// This is run every hour via a cron job.
// If the tracker table's isJavaScript has only 0x10 and on other indicator such as 0x20, 0x40 or
// 0x80 (PAGEHIDE, UNLOAD, BEFOREUNLOAD) then this VISABILITYCHANGE (0x10) must have been the final
// exit code.
/*
CREATE TABLE `tracker` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `botAs` varchar(100) DEFAULT NULL,
  `site` varchar(25) DEFAULT NULL,
  `page` varchar(255) NOT NULL DEFAULT '',
  `finger` varchar(50) DEFAULT NULL,
  `nogeo` tinyint(1) DEFAULT NULL,
  `browser` varchar(50) DEFAULT NULL,
  `ip` varchar(40) DEFAULT NULL,
  `count` int DEFAULT '1',
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
) ENGINE=MyISAM AUTO_INCREMENT=7483269 DEFAULT CHARSET=utf8mb3;
*/

//$DEBUG_END_MSG = true; // Message at the end with counts
$DEBUG_NOT_FOUND = true; // Not found message
$DEBUG_ALL_READY_FOUND = true; // All ready found message.
$DEBUG_HAS_DIFFTIME = true; // Has a difftime value.
$DEBUG_UPDATED = true; // Tracker updated

// This must be an absolute path. Can't use getenv(...) because this is not a web app.

$_site = require_once "/var/www/vendor/bartonlp/site-class/includes/siteload.php";
$S = new dbPdo($_site);

// The beacon mast. All beacon values.

$mask = BEACON_VISIBILITYCHANGE | BEACON_PAGEHIDE | BEACON_UNLOAD | BEACON_BEFOREUNLOAD;

// Select only tracker table items that only have BEACON_VISIBILITYCHANGE and no other indicators.
// I want to look at a window from 75 minites ago and 60 ago.

$sql = "select id, ip, site, page, botAs, isJavaScript, starttime, endtime, difftime from $S->masterdb.tracker ".
       "where starttime>= now() - interval 120 minute  ".
       "and starttime< now() - interval 60 minute ".
       "and isJavaScript & $mask = ". BEACON_VISIBILITYCHANGE .
       " and ip!='". MY_IP . "' order by lasttime";

if(!$S->sql($sql)) {
  echo "nothing found\n";

  if($DEBUG_NOT_FOUND) error_log("checkvischange: No items found");
  exit();
}

// Change count

$changeCount = 0;

while([$id, $ip, $site, $page, $botAs, $java, $starttime, $endtime, $difftime] = $S->fetchrow('num')) {
  $hexjava = dechex($java);

  if(str_contains($botAs, 'vischange-exit')) {
    echo "\$botAs already has 'vischange-exit', id=$id, ip=$ip, site=$site, page=$page, botAs=$botAs\n";

    if($DEBUG_ALL_READY_FOUND) {
      error_log("checkvischange \$botAs already has 'vischange-exit': id=$id, ip=$ip, site=$site, page=$page, botAs=$botAs, java=$hexjava, line=". __LINE__);
    }
    continue;
  }

  if($difftime) {
    echo "Has difftime, id=$id, ip=$ip, site=$site, page=$page, botAs=$botAs, difftime=$difftime\n";
    if($DEBUG_HAS_DIFFTIME) {
      error_log("checkvischange Has difftime: id=$id, ip=$ip, site=$site, page=$page, botAs=$botAs, difftime=$difftime, line=". __LINE__);
    }
    continue;
  }
  
  $botAs .= ",vischange-exit";
  $botAs = ltrim($botAs, ','); // if this is the only thing in $botAs.
  $java |= TRACKER_ADDED; // BLP 2025-03-08 - Added to defines.php 
  $hexjava = dechex($java);

  // Prepair the update string.
  
  $sqlupdate = "update $S->masterdb.tracker set botAs = '$botAs', isJavaScript=$java where id=$id";
  
  // We add this to the PHP_ERRORS.log file also.
  // We need a $formated date that looks like the way error_log() adds the date.
  
  $date = new DateTime("now", new DateTimeZone('America/New_York'));
  $formatted = "[" . $date->format('d-M-Y H:i:s') . " " . $date->getTimezone()->getName() . "]";

  if(file_put_contents("/var/www/PHP_ERRORS.log",
                       "$formatted checkvischange ADDED: id=$id, ip=$ip, site=$site, page=$page, botAs=$botAs, java=$hexjava, line=". __LINE__ . "\n",
                       FILE_APPEND) === false) {
    // If we fail to add to PHP_ERRORS.log explain what happened.
    
    $fileputerror = error_get_last();
    error_log("checkvischange: file_put_contents failed. Error=$fileputerror"); // This goes into PHP_ERRORS_CLI.log.
    exit();
  }

  // Actually do the query.
  
  $S->sql($sqlupdate); // do the update of tracker.

  if($DEBUG_UPDATED) {
    echo "sql=$sqlupdate\n";
    error_log("checkvischange UPDATED: id=$id, ip=$ip, site=$site, page=$page, botAs=$botAs, java=$hexjava, line=". __LINE__);
  }

  $changeCount++;
}

echo "Done. Change count=$changeCount\n";
if($DEBUG_END_MSG) error_log("checkvischange: Done. Change count=$changeCount");
