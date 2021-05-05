<?php

\rkphplib\Dir::copy('out/t2', 'out/t2a');
print "scanTree(out/t2a): ".join('|', \rkphplib\Dir::scanTree('out/t2a'))."\n";
\rkphplib\Dir::copy('out/t2', 'out/t2a');
print "scanTree(out/t2a): ".join('|', \rkphplib\Dir::scanTree('out/t2a'))."\n";

