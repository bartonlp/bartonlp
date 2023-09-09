<?php
// BLP 2023-02-25 - use new approach
// Do listing look ups.
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

// Set up the header info in $S

$S->css = <<<EOF
td a { font-size: 30px; }  
button { color: red; }
input[type='submit'] { color: red;}
#biglabel { font-size: 30px; }
EOF;

[$top, $footer] = $S->getPageTopBottom();

echo <<<EOF
$top
<div class='contain'>
EOF;

// There was more than one result rows so get all the data via the id.

if($id = $_GET['id']) {
  $sql = "select * from rentinfo where id=$id";
  $S->query($sql);
  $row = $S->fetchrow("assoc");
  displayData($row);
  exit;
}

// Display the options for lookup.

if($_POST['options']) {
  $option = $_POST["option"];

  echo <<<EOF
<p style="text-align: left;">You may enter as much or as little as you want. For example if the
you are looking for a street name like 'Lock street' you could enter only 'l'.
Case does not mater. If there are more than one
listing with that starting street name all of the listing will be displayed and you can
select the right one.</p>
EOF;

  switch($option) {
    case "By Rental Street Name":
      echo <<<EOF
<form action="lookup.php" method="post">
Get Rental Street Name: <input type="text" name="addr">
<input type="submit" value="Submit">
EOF;
      break;

    case "By Owner's Name":
      echo <<<EOF
<form action="lookup.php" method="post">
Get Owner's Last Name: <input type="text" name="oname">
<input type="submit" value="Submit">
EOF;
      break;
    case "By Owner's Phone Number":
      echo <<<EOF
<form action="lookup.php" method="post">
Get Owner's Phone Number: <input type="text" name="ophone" class="phone">
<input type="submit" value="Submit">
EOF;
      break;
    case "By Owner's Email Address":
      echo <<<EOF
<form action="lookup.php" method="post">
Get Owner's Email Address: <input type="text" name="oemail">
<input type="submit" value="Submit">
EOF;
      break;
    case "By Account Number":
      echo <<<EOF
<form action="lookup.php" method="post">
Get Renter's Account Number: <input type="text" name="raccnt">
<input type="submit" value="Submit">
EOF;
      break;
      
    case "By Tenant's Name":
      echo <<<EOF
<form action="lookup.php" method="post">
Get Renter's Name: <input type="text" name="rname">
<input type="submit" value="Submit">
EOF;
      break;
      
    case "By Tenant's Phone Number":
      echo <<<EOF
<form action="lookup.php" method="post">
Get Renter's Phone: <input type="text" name="rphone">
<input type="submit" value="Submit">
EOF;
      break;
      
    case "By Tenant's Email Address":
      echo <<<EOF
<form action="lookup.php" method="post">
Get Renter's Email Address: <input type="text" name="remail">
<input type="submit" value="Submit">
EOF;
      break;
      
    default:
      echo "default<br>";
      break;
  }

  echo <<<EOF
<br>
<input type="submit" value="Cancel">
</form>
$footer
EOF;
  exit();
}

// Get information given the $_POST value.

if($_POST) {
  if($ophone = $_POST['ophone']) {
    $sql = "select * from rentinfo where ownerPhone like('$ophone%')";
  } else if($oname = $_POST['oname']) {
    $sql = "select * from rentinfo where ownerName like('$oname%')";
  } else if($addr = $_POST['addr']) {
    $sql = "select * from rentinfo where rentalStreet like ('$addr%')";
  } else if($oemail = $_POST['oemail']) {
    $sql = "select * from rentinfo where ownerEmail like('$oemail%')";
  } else if($raccnt = $_POST['raccnt']) {
    $sql = "select * from rentinfo where renterAccount like('$raccnt%')";
  } else if($rname = $_POST['rname']) {
    $sql = "select * from rentinfo where renterName like('$rname%')";
  } else if($rphone = $_POST['rphone']) {
    $sql = "select * from rentinfo where renterPhone1 like('$rphone%') || renterPhone2 like('$rphone%')";
  } else if($remail = $_POST['remail']) {
    $sql = "select * from rentinfo where renterEmail like('$remail%')";
  } else {
    displayPage();
    exit();
  }
  //echo "sql: $sql<br>";
  $n = $S->query($sql);

  if($n == 0) {
    // No data found. Redisplay table with error message
    displayAll(true);
    exit();
  }

  if($n > 1) {
    // More than one listing so display a smaller table with possible results.
    displayResults($S, false);
    exit();
  }

  // This is the selected listing so display it.
  
  $row = $S->fetchrow('assoc');
  extract($row);

  displayData($row);
  exit();
}

// This is the defalt page

displayPage();

// functions

function displayPage() {  
  echo <<<EOF
<form action="lookup.php" method="post">
<label id="biglabel" for="select">Search</label>
<select id="select" name="option">
<option>By Street Name</option>
<option>By Owner's Name</option>
<option>By Owner's Phone Number</option>
<option>By Owner's Email Address</option>
<option>By Account Number</option>
<option>By Tenant's Name</option>
<option>By Tenant's Phone Number</option>
<option>By Tenant's Email Address</option>
</select>
<input type="submit" name="options" value="Submit">

</form>
<br><br>
<form action="addedit.php" method="post">
<input type="submit" value="Add or Edit Listings">
</form>
<br><br>
EOF;
  displayAll(false);
  exit();
}

// Display all listings.

function displayAll($tf) {
  global $S;

  // If $tf is true we need to display error message before showing everything again.
  
  if($tf) {
    echo <<<EOF
<h2>Listing Not Found</h2>
EOF;
  }
  
  $sql = "select id, rentalStreet, rentalNumber, ownerName, ownerPhone, renterName, renterPhone1 from rentinfo";

  $S->query($sql);
  
  displayResults($S, $tf);
}

function displayResults($S, $tf) {
  global $footer;
  
  echo <<<EOF
<table class="small" border='1'>
<thead>
<!-- Display table header -->
<tr>
<th>ID</th><th>Street</th>
<th>Owner Name<br>Owner Phone</th>
<th>Tenent Name<br>Tenent Phone</th>
</thead>
<tbody>
EOF;

  // Loop through the rows
  
  while($row = $S->fetchrow('assoc')) {
    extract($row);

    // We use $id to do a 'get' to index into table.
    
    echo <<<EOF
<!-- Add the body info -->
<tr>
<td><a href="lookup.php?id=$id">$id</a></td>
<td>$rentalStreet, $rentalNumber</td>
<td>$ownerName<br>$ownerPhone</td>
<td>$renterName<br>$renterPhone1</td>
</tr>
EOF;
  }

  echo <<<EOF
</tbody>
</table>
<form action="lookup.php" method="post">
<input type="submit" value="Return to Search">
</form>
<div>
$footer
EOF;

  exit();
}

// Display a tabale of selected results or everything if called from 'displayAll'

function displayResultsFull($S, $tf) {
  global $footer;
  
  echo <<<EOF
<table border='1'>
<thead>
<!-- Display table header -->
<tr>
<th>ID</th><th>Street</th>
<th>Number</th>
<th>Owner Name</th>
<th>Owner Phone</th>
<th>Tenent Name</th><th>Tenent Phone</th>
</tr>
</thead>
<tbody>
EOF;

  // Loop through the rows
  
  while($row = $S->fetchrow('assoc')) {
    extract($row);

    // We use $id to do a 'get' to index into table.
    
    echo <<<EOF
<!-- Add the body info -->
<tr>
<td><a href="lookup.php?id=$id">$id</a></td>
<td>$rentalStreet</td>
<td>$rentalNumber</td>
<td>$ownerName</td>
<td>$ownerPhone</td>
<td>$renterName</td>
<td>$renterPhone1</td>
</tr>
EOF;
  }

  echo <<<EOF
</tbody>
</table>
<form action="lookup.php" method="post">
<input type="submit" value="Return to Search">
</form>
<div>
$footer
EOF;

  exit();
}

// Display Selected listing as a input form

function displayData($row) {
  global $footer;
  
  extract($row);

  echo <<<EOF
<form action="lookup.php" method="post">
<table>
<tr><td>Rental Street:</td><td><input type="text" name="rentalStreet" value="$rentalStreet" readonly></td></tr>
<tr><td>Rental Number:</td><td><input type="text" name="rentalNumber" value="$rentalNumber" readonly></td></tr>
<tr><td>Owner Name:</td><td><input type="text" name="ownerName" value="$ownerName" readonly></td></tr>
<tr><td>Owner Address:</td><td><input type="text" name="ownerAddress" value="$ownerAddress" readonly></td></tr>
<tr><td>Owner Phone:</td><td><input type="tel" name="ownerPhone" value="$ownerPhone" class="phone" readonly></td></tr>
<tr><td>Owner Email:</td><td><input type="text" name="ownerEmail" value="$ownerEmail" readonly></td></tr>
<tr><td>Renter Account:</td><td><input type="text" name="renterAccount" value="$renterAccount" readonly></td><tr>
<tr><td>Renter Name:</td><td><input type="text" name="renterName" value="$renterName" readonly></td></tr>
<tr><td>Renter Phone1:</td><td><input type="tel" name="renterPhone1" value="$renterPhone1" class="phone" readonly></td></tr>
<tr><td>Renter Phone2:</td><td><input type="tel" name="renterPhone2" value="$renterPhone2" class="phone" readonly></td></tr>
<tr><td>Renter Email:</td><td><input type="text" name="renterEmail" value="$renterEmail" readonly></td></tr>
<tr><td>Lease End Date:</td><td><input type="text" name="leaseEndDate" value="$leaseEndDate" readonly></td></tr>
<tr><td>Rental Status:</td>
<td><input type="text" name="rentalStatus" value="$rentalStatus" readonly></td></tr>
<tr><td>Access Status:</td>
<td><input type="text" name="accessStatus" value="$accessStatus" readonly></td></tr>
<tr><td>Pet Status:</td>
<td><input type="text" name="petStatus" value="$petStatus" readonly></td></tr>
</table>
<input type="submit" value="Return to Search">
</form>
</div>
$footer
EOF;
}
