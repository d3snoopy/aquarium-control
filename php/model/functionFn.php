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

TODO: Update the time calcs, do the form, do the rtn

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
  echo "<h2>Functions</h2>";

  // Iterate through each host found
  $knownFn = mysqli_query($mysqli, "SELECT id,name FROM function");
  $knownPts = mysqli_query($mysqli, "SELECT id,value,timeAdj,timeType,timeSE,function FROM point ORDER BY function, timeSE, timeAdj");

  $numFn = mysqli_num_rows($knownFn);

  if(!$numFn) {
    echo "<p>No Functions Configured.</p>";
        
  } else {
    echo "<table>";

    $ptsRow = mysqli_fetch_array($knownPts);

    for($i=1; $i <= $numFn; $i++) {
      $fnRow = mysqli_fetch_array($knownFn);
      $timeAdjs = array();
      $timeTypes = array();
      $timeSEs = array();
      $dataPts = array();
      
      while ($ptsRow["function"] == $fnRow["id"]) {
        $timeAdjs[] = $ptsRow["timeAdj"];
        $timeTypes[] = $ptsRow["timeType"];
        $timeSEs[] = $ptsRow["timeSE"];
        $dataPts[] = $ptsRow["value"];
        
        $ptsRow = mysqli_fetch_array($knownPts);
      }

      $orgData = \aqctrl\functionCalc($dataPts, $timeAdjs, $timeTypes, $timeSEs, 0);

      echo "<tr>";
      echo "<td>";

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
      $myData->setScatterSerieColor(0,array("R"=>0,"G"=>0,"B"=>0));

      $myPicture = new \pImage(400,200,$myData);

      $myPicture->Antialias = FALSE;

      $myPicture->drawRectangle(0,0,399,199,array("R"=>0,"G"=>0,"B"=>0));
      /* Write the chart title */ 
      $myPicture->setFontProperties(array("FontName"=>"chart/fonts/Forgotte.ttf","FontSize"=>11));
      $myPicture->drawText(10,30,$fnRow["name"],array("FontSize"=>20,"Align"=>TEXT_ALIGN_BOTTOMLEFT));

      /* Set the default font */
      $myPicture->setFontProperties(array("FontName"=>"chart/fonts/pf_arma_five.ttf","FontSize"=>6));

      $myPicture->setGraphArea(40,40,390,150);

      $myScatter = new \pScatter($myPicture, $myData);
      $myScatter->drawScatterScale();

      $myPicture->Antialias = TRUE;

      $myScatter->drawScatterLineChart();

      $myPicture->Render("../static/example.drawLineChart.simple.$i.png");

      echo "<img src='../static/example.drawLineChart.simple.$i.png'>";

      echo "</td>";
      echo "<td>";
      echo $fnRow["name"];
      echo "<br>";
      echo "data: ";
      var_dump($orgData["dataPts"]);
      echo "<br>times: ";
      var_dump($orgData["timePts"]);
      echo "</td>";
      echo "</tr>";

    }

  echo "</table>";
  }
}


function functionRtn($mysqli, $postRet, $debug_mode)
{
    // Handle our form

    $i = 1;
    $mQuery = "";

    while (true) {
        // Iterate through all of the auth fields given.
        $keyName = "auth" . $i;

        if(array_key_exists($keyName, $_POST)) {
            $mQuery .= "UPDATE host SET auth = '" . mysqli_real_escape_string($mysqli, $_POST["$keyName"])
              .  "' WHERE id = $i;";

            $keyName = "inInt" . $i;
            $mQuery .= "UPDATE host SET inInterval = '" . mysqli_real_escape_string($mysqli, $_POST["$keyName"])
              .  "' WHERE id = $i;";

            $keyName = "outInt" . $i;
            $mQuery .= "UPDATE host SET outInterval = '" . mysqli_real_escape_string($mysqli, $_POST["$keyName"])
              .  "' WHERE id = $i;";

            $keyName = "pingInt" . $i;
            $mQuery .= "UPDATE host SET pingInterval = '" . mysqli_real_escape_string($mysqli, $_POST["$keyName"])
              .  "' WHERE id = $i;";

        } else {
            break;
        }
        $i += 1;
    }
    
    // Do the updates
    if (!mysqli_multi_query($mysqli, $mQuery)) {
        if ($debug_mode) echo "multiquery: " . $mQuery . " failed.";
        //Flush the results.
        while (mysqli_next_result($mysqli)) {;};
        return;
    }

    while (mysqli_next_result($mysqli)) {;};
}


// function to make some (model) functions in reset.
function functionInit($mysqli, $debug_mode)
{
  //Plan: make a square wave, triangle wave, and sun/moon rise/set function

  //Add the functions.
  $mQuery = "INSERT INTO function (id, name) VALUES (1, 'Square');";
  $mQuery .= "INSERT INTO function (id, name) VALUES (2, 'Inverse Square');";
  $mQuery .= "INSERT INTO function (id, name) VALUES (3, 'Triangle');";
  $mQuery .= "INSERT INTO function (id, name) VALUES (4, 'Inverse Triangle');";
  $mQuery .= "INSERT INTO function (id, name) VALUES (5, 'Rising Slope');";
  $mQuery .= "INSERT INTO function (id, name) VALUES (6, 'Falling Slope');";
  $mQuery .= "INSERT INTO function (id, name) VALUES (7, 'Solar Motion');";


  //Add points to the functions.

  //Square Function
  $vals = array(0, 1, 1, 0);
  $times = array(0, 0.01, -0.01, 0);
  $types = array(0, 0, 0, 0);
  $SE = array(0, 0, 1, 1);
  $fnNum = 1;
  $numPts = count($vals);

  for ($i = 0; $i < $numPts; $i++) {
    $mQuery .= \aqctrl\functionMQA($vals[$i], $times[$i], $types[$i], $SE[$i], (string)$fnNum);
  }

  //Inverse Square Function
  $vals = array(1, 0, 0, 1);
  $times = array(0, 0.01, -0.01, 0);
  $types = array(0, 0, 0, 0);
  $SE = array(0, 0, 1, 1);
  $fnNum++;
  $numPts = count($vals);

  for ($i = 0; $i < $numPts; $i++) {
    $mQuery .= \aqctrl\functionMQA($vals[$i], $times[$i], $types[$i], $SE[$i], (string)$fnNum);
  }

  //Triangle
  $vals = array(0, 1, 0);
  $times = array(0, 0.5, 0);
  $types = array(1, 1, 1);
  $SE = array(0, 0, 1);
  $fnNum++;
  $numPts = count($vals);

  for ($i = 0; $i < $numPts; $i++) {
    $mQuery .= \aqctrl\functionMQA($vals[$i], $times[$i], $types[$i], $SE[$i], (string)$fnNum);
  }

  //Inverse Triangle
  $vals = array(1, 0, 1);
  $times = array(0, 0.5, 0);
  $types = array(1, 1, 1);
  $SE = array(0, 0, 1);
  $fnNum++;
  $numPts = count($vals);

  for ($i = 0; $i < $numPts; $i++) {
    $mQuery .= \aqctrl\functionMQA($vals[$i], $times[$i], $types[$i], $SE[$i], (string)$fnNum);
  }
  
  //Rising Slope
  $vals = array(0, 1);
  $times = array(0, 0);
  $types = array(0, 0);
  $SE = array(0, 1);
  $fnNum++;
  $numPts = count($vals);

  for ($i = 0; $i < $numPts; $i++) {
    $mQuery .= \aqctrl\functionMQA($vals[$i], $times[$i], $types[$i], $SE[$i], (string)$fnNum);
  }

  //Falling Slope
  $vals = array(1, 0);
  $times = array(0, 0);
  $types = array(0, 0);
  $SE = array(0, 1);
  $fnNum++;
  $numPts = count($vals);

  for ($i = 0; $i < $numPts; $i++) {
    $mQuery .= \aqctrl\functionMQA($vals[$i], $times[$i], $types[$i], $SE[$i], (string)$fnNum);
  }

  //Solar Motion
  $vals = array(0, 0.03, 0.18, 1, 1, 0.18, 0.03, 0);
  $times = array(-2400, 0, 1, 7200, -7200, -1, 0, 2400);
  $types = array(0, 0, 0, 0, 0, 0, 0, 0);
  $SE = array(0, 0, 0, 0, 1, 1, 1, 1);
  $fnNum++;
  $numPts = count($vals);

  for ($i = 0; $i < $numPts; $i++) {
    $mQuery .= \aqctrl\functionMQA($vals[$i], $times[$i], $types[$i], $SE[$i], (string)$fnNum);
  }

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
