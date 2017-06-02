<?php

/*
Copyright 2017 Stuart Asp: d3snoopy AT gmail

This file is part of Aqctrl.

Aqctrl is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

Aqctrl is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with Aqctrl.  If not, see <http://www.gnu.org/licenses/>.
*/



/* hostFn.php

 Model code; make a function per model actions desired

Models objects:

Host - A device that the system talks to.

Hidden with in the host, handled at the host level, via config/arduino code: resources and devices.
  The resources/devices manage the hardware of the host.  Left up to non-database/non web code.


Channel - A single thing to drive/read from.

Source - The user basis for driven channels - the "thing" that you're trying to control/simulate

Profile - A driving shape/schedule for driven channels.

CP - a linking item between profiles and channels.

Macro - prebuilt set of sources/profiles for common cases (TODO)

Scheduler - the driver that dictates updates to a profile

Reaction - an operation taking an input and pushing an output

More details/links between these: see design docs

*/

namespace aqctrl;

// access via: \aqctrl\db_create();, etc

// Host functions

function host_mod($host_name, $input_values)
{
    // Create or edit a host database entry, of name host_name and with values input_values

    global $aqctrl_sql_hostname, $aqctrl_sql_user, $aqctrl_sql_pass, $aqctrl_sql_db;

    

}

function host_read($host_name)
{
    // Ask the host for its channel list


}

function host_write($host_name)
{
    // Send control values out to the host


}

function host_get_channels($host_name)
{
    // Get the db list of channels for this host


}




 
