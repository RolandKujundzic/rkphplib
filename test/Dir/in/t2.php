<?php

\rkphplib\Dir::create('out/t2');
\rkphplib\Dir::create('out/t2/a/b', 0, true);
\rkphplib\File::save('out/t2/1.txt', 1);
\rkphplib\File::save('out/t2/a/2.txt', 1);
\rkphplib\File::save('out/t2/a/b/3.txt', 1);

print "scan(out/t2): ".join('|', \rkphplib\Dir::scan('out/t2'))."\n";
print "scanTree(out/t2): ".join('|', \rkphplib\Dir::scanTree('out/t2'))."\n";
