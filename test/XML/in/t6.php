<?php

print_r(\rkphplib\XML::fromMap('Hallo'));
print_r(\rkphplib\XML::fromMap(['Hallo' => '', 'Welt' => '']));
print_r(\rkphplib\XML::fromMap(['Liste' => ['Roland', 'Peter', 'Mario'], 'Liste2' => ['A', 'B', 'C']]));

