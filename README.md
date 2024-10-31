# Census-Tract-Geocoding-External-Module
Module to map address information onto its relevant census tract.

TigerWeb keys will be sequentially searched for in each geographic bound in ascending order of land area.

## Project Configuration

- **Field Containing the Full Address**: The location which you wish to receive US Census data.
- **Field Containing the Latitude (if address not found)**
- **Field Containing the Longitude (if address not found)**
- **Censuses**
    - **Benchmark - Vintage**: The Benchmark and Vintage from which you wish to receive data. Only currently supported combinations are listed, checked upon opening the module configuration menu.
        - Note that options containing "Current" are subject to flux and are typically not recommended to be used.
    - **Field Mappings**
      - **Data Key from TigerWeb Available Fields**: The attribute which you wish to receive from the US Census API.
        - You will receive the value associated with the smallest geographic region in which the specified attribute appears; please be aware that not every key listed here is guaranteed to appear for every address in every **Benchmark - Vintage**.
        - See [TigerWeb](https://tigerweb.geo.census.gov/tigerwebmain/TIGERweb_attribute_glossary.html?) documentation for descriptions of these attributes.
      - **Field to Populate with Corresponding Data Key**: The REDCap field to populate with the specified **Data Key from TigerWeb Available Fields**.
