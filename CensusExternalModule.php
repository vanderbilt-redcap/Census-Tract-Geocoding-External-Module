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

	function redcap_every_page_before_render(){
		$expectedUrl = APP_URL_EXTMOD . 'manager/ajax/get-settings.php';
		$actualUrl = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['SCRIPT_NAME'];
		if($expectedUrl !== $actualUrl){
			return;
		}

		$pid = $_POST['pid'] ?? null;
		if($pid === null){
			// We're on the system settings
			return;
		}
		else{
			$_GET['pid'] = $pid;

			// Make sure old settings get transitioned
			$this->getCensuses();

			unset($_GET['pid']);
		}
	}

	function transitionOldSettings(){
		$keys = $this->getProjectSetting('keys');
		$fields = $this->getProjectSetting('fields');

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
					var sharedArgs = 'benchmark=Public_AR_Current&vintage=Census{$census['year']}_Current&format=json';

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
							$.LoadingOverlay('show')
							$.ajax(
							{
								url:'".$this->getUrl('getCoordinates.php')."',
								data:{
									get: sharedArgs+'&x=' + longitude + '&y=' + latitude
								},
								type: 'POST'
							}).done(function(json) {
								$.LoadingOverlay('hide')
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

