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

	$timezone = [ 'Europe/Berlin' => '+01:00' ];
	$tz = $timezone[SETTINGS_TIMEZONE];

	if (empty($save_as)) {
		$save_as = DOCROOT.'/sitemap.xml';
	}

	File::save($save_as, $xml);
	$xml = '';
	$n = 0;

	if (preg_match('/^(https?)\:\/\/(.+?)\//', $url_list[0], $match)) {
		$protocol = $match[1];
		$domain = $match[2];
		$url = $protocol.'://'.$domain.'/';
	}
	else {
		throw new Exception('failed to detect url '.$url_list[0]);
	}

	$url_len = mb_strlen($url);
	$url_path = [];
	
	$docroot = dirname($save_as);
	$index_file = [ 'index.html', 'index.php' ];
	$index_ts = '';

	foreach ($url_list as $link) {
		if (mb_substr($link, 0, $url_len) == $url) {
			$path = mb_substr($link, $url_len);

			if (in_array($path, $index_file)) {
				if (File::exists($docroot.'/'.$path)) {
					$index_ts = "\n\t\t<lastmod>".date('Y-m-d\TH:i:s', File::lastModified($docroot.'/'.$path)).$tz.
						"</lastmod>\n\t\t<priority>1.00</priority>";
				}
			}
			else {
				array_push($url_path, $path);
			}
		}
		else {
			throw new Exception('invalid url '.$url);
		}
	}

	$xml .= "\t<url>\n\t\t<loc>$url</loc>$index_ts\n\t</url>\n";

	foreach ($url_path as $path) {
		$path_ts = '';
		if (File::exists($docroot.'/'.$path)) {
			$path_ts = "\n\t\t<lastmod>".date('Y-m-d\TH:i:s', File::lastModified($docroot.'/'.$path)).$tz.
				"</lastmod>\n\t\t<priority>0.80</priority>";
		}

		$xml .= "\t<url>\n\t\t<loc>".$url.str_replace([ '&amp;', '&' ], [ '&', '&amp;' ], $path)."</loc>$path_ts\n\t</url>\n";
		$n++;

		if ($n % 100 == 0) {
			File::append($save_as, $xml);
			$xml = '';
		}
	}

	$xml .= "</urlset>\n";
	File::append($save_as, $xml);
}

