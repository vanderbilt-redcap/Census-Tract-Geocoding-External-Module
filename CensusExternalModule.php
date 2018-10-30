<?php namespace Vanderbilt\CensusExternalModule;

use ExternalModules\AbstractExternalModule;
use ExternalModules\ExternalModules;

class CensusExternalModule extends AbstractExternalModule
{
	function hook_survey_page($project_id, $record, $instrument, $event_id, $group_id, $survey_hash, $response_id) {
		$this->addScript($project_id, $record, $instrument, $event_id, $group_id, $survey_hash, $response_id);
	}

	function hook_data_entry_form($project_id, $record, $instrument, $event_id, $group_id, $survey_hash, $response_id) {
		$this->addScript($project_id, $record, $instrument, $event_id, $group_id);
	}

		function addScript($project_id, $record, $instrument, $event_id, $group_id, $survey_hash = null, $response_id = null) {
			$module_data = ExternalModules::getProjectSettingsAsArray([$this->PREFIX], $project_id);
		if ($project_id && ($instrument == $module_data["instrument"]['value'])) {
			$addressField = $module_data['address']['value'];
			$latitudeField = $module_data['latitude']['value'];
			$longitudeField = $module_data['longitude']['value'];
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
				$(document).ready(function() {
					console.log('Census Geocoder loaded');
					function downloadCensusData() {
						var address = $('[name=\"".$addressField."\"]').val();

						if (address) {
							var encodedAddress = address.replace(/\s+/g, '+');
							encodedAddress = encodedAddress.replace(/United States/g, '+');
							console.log('Looking up '+encodedAddress);
							$.post('".$this->getUrl('getAddress.php')."', { 'get':'address='+encodedAddress+'&benchmark=Public_AR_Census2010&vintage=Census2010_Census2010&layers=14&format=json' }, function(json) {
								console.log('Got data from TigerWeb');
								console.log(json);
								var data = JSON.parse(json);
								if (data && data['result'] && data['result']['addressMatches'] && data['result']['addressMatches'][0] && data['result']['addressMatches'][0]['geographies'] && data['result']['addressMatches'][0]['geographies']['Census Blocks']) {
									console.log('TigerWeb lookup data present');
									var lookupTable = data['result']['addressMatches'][0]['geographies']['Census Blocks'][0];
									processCensusData(lookupTable);
								}
							});
						}
					}

					function downloadCensusDataFromLatLong() {
						var latitude = $('[name=\"".$latitudeField."\"]').val();
						var longitude = $('[name=\"".$longitudeField."\"]').val();

						if(latitude && longitude) {
							console.log('Looking up '+latitude+'/'+longitude);
							$.ajax(
							{
								url:'".$this->getUrl('getCoordinates.php')."',
								data:{
									get:'benchmark=Public_AR_Census2010&vintage=Census2010_Census2010&layers=14&format=json&x=' + longitude + '&y=' + latitude
								},
								type: 'POST'
							}).done(function(json) {
								console.log('Got coordinate data');
								console.log(json);
								var data = JSON.parse(json);
								if(data && data['result'] && data['result']['geographies'] && data['result']['geographies']['Census Blocks']) {
									var lookupTable = data['result']['geographies']['Census Blocks'][0];
									processCensusData(lookupTable);
								}
							});
						}
					}

					function processCensusData(lookupTable) {
						var keys = ".json_encode($keys).";
						var fields = ".json_encode($fields).";

						for (var i=0; i < fields.length; i++) {
							if (lookupTable[keys[i]]) {
								console.log('Census Data Setting '+fields[i]+' to '+lookupTable[keys[i]]);
								$('[name=\"'+fields[i]+'\"]').val(lookupTable[keys[i]]);
							} else {
								console.log('Setting '+fields[i]+' to \"\"');
								$('[name=\"'+fields[i]+'\"]').val('');
							}
							$('[name=\"'+fields[i]+'\"]').change();
						}
					}

					// The following used to occur on the 'blur' event, but we switched it to 'change' since some
					// modules update the field AFTER it has lost focus (like Address Autocompletion).
					$('[name=\"".$addressField."\"]').change(function() {
						console.log('Looking up Census data');
						downloadCensusData();
					});
					$('[name=\"".$latitudeField."\"]').change(function() {
						downloadCensusDataFromLatLong();
					});
					$('[name=\"".$longitudeField."\"]').change(function() {
						downloadCensusDataFromLatLong();
					});
				});
				</script>";
			}
		}
	}
}

