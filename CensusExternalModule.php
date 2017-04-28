<?php namespace ExternalModules;
require_once dirname(__FILE__) . '/../../external_modules/classes/ExternalModules.php';

class CensusExternalModule extends AbstractExternalModule
{
        function hook_save_record($project_id, $record, $instrument, $event_id, $group_id, $survey_hash, $response_id) {
              	$module_data = ExternalModules::getProjectSettingsAsArray(array("vanderbilt_census_geocoder"), $project_id);
		if ($project_id && $record && ($instrument == $module_data["instrument"]['value'])) {
			\REDCap::allowProjects(array($project_id));
			$addressField = $module_data['address']['value'];
			$keys = $module_data['keys']['value'];
			if (!is_array($keys)) {
				$keys = array($keys);
			}
			$fields = $module_data['fields']['value'];
			if (!is_array($fields)) {
				$fields = array($fields);
			}

			if (count($keys) == count($fields)) {
				$rcData = \REDCap::getData($project_id, "array", array($record), array($addressField), array($event_id));
				$address = $rcData[$record][$event_id][$addressField];
				$encodedAddress = preg_replace("/\s+/", "+", $address);
				$encodedAddress = preg_replace("/United States/", "+", $encodedAddress);

				$url = "https://geocoding.geo.census.gov/geocoder/geographies/onelineaddress?address=".$encodedAddress."&benchmark=Public_AR_Census2010&vintage=Census2010_Census2010&layers=14&format=json";
				# city, state, zip encoding
				// $url = "https://geocoding.geo.census.gov/geocoder/geographies/address?street=&city=&state=&zip=&benchmark=Public_AR_Census2010&vintage=Census2010_Census2010&layers=14&format=json";
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

				$json = json_decode($output, true);
				# make into associative array for ease of concept; not necessary
				$toLookup = array();
				$i = 0;
				foreach($keys as $key) {
					$toLookup[$key] = $fields[$i];
					$i++;
				}

				$firstField = \REDCap::getRecordIdField();
				$uploadJson = array(array($firstField => $record));
				$i = 0;
				foreach ($toLookup as $key => $field) {
					$uploadJson[0][$field] = "";
					$i++;
				}
				if ($i > 0) {
					# overwrite with blanks
					$response = \REDCap::saveData('json', json_encode($uploadJson), 'overwrite');
					if (count($response['errors']) > 0) {
						foreach ($response['errors'] as $error) {
							echo "<script>alert('".addslashes($error)."');</script>";
						}
						die("Failed at first upload:<br>".implode("<br>", $response['errors']));
					}
				}

				if (isset($json['result']['addressMatches'][0]['geographies'])) {
					$lookupTable = $json['result']['addressMatches'][0]["geographies"]["Census Blocks"][0];

					# formulate JSON
					$uploadJson = array(array());
					$varsToUpload = 0;
					foreach ($toLookup as $key => $field) {
						if (isset($lookupTable[$key])) {
							if ($field) {
								$value = $lookupTable[$key];
								$uploadJson[0][$field] = $value;
								$varsToUpload++;
							}
						}
					}

					# if have data to upload
					if ($varsToUpload > 0) {
						$uploadJson[0][$firstField] = $record;
						# overwrite with blanks
						$response = \REDCap::saveData('json', json_encode($uploadJson), 'overwrite');
						if (count($response['errors']) > 0) {
							foreach ($response['errors'] as $error) {
								echo "<script>alert('".addslashes($error)."');</script>";
							}
							die("Failed at second upload:<br>".implode("<br>", $response['errors']));
						}
					}
				}
			}
		}
	}
}
