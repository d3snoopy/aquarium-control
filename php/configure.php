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

// setup.php

set_include_path("template:model");

$title = "Configure";

include 'header.php';
include 'model.php';
include 'configFn.php';

// Connect to the db.
$mysqli = \aqctrl\db_connect();

// Find out if we're in debug mode.
$debug_mode = \aqctrl\debug_status();

// Do my content here
if(!$mysqli) {
  //Complain about not being able to connect and give up.
  echo "<p>Could not connect to dB, check /etc/aqctrl.ini</p>\n";
  echo "<p>Or, try going to the <a href=quickstart.php>quickstart page.</a></p>\n";
  include 'footer.php';
  return;
}

if ($_SERVER['REQUEST_METHOD'] == "POST") {
  //Do the form fcn
  if(isset($_POST['cancel'])) {
    //Just return
    \aqctrl\retParse();
    return;
  }

  //Something else has been invoked, call the model function
  $procRtn = \aqctrl\configRtn($mysqli, $debug_mode);

  $newLoc = false;
  $newEdit = false;
  $newMode = false;
  $otherOpts = false;

  if(isset($procRtn['loc'])) $newLoc = $procRtn['loc'];
  if(isset($procRtn['edit'])) $newEdit = $procRtn['edit'];
  if(isset($procRtn['mode'])) $newMode = $procRtn['mode'];
  if(isset($procRtn['other'])) $otherOpts = $procRtn['other'];

  \aqctrl\retGen($newLoc, $newEdit, $newMode, $otherOpts, true);


} else {
  //Show the form

  $newUrl = \aqctrl\retGen();

  echo "<h1>Configure Channels</h1>\n";
  echo "<form action='$newUrl' method='post'>\n";
  \aqctrl\configForm($mysqli, $debug_mode);

  mysqli_close($mysqli);

  echo "<p class='alignleft'>\n";
  echo "<input type='submit' name='save' value='Save' />\n";
  echo "<input type='submit' name='cancel' value='Cancel' />\n";
  echo "</p>\n";
  echo "</form>\n";
  echo "<div style='clear: both;'></div>\n";

  include 'footer.php';

}

