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

// profile.php

set_include_path("template:model");

$title = "Profile Editing";

include 'header.php';
include 'model.php';
include 'profileFn.php';

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

echo "<h1>Profile Editing</h1>\n";

if ($_SERVER['REQUEST_METHOD'] == "POST") {
  //Do the form fcn
  if(isset($_POST['save'])) {
    // Do the return function
    \aqctrl\profileRtn($mysqli, $debug_mode);

    // Redirect back up a level in our nesting
    \aqctrl\retParse(false);
    return;
  }

  if(isset($_POST['cancel'])) {
    // Redirect back up a level without saving
    \aqctrl\retParse(false);
    return;
  }

  // Otherwise, one of the submits from the function has been invoked - process and stay here.
  $procRtn = \aqctrl\profileRtn($mysqli, $debug_mode);

  $newLoc = false;
  $newEdit = false;
  $newMode = false;
  $otherOpts = false;

  if(isset($procRtn['loc'])) $newLoc = $procRtn['loc'];
  if(isset($procRtn['edit'])) $newEdit = $procRtn['edit'];
  if(isset($procRtn['mode'])) $newMode = $procRtn['mode'];
  if(isset($procRtn['other'])) $otherOpts = $procRtn['other'];

  \aqctrl\retGen($newLoc, $newEdit, $newMode, $otherOpts, true);

  mysqli_close($mysqli);    

} else {
  //Show the form.
  echo "<h1>Edit a Profile</h1>\n";

  //Show the form
  if(isset($_GET['units'])) {
    $unitsId = $_GET['units'];
  } else {
    $unitsId = '';
  }


  $newUrl = \aqctrl\retGen(false, false, false, "units=$unitsId", false);
  echo "<form action='$newUrl' method='post'>\n";
  \aqctrl\profileForm($mysqli, $debug_mode);

  mysqli_close($mysqli);

  if((isset($_GET['mode']) && isset($_GET['edit']) && $_GET['mode'] == 'single') || isset($_GET['ret'])) {
    echo "<p class='alignleft'>\n";
    echo "<input type='submit' name='save' value='Save & return' />\n";
    echo "<input type='submit' name='cancel' value='Cancel & return' />\n";
    echo "<div style='clear: both;'></div>\n";
  }

  echo "</form>\n";
  echo "<a href='function.php'>Edit Functions</a>\n<br><br><br>\n";
  echo "</p>\n";

  include 'footer.php';

}

