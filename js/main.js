$(document).ready(() => {
	console.log('Census Geocoder loaded');

	const module = ExternalModules.Vanderbilt.CensusExternalModule;

	const censuses = module.tt('censuses');

	const fields         = module.tt('fields');
	const addressField   = fields['addressField'];
	const latitudeField  = fields['latitudeField'];
	const longitudeField = fields['longitudeField'];

	const urls              = module.tt('urls');
	const getAddressUrl     = urls['getAddressUrl'];
	const getCoordinatesUrl = urls['getCoordinatesUrl'];

	function downloadCensusData(census) {
		// part out fields from census
		const address = $(`[name="${addressField}"]`).val();

		if (!address) { return; }

		let encodedAddress = address.replace(/United States/g, '');
		console.log(`Looking up ${encodedAddress}`);
		$.LoadingOverlay('show');
		$.post(
			getAddressUrl,
			{
				'get': 1,
				'address': encodedAddress,
				'year': census.year,
				'benchmark_vintage': census.benchmark_vintage,
				'redcap_csrf_token': redcap_csrf_token
			},
			function(json) {
				$.LoadingOverlay('hide');

				console.log('Got data from TigerWeb');

				let data = JSON.parse(json);
				console.log(data);

				if (data && data['result'] && data['result']['addressMatches'] && data['result']['addressMatches'][0] && data['result']['addressMatches'][0]['geographies'] && data['result']['addressMatches'][0]['geographies']) {
					console.log('TigerWeb lookup data present');

					census['lookupTable'] = data['result']['addressMatches'][0]['geographies'];
					processCensusData(census);
				}
			});
	}

	function downloadCensusDataFromLatLong(census) {
		console.log('downloadCensusDataFromLatLong()')

		const latitude  = $(`[name="${latitudeField}"]`).val();
		const longitude = $(`[name="${longitudeField}"]`).val();

		if (!latitude || !longitude) { return; }

		console.log(`Looking up ${latitude}/${longitude}`);
		$.LoadingOverlay('show');
		$.ajax(
			{
				url: getCoordinatesUrl,
				data: {
					get: 1,
					lat: latitude,
					long: longitude,
					year: census.year,
					benchmark_vintage: census.benchmark_vintage,
          redcap_csrf_token: redcap_csrf_token
				},
				type: 'POST'
			}).done(function(json) {
				$.LoadingOverlay('hide')
				console.log('Got coordinate data');
				console.log(json);
				let data = JSON.parse(json);
				if (data && data['result'] && data['result']['geographies'] && data['result']['geographies']) {
					census['lookupTable'] = data['result']['geographies'];
					processCensusData(census);
				}
			});
	}

	/**
	 * Returns keys in ascending order of the specified "order_by" key
	 * Intended to be run on a list of geographies returned from the Census API
	 * @param {Object} arr - input object, expected to be a an object containing multiple elements each of which must contain the "order_by" key
	 * @param {string} order_by - the key used to sort, must contain a numeric value
	 * @returns {array}
	 * */
	function sortKeysByValue(arr, order_by = 'AREALAND') {
		let sorted_keys = Object.keys(arr).sort(function(a, b) {
			return arr[a][0][order_by] - arr[b][0][order_by];
		})

		return sorted_keys;
	}

	function processCensusData(census) {
		let lookupTable       = census.lookupTable;
		let mappings          = census.mappings;
		var sortedGeographies = sortKeysByValue(lookupTable);

		for (const mapping of mappings) {
			let value = '';

			for (const geography of sortedGeographies) {
				let potential_val = lookupTable[geography][0][mapping.keys];
				if (potential_val) {
					console.log(`found ${mapping.keys} in ${geography}`);
					value = potential_val;
					break;
				}
			}

			const fieldName = mapping.fields
			console.log(`Setting ${fieldName} to ${value}`);
			var field = $(`[name="${fieldName}"]`);
			field.val(value);
			field.change();

			if (field.hasClass('rc-autocomplete')) {
				var autocompleteField = field.closest('td').find('.ui-autocomplete-input')
				autocompleteField.val(field.find('option:selected').text())
				autocompleteField.change()
			}
		}
	}

	// The following used to occur on the 'blur' event, but we switched it to 'change' since some
	// modules update the field AFTER it has lost focus (like Address Autocompletion).
	if (addressField) {
		$(`[name="${addressField}"]`).change(function() {
			console.log('Looking up Census data');
			for(const census of censuses) { downloadCensusData(census); }
		});
	}

	if (latitudeField && longitudeField) {
		$(`[name="${latitudeField}"]`).change(function() {
			for (const census of censuses) { downloadCensusDataFromLatLong(census); }
		});

		$(`[name="${longitudeField}"]`).change(function() {
			for(const census of censuses) { downloadCensusDataFromLatLong(census); }
		});
	}
});
