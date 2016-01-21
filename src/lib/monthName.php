<?php

namespace rkphplib\lib;


/**
 * Return localized month names ($settings_LANGUAGE = en|de|hr). 
 *
 * Month is from [1,12].
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 * @param int $month
 * @return string
 */
function monthName($month) {
	global $settings_LANGUAGE;

	$month = ($month > 100000) ? intval(mb_substr($month, -2)) : intval($month);

	if ($month < 1 || $month > 12) {
		throw new Exception('invalid month', $month);
	}

	$lang = $settings_LANGUAGE;

	$month_names = array();

	$month_names['de'] = array('Januar', 'Februar', 'März', 'April', 'Mai',
		'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember');

	$month_names['en'] = array('January', 'February', 'March', 'April', 'Mai',
		'June', 'July', 'August', 'September', 'October', 'November', 'December');

	$month_names['hr'] = array('Sije&#269;anj', 'Velj&#269;a', 'O&#382;ujak', 'Travanj', 'Svibanj',
		'Lipanj', 'Srpanj', 'Kolovoz', 'Rujan', 'Listopad', 'Studeni', 'Prosinac');

	if (!isset($month_names[$lang])) {
		throw new Exception("no month names for [$lang]");
	}

	return $month_names[$lang][$month - 1];
}

