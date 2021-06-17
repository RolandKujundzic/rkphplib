<?php

namespace rkphplib;

require_once __DIR__.'/File.php';


/**
 * Load and Convert yaml files. Requires symphony/yaml:
 *
 * require_once('vendor/autoload.php');
 * shell> composer require symfony/yaml 
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 */
class YAML {

/**
 * Return yaml file converted to php multi-map.
 *
 * @param string $file
 * @return multi-map
 */
public static function load($file) {
  $parser = new \Symfony\Component\Yaml\Parser();
	return $parser->parse(File::load($file));
}


/**
 * Save php object as yaml file
 *
 * @param string $file
 * @param multi-map $object
 */
public static function save($file, $object) {
	$dumper = new \Symfony\Component\Yaml\Dumper(2);
	File::save($file, $dumper->dump($object, 40));
}

}

