<?php

if ($_POST['get']) {
	$lat = (float)$_POST['lat'];
	$long = (float)$_POST['long'];
	$year = $_POST['year'];
	$benchmark_vintage = $_POST['benchmark_vintage'];
	if ($benchmark_vintage) {
		$url = 'https://geocoding.geo.census.gov/geocoder/geographies/coordinates?y='.$lat.'&x='.$long."&".$module->getSharedArgsBenchmark($year);
	} else {
		$url = 'https://geocoding.geo.census.gov/geocoder/geographies/coordinates?y='.$lat.'&x='.$long."&".$module->getSharedArgsYear($year);
	}
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_VERBOSE, 0);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt($ch, CURLOPT_AUTOREFERER, true);
	curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
	curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);
	$output = curl_exec($ch);
	curl_close($ch);

	echo $output;
}
