<?php

if ($_POST['get']) {
	$address = url_encode(preg_replace("/[^a-zA-Z0-9 ,]/","",$_POST['address']));
	$year = $_POST['year'];
    $url = 'https://geocoding.geo.census.gov/geocoder/geographies/onelineaddress?address='.$address.'&'.$module->getSharedArgs($census['year']);
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
