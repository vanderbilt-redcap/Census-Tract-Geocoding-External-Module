<?php namespace ExternalModules;
require_once dirname(__FILE__) . '/../../external_modules/classes/ExternalModules.php';

class CensusExternalModule extends AbstractExternalModule
{
        function hook_survey_page($project_id, $record, $instrument, $event_id, $group_id, $survey_hash, $response_id) {
		$this->addScript($project_id, $record, $instrument, $event_id, $group_id, $survey_hash, $response_id);
	}

        function hook_data_entry_form($project_id, $record, $instrument, $event_id, $group_id, $survey_hash, $response_id) {
		$this->addScript($project_id, $record, $instrument, $event_id, $group_id);
	}

        function addScript($project_id, $record, $instrument, $event_id, $group_id, $survey_hash = null, $response_id = null) {
              	$module_data = ExternalModules::getProjectSettingsAsArray(array("vanderbilt_census_geocoder"), $project_id);
		if ($project_id && $record && ($instrument == $module_data["instrument"]['value'])) {
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
				echo "<script>
					function downloadCensusData() {
						var address = $('#".$addressField."').val();
						var keys = ".json_encode($keys).";
						var fields = ".json_encode($fields).";

						if (address) {
							var encodedAddress = address.replace(/\s+/, '+');
							encodedAddress = encodedAddress.replace(/United States/, '+');
							var url = 'https://geocoding.geo.census.gov/geocoder/geographies/onelineaddress?address='+encodedAddress+'&benchmark=Public_AR_Census2010&vintage=Census2010_Census2010&layers=14&format=json';
							$.post(url, {}, function(json) {
								var data = JSON.parse(json);

								if (data && data['result'] && data['result']['addressMatches'] && data['result']['addressMatches'][0] && data['result']['addressMatches'][0]['geographies'] && data['result']['addressMatches'][0]['geographies']['Census Blocks']) {
									var lookupTable = data['result']['addressMatches'][0]['geographies']['Census Blocks'][0];
									for (var i=0; i < fields.length; i++) {
										if (lookupTable[keys[i]]) {
											$('#'+fields[i]).val(lookupTable[keys[i]]);
										} else {
											$('#'+fields[i]).val('');
										}
									}
								});
						}
					}
					$('#'".$addressField.").blur(function() {
						downloadCensusData();
					});
				echo </script>";
			}
		}
	}
}
