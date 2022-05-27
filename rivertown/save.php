/*
// Medium table for computers

echo <<<EOF
<table class="mediumtable" border='1'>
<thead>
<!-- First table header -->
<tr>
<th>ID</th><th>RtlStreet</th><th>RtlNumber</th>
</tr>
</thead>
<tbody>
EOF;

while($row = $S->fetchrow('assoc')) {
  extract($row);

  echo <<<EOF
<!-- Add the body info -->
<tr>
<td><a href="addedit.php?id=$id">$id</a></td><td>$rentalStreet</td><td>$rentalNumber</td>
</tr>
EOF;
}

// Second table header

echo <<<EOF
</tbody>
</table>
<br>
<table class="mediumtable" border="1">
<thead>
<tr>
<th>ID</th>
<th>O Name</th><th>O Addr</th>
<th>O Phone</th><th>O Email</th>
</tr>
</thead>
<tbody>
EOF;

$sql = "select * from rentinfo";
$S->query($sql);

while($row = $S->fetchrow("assoc")) {
  extract($row);
  
  echo <<<EOF
<tr>
<td><a href="addedit.php?id=$id">$id</a></td>
<td>$ownerName</td>
<td>$ownerAddress</td>
<td>$ownerPhone</td><td>$ownerEmail</td>
</tr>
EOF;
}

// Third table header

echo <<<EOF
</tbody>
</table>
<br>
<table class="mediumtable" border="1">
<thead>
<tr>
<th>ID</th>
<th>R Acct</th><th>R Name</th><th>R Phone1</th><th>R Phone2</th>
<th>R Email</th>
</tr>
</thead>
<tbody>
EOF;

$sql = "select * from rentinfo";
$S->query($sql);

while($row = $S->fetchrow("assoc")) {
  extract($row);
  
  echo <<<EOF
<tr>
<td><a href="addedit.php?id=$id">$id</a></td>
<td>$renterAccount</td><td>$renterName</td>
<td>$renterPhone1</td><td>$renterPhone2</td><td>$renterEmail</td>
</tr>
EOF;
}

// Forth table header

echo <<<EOF
</tbody>
</table>
<br>
<table class="mediumtable" border="1">
<thead>
<tr>
<th>ID</th>           
<th>L End</th>
<th>RtlStat</th><th>AccessStat</th>
<th>PStat</th>
</tr>
</thead>
<tbody>
EOF;

$sql = "select * from rentinfo";
$S->query($sql);

while($row = $S->fetchrow('assoc')) {
  extract($row);

  echo <<<EOF
<!-- Add the body info -->
<tr>
<td><a href="addedit.php?id=$id">$id</a></td>
<td>$leaseEndDate</td>
<td>$rentalStatus</td><td>$accessStatus</td><td>$petStatus</td>
</tr>
EOF;
}

echo <<<EOF
</tbody>
</table>
EOF;
*/

// Small table for phones

/*$sql = "select id, rentalStreet, rentalNumber, ownerName, ownerPhone, renterName, renterPhone1 from rentinfo";
$S->query($sql);
*/

echo <<<EOF
<table class="smalltable" border='1'>
<thead>
<!-- Display table header -->
<tr>
<th>ID</th><th>RtlStreet</th>
<th>RtlNumber</th>
<th>O Name</th>
<th>O Phone</th>
<th>R Name</th><th>R Phone</th>
</tr>
</thead>
<tbody>
EOF;

while($row = $S->fetchrow('assoc')) {
  extract($row);

  echo <<<EOF
<!-- Add the body info -->
<tr>
<td><a href="addedit.php?id=$id">$id</a></td><td>$rentalStreet</td>
<td>$rentalNumber</td>
<td>$ownerName</td>
<td>$ownerPhone</td>
<td>$renterName</td>
<td>$renterPhone1</td>
</tr>
EOF;
}
