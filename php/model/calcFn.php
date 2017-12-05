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

function functionCalc($knownPts, $fnID, $startTime, $endTime = false)
{

  //Gather our data from the points.
  $timeAdjs = array();
  $timeTypes = array();
  $timeSEs = array();
  $dataPts = array();
  $ids = array();

  foreach($knownPts as $ptsRow) {
    if($ptsRow["function"] == $fnID) {
      $timeAdjs[] = $ptsRow["timeAdj"];
      $timeTypes[] = $ptsRow["timeType"];
      $timeSEs[] = $ptsRow["timeSE"];
      $dataPts[] = $ptsRow["value"];
    }
  }

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
    "data0" => $dataPts);
}






function sourceCalc()
{
  //Data Inputs


  //Return format

}


function plotData($plotData)
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

  $myData = new \pData();

  $myData->addPoints($plotData["timePts"], "Labels");
  $myData->setAxisName(0,"Time");
  $myData->setAxisXY(0,AXIS_X);
  $myData->setAxisPosition(0,AXIS_POSITION_BOTTOM);

  $i = 0;

  while(isset($plotData["data$i"])) {
    $myData->addPoints($plotData["data$i"], "data$i");
    $myData->setSerieOnAxis("data$i",1);
    $myData->setScatterSerie("Labels","data$i",$i);
    $myData->setScatterSerieDescription($i,$plotData["label$i"]);
    if(isset($plotData["color$i"])) {
      $myData->setScatterSerieColor($i,array("R"=>$plotData["color$i"]["R"],
        "G"=>$plotData["color$i"]["G"],"B"=>$plotData["color$i"]["B"]));
    } else {
      $myData->setScatterSerieColor($i,array("R"=>102,"G"=>140,"B"=>255));
    }
  $i++;
  }

  $myData->setAxisName(1,"Value"); //TODO May want to make this configurable in the future
  $myData->setAxisXY(1,AXIS_Y);
  $myData->setAxisPosition(1,AXIS_POSITION_LEFT);

  $myPicture = new \pImage(400,200,$myData);

  $myPicture->Antialias = FALSE;

  $myPicture->drawFilledRectangle(0,0,399,199,array("R"=>9,"G"=>9,"B"=>9));

  /* Write the chart title */
  $myPicture->drawText(10,30,$plotData["title"],array("FontSize"=>20,"Align"=>TEXT_ALIGN_BOTTOMLEFT,"R"=>102,"G"=>140,"B"=>255,"FontName"=>"chart/fonts/Forgotte.ttf"));

  /* Set the default font */
  $myPicture->setFontProperties(array("R"=>102,"G"=>140,"B"=>255,"FontName"=>"chart/fonts/Forgotte.ttf","FontSize"=>12));

  $myPicture->setGraphArea(40,40,390,150);

  $myScatter = new \pScatter($myPicture, $myData);
  $myScatter->drawScatterScale(array("AxisR"=>90,"AxisG"=>90,"AxisB"=>90));

  $myPicture->Antialias = TRUE;

  $myScatter->drawScatterLineChart();

  if($i > 1) {
    // Draw a legend
    $myScatter->drawScatterLegend(280,380,array("Mode"=>LEGEND_HORIZONTAL,"Style"=>LEGEND_NOBORDER));
  }

  $myPicture->Render("../static/" . $plotData["outName"] . ".png");

}

