<?php

namespace rkphplib;

require_once(__DIR__.'/File.class.php');

use \rkphplib\File;

if (File::exists('vendor/autoload.php')) {
  require_once('vendor/autoload.php');
}


/**
 * Load and Convert yaml files. Requires symphony/yaml:
 *
 * shell> composer require symfony/yaml 
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 */
class Yaml {

/**
 * Return yaml file converted to php multi-map.
 *
 * @param string $file
 * @return multi-map
 */
public static function load($file) {
  $parser = new \Symfony\Component\Yaml\Parser();
	return $parser->parse(File::load($yaml_file));
}


/**
 * Save php object as yaml file
 *
 * @param string $file
 * @param multi-map $object
 */
public static function save($file, $object) {
	$dumper = new \Symfony\Component\Yaml\Dumper();
	File::save($file, $dumper->dump($object)); 
}

}

