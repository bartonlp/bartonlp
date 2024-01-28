#!/usr/bin/php
<?php
// BLP 2024-01-28 - Changed the query() to sql().
// Added ip and agent because $_SERVER does not have these.

// clean up the tables so they only have 90 days of info

echo "bartonlp: cleanuptable.php Start\n";

$days = 90; // I thing I only need six days.

// This will use the mysitemap.json in /var/www/bartonlp
// I now set SITELOADNAME in ~/.profile, along with GITGUARDIAN_API_KEY and IP2COUNTRY.
  
$_site = require_once(getenv("SITELOADNAME"));

$_site->ip = '195.252.232.86'; // BLP 2024-01-28 - $_SERVER['REMOTE_ADDR'] is not available.
$_site->agent = 'cleanuptables.php'; // BLP 2024-01-28 - dito

$S = new Database($_site);

$db = $S->masterdb;

$ar = ['tracker', 'bots2', 'daycounts', 'counter2'];

foreach($ar as $v) {
  //echo "delete from $v\n";
  $del = $S->sql("delete from $db.$v where lasttime < current_date() - interval $days day");
  $$v = $del;
}

// For lagagent only keep 3 years

$years = 3;

$del_logagent = $S->sql("delete from $db.logagent where lasttime < current_date() - interval $years year");

foreach($ar as $v) {
  echo "Del: $v=" . $$v."\n";
}

echo "Del: logagent={$del_logagent}\n";
echo "bartonlp: cleanuptables.php Done\n";
