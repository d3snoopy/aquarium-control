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

// calcFn.php
// TODO: update the count
// TODO: don't add end point if at number, filter grouped set down to limit#

namespace aqctrl;

/* pChart library inclusions */
include("chart/class/pData.class.php");
include("chart/class/pDraw.class.php");
include("chart/class/pImage.class.php");
include("chart/class/pScatter.class.php");

// These are supporting functions to do the calculation heavy lifting.
// The functions all expect to be handed data relevant to the calculations (I.E. they don't
// do the queries for you, but given the data as perscribed it will do the necessary calculations.
// They will accept bulk data and filter.

function functionCalc($knownPts, $fnID, $startTime, $endTime = 0)
{
  //Make sure to cast our inputs.
  $startTime = (double)$startTime;
  $endTime = (double)$endTime;
  $fnID = (int)$fnID;

  //Gather our data from the points.
  $timeAdjs = array();
  $timeTypes = array();
  $timeSEs = array();
  $dataPts = array();
  $ids = array();

  foreach($knownPts as $ptsRow) {
    if($ptsRow["function"] == $fnID) {
      $timeAdjs[] = (double)$ptsRow["timeAdj"];
      $timeTypes[] = (boolean)$ptsRow["timeType"];
      $timeSEs[] = (boolean)$ptsRow["timeSE"];
      $dataPts[] = (double)$ptsRow["value"];
    }
  }

  $numElements = count($timeTypes);

  //See if we've been given an end time.
  if (!$endTime) {
    //We need to define the end time.  We want to make end-start 2x the length of the sum of the
    //beginning and end portions, or 1 depending on if we have offset types.
    $begMin = INF;
    $begMax = -INF;
    $endMin = INF;
    $endMax = -INF;

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
    "data0" => $dataPts);
}


function profileCalc($knownPts, $profRow, $startTime = 0, $endTime = 0, $numPts = 0)
{
  //Function to calculate our profile data.
  //If we're not given start and end times, assumed to be now and now+24h.
  //Similarly, if the end time ends up before the start time, it'll be over-written
  if(!$startTime) $startTime = time();

  if($endTime < $startTime) $endTime = $startTime+86400;

  if(!$numPts) $numPts = 100;

  //Calculate the base data.
  $calcData = \aqctrl\functionCalc($knownPts, $profRow['function'],
    $profRow['UNIX_TIMESTAMP(start)'], $profRow['UNIX_TIMESTAMP(end)']);

  //Now, figure out bringing the data into the time range that we want calculated.
  //Figure out how many refreshes we are away.
  if($profRow['refresh']) {
    $numRefr = ($startTime-$profRow['UNIX_TIMESTAMP(start)'])/$profRow['refresh'];

    $i = floor($numRefr);

    $retData['timePts'] = array();
    $retData['data0'] = array();

    do {
      $newTimes = array();
      //Shift the time data as necessary.
      foreach($calcData['timePts'] as $key => $value) {
        $retData['timePts'][] = $value+($i*$profRow['refresh']);
        $retData['data0'][] = $calcData['data0'][$key];
      }

      $i++;
      $timeCount = count($retData['timePts']);
      $calcEnd = $retData['timePts'][$timeCount-1];

      if($timeCount > $numPts) break;

    } while (($calcEnd < $endTime) || ($timeCount < $numPts));

  } else {
    //Don't repeat
    $retData = $calcData;
  }

  //Truncate the data to start at startime and end at endtime.
  $newTimes = array();
  
  $newTimes[0] = $startTime;

  foreach($retData['timePts'] as $timePt) {
    if ($timePt>$startTime && $timePt<$endTime) $newTimes[] = $timePt;
  }

  $newTimes[] = $endTime;

  //Slice the data to limit to our count.
  if(count($newTimes) > $numPts) $newTimes = array_slice($newTimes, 0, $numPts);

  return array(
    'timePts' => $newTimes,
    'data0' => \aqctrl\interpData($newTimes, $retData['timePts'], $retData['data0']));
}


function channelCalc($chanID, $numPts, $mysqli, $knownSrc=0, $knownFn=0, $knownPts=0,
   $knownProf=0, $knownCPS=0)
{
  //Function to calculate values for a channel
  $profData['ids'] = array();

  if(!$knownSrc) {
    $knownSrc = mysqli_query($mysqli, "SELECT id, name, scale, type FROM source ORDER BY type, id");
  }

  if(!$knownFn) {
    $knownFn = mysqli_query($mysqli, "SELECT id,name FROM function");
  }

  if(!$knownPts) {
    $knownPts = mysqli_query($mysqli, "SELECT id,value,timeAdj,timeType,timeSE,function FROM point ORDER BY function, timeSE, timeAdj");
  }

  if(!$knownProf) {
    $knownProf = mysqli_query($mysqli, "SELECT id, name, UNIX_TIMESTAMP(start), UNIX_TIMESTAMP(end), refresh, reaction, function FROM profile");
  }

  if(!$knownCPS) {
    $knownCPS = mysqli_query($mysqli, "SELECT id, scale, channel, profile, source FROM cps ORDER BY source, channel, profile");
  }

  //Plan: similar to the source calc: go through our items, calculating, then all up all of our sources for the channel.
  //TODO: add reaction, trigger work

  foreach($knownCPS as $CPSRow) {
    if($CPSRow['channel'] == $chanID && $CPSRow['source'] && $CPSRow['profile']) {
      //This CPS belongs to the channel that we're calculating for.
      //Add this profile ID to a list.
      $profData['ids'][] = $CPSRow['profile'];
    }
  }

  //We now have a list of profiles that we care about.
  $profData['ids'] = array_unique($profData['ids']);

  $timesFound = array();

  $timeNow = time();

  //Now go through and calculate for each profile.
  foreach($knownProf as $profRow) {
    if(in_array($profRow['id'], $profData['ids'])) {
      $profData[$profRow['id']] = profileCalc($knownPts, $profRow, $timeNow, 0, $numPts);
      $timesFound = array_merge($timesFound, $profData[$profRow['id']]['timePts']);
    }
  }

  //Get unique points, sort, and slice.
  $timesFound = array_unique($timesFound);
  sort($timesFound);
  $newTimes = array_slice($timesFound, 0, $numPts);
  $newTimes = array_values($newTimes);

  //Interpolate all of our profiles.
  foreach($profData['ids'] as $profID) {
    $newData[$profID] = \aqctrl\interpData($newTimes, $profData[$profID]['timePts'],
      $profData[$profID]['data0']);
  }

  //Calculate our data.
  $dataOut = array();

  foreach($newTimes as $i => $timeStamp) {
    $dataOut[$i] = 0;
    foreach($knownSrc as $srcRow) {
      $srcUsed = false;
      $newVal = $srcRow['scale'];

      foreach($knownCPS as $CPSRow) {
        if(($CPSRow['source'] == $srcRow['id']) && ($CPSRow['channel'] == $chanID)
          && ($CPSRow['profile'])) {

          //Use this CPS.
          $srcUsed = true;
          $newVal *= $CPSRow['scale'];
          $newVal *= $newData[$CPSRow['profile']][$i];
        }
      }

      if($srcUsed) $dataOut[$i] += $newVal;
    }
  }

  return array(
    'timePts' => $newTimes,
    'data0' => $dataOut);
}


function channelCalcOld($chanInfo, $chanValLim)
{
  //TODO: rework
  //Function to create the return for a host
  global $debug_mode;

  $retInfo = "";

  $now = time();

  for($i=0;$i<$chanValLim;$i++) {
    $t = $now + ($i*100);
    $retInfo .= "$t,";
  }

  $retInfo .= ';';

  for($i=0;$i<$chanValLim;$i++) {
    $v = mt_rand() / mt_getrandmax();
    $retInfo .= "$v,";
  }

  return $retInfo;
}


function sourceCalc($knownChan, $srcID, $knownFn, $knownPts, $knownProf, $knownCPS, $srcScale = 1,
   $srcName = 'No Name', $duration = 0)
{
  //Calculate values for our source.
  //We're given a source ID: $srcID that needs to be calculated.
  //Go through our CPSes - these give us access to the associated profiles.
  //Assemble the data all into an array that plotdata likes.
  $retData = array();
  $chanList = array();

  //Mine the CPSes for data we care about.
  foreach($knownCPS as $thisCPS) {
    if($thisCPS['source'] == $srcID && $thisCPS['profile'] && $thisCPS['channel']) {
      //This CPS belongs to the source we're calculating for.
      $profKey = $thisCPS['profile'];

      //Add this channel and CPS scale info to the array.
      $retData['chans'][$profKey][] = $thisCPS['channel'];
      $retData['scales'][$profKey][] = $thisCPS['scale'];

      //Add this channel to our running channel list.
      $chanList[] = $thisCPS['channel'];
    }
  }

  //Jump out if we didn't get any matches (catch early work with no profiles assoc)
  if(!isset($retData['chans']) || !count($retData['chans'])) return false;

  $retData['chanInfo'] = array();
  $chanList = array_unique($chanList);
  $chanList = array_values($chanList);

  //Mine the channels for data we care about.
  foreach($knownChan as $thisChan) {
    if(in_array($thisChan['id'], $chanList)) {
      //Add this channel info to our list
      $retData['chanInfo'][$thisChan['id']] = [
        'color' => $thisChan['color'],
        'name' => $thisChan['name'],
      ];
    }
  }

  //Time point tracker - for the future.
  $timePtsSeen = array();

  //Now, go through the profiles, calculating along the way.
  foreach($knownProf as $thisProf) {
    //We have CPS and chann data tee'd up for us in the $retData variable
    $profKey = $thisProf['id'];

    //If the profile isn't used, move on to the next profile.
    if(!array_key_exists($profKey, $retData['scales'])) continue;

    //Get our profile points.
    $retData['profData'][$profKey] = array();
    $retData['profData'][$profKey]['timePts'] = array();
    $retData['profData'][$profKey]['dataPts'] = array();
    
    //Calculate data for this profile.
    $calcData = \aqctrl\profileCalc($knownPts, $thisProf);

    $retData['profData'][$profKey]['timePts'] = $calcData['timePts'];
    $retData['profData'][$profKey]['dataPts'] = $calcData['data0'];

    //Log that we've seen these time points.
    $timePtsSeen = array_merge($timePtsSeen, $calcData['timePts']);

    //Now, we have a set of function points for this profile.
    //Next, calculate and stage our individual channel data; this will be usable later for plotting.
    $retData['profData'][$profKey]['title'] = $thisProf['name'];
    $retData['profData'][$profKey]['outName'] = 'profileChart' . $profKey . '_' . $srcID;

    foreach($retData['chans'][$profKey] as $key => $chanID) {
      //Multiply by the channels scale.
      foreach($retData['profData'][$profKey]['dataPts'] as $inData) {
        $retData['profData'][$profKey]["data$key"][] = $retData['scales'][$profKey][$key] * $inData;
      }

      //Stage the color, label, etc.
      $retData['profData'][$profKey]["color$key"] = $retData['chanInfo'][$chanID]['color'];
      $retData['profData'][$profKey]["label$key"] = $retData['chanInfo'][$chanID]['name'];

    }
  }

  //Now, calculate the product of the profiles.
  //Make sure to remember to map channels (they don't necessarily correlate)
  //Also make sure to remember to interpolate so everything is on the same time points.
  
  $retData['timePts'] = array_unique($timePtsSeen);
  sort($retData['timePts']);

  $retData['title'] = $srcName;
  $retData['outName'] = 'srcChart' . $srcID;


  //Seed the data with our overall source scale.
  foreach($chanList as $i => $chanID) {
    $retData["data$i"] = array_fill(0, count($retData['timePts']), $srcScale);
    $retData["color$i"] = $retData['chanInfo'][$chanID]['color'];
    $retData["label$i"] = $retData['chanInfo'][$chanID]['name'];
  }

  //Channel mapping for our source data: from $chanList.
  //Channel mapping for each proflie: $retData['chans'][$profKey]

  foreach($retData['profData'] as $profKey => $profData) {

    foreach($chanList as $i => $chanID) {
      //Find this chanID in our profData.
      $PCKey = array_search($chanID, $retData['chans'][$profKey]);

      if($PCKey === false) continue; //This chanID not used in this profile.

      //The chanID was found.
      $newData = \aqctrl\interpData($retData['timePts'], $profData['timePts'],
        $profData["data$PCKey"]);

      //Multiply through.
      foreach($newData as $j => $newPt) {
        $retData["data$i"][$j] *= $newPt;
      }
    }
  }

  //Return format to match plotData already queued up
  return($retData);
}


function interpData($outX, $inX, $inY)
{
  //Function to interpolate points for us.
  $inCtr = 0;
  $y = array();
  $inXCnt = count($inX)-1; //Note: it's the final index.

  foreach($outX as $x) {
    //First, decide if we need to increment our inCtr
    while($inCtr < $inXCnt && $inX[$inCtr+1]<$x) {
      $inCtr++;
    }

    //Handle leading points - where outX is earlier than any known inX
    //In this case, make them all equal to inY0.
    if(!$inCtr && $inX[$inCtr]>$x) {
      $y[] = $inY[$inCtr];
    } elseif ($inCtr == $inXCnt) {
      //Trailing points: use the final inY
      $y[] = $inY[$inXCnt];
    } else {
      //Interpolate
      $a = (($inY[$inCtr+1]-$inY[$inCtr])/($inX[$inCtr+1]-$inX[$inCtr]));
      $y[] = $a*($x-$inX[$inCtr])+$inY[$inCtr];
    }
  }

  return($y);
}


function plotData($plotData, $fromNow = false)
{
  /*Function to do the pChart plotting
  Expects the $plotData variable to be an array of arrays witht he following keys:
   'timePts' - X-axis data
   'dataN' - Y-axis data, N starting at 0 and counting up, each element will be a different line plotted
   'colorN' - The color for the line for dataN (optional)
   'labelN' - The label for the line for dataN
   'title' - The title for the plot
   'outName' - The desired name for the plot.
  */

  $xLabel = "Time (sec)";

  if(isset($plotData['timeUnits'])) $xLabel = "Time (" . $plotData['timeUnits'] . ")";

  if($fromNow) {
    $timeNow = time();
    foreach($plotData['timePts'] as $i => $t) {
      $plotData['timePts'][$i] = ($t - $timeNow)/60;
    }
    $xLabel = "Time (minutes from now)";
  }

  $myData = new \pData();

  $myData->addPoints($plotData["timePts"], "Labels");
  $myData->setAxisName(0,$xLabel);
  $myData->setAxisXY(0,AXIS_X);
  $myData->setAxisPosition(0,AXIS_POSITION_BOTTOM);

  $i = 0;

  while(isset($plotData["data$i"])) {
    $myData->addPoints($plotData["data$i"], "data$i");
    $myData->setSerieOnAxis("data$i",1);
    $myData->setScatterSerie("Labels","data$i",$i);
    $myData->setScatterSerieDescription($i,$plotData["label$i"]);
    if(isset($plotData["color$i"])) {
      $R = hexdec(substr($plotData["color$i"],0,2));
      $G = hexdec(substr($plotData["color$i"],2,2));
      $B = hexdec(substr($plotData["color$i"],4,2));

      $myData->setScatterSerieColor($i,array("R"=>$R, "G"=>$G,"B"=>$B));
    } else {
      $myData->setScatterSerieColor($i,array("R"=>102,"G"=>140,"B"=>255));
    }
  $i++;
  }

  $myData->setAxisName(1,"Value"); //TODO May want to make this configurable in the future
  $myData->setAxisXY(1,AXIS_Y);
  $myData->setAxisPosition(1,AXIS_POSITION_LEFT);

  $myPicture = new \pImage(450,400,$myData);

  $myPicture->Antialias = FALSE;

  $myPicture->drawFilledRectangle(0,0,449,399,array("R"=>30,"G"=>30,"B"=>30));

  /* Write the chart title */
  $myPicture->drawText(10,30,$plotData["title"],array("FontSize"=>20,"Align"=>TEXT_ALIGN_BOTTOMLEFT,"R"=>102,"G"=>140,"B"=>255,"FontName"=>"chart/fonts/Forgotte.ttf"));

  /* Set the default font */
  $myPicture->setFontProperties(array("R"=>102,"G"=>140,"B"=>255,"FontName"=>"chart/fonts/Forgotte.ttf","FontSize"=>12));

  $myPicture->setGraphArea(40,40,410,300);

  $myScatter = new \pScatter($myPicture, $myData);
  $myScatter->drawScatterScale(array("AxisR"=>90,"AxisG"=>90,"AxisB"=>90));

  $myPicture->Antialias = TRUE;

  $myScatter->drawScatterLineChart();

  if($i > 1) {
    // Draw a legend
    $myScatter->drawScatterLegend(40,380,array("Mode"=>LEGEND_HORIZONTAL,"Style"=>LEGEND_NOBORDER));
  }

  $myPicture->Render("../static/" . $plotData["outName"] . ".png");

}

