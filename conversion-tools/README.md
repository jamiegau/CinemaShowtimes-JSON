# CinemaShowtimes-JSON conversion tools

This directory contains examples of vendor specific data into CinemaShowtime-JSON format.  The vendor is represented by the directory names

Typically all you need to do it place the php file onto a web server, after editing the source of the vendor data into the file.  Then simply hitting the php file from any browser will result in the converted JSON output.

Conversion tools are provided to transform vendor-specific feeds (such as Vista, Radiant, Veezi) into the CinemaShowtimes-JSON format. These tools are located in the vendors name directory and are implemented as simple, single-file PHP scripts. You can deploy these scripts on any standard web server. Each script can be configured to point to a vendor’s data source and will return the equivalent CinemaShowtimes-JSON output by proxying the vendor endpoint, converting the data, and delivering it in the standardized format.

## supported vendors

If you would like a conversion tool implemented for your API, please contact me. If others develop conversion tools for their own cinema software, I would greatly appreciate it if they submit them here so others can benefit.

The objective is to create a base set of conversion tools that end users can utilize or extend according to their requirements.

Below is a list of well-known POS vendors and the status of conversion tools available for each. These tools are designed to help you get started, and exhibitors are encouraged to modify them to suit their specific needs. For example, you might add extra functionality for posters or other auxiliary data not included in the POS system you are using.


| Vendor                                   | Implemented  | Typical segment                      | Strong in…                                           | 
| ---------------------------------------- | ------------ | ------------------------------------ | ---------------------------------------------------- | 
| **Admit One (Showtime Group Solutions)** | Help         | Enterprise & mid-market              | UK/EU; expanding Americas                            | 
| **Agile Ticketing Solutions**            | Help         | US art-house/festivals, small chains | United States                                        | 
| **COMPESO (WinTICKET)**                  | Help         | Chains & independents                | Germany; wider Europe                                | 
| **DX (Norway)**                          | Help         | Regional chains/indies               | Nordics (Norway esp.)                                | 
| **Haxlen**                               | APIRequested | Small Indies                         | Australia                                            |
| **Jack Roe / TAPOS (JACRO)**             | Help         | Indies → regional chains             | UK/IE; growing US                                    | 
| **Omniterm (Jonas Software)**            | Help         | Mid-large chains                     | North America                                        | 
| **POSitive Cinema**                      | underreview  | Mid-large circuits                   | Europe; Southeast Europe                             | 
| **RTS (Ready Theatre Systems)**          | Help         | US indies, drive-ins, mid-size       | United States                                        | 
| **Savoy Systems (Oscar)**                | Help         | UK/IE indies & arts                  | UK & Ireland                                         | 
| **VenueMaster**                          | Yes          | Indies & mini-majors                 | Australia                                            |        
| **Veezi (by Vista)**                     | Yes          | Independents / mini-chains (SaaS)    | Anglo markets; 25+ countries                         | 
| **Vista Cinema (Vista Group)**           | APIRequested | Enterprise chains                    | Global (ex-China/India); Europe, North America, APAC |
