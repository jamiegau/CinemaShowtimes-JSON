Under this folder you will find a Wordpress plugin source for a tool that does the following.

Tool Outline:  The tool will have a setup page that you can set up a url endpoint that returns JSON as outlined in the direcotry above RRADME.md files.  You must also set a API string that must be used to athenrticate against the endpoing.
This end point can be set to manual, or automatic periodic calls.  set from 1-60 minutes.

Then the endpoint is called, it will take the response as outlines in CinemaShowtimes-full-example-with-description.json5, and store it into a database of Films, and sessions.  These tables should represent all the possible values in the example.

The setup page should also allow the user to set a endpoint on the wordpress website that can then reproduce the CinemaShowtimes-full-example-with-description.json5 from the database.

After this has been done, we need to add a plugin menu that allows CRUD functions on the Films and sessions table, two different menus.
This menau also allows the user to mark a Film or session as READ-ONLY, so will be ignored if an imported json file will ignore items if they already exist and leave them as is and not update those items.
 The plugin should be called "wp_d-cine.com_sessiontimes_data"
 