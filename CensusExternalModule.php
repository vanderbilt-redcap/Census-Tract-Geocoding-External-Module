<?php namespace Vanderbilt\CensusExternalModule;

use ExternalModules\AbstractExternalModule;
use ExternalModules\ExternalModules;

class CensusExternalModule extends AbstractExternalModule
{
	const BENCHMARKS_URL = "https://geocoding.geo.census.gov/geocoder/benchmarks";
	const VINTAGES_URL = "https://geocoding.geo.census.gov/geocoder/vintages?benchmark=";

	function redcap_survey_page($project_id, $record, $instrument, $event_id, $group_id, $survey_hash, $response_id) {
		$this->addScript($project_id, $record, $instrument, $event_id, $group_id, $survey_hash, $response_id);
	}

	function redcap_data_entry_form($project_id, $record, $instrument, $event_id, $group_id, $survey_hash = null, $response_id = null) {
		$this->addScript($project_id, $record, $instrument, $event_id, $group_id);
	}

	function redcap_module_configuration_settings($project_id, $settings): array {
		$combo_choices = $this->generateBenchmarkVintageChoices();

		$census_idx = array_search("censuses", array_column($settings, "key"));
		$bv_idx = array_search("benchmark_vintage", array_column($settings[$census_idx]["sub_settings"], "key"));

		$settings[$census_idx]["sub_settings"][$bv_idx]["choices"] = $combo_choices;

		return $settings;
	}

	function generateBenchmarkVintageChoices(): array {
		$benchmarks_from_api = json_decode(file_get_contents($this::BENCHMARKS_URL), 1)["benchmarks"];

		$combo_choices = [];

		foreach ($benchmarks_from_api as $benchmark) {
			$vintages = [];
			$this_benchmark_name = $benchmark["benchmarkName"];

			// NOTE: this can be quite slow, if this becomes a problem consider caching and setting up a cron to refresh these
			$vintages = json_decode(file_get_contents($this::VINTAGES_URL . $benchmark["id"]), 1)["vintages"];

			foreach($vintages as $vintage) {
				$vintage_choice = [
					"name" => $this_benchmark_name . " - " . $vintage["vintageName"],
					"value" => $this_benchmark_name . " - " . $vintage["vintageName"]
				];

				if ($vintage['isDefault']) {
					// bubble defaults to top
					array_unshift($combo_choices, $vintage_choice);
				} else {
					$combo_choices[] = $vintage_choice;
				}
			}

		}
		return $combo_choices;
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
		if (!isset($censuses[0]['year']) && !isset($censuses[0]['benchmark_vintage'])) {
			$this->transitionOldSettings();
			$censuses = $this->getSubSettings('censuses');
		}

		return $censuses;
	}

	function getSharedArgsBenchmark($benchmark_vintage) {
		[$benchmark, $vintage] = explode(" - ", $benchmark_vintage);

		return "benchmark={$benchmark}&vintage={$vintage}&format=json";
	}

	/*
	 * @deprecated 2.0.0 The "year" option is no longer visible in the configuration settings
	 */
	function getSharedArgsYear($censusYear){
		$censusYear = (int)$censusYear;
		// NOTE: The US census is conducted every 10 years on years ending in 0
		$mostRecentCensusYear = (int) (floor($censusYear / 10) * 10);
		// HACK: US Census site eliminated most vintages and benchmarks in August 2024
		if (!in_array($censusYear, [2010, 2020])) { $censusYear = $mostRecentCensusYear; }
		if ($mostRecentCensusYear == $censusYear) {
			// NOTE: vintage Census<mostRecentCensusYear> is chosen for similarity to Census2020_Current scheme, namely the presence of data in "Census Blocks" field of API results
			// see comments on related PR for further details
			// https://github.com/vanderbilt-redcap/Census-Tract-Geocoding-External-Module/pull/4
			// HACK: At the release of ACS 2024, all other ACS benchmarks were eliminated as well as all non ACS2024 vintages
			$ACS_year = 2024;
			$benchmark = "Public_AR_ACS{$ACS_year}";
			$vintage = "Census{$mostRecentCensusYear}_ACS{$ACS_year}";
		} else {
			$benchmark = "Public_AR_Current";
			$vintage = "Census{$censusYear}_Current";
		}
		return "benchmark={$benchmark}&vintage={$vintage}&format=json";
	}

	function addScript($project_id, $record, $instrument, $event_id, $group_id, $survey_hash = null, $response_id = null) {
		if (!$project_id) { return; }
		$this->initializeJavascriptModuleObject();

		echo "<script> const module = " . $this->getJavascriptModuleObjectName() . "</script>";

		$censuses = $this->getCensuses();

		$this->tt_addToJavascriptModuleObject("censuses", $censuses);

		$fields = [
			"addressField" => $this->getProjectSetting('address'),
			"latitudeField" => $this->getProjectSetting('latitude'),
			"longitudeField" => $this->getProjectSetting('longitude')
		];
		$this->tt_addToJavascriptModuleObject("fields", $fields);

		$urls = [
			"getAddressUrl" => $this->getUrl("getAddress.php"),
			"getCoordinatesUrl" => $this->getUrl("getCoordinates.php")
		];
		$this->tt_addToJavascriptModuleObject("urls", $urls);

		echo '<script src="https://cdn.jsdelivr.net/npm/gasparesganga-jquery-loading-overlay@2.1.0/dist/loadingoverlay.min.js" integrity="sha384-MySkuCDi7dqpbJ9gSTKmmDIdrzNbnjT6QZ5cAgqdf1PeAYvSUde3uP8MGnBzuhUx"
				crossorigin="anonymous"></script>';

		echo "<script src='" . $this->getUrl("js/main.js") . "'></script>";
	}
}
