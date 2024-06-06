<?php namespace Vanderbilt\CensusExternalModule;

use ExternalModules\AbstractExternalModule;
use ExternalModules\ExternalModules;

class CensusExternalModule extends AbstractExternalModule
{
	// When using the Public_AR_Current benchmark, the "vintage" parameter for some years do not follow the format "Census<year>_Current" but rather "ACS<year>_Current"
	// Note that the "Current_Current" vintage has caused data corruption issues and should not be implemented
	const ACSX_CURRENT_YEARS = [2023];

	function hook_survey_page($project_id, $record, $instrument, $event_id, $group_id, $survey_hash, $response_id) {
		$this->addScript($project_id, $record, $instrument, $event_id, $group_id, $survey_hash, $response_id);
	}

	function hook_data_entry_form($project_id, $record, $instrument, $event_id, $group_id, $survey_hash = null, $response_id = null) {
		$this->addScript($project_id, $record, $instrument, $event_id, $group_id);
	}

	function redcap_every_page_before_render(){
		if(PHP_SAPI === 'cli'){
			return;
		}

		$expectedUrl = APP_URL_EXTMOD . 'manager/ajax/get-settings.php';
		$actualUrl = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['SCRIPT_NAME'];
		if($expectedUrl !== $actualUrl){
			return;
		}

		$pid = $_GET['pid'] ?? null;
		if($pid === null){
			// We're on the system settings
			return;
		}
		else{
			// Make sure old settings get transitioned
			$this->getCensuses();
		}
	}

	function transitionOldSettings(){
		$keys = $this->getProjectSetting('keys');
		$fields = $this->getProjectSetting('fields');

		if($fields === null){
			/**
			 * Do nothing.  This project has not been configured, and does not need settings to be transitioned.
			 * This was added to fix an odd subsetting display issue caused by setting 'mappings' to '[[]]',
			 * when they would normally be set to '[[null]]' when a single null 'fields' value exists.
			 */
			return;
		}

		$this->setProjectSetting('year', ['2020']);
		$this->setProjectSetting('censuses', ['true']);

		$mappingValues = [];
		foreach($fields as $field){
			$mappingValues[] = "true";
		}

		$this->setProjectSetting('mappings', [$mappingValues]);

		$this->setProjectSetting('keys', [$keys]);
		$this->setProjectSetting('fields', [$fields]);
	}

	function getCensuses(){
		$censuses = $this->getSubSettings('censuses');
		if(!isset($censuses[0]['year'])){
			$this->transitionOldSettings();
			$censuses = $this->getSubSettings('censuses');
		}

		return $censuses;
	}

	function getSharedArgs($censusYear){
		$censusYearInt = ((int)$censusYear);
		if (in_array($censusYearInt, self::ACSX_CURRENT_YEARS)) {
			return "benchmark=Public_AR_ACS".$censusYearInt."&vintage=Current_ACS".$censusYearInt."&format=json";
		}
		return "benchmark=Public_AR_Current&vintage=Census".$censusYearInt."_Current&format=json";
	}

	function addScript($project_id, $record, $instrument, $event_id, $group_id, $survey_hash = null, $response_id = null) {
		if ($project_id) {
			$addressField = $this->getProjectSetting('address');
			$latitudeField = $this->getProjectSetting('latitude');
			$longitudeField = $this->getProjectSetting('longitude');

			$censuses = $this->getCensuses();

			foreach($censuses as $census){
				echo '<script src="https://cdn.jsdelivr.net/npm/gasparesganga-jquery-loading-overlay@2.1.0/dist/loadingoverlay.min.js" integrity="sha384-MySkuCDi7dqpbJ9gSTKmmDIdrzNbnjT6QZ5cAgqdf1PeAYvSUde3uP8MGnBzuhUx"
				crossorigin="anonymous"></script>';
				echo "<script>
				$(document).ready(function() {
					console.log('Census Geocoder loaded');
					var year = " . (int)$census['year'] . ";

					function downloadCensusData() {
						var address = $('[name=\"".$addressField."\"]').val();

						if (address) {
							var encodedAddress = address.replace(/United States/g, '');
							console.log('Looking up '+encodedAddress);
							$.post('".$this->getUrl('getAddress.php')."', { 'get':1,'address':encodedAddress, 'year': year}, function(json) {
								console.log('Got data from TigerWeb');
								console.log(json);
								var data = JSON.parse(json);
								// NOTE: benchmark:Public_AR_ACS<year>, vintage=Current_ACS<year> data is has its 'Census Blocks' data prefixed with the year the data is actually sourced from
								// TODO: account for the most recent applicable year (2020 Census Blocks for 2023 data)
								if (data && data['result'] && data['result']['addressMatches'] && data['result']['addressMatches'][0] && data['result']['addressMatches'][0]['geographies'] && data['result']['addressMatches'][0]['geographies']['Census Blocks']) {
									console.log('TigerWeb lookup data present');
									var lookupTable = data['result']['addressMatches'][0]['geographies']['Census Blocks'][0];
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
							$.LoadingOverlay('show')
							$.ajax(
							{
								url:'".$this->getUrl('getCoordinates.php')."',
								data:{
									get: 1,
									lat: latitude,
									long: longitude,
									year: year
								},
								type: 'POST'
							}).done(function(json) {
								$.LoadingOverlay('hide')
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
						const mappings = " . json_encode($census['mappings']) . "
						
						mappings.forEach(mapping => {
							let value = lookupTable[mapping.keys]
							if (!value) {
								value = '';
							}
							
							const fieldName = mapping.fields
							console.log('Setting '+fieldName+' to '+value);
							var field = $('[name=\"'+fieldName+'\"]');
							field.val(value);
							field.change();

							if(field.hasClass('rc-autocomplete')){
								var autocompleteField = field.closest('td').find('.ui-autocomplete-input')
								autocompleteField.val(field.find('option:selected').text())
								autocompleteField.change()
							}
						})
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

