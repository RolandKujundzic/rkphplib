<?php

$cat_str = <<<EOL
a,,A
	aa,a,AA
	ab,a,AB
b,,B
	ba,b,BA
		baa,ba,BAA
	bb,b,BB
		bba,bb,BBA
c,,C
EOL;

category_sid_level(null);
cat_tree($cat_str);

