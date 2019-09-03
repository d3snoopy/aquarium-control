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

// index.php

set_include_path("template:model");

$title = "Summary";

// Add the header.
include 'header.php';

// Pull in the models.
include 'model.php';
include 'statusFn.php';
include 'setupFn.php';
include 'configFn.php';

//Test the db connection and connect
$mysqli = \aqctrl\db_connect();

// Find out if we're in debug mode.
$debug_mode = \aqctrl\debug_status();


if(!$mysqli) {
    header("Location: quickstart.php");
    return;
}


//Test to see if we made it through the quickstart
$res = mysqli_query($mysqli, "SELECT step, numStep FROM quickstart WHERE id = 1");

$QSrow = mysqli_fetch_assoc($res);

if($QSrow["step"] <= $QSrow["numStep"]) {
    mysqli_close($mysqli);
    header("Location: quickstart.php");
    return;
}

echo "<h2>Aquarium Controller</h2>\n";

echo "<p>\n";

// echo "<h3>Data:</h3>\n";
echo "<a href='status.php'>\n";
\aqctrl\statusForm($mysqli, $debug_mode, 1);
echo "</a>\n";

echo "<a href='configure.php'>\n";
\aqctrl\configIndex($mysqli, $debug_mode);
echo "</a>\n";

echo "</p>\n<p>\n";
echo "<h3>Host Last Ping:</h3>\n";

$knownHosts = mysqli_query($mysqli, "SELECT id,ident,name,auth,UNIX_TIMESTAMP(lastPing),inInterval,
  outInterval,pingInterval,status FROM host");

foreach($knownHosts as $hostRow) {
  echo "<br>\n";
  echo $hostRow["name"] . ": \n";
  echo \aqctrl\time_elapsed_string($hostRow["UNIX_TIMESTAMP(lastPing)"], $hostRow["pingInterval"]) .
        "\n";
  echo ", Status: " . $hostRow["status"];
}

echo "</p>\n";


// End do the footer
include 'footer.php';
?>

