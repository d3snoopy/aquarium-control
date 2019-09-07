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


function CPSCalc($CPS, $profileData)
{
  //Function to calculate data for a CPS.
  //This will be used by channelCalc and sourceCalc to agegate and give a final answer.
  $newTimes = array();
  $newData = array();

  //Update the scale and the time for our CPS.
  foreach($profileData['timePts'] as $timePt) {
    $newTimes[] = $timePt+$CPS['offset'];
  }
  foreach($profileData['data0'] as $dataPt) {
    $newData[] = $dataPt*$CPS['scale'];
  }
      
  //We're done here, return without bothering to finish the loop.
  return array(
    'timePts' => $newTimes,
    'data0' => $newData,
    'channel' => $CPS['channel'],
    'source' => $CPS['source'],
    'profile' => $CPS['profile']);
}



function doCalc($mysqli, $knownSrc=0, $knownChan=0, $knownFn=0, $knownPts=0, $knownProf=0, $knownCPS=0, $numPts = 1000, $startTime=0, $endTime=0)
{
  //Function to do the actual calculations.  Works for both channel calc and source calc.
  //Hand this function $knownSrc and/or $knownChan in the dimensionality that you care about.
  //I.E. If you want calculations for a single channel, then hand it a single knownChan (sqli result within an array).
  //If you want calculations for a single source, then hand it a single knownSrc (sqli result within an array).
  //Outputs CPS Calcs, CS Calcs, C Calcs.  It's then up to the calling function to re-organize as desired.
  //Note: Outputs all data on a global


  //First, if we haven't been handed query data, go get it.  (Careful, this will end up calculating everything for everthing!)
  if(!$knownSrc) {
    $knownSrc = mysqli_query($mysqli, "SELECT id, name, scale, type FROM source ORDER BY type, id");
  }

  if(!$knownChan) {
    $knownChan = mysqli_query($mysqli, "SELECT id, name, type, variable, active, max, min, color, units FROM channel WHERE input=0 AND active=1 ORDER BY type, id");
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
    $knownCPS = mysqli_query($mysqli, "SELECT id, scale, offset, channel, profile, source FROM cps ORDER BY source, channel, profile");
  }

  //Make our source, channel single element arrays if necessary so later logic can just flow.
  //if(!is_countable($knownSrc)) $knownSrc = array($knownSrc);
  //if(!is_countable($knownChan)) $knownChan = array($knownChan);


  //Next, filter down to applicable data that we actually care about.
  $relevantCPS = array();
  $srcIDs = array();
  $chanIDs = array();
  $profIDs = array();

  //Find our relevant source and channel IDs
  foreach($knownSrc as $srcRow) {
    $srcIDs[] = $srcRow['id'];
  }

  foreach($knownChan as $chanRow) {
    $chanIDs[] = $chanRow['id'];
  }

  //Find the profile IDs that we care about and gather relevant CPSes in a new array.
  foreach($knownCPS as $CPSRow) {
    if(in_array($CPSRow['channel'], $chanIDs) && in_array($CPSRow['source'], $srcIDs) && $CPSRow['profile']) {
      //This is a CPS that's relevant to us.
      $profIDs[] = $CPSRow['profile'];
      $relevantCPS[] = $CPSRow;
    }
  }

  $profIDs = array_unique($profIDs);


  //Now go through and calculate each profile that we care about.
  $profData = array();
  foreach($knownProf as $profRow) {
    if(in_array($profRow['id'], $profIDs)) {
      $profData[$profRow['id']] = profileCalc($knownPts, $profRow, $startTime, $endTime, $numPts);
    }
  }

  //Collect all of the source scales that we care about.
  $srcScales = array();
  foreach($knownSrc as $srcRow) {
    if(in_array($srcRow['id'], $srcIDs)) $srcScales[$srcRow['id']] = $srcRow['scale'];
  }

  //Calculate all of the relevant CPS data.
  $CPSdata = array();
  $timesFound = array();
  foreach($relevantCPS as $CPSRow) {
    $CPSdata[] = \aqctrl\CPSCalc($CPSRow, $profData[$CPSRow['profile']]);
    $timesFound = array_merge($timesFound, end($CPSdata)['timePts']);
  }

  //Get unique time points, sort, and slice.
  $timesFound = array_unique($timesFound);
  sort($timesFound);
  $newTimes = array_slice($timesFound, 0, $numPts);
  $newTimes = array_values($newTimes);

  //Interpolate all of our CPSes, also arrange the data for calling function's consumption.
  $CPSfinal = array();
  foreach($CPSdata as $i => $dataSet) {
    $CPSdata[$i]['data0'] = \aqctrl\interpData($newTimes, $dataSet['timePts'], $dataSet['data0']);
    $CPSfinal[$dataSet['channel']][$dataSet['source']][$dataSet['profile']] = $CPSdata[$i]['data0'];
  }

  //Now, all CPS data is in a common timeset.  From here, all math can be done element-wise.
  
  //Calculate our CS (Channel Source) data now.
  $Cfinal = array();
  $CSfinal = array();

  //CPSfinal[channel][src][prof] = profile*CPS scale, shift
  //CSfinal[channel][src] = src*cps*cps*cps...
  //Cfinal[channel] = sum(CS data[channel])

  foreach($CPSdata as $CPSset) {
    //Test to see if the channel key exists yet.
    if(!array_key_exists($CPSset['channel'],$Cfinal)) {
      $Cfinal[$CPSset['channel']] = array_fill(0,count($newTimes),0);
    }

    //Test to see if the array key exists in the CS array.
    if(!array_key_exists($CPSset['channel'],$CSfinal) ||
      !array_key_exists($CPSset['source'],$CSfinal[$CPSset['channel']]))
    {
      //Need to seed the source array with the source scale
      $CSfinal[$CPSset['channel']][$CPSset['source']] = array_fill(0,count($newTimes),$srcScales[$CPSset['source']]);
    }

    //The keys exist, need to multiply through our source calculation.
    foreach($CPSset['data0'] as $i => $dataPt) {
      $CSfinal[$CPSset['channel']][$CPSset['source']][$i] *= $dataPt;
    }
  }

  //Now, need to do our final additions to get Cfinal.
  foreach($CSfinal as $chanID => $srcSet) {
    foreach($srcSet as $srcData) {
      //Need to add through for this data.
      //In the previous round we created all of our keys and seeded with 0's.
      foreach($srcData as $i => $dataPt) {
        $Cfinal[$chanID][$i] += $dataPt;
      }
    }
  }

  //Bounce the Cfinal data against the channel max, min, variable statuses.
  foreach($knownChan as $chanRow) {
    if(array_key_exists($chanRow['id'],$Cfinal)) { //Catch the case where we have some channels with no CPS data.
      foreach($Cfinal[$chanRow['id']] as $i => $dataPt) {
        //Filter this through our min, max, variable status.
        if(!$chanRow['variable']) {
          $a = $dataPt - $chanRow['min'];
          $b = $chanRow['max'] - $chanRow['min'];
          $r = (bool)round($a/$b);
          $Cfinal[$chanRow['id']][$i] = ($r*$b)+$chanRow['min'];
        } else {
          $Cfinal[$chanRow['id']][$i] = max($chanRow['min'], min($chanRow['max'], $dataPt));
        }
      }
    }
  }

  //We have all of our relevant data calculated.
  return array(
    'timePts' => $newTimes,
    'CPSdata' => $CPSfinal,
    'CSdata' => $CSfinal,
    'Cdata' => $Cfinal);
}


function channelCalc($mysqli, $chanRet, $knownSrc=0, $knownFn=0, $knownPts=0,
   $knownProf=0, $knownCPS=0, $numPts = 1000)
{
  //Function to calculate values for a channel
  //About all that calls this at this point is the host.
  //Note: assumed that you have a single channel in chanRet.

  //Leverage the doCalc function
  $dataIn = \aqctrl\doCalc($mysqli, $knownSrc, $chanRet, $knownFn, $knownPts, $knownProf, $knownCPS, $numPts);

  //TODO: add reaction, trigger work
  
  //reset the result pointer, just in case.
  mysqli_data_seek($chanRet, 0);
  $chanInfo = mysqli_fetch_array($chanRet);

  return array(
    'timePts' => $dataIn['timePts'],
    'data0' => $dataIn['Cdata'][$chanInfo['id']]);
}


function sourcePlot($calcData, $srcRow, $knownChan)
{
  //Plot the data for a source.
  
  //Stage our channel names and colors
  $chanNames = array();
  $chanColors = array();

  foreach($knownChan as $chanRow) {
    $chanNames[$chanRow['id']] = $chanRow['name'];
    $chanColors[$chanRow['id']] = $chanRow['color'];
  }


  //Go through our data and set it up for the plot.
  $stageData = array();
  $stageData['timePts'] = $calcData['timePts'];
  $stageData['title'] = $srcRow['name'];
  $stageData['outName'] = 'srcChart' . $srcRow['id'];

  $i = 0;

  foreach($calcData['CSdata'] as $chan => $data) {
    if(array_key_exists($srcRow['id'], $data)) {
      //This source has data for this channel.
      $stageData["data$i"] = $data[$srcRow['id']];
      $stageData["color$i"] = $chanColors[$chan];
      $stageData["label$i"] = $chanNames[$chan];
      $i++;
    }
  }

  if(array_key_exists('data0', $stageData)) {
    \aqctrl\plotData($stageData, true);
    echo "<img src='../static/" . $stageData['outName'] . ".png' />\n";
  } else {
    echo "Configure some channels and profiles.\n";
  }
}


function profilePlot($calcData, $srcRow, $profID, $profName, $knownChan)
{
  //Plot the data for a profile within a source.

  //Stage our channel names and colors
  $chanNames = array();
  $chanColors = array();

  foreach($knownChan as $chanRow) {
    $chanNames[$chanRow['id']] = $chanRow['name'];
    $chanColors[$chanRow['id']] = $chanRow['color'];
  }


  //Go through our data and set it up for the plot.
  $stageData = array();
  $stageData['timePts'] = $calcData['timePts'];
  $stageData['title'] = $profName;
  $stageData['outName'] = 'profileChart' . $profID . '_' . $srcRow['id'];

  $i = 0;

  foreach($calcData['CPSdata'] as $chan => $PSdata) {
    if(array_key_exists($srcRow['id'], $PSdata)) {
      //This source has data for this channel.
      if(array_key_exists($profID, $PSdata[$srcRow['id']])) {
        //We have data for this Profile in this Source.
        $stageData["data$i"] = $PSdata[$srcRow['id']][$profID];
        $stageData["color$i"] = $chanColors[$chan];
        $stageData["label$i"] = $chanNames[$chan];
        $i++;
      }
    }
  }

  if(array_key_exists('data0', $stageData)) {
    \aqctrl\plotData($stageData, true);
    echo "<img src='../static/" . $stageData['outName'] . ".png' />\n";
  } else {
    echo "Add Channels to this profile.\n";
  }
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
   'unitsY' - The units to display for the Y axis (optional)
   'title' - The title for the plot
   'outName' - The desired name for the plot.
   'timeUnits' = The units to dosplay for the X axis (optional)
  */

  $xLabel = "Time (sec)";

  if(isset($plotData['timeUnits'])) $xLabel = "Time (" . $plotData['timeUnits'] . ")";

  if($fromNow) {
    $timeNow = time();
    foreach($plotData['timePts'] as $i => $t) {
      $plotData['timePts'][$i] = ($t - $timeNow)/3600;
    }
    $xLabel = "Time (hr from now)";
  }

  $yLabel = 'Value';

  if(isset($plotData['unitsY'])) $yLabel = $plotData['unitsY'];

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

  $myData->setAxisName(1,$yLabel);
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


function plotChanByType($stageData, $knownChan, $label, $timeUnits, $numPts = 1000)
{
  //Plot channel data, grouped by types.
  //This function is used to generate the index plots
  //$stageData: staged data, "CData" array of data with keys which are the index of each channel.
  //$knownChan: SQL query result with channel info; note that there needs be a $stageData['Cdata'] element for each element in this dataset.  Also, needs to be ordered by type if you want predictably grouped plots.
  //$label: a prefix label to put in the plot header
  //$numPts: the number of points limit for our plots.


  // Stage the data for the plot function, by group.
  $indexCnt = 0;
  $currentType = '';

  foreach($knownChan as $chanRow) {
    if ($indexCnt == 0) {
      //We have a new type set; this will really only execute the first time through the loop
      $plotData = array();
      $currentType = $chanRow['type'];
      $plotData['timePts'] = $stageData['timePts'];
      $plotData['unitsY'] = $chanRow['units'];
      $plotData['title'] = "$label - $currentType";
      $plotData['outName'] = preg_replace("/[^a-zA-Z0-9]+/", "", "$label$currentType");  //filtered to only alphanumeric characters
      $plotData['timeUnits'] = $timeUnits;
    }

    if ($currentType == $chanRow['type']) {
      //More data from the type that we are on.
      //Test to see if there's actually calculated data for this channel.
      if(array_key_exists($chanRow['id'], $stageData['Cdata'])) {
        $plotData["data$indexCnt"] = $stageData['Cdata'][$chanRow['id']];
      
        $plotData["color$indexCnt"] = $chanRow['color'];
        $plotData["label$indexCnt"] = $chanRow['name'];

        $indexCnt++;
      }
    } else {
      //We have a new type set, plot, reset, and seed.

      //plot if we have data.
      if(array_key_exists('data0', $plotData)) {
        \aqctrl\plotData($plotData);
        echo "<img src='../static/" . $plotData['outName'] . ".png' />\n";
      }

      //reset
      $plotData = array();
      $indexCnt = 0;
      $currentType = $chanRow['type'];

      //reseed
      $plotData = array();
      $plotData['timePts'] = $stageData['timePts'];
      $plotData['unitsY'] = $chanRow['units'];
      $plotData['title'] = "$label - $currentType";
      $plotData['outName'] = preg_replace("/[^a-zA-Z0-9]+/", "", "$label$currentType");  //filtered to only alphanumeric characters
      $plotData['timeUnits'] = $timeUnits;

      //add the first set of data.
      if(array_key_exists($chanRow['id'], $stageData['Cdata'])) {
        $plotData["data$indexCnt"] = $stageData['Cdata'][$chanRow['id']];

        $plotData["color$indexCnt"] = $chanRow['color'];
        $plotData["label$indexCnt"] = $chanRow['name'];

        $indexCnt++;
      }

    }
  }

  //Do a trailing plot for our last type.
  if(array_key_exists('data0', $plotData)) {
    \aqctrl\plotData($plotData);
    echo "<img src='../static/" . $plotData['outName'] . ".png' />\n";
  }
}

