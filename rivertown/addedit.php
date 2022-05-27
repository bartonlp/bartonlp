<?php
// addedit.php. Add new or edit old entries in database
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
) ENGINE=InnoDB AUTO_INCREMENT=205 DEFAULT CHARSET=utf8;
*/

$_site = require_once(getenv("SITELOADNAME"));
$S = new $_site->className($_site);

$f->script = <<<EOF
<script>
  $('#main tr').on('click', function(ev) {
  var key;
  
  if(key = $(this).find('a').length) {
    key = $(this).find('a')[0].href;
  } else {
    key = $(this).parents('#main tr').find("a")[0].href;
  }
  document.location = key;
});

// For either class=phone or class=date

$('.phone,.date')
.on('keypress', function(e) {
  var key = e.charCode || e.keyCode || 0;
  var item = $(this);
  var myclass = item.attr('class');
  var phone = {delim: '-', pos: [3,7,12]};
  var date = {delim: '/', pos: [2,5,10]};

  if(myclass == 'phone') {
    var delim = phone.delim;
    var pos = phone.pos;
  } else {
    var delim = date.delim;
    var pos = date.pos;
  }

  // Auto-format- do not expose the mask as the user begins to type
  if (key !== 8 && key !== 9) {
    if (item.val().length === pos[0]) {
      item.val(item.val() + delim);
    }
    if (item.val().length === pos[1]) {
      item.val(item.val() + delim);
    }
    if (item.val().length >= pos[2]) {
      item.val(item.val().slice(0, pos[2] -1));
    }
  }

  // Allow numeric (and tab, backspace, delete) keys only
  return (key == 8 ||
    key == 9 ||
    key == 46 ||
    (key >= 48 && key <= 57) ||
    (key >= 96 && key <= 105));
});
</script>
EOF;

$h->css = <<<EOF
<style>
table {font-size: 16px; margin-left: auto; margin-right: auto;}
#main td:first-child { font-size: 20px; width: 20px; padding: 3px;}
#input { width: 100%; max-width: 600px; }
#input th:first-child {
  width: 130px; text-align: left;
  padding: 0px 3px 0px 3px; background: lightblue;
}
#input td input { width: 100%; }
.inner { width: 100%; text-align: left; }
.inner th { width: 20px; }
/*table td:nth-child(2) { width: 100px; }*/
form { text-align: center; }
button { color: red; }
input[type='submit'] { color: red; }
</style>
EOF;

list($top, $footer) = $S->getPageTopBottom($h, $f);

echo <<<EOF
$top
EOF;

// if $_POST['submit'] == "Cancel" we just fall through to redisplay data

// Add a new listing. Gather the rentinfo data

if($_POST['submit'] == 'add') {
  getData("");
  exit();
}

// Delete rentinfo given ID

if($_POST['submit'] == 'Delete') {
  $id = $_POST["id"];
  $sql = "delete from rentinfo where id='$id'";
  $S->query($sql);
  echo "<h2>rentinfo record with id=$id has been deleted</h2>";

  // fall throgh to redisplay table
}

// Display via ID

if($xid = $_GET['id']) {
  $sql = "select * from rentinfo where id=$xid";
  $S->query($sql);
  $row = $S->fetchrow("assoc");
  getData($row);
  exit();
}

// Post the rentinfo data. Do the insert or update to the database.

if($_POST['submit'] == "Post") {
  // We need to sanitize the input by escaping all of the fields just in cases.
  // This will escape the dreded '!
  
  foreach($_POST as $k=>$v) {
    $post[$k] = $S->escape($v);
  }
  
  extract($post);

  if(!$id) {
    // This is an ADD because we have NO id.

    $sql = "insert into rentinfo (rentalStreet, rentalNumber, ".
           "ownerName, ownerAddress, ".
           "ownerPhone, ownerPhone2, ownerEmail, ".
           "renterAccount, renterName, renterPhone1, renterPhone2,".
           "renterEmail, leaseEndDate, rentalStatus, accessStatus, petStatus, reserve, ".
           "keyCode, deposit, created) ".
           "values('$rentalStreet', '$rentalNumber' ".
           "'$ownerName', '$ownerAddress', ".
           "'$ownerPhone', '$ownerPhone2', '$ownerEmail', ".
           "'$renterAccount', '$renterName', ".
           "'$renterPhone1', '$renterPhone2', '$renterEmail', ".
           "'$leaseEndDate', '$rentalStatus', '$accessStatus', '$petStatus', '$reserve', ".
           "'$keyCode', $deposit, now())";

    $S->query($sql);
  } else {
    // This is an EDIT because we have an 'id'

    $sql = "update rentinfo set rentalStreet='$rentalStreet', rentalNumber='$rentalNumber', ".
           "ownerName='$ownerName', ".
           "ownerAddress='$ownerAddress', ".
           "ownerPhone='$ownerPhone', ownerPhone2='$ownerPhone2', ownerEmail='$ownerEmail', ".
           "renterAccount='$renterAccount', renterName='$renterName', ".
           "renterPhone1='$renterPhone1', renterPhone2='$renterPhone2', ".
           "renterEmail='$renterEmail', ".
           "leaseEndDate='$leaseEndDate', rentalStatus='$rentalStatus', accessStatus='$accessStatus', ".
           "petStatus='$petStatus', reserve='$reserve', keyCode='$keyCode', deposit='$deposit' ".
           "where id=$id";
      
    $S->query($sql);
  }

  // fall through to display info
}

// Display the current info in the table.
// This is the default at start, after a post or add, and if getData does a Cancel.

$sql = "select * from rentinfo";
$S->query($sql);

// Table thead

echo <<<EOF
<form action="addedit.php" method="post">
<button type="submit" name='submit' value="add">Add New Rental</button>
</form>
<br>
<form action="lookup.php" method="post">
<input type="submit" value="Search For Info">
</form>
<br>
<table id="main" border='1'>
<thead>
<tr>
<th>Account</th><th>Street</th>
<th>Owner</th>
<th>Tenant</th>
<th>Lease End</th>
<th>Status</th><th>Access</th>
<th>Pets</th>
</tr>
</thead>
<tbody>
EOF;

// Now loop through all rows

while($row = $S->fetchrow("assoc")) {
  extract($row);

  $ophone2 = $ownerPhone2 ? ", $ownerPhone2" : "";
  $phone2 = $renterPhone2 ? ", $renterPhone2" : "";

  // Use $id with a 'get' to index into table.

  if(!$renterAccount) {
    $renterAccount = "***";
  }
  
  echo <<<EOF
<td><a href="addedit.php?id=$id">$renterAccount</a></td>
<td>$rentalStreet, $rentalNumber<br>Key Code: $keyCode</td>
<td>
<table class="inner">
<tr><th>Name</th><td>$ownerName</td></tr>
<tr><th>Address</th><td>$ownerAddress</td></tr>
<tr><th>Phone</th><td>$ownerPhone{$ophone2}</td></tr>
<tr><th>Email</th><td>$ownerEmail</td></tr>
<tr><th>Reserve</th><td>$reserve</td></tr>
</table>
</td>
<td>
<table class="inner">
<tr><th>Name</th><td>$renterName</td></tr>
<tr><th>Phone</th><td>$renterPhone1{$phone2}</td></tr>
<tr><th>Email</th><td>$renterEmail</td></tr>
<tr><th>Deposit</th><td>$deposit</td></tr>
</table>
</td>
<td>$leaseEndDate</td>
<td>$rentalStatus</td><td>$accessStatus</td><td>$petStatus</td>
</tr>
EOF;
}

// Add the tail. Two forms one for 'add' the other for 'Search For Info'

echo <<<EOF
</tbody>
</table>
$footer
EOF;
exit();

// functions

// This is used by 'add' and 'edit'
// We display a table with input statements

function getData($row) {
  global $footer;


  if($row) extract($row); // if no $row then this is 'add' else 'edit'

  // Set up the select statements
  
  $rstat = array("Vacant", "Occupied", "Last Month");
  $rstatOptions = "";
  if(!$rentalStatus) {
    $rstatOptions = "<option value='' selected>Select</option>";
  }
  for($i=0; $i<count($rstat); ++$i) {
    $rstatOptions .= "<option " .($rentalStatus == $rstat[$i] ? "selected" : "") . ">$rstat[$i]</option>";
  }

  $astat = array("Lockbox Black", "Lockbox Gray", "Key", "Appointment");
  $astatOptions = "";
  if(!$accessStatus) {
    $astatOptions = "<option value='' selected>Select</option>";
  }
  for($i=0; $i<count($astat); ++$i) {
    $astatOptions .= "<option " .($accessStatus == $astat[$i] ? "selected" : "") . ">$astat[$i]</option>";
  }

  $pstat = array("No", "Yes", "Negot");
  $pstatOptions = "";
  if(!$petStatus) {
    $pstatOptions = "<option value='' selected>Select</option>";
  }
  
  for($i=0; $i < count($pstat); ++$i) {
    $pstatOptions .= "<option " .($petStatus == $pstat[$i] ? "selected" : "") . ">$pstat[$i]</option>";
  }
  
  echo <<<EOF
<form action="addedit.php" method="post">
<table id="input" border="1">
<tr><th>Street</th><td><input type="text" name="rentalStreet" value="$rentalStreet"></td></tr>
<tr><th>Number</th><td><input type="text" name="rentalNumber" value="$rentalNumber"></td></tr>
<tr><th>Owner Name</th><td><input type="text" name="ownerName" value="$ownerName"></td></tr>
<tr><th>Owner Address</th><td><input type="text" name="ownerAddress" value="$ownerAddress"></td></tr>
<tr><th>Owner Phone</th><td><input type="tel" name="ownerPhone" value="$ownerPhone" class="phone" maxlength="12" placeholder="nnn-nnn-nnnn"></td></tr>
<tr><th>Owner Phone2</th><td><input type="tel" name="ownerPhone2" value="$ownerPhone2" class="phone" maxlength="12" placeholder="nnn-nnn-nnnn"></td></tr>
<tr><th>Owner Email</th><td><input type="text" name="ownerEmail" value="$ownerEmail"></td></tr>
<tr><th>Tenant Account</th><td><input type="text" name="renterAccount" value="$renterAccount"></td><tr>
<tr><th>Tenant Name</th><td><input type="text" name="renterName" value="$renterName"></td></tr>
<tr><th>Tenant Phone1</th><td><input type="tel" name="renterPhone1" value="$renterPhone1" class="phone" maxlength="12" placeholder="nnn-nnn-nnnn"></td></tr>
<tr><th>Tenant Phone2</th><td><input type="tel" name="renterPhone2" value="$renterPhone2" class="phone" maxlength="12" placeholder="nnn-nnn-nnnn"></td></tr>
<tr><th>Tenant Email</th><td><input type="text" name="renterEmail" value="$renterEmail"></td></tr>
<tr><th>Deposit</th><td><input type="text" name="deposit" value="$deposit"></td></tr>
<tr><th>Lease End Date</th><td><input type="text" class="date" name="leaseEndDate" value="$leaseEndDate" maxlength="10" placeholder="mm/dd/yyyy"></td></tr>
<tr><th>Status</th>
<td>
<select name="rentalStatus">
$rstatOptions
</select>
</td>
</tr>
<tr><th>Access</th>
<td>
<select name="accessStatus">
$astatOptions
</select>
</td>
</tr>
<tr>
<th>Pets</th>
<td>
<select name="petStatus">
$pstatOptions
</select>
</td>
</tr>
<tr>
<th>Key Code</th><td><input type="text" name="keyCode" value="$keyCode"></td></tr>
<th>Reserve</th><td><input type="text" name="reserve" value="$reserve"></td></tr>
</table>
<input type="hidden" name="id" value="$id">
<input type="submit" name="submit" value="Post">&nbsp;
<input type="submit" value="Cancel">&nbsp;
<input type="submit" name="submit" value="Delete">
</form>
$footer
EOF;
}
