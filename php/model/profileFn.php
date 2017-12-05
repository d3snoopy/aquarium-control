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
profileFn.php


Functions to support the profile.php page.

profileForm - function to generate the form to fill out

profileRtn - function to handle the return a POST of SetupForm

*/

namespace aqctrl;


function profileForm($mysqli, $debug_mode)
{

  // Show the form
  echo "<h2>Profiles</h2>";

  //Add some csrf/replay protection
  echo \aqctrl\token_insert($mysqli, $debug_mode);

  // Grab our existing data
  if(isset($_GET['mode']) && isset($_GET['edit']) && $_GET['mode'] == 'single') {
    $editID = (int)$_GET['edit'];
    $knownProf = mysqli_query($mysqli, "SELECT id, name, UNIX_TIMESTAMP(start), UNIX_TIMESTAMP(end), refresh,
      reaction, function FROM profile WHERE id = $editID");
  } else {
    echo "<p>\n<input type='submit' name='new' value='Create New Profile'>\n</p>\n";
    $knownProf = mysqli_query($mysqli, "SELECT id, name, UNIX_TIMESTAMP(start), UNIX_TIMESTAMP(end), refresh,
      reaction, function FROM profile");
  }

  if(!mysqli_num_rows($knownProf)) {
    echo "<p>No profiles configured or profile not found.</p>\n";
    return;
  }

  // If we got here, we do have at least one profile
  $knownSrc = mysqli_query($mysqli, "SELECT id, name FROM source");
  $knownFn = mysqli_query($mysqli, "SELECT id, name FROM function");
  $knownReact = mysqli_query($mysqli, "SELECT id, action, scale, channel, react FROM reaction");
  $knownChan = mysqli_query($mysqli, "SELECT id, name, type, variable, active, max, min, color, units FROM channel WHERE input=1");
  $knownCPS = mysqli_query($mysqli, "SELECT id, scale, channel, profile, source FROM cps ORDER BY profile, source, channel");

  // Cycle through all of our profiles
  echo "<table>\n";

  foreach($knownProf as $profRow) {
    echo "<tr>\n";
    echo "<td>\n";
    echo "<h3>" . $profRow["name"] . "</h3>\n";
    //echo "<table>\n";

    //Build a list of sources associated with this profile, for reference.
    $assocSrc = array();

    foreach($knownCPS as $CPSRow) {
      if($CPSRow["profile"] == $profRow["id"]) $assocSrc[] = $CPSRow["source"];
    }

    //Now, build this into a list of names.
    $srcNames = array();

    foreach($knownSrc as $srcRow) {
      if(in_array($srcRow["id"], $assocSrc)) $srcNames[] = $srcRow["name"];
    }

    $srcNames = array_unique($srcNames);

    //echo "<tr>\n<td>\n";

    if(isset($_GET['edit']) && $_GET['edit'] == $profRow['id']) {
      //Give all of the controls
      echo "<input type='hidden' name='editID' value=" . $profRow["id"] . ">\n";

      echo "Name: \n";
      echo "<input type='text' name='name' value='" . $profRow["name"] . "'>\n<br>\n";

      $tDelta = $profRow["UNIX_TIMESTAMP(start)"]-time();

      echo "Start: \n";
      echo "<input type='number' name='start' value='" . $tDelta . "' step='1'>\n seconds from save.\n<br>\n";

      $tDelta = $profRow["UNIX_TIMESTAMP(end)"]-time();

      echo "End: \n";
      echo "<input type='number' name='end' value='" . $tDelta . "' step='1'>\n seconds from save.\n<br>\n";

      echo "Refresh: \n";
      echo "<input type='number' name='refresh' value='" . $profRow["refresh"] . "' step='1'>\n seconds.\n<br>\n";

      //Define this as either function driven or reaction-driven.  Default to function-driven.
      echo "Type: \n";
      echo "<select name='type'>\n";

      $reactType = '';
      $funcType = '';

      if($profRow['reaction']) {
        $reactType = 'selected';
      } else {
        $funcType = '';
      }

      echo "<option value='function' $funcType>function</option>\n";
      echo "<option value='reaction' $reactType>reaction</option>\n";
      echo "</select>\n<br>\n";

      //Provide a picker for the type.
      if($profRow['reaction']) {
        //TODO: Show the reaction chain and provide an edit link
        //Note: Have a unique reaction chain per profile, so don't worry about many to one types
      } else {
        //This is a function profile
        echo "Function: \n";
        echo "<select name='function'>\n";
        $fnNew = 'selected';
        $fnID = false;

        foreach ($knownFn as $fn) {
          if($profRow['function'] == $fn['id']) {
            //We have already picked this reaction
            $fnSel = 'selected';
            $fnNew = '';
          } else {
            $fnSel = '';
          }
          //Print the reaction option
          echo "<option value='" . $fn['id'] . "' $fnSel>" . $fn['name'] . "</option>\n";
        }
        //Print a "New" option
        echo "<option value='new' $fnNew>New</option>\n";
        echo "</select>\n";

        //Edit link for the function.
        if ($profRow['function']) {
          echo "<a href='" . \aqctrl\retGen('function.php', $profRow['function'], 'single', false, false) . "'>";
          echo "edit</a>\n";
        }

        echo "<br>\n";
      }
        
      echo "<input type='submit' name='delete' value='Delete Profile' />\n";

    } else {
      //Just print the info
      echo "Name: " . $profRow["name"] . "\n<br>\n";

      $tDelta = $profRow["UNIX_TIMESTAMP(start)"]-time();

      echo "Start: " . $tDelta . " seconds from now.\n<br>\n";

      $tDelta = $profRow["UNIX_TIMESTAMP(end)"]-time();

      echo "End: " . $tDelta . " seconds from now.\n<br>\n";
      echo "Refresh: " . $profRow["refresh"] . " seconds.\n<br>\n";

    }

    echo "</td>\n<td>\n";
    echo "Associated Sources:\n<br>\n";

    foreach($srcNames as $srcN) {
      echo "$srcN\n<br>\n";
    }

    //echo "</td>\n";
    //echo "</tr>\n"; 
    //echo "</table>\n";

    if(isset($_GET['edit']) && $_GET['edit'] == $profRow["id"]) {
      echo "<p class='alignright'>\n";
      echo "<input type='submit' name='update' value='Update' />\n";
    } else {
      //Show an edit link
      echo "<p class='alignright'>\n";
      echo "<a href='" . \aqctrl\retGen(false, $profRow["id"], false, false, false) . "'>";
      echo "edit</a>\n";
    }

    echo "</td>\n";
    echo "</tr>\n";
  }

  echo "</table>\n";
}


function profileRtn($mysqli, $debug_mode)
{
  // Handle our form

  // Check the token
  if(!\aqctrl\token_check($mysqli, $debug_mode)) {
    //We don't have a good token
    if ($debug_mode) echo "<p>Error: token not accepted</p>\n";
    return;
  }

  $profID = (int)$_POST['editID'];

  // First test for the "New Profile" button
  if(isset($_POST['new'])) {
    // Add a new profile
    $timeNow = time();
    $sql = "INSERT INTO profile (name, start, end, refresh) VALUES ('new', FROM_UNIXTIME($timeNow),
      FROM_UNIXTIME($timeNow), 0)";

    if(!mysqli_query($mysqli, $sql)) {
      if ($debug_mode) echo "<p>Error adding new profile" . mysqli_error($mysqli) . "\n</p>\n";
    }
    return(['edit' => mysqli_insert_id($mysqli), 'loc' => 'profile.php']); //We can return
  }

  // Next, test for the "Delete Source" button
  if(isset($_POST["delete"])) {
    $sql = "DELETE FROM profile WHERE id = " . (int)$_POST['editID'];

    if(!mysqli_query($mysqli, $sql)) {
      if ($debug_mode) echo "<p>Error deleting profile" . mysqli_error($mysqli) . "\n</p>\n";
    }
    return; //We can return.
  }

  // Next, test the type & (potential) function selections.
  if($_POST["type"] == "reaction") {
    //We have a reaction type
    $sql = "SELECT reaction FROM profile WHERE id = $profID";

    $profRet = mysqli_query($mysqli, $sql);

    if(!$profRet) {
      if ($debug_mode) echo "<p>Error getting profile" . mysqli_error($mysqli) . "\n</p>\n";
    }

    $profRow = mysqli_fetch_array($profRet);

    if(!$profRow['reaction']) {
      $sql = "INSERT INTO reaction (action, scale) VALUES (1, 1)";

      if(!mysqli_query($mysqli, $sql)) {
        if ($debug_mode) echo "<p>Error adding new reaction" . mysqli_error($mysqli) . "\n</p>\n";
      }

      $reactID = mysqli_insert_id($mysqli);

      $retArgs = ['loc' => 'reaction.php', 'edit' => $reactID, 'mode' => 'single'];

      $sql = "UPDATE profile SET reaction = $reactID WHERE id = $profID";

      if(!mysqli_query($mysqli, $sql)) {
        if ($debug_mode) echo "<p>Error adding new profile with reaction " . mysqli_error($mysqli) . "\n</p>\n";
      }
    } else {
      $retArgs = [];
    }

    //Update the profile
    $stmt = $mysqli->prepare("UPDATE profile SET name = ?, start = FROM_UNIXTIME(?),
      end = FROM_UNIXTIME(?), refresh = ?, function = NULL WHERE id = ?");

    $stmt-> bind_param("siiii", $newName, $startTime, $endTime, $refreshVal, $profID);

    $newName = $_POST["name"];
    $startTime = $_POST["start"] + time();
    $endTime = $_POST["end"] + time();
    $refreshVal = $_POST["refresh"];

    if(!$stmt->execute() && $debug_mode) {
      echo "Execute failed: (" . $stmt->errno . ") " . $stmt->error;
    }

  } else {
    //We have a function type.
    if($_POST["function"] == "new") {
      //Create a new function
      $sql = "INSERT INTO function (name) VALUES ('new')";

      if(!mysqli_query($mysqli, $sql)) {
        if ($debug_mode) echo "<p>Error adding new function " . mysqli_error($mysqli) . "\n</p>\n";
      }
      $fnID = mysqli_insert_id($mysqli);

      //Redirect to the page to edit our new function.
      $retArgs = ['loc' => 'function.php', 'edit' => $fnID, 'mode' => 'single'];

    } else {
      //Associate existing function with this profile.
      //Flag to update the function.
      $fnID = $_POST['function'];
    }

    //Now, update the profile.
    $stmt = $mysqli->prepare("UPDATE profile SET name = ?, start = FROM_UNIXTIME(?),
      end = FROM_UNIXTIME(?), refresh = ?, function = ? WHERE id = ?");

    $stmt-> bind_param("siiiii", $newName, $startTime, $endTime, $refreshVal, $fnID, $profID);

    $newName = $_POST["name"];
    $startTime = $_POST["start"] + time();
    $endTime = $_POST["end"] + time();
    $refreshVal = $_POST["refresh"];

    if(!$stmt->execute() && $debug_mode) {
      echo "Execute failed: (" . $stmt->errno . ") " . $stmt->error;
    }
  }

  return($retArgs);
}

