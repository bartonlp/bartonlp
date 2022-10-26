#!/usr/bin/php
<?php
// Delete entries from the myIp table older than N days that are not my home IP

$_site = require_once(getenv("SITELOADNAME"));
$S = new Database($_site);

echo "bartonlp: myipupdate.php Start\n";

$N = 10; // after N days delete the item.
$myipdelcnt = 0;

$home = gethostbyname("bartonphillips.dyndns.org");
echo "home: $home\n";
// Now get each entry from myip and look at the bartonphillips.members table and remove that item
$S->query("select myIp from $S->masterdb.myip where lasttime < current_date() - interval $N day and myIp != '$home'");
$r = $S->getResult();

$ips = '';

while($ip = $S->fetchrow($r, 'num')[0]) {
  $ips .= "$ip, ";
  $myipdelcnt += $S->query("delete from $S->masterdb.myip where myIp='$ip'");
}

if($ips != '') {
  echo "The following ip have been deleted from the myip table:\n";
  echo "$ips\n";
}
echo "deleted $myipdelcnt from myip\n";
echo "bartonlp: myipupdate.php Done\n";



