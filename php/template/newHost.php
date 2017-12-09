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

/*
newHost.php

*/

// newHost.php

// This returns a link when a new host is found.

set_include_path("../template:../model");

include 'model.php';

//Test the db connection and connect
$mysqli = \aqctrl\db_connect();

if(!$mysqli) return;

$res = mysqli_query($mysqli, "SELECT id FROM host WHERE auth = '0'");

if(mysqli_num_rows($res)) {
  echo "<p class='alignleft'>\n";
  echo(mysqli_num_rows($res) . " new hosts connected, click <a href='./setup.php'>here</a> to configure!\n");
  echo "</p>\n";
}

?>

<p class="alignright">Created by: Stuart Asp</p>
<div style="clear:both;"></div>
