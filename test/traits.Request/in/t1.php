<?php

$t = new RTest([
    "item/color.weiß" => "white",
    "item/color.königsblau" => "#4169e1",
    "item/color.schwarz" => "black",
    "item/color.marineblau" => "#000080",
    "item/color.grau" => "#888",
    "item/color.grün" => "#008800",
    "item/color.orange" => "#ffa500",
    "item/color.gelb" => "#f5ff00",
    "item/color.rot" => "#880000",
    "item/color.khaki" => "#bdb76b"
]);

print_r($t->get('/'));
