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


/* functionFn.php

Functions to support the function.php page.

functionForm - function to generate the form to fill out

functionRtn - function to handle the return a POST of SetupForm

functionInit - function to create some model functions on start

*/

namespace aqctrl;

include_once("calcFn.php");


function functionForm($mysqli, $debug_mode)
{
  // Show the form
  echo "<h2>Functions</h2>\n";

  //Add some csrf/replay protection
  echo \aqctrl\token_insert($mysqli, $debug_mode);

  // Grab our existing data
  if(isset($_GET['mode']) && isset($_GET['edit']) && $_GET['mode'] == 'single') {
    // We're editing and viewing a single entity
    $editID = (int)$_GET['edit'];
    $knownFn = mysqli_query($mysqli, "SELECT id, name FROM function WHERE id = $editID");
    $knownPts = mysqli_query($mysqli, "SELECT id,value,timeAdj,timeType,timeSE,function FROM point WHERE
      function = $editID ORDER BY timeSE, timeAdj");
  } else {
    // Show a "new" button
    echo "<p>\n<input type='submit' name='new' value='Create New Function'>\n</p>\n";

    // Iterate through each function found
    $knownFn = mysqli_query($mysqli, "SELECT id,name FROM function");
    $knownPts = mysqli_query($mysqli, "SELECT id,value,timeAdj,timeType,timeSE,function FROM point
      ORDER BY function, timeSE, timeAdj");
    $knownProf = mysqli_query($mysqli, "SELECT id, name, function FROM profile");
  }

  if(!mysqli_num_rows($knownFn)) {
    echo "<p>No functions configured or function not found.</p>\n";
    return;
  }

  // If we got here, we do have at least one function
  echo "<table>\n";

  foreach($knownFn as $fnRow) {
    //Get our plot data.
    $plotData = \aqctrl\functionCalc($knownPts, $fnRow["id"], 0.1);

    echo "<tr>\n";
    echo "<td>\n";

    if (count($plotData["timePts"])) {

      $plotData["label0"] = 'Value';
      $plotData["title"] = $fnRow["name"];
      $plotData["outName"] = "functionChart" . $fnRow["id"];

      \aqctrl\plotData($plotData);

      echo "<img src='../static/functionChart" . $fnRow["id"] . ".png' />\n";
    } else {
      echo "<h3>No points in function</h3>\n";
    }

    //Plot is done, proceed to the next table element.
    echo "</td>\n";
    echo "<td>\n";

    //If we're editing this one, show the form; otherwise, show the associated profiles.
    if(isset($_GET["edit"]) && $_GET["edit"] == $fnRow["id"]) {
      //We are editing this function
      echo "<input type='hidden' name='editID' value=" . $fnRow["id"] . ">\n";

      //Count the number of points for this function.
      $numElements = 0;

      foreach($knownPts as $ptRow) {
        if($ptRow["function"] == $fnRow["id"]) $numElements++;
      }

      echo "<p>\nName: \n";
      echo "<input type='text' name='name' value='" . $fnRow["name"] . "'>\n";
      echo " Number of points: \n";
      echo "<input type='number' name='numPts' value=" . $numElements . ">\n";
      echo "<br>\n";

      $i = 0;

      foreach($knownPts as $ptRow) {
        if($ptRow["function"] == $fnRow["id"]) {
          echo "<br>\n";
          
          //Make a hidden form to associate ids.
          echo "<input type='hidden' name='id" . $i . "' value=" .
            $ptRow["id"] . ">\n";

          if ($ptRow["timeType"]) {
            $biasSel = "";
            $pctSel = "selected";
            $tScale = 100;
          } else {
            $biasSel = "selected";
            $pctSel = "";
            $tScale = 1;
          }

          echo "<input type='number' name='timeAdj" . $i . "' value=" .
            $ptRow["timeAdj"]*$tScale . " step='any'>\n";
          echo "<select name='timeType" . $i . "'>\n";
          echo "<option value='0' " . $biasSel . ">Sec.</option>\n";
          echo "<option value='1' " . $pctSel . ">%</option>\n";
          echo "</select>\n";
          echo " from ";

          echo "<select name='timeSE" . $i . "'>\n";

          if ($ptRow["timeSE"]) {
            $startSel = "";
            $endSel = "selected";
          } else {
            $startSel = "selected";
            $endSel = "";
          }

          echo "<option value='0' " . $startSel . ">Start</option>\n";
          echo "<option value='1' " . $endSel . ">End</option>\n";
          echo "</select>\n";
          echo "Value: ";
          echo "<input type='number' name='val" . $i . "' value=" .
            $ptRow["value"] . " step='any'>\n";
          $i++;
        }
      }

      echo "<br>\n<br>\n";
      echo "<input type='submit' name='delete' value='Delete Function'>\n";
    } else {
      //We want to list the associated profiles.
      echo "<p>\nAssociated Profiles:\n<br>\n";

      foreach($knownProf as $profRow) {
        if ($profRow["function"] == $fnRow["id"]) echo $profRow['name'] . "\n<br>\n";
      }
    }
    
    echo "<p class='alignright'>\n";

    if(isset($_GET["edit"]) && $_GET["edit"] == $fnRow["id"]) {
      echo "<input type='submit' name='update' value='Update' />\n";
    } else {
      //Show an edit link
      echo "<a href='" . \aqctrl\retGen(false, $fnRow["id"], false, false, false) . "'>";
      echo "edit</a>\n";
    }

    echo "</td>\n";
    echo "</tr>\n";
  }
  
  echo "</table>\n";
}


function functionRtn($mysqli, $debug_mode)
{
  // Handle our form

  // Check our token
  if(!\aqctrl\token_check($mysqli, $debug_mode)) {
    //We don't have a good token
    if ($debug_mode) echo "<p>Error: token not accepted</p>\n";
    return;
  }

  // First, test for the "new function" button having been pressed
  if(isset($_POST['new'])) {
    //The user clicked the new function button
    $sql = "INSERT INTO function (name) VALUES ('new')";

    if(!mysqli_query($mysqli, $sql)) {
      if ($debug_mode) echo "<p>Error adding new function" . mysqli_error($mysqli) . "\n</p>\n";
    }
    return(['edit' => mysqli_insert_id($mysqli), 'loc' => 'function.php']); //We can return.
  }

  // Next, handle function deletion
  if(isset($_POST["delete"])) {
    //The user clicked delete for this function.
    $sql = "DELETE FROM function WHERE id=" . (int)$_POST["editID"];

    if(!mysqli_query($mysqli, $sql)) {
      if ($debug_mode) echo "<p>Error deleting function:" . mysqli_error($mysqli) . "</p>";
    }

    return;  //We can return because all we want to do is the deletion
  }

  //Finally, go through everything else

  //Prepare two different prepared statements:
  //One to update the function names
  //A second to update the points information

  //Then, delete our excess points
  //Finally do a bulk insert (not prepared)

  $stmt1 = $mysqli->prepare("UPDATE function SET name = ? WHERE id = ?");
  $stmt1-> bind_param("si", $fnName, $fnId);

  $stmt2 = $mysqli->prepare("UPDATE point SET value=?, timeAdj=?, timeType=?, timeSE=?
    WHERE id=?");
  $stmt2->bind_param("ddiii", $ptVal, $ptAdj, $ptType, $ptSE, $ptID);

  //Update the function name.
  $fnName = $_POST["name"];
  $fnId = $_POST["editID"];
      
  $stmt1->execute();

  //Go through this function's points.
  $i = 0;

  while(isset($_POST["id$i"])) {
    //Test whether we need to delete or update this point.
    if($i<(int)$_POST["numPts"]) {
      //Update this point.
      $ptVal = $_POST["val$i"];
      $ptType = (bool)$_POST["timeType$i"];

      if ($ptType) {
        $ptAdj = ((float)$_POST["timeAdj$i"])/100;
      } else {
        $ptAdj = $_POST["timeAdj$i"];
      }

      $ptSE = (bool)$_POST["timeSE$i"];
      $ptID = $_POST["id$i"];

      $stmt2->execute();

    } else {
      //Delete this point.
      $sql = "DELETE FROM point WHERE id = " . (int)$_POST["id$i"];

      //Do the query
      if(!mysqli_query($mysqli, $sql)) {
        if ($debug_mode) echo "Error deleting point: " . mysqli_error($mysqli) . "<br>";
      }
    }

    $i++;
  }

  //See if we need to create more points.
  if((int)$_POST["numPts"] > $i) {
    //Add a point.
    $sql = "INSERT INTO point (value, timeAdj, timeType, timeSE, function) VALUES ";

    for ($k=$i; $k<$_POST["numPts"]; $k++) {
      $sql .= "(0, 0, 0, 0, $fnId),";
    }

    //Strip the trailing comma
    $sql = substr($sql, 0, -1);

    //Do the query
    if(!mysqli_query($mysqli, $sql)) {
      if ($debug_mode) echo "Error adding points: " . mysqli_error($mysqli) . "<br>";
    }
  }
}


// function to make some (model) functions in reset.
function functionInit($mysqli, $debug_mode)
{
  //Plan: make a square wave, triangle wave, and sun/moon rise/set function

  //Add the functions.
  $mQuery = "INSERT INTO function (id, name) VALUES (1, 'Square'), 
    (2, 'Inverse Square'),
    (3, 'Triangle'),
    (4, 'Inverse Triangle'),
    (5, 'Rising Slope'),
    (6, 'Falling Slope'),
    (7, 'Solar Motion');";


  //Add points to the functions.
  $mQuery .= "INSERT INTO point (value, timeAdj, timeType, timeSE, function) VALUES ";

  //Square Function
  $vals = array(0, 1, 1, 0);
  $times = array(0, 0.01, -0.01, 0);
  $types = array(0, 0, 0, 0);
  $SE = array(0, 0, 1, 1);
  $fnNum = 1;
  $numPts = count($vals);

  for ($i = 0; $i < $numPts; $i++) {
    $mQuery .= " (" . $vals[$i] . ", " . $times[$i] . ", " . $types[$i] . ", " . 
      $SE[$i]. ", " . $fnNum . "),";
  }

  //Inverse Square Function
  $vals = array(1, 0, 0, 1);
  $times = array(0, 0.01, -0.01, 0);
  $types = array(0, 0, 0, 0);
  $SE = array(0, 0, 1, 1);
  $fnNum++;
  $numPts = count($vals);

  for ($i = 0; $i < $numPts; $i++) {
    $mQuery .= " (" . $vals[$i] . ", " . $times[$i] . ", " . $types[$i] . ", " . 
      $SE[$i]. ", " . $fnNum . "),";
  }

  //Triangle
  $vals = array(0, 1, 0);
  $times = array(0, 0.5, 0);
  $types = array(1, 1, 1);
  $SE = array(0, 0, 1);
  $fnNum++;
  $numPts = count($vals);

  for ($i = 0; $i < $numPts; $i++) {
    $mQuery .= " (" . $vals[$i] . ", " . $times[$i] . ", " . $types[$i] . ", " . 
      $SE[$i]. ", " . $fnNum . "),";
  }

  //Inverse Triangle
  $vals = array(1, 0, 1);
  $times = array(0, 0.5, 0);
  $types = array(1, 1, 1);
  $SE = array(0, 0, 1);
  $fnNum++;
  $numPts = count($vals);

  for ($i = 0; $i < $numPts; $i++) {
    $mQuery .= " (" . $vals[$i] . ", " . $times[$i] . ", " . $types[$i] . ", " . 
      $SE[$i]. ", " . $fnNum . "),";
  }
  
  //Rising Slope
  $vals = array(0, 1);
  $times = array(0, 0);
  $types = array(0, 0);
  $SE = array(0, 1);
  $fnNum++;
  $numPts = count($vals);

  for ($i = 0; $i < $numPts; $i++) {
    $mQuery .= " (" . $vals[$i] . ", " . $times[$i] . ", " . $types[$i] . ", " . 
      $SE[$i]. ", " . $fnNum . "),";
  }

  //Falling Slope
  $vals = array(1, 0);
  $times = array(0, 0);
  $types = array(0, 0);
  $SE = array(0, 1);
  $fnNum++;
  $numPts = count($vals);

  for ($i = 0; $i < $numPts; $i++) {
    $mQuery .= " (" . $vals[$i] . ", " . $times[$i] . ", " . $types[$i] . ", " . 
      $SE[$i]. ", " . $fnNum . "),";
  }

  //Solar Motion
  $vals = array(0, 0.03, 0.18, 1, 1, 0.18, 0.03, 0);
  $times = array(-2400, 0, 1, 7200, -7200, -1, 0, 2400);
  $types = array(0, 0, 0, 0, 0, 0, 0, 0);
  $SE = array(0, 0, 0, 0, 1, 1, 1, 1);
  $fnNum++;
  $numPts = count($vals);

  for ($i = 0; $i < $numPts; $i++) {
    $mQuery .= " (" . $vals[$i] . ", " . $times[$i] . ", " . $types[$i] . ", " . 
      $SE[$i]. ", " . $fnNum . "),";
  }


  //Drop the trailing comma and replace with a semicolon.
  $mQuery = substr($mQuery, 0, -1) . ";";


  //Do the query.
  if (!mysqli_multi_query($mysqli, $mQuery)) {
    if ($debug_mode) echo "multiquery: " . $mQuery . " failed.";
    //Flush the results.
    while (mysqli_next_result($mysqli)) {;};
    return;
  }

  while (mysqli_next_result($mysqli)) {;};

}



