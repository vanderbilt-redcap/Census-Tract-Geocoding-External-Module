<?php namespace Vanderbilt\CensusExternalModule;

use ExternalModules\AbstractExternalModule;
use ExternalModules\ExternalModules;

class CensusExternalModule extends AbstractExternalModule
{
	function hook_survey_page($project_id, $record, $instrument, $event_id, $group_id, $survey_hash, $response_id) {
		$this->addScript($project_id, $record, $instrument, $event_id, $group_id, $survey_hash, $response_id);
	}

	function hook_data_entry_form($project_id, $record, $instrument, $event_id, $group_id, $survey_hash = null, $response_id = null) {
		$this->addScript($project_id, $record, $instrument, $event_id, $group_id);
	}

		function addScript($project_id, $record, $instrument, $event_id, $group_id, $survey_hash = null, $response_id = null) {
			$module_data = ExternalModules::getProjectSettingsAsArray([$this->PREFIX], $project_id);
		if ($project_id) {
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
					var sharedArgs = 'benchmark=Public_AR_Current&vintage=Current_Current&format=json';

					function downloadCensusData() {
						var address = $('[name=\"".$addressField."\"]').val();

						if (address) {
							var encodedAddress = address.replace(/\s+/g, '+');
							encodedAddress = encodedAddress.replace(/United States/g, '+');
							console.log('Looking up '+encodedAddress);
							$.post('".$this->getUrl('getAddress.php')."', { 'get':'address='+encodedAddress+'&'+sharedArgs }, function(json) {
								console.log('Got data from TigerWeb');
								console.log(json);
								var data = JSON.parse(json);
								if (data && data['result'] && data['result']['addressMatches'] && data['result']['addressMatches'][0] && data['result']['addressMatches'][0]['geographies'] && data['result']['addressMatches'][0]['geographies']['Census Tracts']) {
									console.log('TigerWeb lookup data present');
									var lookupTable = data['result']['addressMatches'][0]['geographies']['Census Tracts'][0];
									processCensusData(lookupTable);
								}
							});
						}
					}

					function downloadCensusDataFromLatLong() {
						console.log('downloadCensusDataFromLatLong()')
						
						var latitude = $('[name=\"".$latitudeField."\"]').val();
						var longitude = $('[name=\"".$longitudeField."\"]').val();

						if(latitude && longitude) {
							console.log('Looking up '+latitude+'/'+longitude);
							$.ajax(
							{
								url:'".$this->getUrl('getCoordinates.php')."',
								data:{
									get: sharedArgs+'&x=' + longitude + '&y=' + latitude
								},
								type: 'POST'
							}).done(function(json) {
								console.log('Got coordinate data');
								console.log(json);
								var data = JSON.parse(json);
								if(data && data['result'] && data['result']['geographies'] && data['result']['geographies']['Census Tracts']) {
									var lookupTable = data['result']['geographies']['Census Tracts'][0];
									processCensusData(lookupTable);
								}
							});
						}
					}

					function processCensusData(lookupTable) {
						var keys = ".json_encode($keys).";
						var fields = ".json_encode($fields).";

						for (var i=0; i < fields.length; i++) {
							var value = lookupTable[keys[i]]
							if (!value) {
								value = '';
							}
							
							console.log('Setting '+fields[i]+' to '+value);
							var field = $('[name=\"'+fields[i]+'\"]');
							field.val(value);
							field.change();

							if(field.hasClass('rc-autocomplete')){
								var autocompleteField = field.closest('td').find('.ui-autocomplete-input')
								autocompleteField.val(field.find('option:selected').text())
								autocompleteField.change()
							}
						}
					}

					// The following used to occur on the 'blur' event, but we switched it to 'change' since some
					// modules update the field AFTER it has lost focus (like Address Autocompletion).
					if('$addressField'){
						$('[name=\"".$addressField."\"]').change(function() {
							console.log('Looking up Census data');
							downloadCensusData();
						});
					}

					if('$latitudeField' && '$longitudeField'){
						$('[name=\"".$latitudeField."\"]').change(function() {
							downloadCensusDataFromLatLong();
						});

						$('[name=\"".$longitudeField."\"]').change(function() {
							downloadCensusDataFromLatLong();
						});
					}
				});
				</script>";
			}
		}
	}
}

