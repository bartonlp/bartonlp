#!/usr/bin/php
<?php
// Delete entries from the myIp table older than N days that are not my home IP
// BLP 2024-01-28 - change query to sql. Also ip and agent are not in S_SERVER.

$_site = require_once "/var/www/vendor/bartonlp/site-class/includes/siteload.php";

$_site->ip = '195.252.232.86';  // BLP 2024-01-28 - 
$_site->agent = 'myipupdate.php';

$S = new Database($_site);

echo "bartonlp: myipupdate.php Start\n";

$N = 10; // after N days delete the item.
$myipdelcnt = 0;

$home = gethostbyname("bartonphillips.org");
echo "home: $home\n";
// Now get each entry from myip and look at the bartonphillips.members table and remove that item
$S->sql("select myIp from $S->masterdb.myip where lasttime < current_date() - interval $N day and myIp != '$home'");
$r = $S->getResult();

$ips = '';

while($ip = $S->fetchrow($r, 'num')[0]) {
  $ips .= "$ip, ";
  $myipdelcnt += $S->sql("delete from $S->masterdb.myip where myIp='$ip'");
}

if($ips != '') {
  echo "The following ip have been deleted from the myip table:\n";
  echo "$ips\n";
}
echo "deleted $myipdelcnt from myip\n";
echo "bartonlp: myipupdate.php Done\n";
