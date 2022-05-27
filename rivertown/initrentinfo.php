<?php
$_site = require_once(getenv("SITELOADNAME"));
ErrorClass::setDevelopment(true);

$S = new $_site->className($_site);
  
/* need rows 1 street, 2 number, 4 rname, 5 rphone1, 6 rphone2, 7 remail,
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
?>
