<?php

namespace rkphplib\lib;


/**
 * Return $txt encoded as entity. Convert every character to '&#'.ord(char).';'.
 * Example: entity('|#|') = '&#124;&#35;&#124;'
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 */
function entity($txt) {
  $res = '';

  for ($i = 0; $i < strlen($txt); $i++) {
    $res .= '&#'.ord($txt[$i]).';';
  }

  return $res;
}

