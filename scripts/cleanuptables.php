#!/usr/bin/php
<?php
// I now set SITELOADNAME in ~/.profile

// clean up the tables so they only have 90 days of info
echo "bartonlp: cleanuptable.php Start\n";

$days = 90; // I thing I only need six days.

// Use full path to siteload.php because this is a CLI.
// This will use the mysitemap.json in /var/www/bartonlp
  
$_site = require_once(getenv("SITELOADNAME"));
$S = new Database($_site);

$db = $S->masterdb;

$ar = ['tracker', 'bots2', 'daycounts', 'counter2'];

foreach($ar as $v) {
  //echo "delete from $v\n";
  $del = $S->query("delete from $db.$v where lasttime < current_date() - interval $days day");
  $$v = $del;
}

// For lagagent only keep 3 years

$years = 3;

$del_logagent = $S->query("delete from $db.logagent where lasttime < current_date() - interval $years year");

foreach($ar as $v) {
  echo "Del: $v=" . $$v."\n";
}
echo "Del: logagent=$del_logagent\n";
echo "bartonlp: cleanuptables.php Done\n";