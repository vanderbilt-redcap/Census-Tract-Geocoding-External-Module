# Census-Tract-Geocoding-External-Module
Module to map address information onto its relevant census tract.

## Project Configuration

- **Field Containing the Full Address**: The field containing the address for which you wish to receive US Census data.
- **Field Containing the Latitude/Longitude (if address not found)**: These fields are most useful when populated by the [Address Autocomplete External Module](https://github.com/vanderbilt-redcap/address-autocomplete).
- **Censuses**
    - **Benchmark - Vintage**: The Benchmark and Vintage from which you wish to receive data. Only currently supported combinations are listed, these are checked upon opening the module configuration menu. Note that options containing "Current" are subject to flux and are typically not recommended to be used.
        - "Benchmark" refers to the time period when the address range was captured in TIGER, "Vintage" is the date when the geography information was captured. For more information see [the official FAQ](https://www2.census.gov/geo/pdfs/maps-data/data/FAQ_for_Census_Bureau_Public_Geocoder.pdf).
    - **Field Mappings**
      - **Data Key from TigerWeb Available Fields**: The attribute which you wish to receive from the US Census API.
        - Associated fields will receive the value associated with the smallest geographic region (ordered by land area) in which the specified attribute appears; please be aware that not every key listed here is guaranteed to appear for every address in every **Benchmark - Vintage**.
        - See [TigerWeb](https://tigerweb.geo.census.gov/tigerwebmain/TIGERweb_attribute_glossary.html?) documentation for descriptions of these attributes.
      - **Field to Populate with Corresponding Data Key**: The REDCap field to populate with the specified **Data Key from TigerWeb Available Fields**.
