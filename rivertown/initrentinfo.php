<?php
// NOTE the database 'rivertown' must already be created.
// NOTE the table 'rentinfo' must be created as follows:
/*
CREATE TABLE `rentinfo` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `rentalStreet` varchar(100) NOT NULL,
  `rentalNumber` varchar(100) NOT NULL,
  `ownerName` varchar(100) DEFAULT NULL,
  `ownerAddress` varchar(200) DEFAULT NULL,
  `ownerPhone` varchar(20) DEFAULT NULL COMMENT '(nnn)nnn-nnnn',
  `ownerPhone2` varchar(20) DEFAULT NULL,
  `ownerEmail` varchar(200) DEFAULT NULL,
  `renterAccount` varchar(20) DEFAULT NULL COMMENT 'if rented the renterXXX values will be filled',
  `renterName` varchar(100) DEFAULT NULL,
  `renterPhone1` varchar(20) DEFAULT NULL,
  `renterPhone2` varchar(20) DEFAULT NULL,
  `renterEmail` varchar(200) DEFAULT NULL,
  `leaseEndDate` varchar(20) DEFAULT NULL,
  `rentalStatus` varchar(20) DEFAULT NULL COMMENT 'vacant, notVacant, lastMonth',
  `accessStatus` varchar(20) DEFAULT NULL COMMENT 'lockboxBlack, lockboxGray, key, appointment',
  `petStatus` varchar(20) DEFAULT NULL,
  `reserve` varchar(20) DEFAULT NULL,
  `keyCode` varchar(10) DEFAULT NULL,
  `notes` text,
  `created` datetime DEFAULT NULL,
  `lasttime` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
*/

$_site = require_once(getenv("SITELOADNAME"));

$S = new $_site->className($_site);
  
/*
  The file initdata.csv is really just an example. It may have been valid at some time but surely not now.
  need rows 1 street, 2 number, 4 rname, 5 rphone1, 6 rphone2, 7 remail,
  9 leaseend, 13 oname, 14 oaddress, 17 ocity, 18 ostate, 19 ozip, 15 ophone, 16 oemail
  Need to put "oaddress, ocity, ostate, ozip" together.
*/

$row = 1;
if(($handle = fopen("initdata.csv", "r")) !== FALSE) {
  while(($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
    echo "<p>line $row: <br></p>\n";
    $row++;
    $rentalStreet = $data[1];
    $rentalNumber = $data[2];
    $renterName = $data[4];
    $renterPhone1 = $data[5];
    $renterPhone2 = $data[6];
    $renterEmail = $data[7];
    $leaseEndDate = $data[9];
    $ownerName = $data[13];
    $ownerAddress = "$data[14], $data[17], $data[18] $data[19]";
    $ownerPhone = $data[15];
    $ownerEmail = $data[16];

    $sql = "insert into rentinfo (rentalStreet, rentalNumber, ".
           "ownerName, ownerAddress, ".
           "ownerPhone, ownerEmail, ".
           "renterName, renterPhone1, renterPhone2, ".
           "renterEmail, leaseEndDate, created) ".
           "values('$rentalStreet', '$rentalNumber', ".
           "'$ownerName', '$ownerAddress', ".
           "'$ownerPhone', '$ownerEmail', ".
           "'$renterName', '$renterPhone1', '$renterPhone2', '$renterEmail', ".
           "'$leaseEndDate', now())";
    echo "$sql<br>";
    $S->query($sql);
  }
  fclose($handle);
}

