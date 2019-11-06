<?php

namespace rkphplib\lib;

require_once dirname(__DIR__).'/File.class.php';

use rkphplib\File;



/**
 * Create DOCROOT/sitemap.xml (= default $save_as) from $url_list.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 */
function sitemap_xml(array $url_list, string $save_as = '') : void {
	$xml = chr(60).'?xml version="1.0" encoding="UTF-8"?'.chr(62)."\n".chr(60).
		'urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"'.chr(62)."\n";

	if (empty($save_as)) {
		$save_as = DOCROOT.'/sitemap.xml';
	}

	File::save($save_as, $xml);
	$xml = '';
	$n = 0;

	foreach ($url_list as $url) {
		$xml .= "<url>\n<loc>".str_replace([ '&amp;', '&' ], [ '&', '&amp;' ], $url)."</loc>\n</url>\n";
		$n++;

		if ($n % 100 == 0) {
			File::append($save_as, $xml);
			$xml = '';
		}
	}

	$xml .= '</urlset>';
	File::append($save_as, $xml);
}
