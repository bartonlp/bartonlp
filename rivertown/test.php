<?php
// BLP 2023-02-25 - use new approach
// test.php. A test of the keypress logic
      
$_site = require_once(getenv("SITELOADNAME"));

$S = new $_site->className($_site);

$S->b_inlineScript = <<<EOF
$('.phone,.date').on('keypress', function(e) {
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
EOF;

[$top, $footer] = $S->getPageTopBottom();

echo <<<EOF
$top
<form id="example-form" name="my-form">
  <label for="phone-number">Phone number:</label>
  <br />
  <!-- I used an input type of text here so browsers like Chrome do not display the spin box -->
  <input class="phone" id="phone" name="phone-number" type="text" maxlength="12" placeholder="XXX-XXX-XXXX" />
  <br />
  <label for="lease">Date:</label>
  <input class="date" id="lease" name="lease" type="text" maxlength="10" placeholder="XX/XX/XXXX" />
  <br />
  <input type="button" value="Submit" />
</form>  
$footer
EOF;
