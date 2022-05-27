<?php
// Footer file
// BLP 2018-02-24 -- added 'script' just before </body>

return <<<EOF
<footer>
<div id="address">
<address>
  Copyright &copy; $this->copyright<br>
$this->address<br>
<a href='mailto:bartonphillips@gmail.com'>bartonphillips@gmail.com</a>
</address>
</div>
{$b->msg}
{$b->msg1}
<br>
$lastmod
{$b->msg2}
</footer>
{$b->script}
</body>
</html>
EOF;
