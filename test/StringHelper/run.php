<?php

require_once '../settings.php';

require_once PATH_SRC.'StringHelper.php';

global $th;

$html = <<<EOF
<!-- COMMENT -->
some text
<br><div class="test box" id="x1">
<a href="../link.html">Home </a>  |  <a href="https://another/link.html"> Somewhere </a>
</div>
Some more<br>text<br/>
EOF;

$th->run(1, 5);

