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

// Quickstart.php

set_include_path("template:model");

include 'model.php';

// Connect to the db.
$mysqli = \aqctrl\db_connect();

// Find out if we're in debug mode.
$debug_mode = \aqctrl\debug_status();

// Test to see if we should reset the db
if(($_SERVER['REQUEST_METHOD'] == "POST" && isset($_POST['okay']) && isset($_POST['reset']) &&
   \aqctrl\token_check($mysqli, $debug_mode)) || !$mysqli) {

  //Reset the db
  \aqctrl\db_create();
}

// Do our quickstart step content; essentially include the correct stuff
// Separate: By POST vs GET
// POST: read the step from the form data & do our db actions
// GET: read the step from the db and load the correct form the fill out
// In both cases: Do a quickstart dressing and pull another function to actually create the form, do the db work, etc.

// TODO: "saved" status.

//Set the quickstart step number
$res = mysqli_query($mysqli, "SELECT step FROM quickstart WHERE id = 1");

$stepNum = mysqli_fetch_row($res)[0];

switch ($stepNum) {

  case 1:
    $stepTitle = "Setup Connected Hardware";
    $stepForm = '\aqctrl\setupForm';
    $stepRtn = '\aqctrl\setupRtn';
    $stepIncl = 'setupFn.php';
    break;
  case 2:
    $stepTitle = "Edit Controlling Functions";
    $stepForm = '\aqctrl\functionForm';
    $stepRtn = '\aqctrl\functionRtn';
    $stepIncl = 'functionFn.php';
    break;
  case 3:
    $stepTitle = "Configure Channel Control";
    $stepForm = '\aqctrl\configForm';
    $stepRtn = '\aqctrl\configRtn';
    $stepIncl = 'configFn.php';
    break;
}

include $stepIncl;

//Test for what we received

if ($_SERVER['REQUEST_METHOD'] == "POST") {
  //Test to see if we have a "next" or "back" button.
  if (isset($_POST['nextCan'])) {
    //Continue without save
    $newStep = $stepNum+1;
    $sql = "UPDATE quickstart
      SET step = $newStep
      WHERE id = 1";

    if(!mysqli_query($mysqli, $sql)) {
      if ($debug_mode) echo "<p>Error adding quickstart count " . mysqli_error($mysqli) . "</p>";
    }

  } elseif (isset($_POST['nextSave'])) {
    $newStep = $stepNum+1;
    $sql = "UPDATE quickstart
      SET step = $newStep
      WHERE id = 1";

    if(!mysqli_query($mysqli, $sql)) {
      if ($debug_mode) echo "<p>Error adding quickstart count " . mysqli_error($mysqli) . "</p>";
    }

    $procRtn = $stepRtn($mysqli, $debug_mode);

  } elseif (isset($_POST['back'])) {
    $newStep = $stepNum-1;
    if ($newStep != 0) {
      $sql = "UPDATE quickstart
      SET step = $newStep
      WHERE id = 1";
  
      if(!mysqli_query($mysqli, $sql)) {
        if ($debug_mode) echo "<p>Error adding quickstart count " . mysqli_error($mysqli) . "</p>";
      }
    }
  } else {
    //Default to sending this on to the rtnfunction without the quickstart change.
    $procRtn = $stepRtn($mysqli, $debug_mode);

  }

  header("Location: quickstart.php?" . $procRtn);

  mysqli_close($mysqli);
  return;

} else {
  $title = "Quickstart Step " . $stepNum . ": " . $stepTitle;

  // Add the header info
  include 'header.php';

  echo "<h1>Quickstart Step " . $stepNum . ": " . $stepTitle . "</h1>";

  //Show the form
  echo '<form action="quickstart.php" method="post">';
  $stepForm($mysqli, $debug_mode);
}

mysqli_close($mysqli);

// Build the save/continue/etc options.
?>

<p class="alignleft">
<input type="submit" name="back" value="Back, Discarding Changes" />
</p>
<p class="alignright">
<input type="submit" name="save" value="Save" />
<input type="submit" name="nextCan" value="Continue, Discarding Changes" />
<input type="submit" name="nextSave" value="Save and Continue" />
</p>
</form>

<div style="clear: both;"></div>
        
<?php
include 'footer.php';
