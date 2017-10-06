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

/* pChart library inclusions */
include("chart/class/pData.class.php");
include("chart/class/pDraw.class.php");
include("chart/class/pImage.class.php");
include("chart/class/pScatter.class.php");


function functionForm($mysqli, $debug_mode)
{
  // Show the form
  echo "<h2>Functions</h2>\n";

  // Iterate through each host found
  $knownFn = mysqli_query($mysqli, "SELECT id,name FROM function");
  $knownPts = mysqli_query($mysqli, "SELECT id,value,timeAdj,timeType,timeSE,function FROM point ORDER BY function, timeSE, timeAdj");

  $numFn = mysqli_num_rows($knownFn);

  if(!$numFn) {
    echo "<p>No Functions Configured.</p>\n";
        
  } else {
    echo "<table>\n";

    $ptsRow = mysqli_fetch_array($knownPts);

    for($i=0; $i < $numFn; $i++) {
      $fnRow = mysqli_fetch_array($knownFn);
      $timeAdjs = array();
      $timeTypes = array();
      $timeSEs = array();
      $dataPts = array();
      $ids = array();
      
      while ($ptsRow["function"] == $fnRow["id"]) {
        $timeAdjs[] = $ptsRow["timeAdj"];
        $timeTypes[] = $ptsRow["timeType"];
        $timeSEs[] = $ptsRow["timeSE"];
        $dataPts[] = $ptsRow["value"];
        $ids[] = $ptsRow["id"];
        
        $ptsRow = mysqli_fetch_array($knownPts);
      }

      $orgData = \aqctrl\functionCalc($dataPts, $timeAdjs, $timeTypes, $timeSEs, 0);

      echo "<tr>\n";
      echo "<td>\n";

      if (count($ids)) {

        $myData = new \pData();

        $myData->addPoints($orgData["timePts"], "Labels");
        $myData->setAxisName(0,"Time");
        $myData->setAxisXY(0,AXIS_X);
        $myData->setAxisPosition(0,AXIS_POSITION_BOTTOM);

        $myData->addPoints($orgData["dataPts"], "Data");
        $myData->setSerieOnAxis("Data",1);
        $myData->setAxisName(1,"Value");
        $myData->setAxisXY(1,AXIS_Y);
        $myData->setAxisPosition(1,AXIS_POSITION_LEFT);

        $myData->setScatterSerie("Labels","Data",0);
        $myData->setScatterSerieColor(0,array("R"=>102,"G"=>140,"B"=>255));

        $myPicture = new \pImage(400,200,$myData);

        $myPicture->Antialias = FALSE;

        $myPicture->drawFilledRectangle(0,0,399,199,array("R"=>9,"G"=>9,"B"=>9));
        /* Write the chart title */ 
        $myPicture->drawText(10,30,$fnRow["name"],array("FontSize"=>20,"Align"=>TEXT_ALIGN_BOTTOMLEFT,"R"=>102,"G"=>140,"B"=>255,"FontName"=>"chart/fonts/Forgotte.ttf"));

        /* Set the default font */
        $myPicture->setFontProperties(array("R"=>102,"G"=>140,"B"=>255,"FontName"=>"chart/fonts/Forgotte.ttf","FontSize"=>12));

        $myPicture->setGraphArea(40,40,390,150);

        $myScatter = new \pScatter($myPicture, $myData);
        $myScatter->drawScatterScale(array("AxisR"=>90,"AxisG"=>90,"AxisB"=>90));

        $myPicture->Antialias = TRUE;

        $myScatter->drawScatterLineChart();

        $myPicture->Render("../static/functionChart.$i.png");

        echo "<img src='../static/functionChart.$i.png'>\n";
      } else {
        echo "<h3>No points in function</h3>\n";
      }

      $numElements = count($timeTypes);

      echo "</td>\n";
      echo "<td>\n";
      echo "<p>\nName: \n";
      echo "<input type='text' name='name" . $i . "' value='" . 
        $fnRow["name"] . "'>\n";
      echo " Number of points: \n";
      echo "<input type='number' name='numPts" . $i . "' value=" .
        $numElements . ">\n";
      echo "<br>\n";


      for ($j=0; $j<$numElements; $j++) {
        //Create a table line for each point.
        echo "<br>\n";

        //Make a hidden form to associate ids.
        echo "<input type='hidden' name='id" . $i . "_" . $j . "' value=" .
          $ids[$j] . ">\n";

        if ($timeTypes[$j]) {
          $biasSel = "";
          $pctSel = "selected";
          $tScale = 100;
        } else {
          $biasSel = "selected";
          $pctSel = "";
          $tScale = 1;
        }

        echo "<input type='number' name='timeAdj" . $i . "_" . $j . "' value=" .
          $timeAdjs[$j]*$tScale . " step='any'>\n";
        echo "<select name='timeType" . $i . "_" . $j . "'>\n";
        echo "<option value='0' " . $biasSel . ">Sec.</option>\n";
        echo "<option value='1' " . $pctSel . ">%</option>\n";
        echo "</select>\n";
        echo " from ";

        echo "<select name='timeSE" . $i . "_" . $j . "'>\n";

        if ($timeSEs[$j]) {
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
        echo "<input type='number' name='val" . $i . "_" . $j . "' value=" .
          $dataPts[$j] . " step='any'>\n";

      }


      echo "<br>\n<br>\n";
      echo "<input type='submit' name='delete$i' value='Delete Function'>\n";
      echo "<input type='hidden' name='delId$i' value=" . $fnRow["id"] . ">\n";
      echo "</p>\n</td>\n</tr>\n";
    }

  echo "</table>\n";

  echo "<p>\n<input type='submit' name='new' value='Create New Function'>\n</p>\n";

  //Add some csrf/replay protection.
  echo \aqctrl\token_insert($mysqli, $debug_mode);
  }
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
    return; //We can return because all we're doing is creating a new function.
  }

  // Second, handle function deletion
  for ($i=0; isset($_POST["name$i"]); $i++) {
    if(isset($_POST["delete$i"])) {
      //The user clicked delete for this function.
      $delNum = (int)$_POST["delId$i"];
      $sql = "DELETE FROM function WHERE id=" . $delNum;

      if(!mysqli_query($mysqli, $sql)) {
        if ($debug_mode) echo "<p>Error deleting function:" . mysqli_error($mysqli) . "</p>";
      }

      return;  //We can return because all we want to do is the deletion
    }
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

  for ($i=0; isset($_POST["name$i"]); $i++) {
    
    //Update the function name.
    $fnName = $_POST["name$i"];
    $fnId = $_POST["delId$i"];
      
    $stmt1->execute();

    //Go through this function's points.
    for ($j=0; $j<$_POST["numPts$i"]; $j++) {
      //Update this point - loop is driven by the count input, so handle extra/missing points.

      //Make sure the key exists in our given data.
      if (isset($_POST["id$i" . "_$j"])) {
        //Update this point.
        $ptVal = $_POST["val$i" . "_$j"];
        $ptType = (bool)$_POST["timeType$i" . "_$j"];

        if ($ptType) {
          $ptAdj = ((float)$_POST["timeAdj$i" . "_$j"])/100;
        } else {
          $ptAdj = $_POST["timeAdj$i" . "_$j"];
        }

        $ptSE = (bool)$_POST["timeSE$i" . "_$j"];
        $ptID = $_POST["id$i" . "_$j"];

        $stmt2->execute();
      } else {
        //This point doesn't exist; we need to create some more points.
        $sql = "INSERT INTO point (value, timeAdj, timeType, timeSE, function) VALUES ";

        for ($k=$j; $k<$_POST["numPts$i"]; $k++) {
          $sql .= "(0, 0, 0, 0, $fnId),";
        }

        //Strip the trailing comma
        $sql = substr($sql, 0, -1);

        //Do the query
        if(!mysqli_query($mysqli, $sql)) {
          if ($debug_mode) echo "Error adding points: " . mysqli_error($mysqli) . "<br>";
        }

        //break out of our inner loop
        break;
      }
    }

    //Test to see if we have an excess of points and delete
    while (isset($_POST["id$i" . "_$j"])) {
      $sql = "DELETE FROM point WHERE id = " . (int)$_POST["id$i" . "_$j"];

      //Do the query
      if(!mysqli_query($mysqli, $sql)) {
        if ($debug_mode) echo "Error deleting points: " . mysqli_error($mysqli) . "<br>";
      }

    $j++;

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

// function to append the mQuery.
function functionMQA($val, $time, $type, $SE, $fnNum)
{
  return "INSERT INTO point (value, timeAdj, timeType, timeSE, function)
    VALUES (" . $val . ", " . $time . ", " . $type . ", "
    . $SE . ", " . $fnNum . ");";
}


//Function to calculate values/times based on our dB data and start/end times for a function
function functionCalc($dataPts, $timeAdjs, $timeTypes, $timeSEs, $startTime, $endTime = false)
{
  //See if we've been given an end time.
  if (!$endTime) {
    //We need to define the end time.  We want to make end-start 2x the length of the sum of the
    //beginning and end portions, or 1 depending on if we have offset types.
    $begMin = INF;
    $begMax = -INF;
    $endMin = INF;
    $endMax = -INF;
    $numElements = count($timeTypes);

    for ($i=0; $i<$numElements; $i++) {
      if (!$timeSEs[$i]) {
        //Start Type - find the min and max bias values
        if (!$timeTypes[$i] && ($timeAdjs[$i] < $begMin)) $begMin = $timeAdjs[$i];
        if (!$timeTypes[$i] && ($timeAdjs[$i] > $begMax)) $begMax = $timeAdjs[$i];
      } else {
        //End Type - find the min and max bias values
        if (!$timeTypes[$i] && ($timeAdjs[$i] < $endMin)) $endMin = $timeAdjs[$i];
        if (!$timeTypes[$i] && ($timeAdjs[$i] > $endMax)) $endMax = $timeAdjs[$i];
      }
    }
    //We have now found the min, max biases for both the beginning and end types.
    //Calc our end time.

    $begDelta = is_finite($begMax-$begMin) ? $begMax-$begMin : 0;
    $endDelta = is_finite($endMax-$endMin) ? $endMax-$endMin : 0;

    $endTime = max(1, 2*($begDelta + $endDelta)) + $startTime;
  }

  //We are now sure to have an end time.
  $timeDelta = $endTime - $startTime;

  $timesOut = array();

  $maxStart = -INF;

  //Calculate our time points.
  //First loop does all of the start points.
  for ($i=0; $i<$numElements; $i++) {
    if (!$timeSEs[$i]) {
      //Start Type
      if ($timeTypes[$i]) {
        //scale
        $timesOut[] = $startTime + $timeDelta*$timeAdjs[$i];
      } else {
        //bias
        $timesOut[] = $startTime + $timeAdjs[$i];
      }

      //Update maxStart
      $maxStart = end($timesOut) > $maxStart ? end($timesOut) : $maxStart;
    }
  }

  //Second loop does all of the end points.
  for ($i=0; $i<$numElements; $i++) {
    if ($timeSEs[$i]) {
      //End Type - these we stage and drop if less than maxStart.
      if ($timeTypes[$i]) {
        //scale
        $calcOut = $endTime + $timeDelta*$timeAdjs[$i];
      } else {
        //bias
        $calcOut = $endTime + $timeAdjs[$i];
      }

      //Determine whether to keep this point.
      if ($calcOut > $maxStart) {
        $timesOut[] = $calcOut;
      } else {
        //pull this element out of our values array.
        unset($dataPts[$i]);
      }
    }
  }

  //Finally, sort our points.
  array_multisort($timesOut, $dataPts);

  return array(
    "timePts" => $timesOut,
    "dataPts" => $dataPts);
}
