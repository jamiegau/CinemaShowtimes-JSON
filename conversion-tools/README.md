# CinemaShowtimes-JSON conversion tools

This directory contains examples of vendor specific data into CinemaShowtime-JSON format.  The vendor is represented by the directory names

Typically all you need to do it place the php file onto a web server, after editing the source of the vendor data into the file.  Then simply hitting the php file from any browser will result in the converted JSON output.

Conversion tools are provided to transform vendor-specific feeds (such as Vista, Radiant, Veezi) into the CinemaShowtimes-JSON format. These tools are located in the vendors name directory and are implemented as simple, single-file PHP scripts. You can deploy these scripts on any standard web server. Each script can be configured to point to a vendor’s data source and will return the equivalent CinemaShowtimes-JSON output by proxying the vendor endpoint, converting the data, and delivering it in the standardized format.

## supported vendors
* Veezi
* VenueMaster
* Vista - (ToDo)
* CinTix (Bruce-POS)

Pleasse contact me if you would like me to implement one agsint your API.