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
configFn.php


Functions to support the configure.php page.

configForm - function to generate the form to fill out

configRtn - function to handle the return a POST of SetupForm

*/

namespace aqctrl;

include("calcFn.php");


function statusForm($mysqli, $debug_mode, $indexRef = 0)
{

  // Show the form
  if(!$indexRef) echo "<h2>Sensor Status</h2>";

  // Grab our existing data

  // First get sources and see if we have any
  $knownChan = mysqli_query($mysqli, "SELECT id, name, type, variable, active, max, min, color, units FROM channel WHERE input=1 AND active=1");

  $numChan = mysqli_num_rows($knownChan);

  if(!$numChan) {
    echo "<p>No Sensor Channels Configured.</p>\n";
    return;
  }

  // If we got here, we do have at least one channel

  $secSel = '';
  $minSel = '';
  $hrSel = '';
  $daySel = '';
  $monSel = '';
  $yrSel = '';
  $timeUnits = 'hr from now';

  // Parse our GET parameters.
  if(isset($_GET['units'])) {
    $timeUnits = $_GET['units']. " from end";

    switch ($_GET['units']) {
      case 'sec':
        $scale = 1;
        $secSel = 'selected';
        break;
      case 'min':
        $scale = 60;
        $minSel = 'selected';
        break;
      case 'hr':
        $scale = 3600;
        $hrSel = 'selected';
        break;
      case 'day':
        $scale = 86400;
        $daySel = 'selected';
        break;
      case 'mon':
        $scale = 2592000;
        $monSel = 'selected';
        break;
      case 'yr':
        $scale = 31536000;
        $yrSel = 'selected';
        break;
      default:
        $scale = 3600;
        $hrSel = 'selected';
    }
  } else {
    $scale = 3600;
    $hrSel = 'selected';
  }

  //Calculate our values.
  $timeNow = time();

  if(isset($_GET['start'])) {
    $startTime = $timeNow - (double)$_GET['start']*$scale;
    $startDef = $_GET['start'];
  } else {
    $startTime = $timeNow - 24*$scale;
    $startDef = 24;
  }

  if(isset($_GET['end'])) {
    $endTime = $timeNow - (double)$_GET['end']*$scale;
    $endDef = $_GET['end'];
  } else {
    $endTime = $timeNow;
    $endDef = 0;
  }

  //Switch start and end if needed.
  if($startTime > $endTime) {
    $tempTime = $startTime;
    $startTime = $endTime;
    $endTime = $tempTime;
  }

  // Put in a form to control what data's shown.
  if(!$indexRef) {
    echo "<p>\n";
    echo "Plot from: \n";
    echo "<input type='number' name='start' value='$startDef' step='any'>\n";
    echo "to: \n";
    echo "<input type='number' name='end' value='$endDef' step='any'>\n";
    echo "<select name='units'>\n";
    echo "<option value='sec' $secSel>Seconds</option>\n";
    echo "<option value='min' $minSel>Minutes</option>\n";
    echo "<option value='hr' $hrSel>Hours</option>\n";
    echo "<option value='day' $daySel>Days</option>\n";
    echo "<option value='mon' $monSel>Months</option>\n";
    echo "<option value='yr' $yrSel>Years</option>\n";
    echo "</select>\n";
    echo " before now.\n";

    echo "<p class='alignright'>\n";
    echo "<input type='submit' name='save' value='Update' />\n";
    echo "<input type='submit' name='cancel' value='Cancel' />\n";
    echo "</p>\n";
    echo "<div style='clear: both;'></div>\n";
  }


  //Get the data.
  $knownData = mysqli_query($mysqli, "SELECT id, UNIX_TIMESTAMP(date), value, channel FROM data 
    WHERE date >= FROM_UNIXTIME($startTime) AND date <= FROM_UNIXTIME($endTime)
    ORDER BY date, channel");

  $stageData = array();

  //Go through the data, staging it.
  foreach($knownData as $dataRow) {
    $stageData[$dataRow['channel']]['data0'][] = $dataRow['value'];
    $timeCalc = ((double)$dataRow['UNIX_TIMESTAMP(date)'] - $endTime)/$scale;
    $stageData[$dataRow['channel']]['timePts'][] = $timeCalc;
  }

  $indexCnt = 0;

  //Go through each channel and plot.
  foreach($knownChan as $chanRow) {
    if (!$indexRef) {
      echo "<br>\n";
    } else {
      $indexCnt++;
      if ($indexCnt > 3) {
        echo "<br>\n";
        $indexCnt = 0;
      }
    }
    $plotData = $stageData[$chanRow['id']];

    $plotData['color0'] = $chanRow['color'];
    $plotData['label0'] = $chanRow['name'];
    $plotData['title'] = $chanRow['name'];
    $plotData['outName'] = "chanChart" . $chanRow['id'];
    $plotData['timeUnits'] = $timeUnits;
    $plotData['unitsY'] = $chanRow['units'];

    if(count($plotData['timePts'])) {
      \aqctrl\plotData($plotData);
      echo "<img src='../static/" . $plotData['outName'] . ".png' />\n";
    } else {
      if (!$indexRef) echo $chanRow['name'] . " - No data Found";
    }
  }

  //Now, thin out our old data so we don't have a ton just hanging around.
  //First, drop any data from 2017 or older since the system wasn't up and running before 2018.
  //Second, thin any data more than 1 month old to 1 data pt/day (average)
  //Third, thin any data more than 1 year old to 1 data pt/wk (average)

  //1
  $dropTime = strtotime('01 January 2018');
  mysqli_query($mysqli, "DELETE FROM data WHERE date < FROM_UNIXTIME($dropTime)");

  //2
  $moTime = time() - 2592000;
  $moRet = mysqli_query($mysqli, "SELECT id, UNIX_TIMESTAMP(date), value, channel, thinned FROM data
    WHERE date < FROM_UNIXTIME($moTime) AND thinned IS NULL
    ORDER BY date, channel");

  $logData = array();
  $currentIncr = 0;
  $currentChan = 0;
  $dropIDs = array();

  foreach($moRet as $moRow) {
    if(!$currentChan) $currentChan = $moRow['channel'];
    //Test for the queue to reset.
    if(!$currentIncr) $currentIncr = $moRow['UNIX_TIMESTAMP(date)'];
    
    //Test to see if this data belongs in this group.
    $tDelta = $moRow['UNIX_TIMESTAMP(date)'] - $currentIncr;
    if($tDelta < 86400 && $currentChan == $moRow['channel']) {
      //Add this data to our log - we are within 1 day and in the same channel
      $logData['times'][] = $moRow['UNIX_TIMESTAMP(date)'];
      $logData['values'][] = $moRow['value'];
      $logData['ids'][] = $moRow['id'];
    } else {
      //We have a grouping, handle.

      //Test to see if we have a single element or a new channel, in which case don't do any queries.
      if((count($logData['ids']) > 1) && ($currentChan == $moRow['channel'])) {
        //We have to have a full block of data; average and do an update query.
        $avVal = array_sum($logData['values'])/count($logData['values']);
        $avTime = array_sum($logData['times'])/count($logData['times']);

        mysqli_query($mysqli, "INSERT INTO data (date, value, thinned, channel)
          VALUES (FROM_UNIXTIME($avTime), $avVal, 1, $currentChan)");

        //Add the original data to drop.
        $dropIDs = array_merge($dropIDs, $logData['ids']);
      }
      //Reset data and add first value
      $logData = array();
      $currentIncr = $moRow['UNIX_TIMESTAMP(date)'];
      $currentChan = $moRow['channel'];

      $logData['times'][] = $moRow['UNIX_TIMESTAMP(date)'];
      $logData['values'][] = $moRow['value'];
      $logData['ids'][] = $moRow['id'];
    }
  }


  //Drop all of the IDs from our drop list.
  mysqli_query($mysqli, "DELETE FROM data WHERE id IN (" . implode(",", $dropIDs) . ")");

  //3
  $yrTime = time() - 31536000;
  $yrRet = mysqli_query($mysqli, "SELECT id, UNIX_TIMESTAMP(date), value, channel, thinned FROM data
    WHERE date < FROM_UNIXTIME($yrTime) AND thinned IS NOT NULL
    ORDER BY date, channel");

  $logData = array();
  $currentIncr = 0;
  $currentChan = 0;
  $dropIDs = array();

  foreach($yrRet as $yrRow) {
    if(!$currentChan) $currentChan = $yrRow['channel'];
    //Test for the queue to reset.
    if(!$currentIncr) $currentIncr = $yrRow['UNIX_TIMESTAMP(date)'];

    //Test to see if this data belongs in this group.
    $tDelta = $yrRow['UNIX_TIMESTAMP(date)'] - $currentIncr;
    if($tDelta < 604800 && $currentChan == $yrRow['channel']) {
      //Add this data to our log - we are within 1 day and in the same channel
      $logData['times'][] = $yrRow['UNIX_TIMESTAMP(date)'];
      $logData['values'][] = $yrRow['value'];
      $logData['ids'][] = $yrRow['id'];
    } else {
      //We have a grouping, handle.

      //Test to see if we have a single element or a new channel, in which case don't do any queries.
      if((count($logData['ids']) > 1) && ($currentChan == $yrRow['channel'])) {
        //We have to have a full block of data; average and do an update query.
        $avVal = array_sum($logData['values'])/count($logData['values']);
        $avTime = array_sum($logData['times'])/count($logData['times']);

        mysqli_query($mysqli, "INSERT INTO data (date, value, thinned, channel)
          VALUES (FROM_UNIXTIME($avTime), $avVal, 1, $currentChan)");

        //Add the original data to drop.
        $dropIDs = array_merge($dropIDs, $logData['ids']);
      }
      //Reset data and add first value
      $logData = array();
      $currentIncr = $yrRow['UNIX_TIMESTAMP(date)'];
      $currentChan = $yrRow['channel'];

      $logData['times'][] = $yrRow['UNIX_TIMESTAMP(date)'];
      $logData['values'][] = $yrRow['value'];
      $logData['ids'][] = $yrRow['id'];
    }
  }

  //Drop all of the IDs from our drop list.
  mysqli_query($mysqli, "DELETE FROM data WHERE id IN (" . implode(",", $dropIDs) . ")");


}



function statusRtn($mysqli, $debug_mode)
{
  // Handle our form
  // All we're doing is assembling a new URL.
  $optString = 'start=' . urlencode($_POST['start']) . "&";
  $optString .= 'end=' . urlencode($_POST['end']) . "&";
  $optString .= 'units=' . urlencode($_POST['units']);

  return($optString);

}
