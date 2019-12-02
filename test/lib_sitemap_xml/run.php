<?php

global $th;

if (!isset($th)) {
  require_once(dirname(dirname(__DIR__)).'/src/TestHelper.class.php');
  $th = new rkphplib\TestHelper();
}

$th->load('src/lib/sitemap_xml.php');

$url_list = [ 
	'https://koeln.panorama.community/portfolio_virtueller-rundgang_koeln.html',
	'https://koeln.panorama.community/impressum_panorama.html',
	'https://koeln.panorama.community/index.html',
	'https://koeln.panorama.community/leistungen_panoramafotograf_koeln.html',
	'https://koeln.panorama.community/datenschutz_rundgang.html' ];

\rkphplib\lib\sitemap_xml($url_list, 'sitemap.xml');
