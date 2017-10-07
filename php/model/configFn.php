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

/* pChart library inclusions */
include("chart/class/pData.class.php");
include("chart/class/pDraw.class.php");
include("chart/class/pImage.class.php");
include("chart/class/pScatter.class.php");


function configForm($mysqli, $debug_mode)
{

  // Show the form
  echo "<h2>Configure</h2>";

  // Determine how to organize the data
  // Organize either by source, channel, or profile
  if ($_GET["mode"] == "channel") {
    \aqctrl\chanConfigForm($mysqli, $debug_mode);
  } elseif ($_GET["mode"] == "profile") {
    \aqctrl\profConfigForm($mysqli, $debug_mode);
  } else {
    \aqctrl\srcConfigForm($mysqli, $debug_mode);
  }

  echo "<p>\n<input type='submit' name='new' value='Create New Source'>\n</p>\n";

  //Add some csrf/replay protection
  echo \aqctrl\token_insert($mysqli, $debug_mode);
}


function srcConfigForm($mysqli, $debug_mode)
{
  //Paint the page organizing by source
  echo "<h3>\nSort By:\n";
  echo "<table width='100%'>\n";
  echo "<tr>\n";
  echo "<td align='center'>\n";
  echo "Source\n";
  echo "</td>\n";
  echo "<td align='center'>\n";
  echo "<a href='" . $_SERVER["SCRIPT_NAME"] . "?mode=channel'>\n";
  echo "Channel\n";
  echo "</a>\n";
  echo "</td>\n";
  echo "<td align='center'>\n";
  echo "<a href='" . $_SERVER["SCRIPT_NAME"] . "?mode=profile'>\n";
  echo "Profile\n";
  echo "</a>\n";
  echo "</td>\n";
  echo "</tr>\n";
  echo "</table>\n";
  echo "</h3>\n";

  // Grab our existing data

  // First get sources and see if we have any
  $knownSrc = mysqli_query($mysqli, "SELECT id, name, scale, type FROM source ORDER BY type, id");

  $numSrc = mysqli_num_rows($knownSrc);

  if(!$numSrc) {
    echo "<p>No Sources Configured.</p>\n";
    return;
  }

  // If we got here, we do have at least one source
  $knownFn = mysqli_query($mysqli, "SELECT id,name FROM function");
  $knownPts = mysqli_query($mysqli, "SELECT id,value,timeAdj,timeType,timeSE,function FROM point ORDER BY function, timeSE, timeAdj");
  $knownReact = mysqli_query($mysqli, "SELECT id, action, scale, channel, react FROM reaction");
  $knownChan = mysqli_query($mysqli, "SELECT id, name, type, variable, active, max, min, color, units FROM channel");
  $knownProf = mysqli_query($mysqli, "SELECT id, name, start, end, refresh, scale, reaction, function FROM profile");
  $knownCPS = mysqli_query($mysqli, "SELECT id, scale, channel, profile, source FROM cps ORDER BY source, profile, channel");
  $knownSched = mysqli_query($mysqli, "SELECT id, name, type, profile FROM scheduler");

  // Warn if we don't know about any channels
  if(!mysqli_num_rows($knownChan)) echo "<h3>Warning: no channels found.</h3>\n
    <p>Channels are created to connecting hardware hosts via the setup page, you can't manually create channels.</p>\n
    <p>You won't be able to properly configure sources until the hardware hosts connect and inform the system about their channels. Click <a href=./setup.php>here</a> to go to the setup page.</p>\n";

  // Cycle through all of our sources
  echo "<table width='100%' >\n";

  // Pre-fetch our first CPS
  $CPSRow = mysqli_fetch_array($knownCPS);

  for($i=0; $i < $numSrc; $i++) {
    
    $srcRow = mysqli_fetch_array($knownSrc);

    echo "<tr>\n";
    echo "<td>\n";
    echo "<h3>" . $srcRow["name"] . "</h3>\n";
    echo "<table>\n";

    //Get through any preceeding CPSes that don't map to this source.
    while ($CPSRow["source"] != $srcRow["id"]) {
      $CPSRow = mysqli_fetch_array($knownCPS);

      //Catch reaching the end of our rows
      if(!$CPSRow) {
        //We reached the end of our rows with no matches
        echo "<tr>\n<td>\nNo channels associated with this source\n</td>\n</tr>\n";
        break;
      }
    }

    //We're up to the CPSes for this source.
    while ($CPSRow["source"] == $srcRow["id"]) {
      //TODO Add an overall plot for this source.

      //TODO Do stuff with the data
      //First, make an overall plot
      //Then, plot out the effect of each profile
      //Catch for edit and make stuff configurable
    }

    //TODO reset the CPS pointer to row 1
    //TODO reset our counters as necessary

    if(isset($_GET['edit']) && $_GET['edit'] == $srcRow["id"]) {
      //Give all of the controls
      echo "<input type='hidden' name='editID' value=" . $srcRow["id"] . ">\n";
      echo "<tr>\n<td>\n";

      //Populate a list of potential types
      $chanRow = mysqli_fetch_array($knownChan);
      $knownTypes = array();
      $chanMatch = array();

      while($chanRow) {
        $knownTypes[] = $chanRow['type'];
        $chanRow = mysqli_fetch_array($knownChan);

        if($chanRow['type'] == $srcRow["type"]) $chanMatch[$chanRow['name']] = $chanRow['id'];
      }

      mysqli_data_seek($knownChan, 0);

      $knownTypes = array_unique($knownTypes);

      echo "Name: \n";
      echo "<input type='text' name='name' value='" . $srcRow["name"] . "'>\n<br>\n";

      echo "Source Type: \n";
      echo "<select name='srcType'>\n";
      
      foreach ($knownTypes as $typeName) {
        $selOpt = "";
        if($typeName == $srcRow["type"]) $selOpt = "selected";

        echo "<option value='" . $typeName . "' " . $selOpt . ">" . $typeName . "</option>\n";
      }
      echo "</select>\n";

      echo "<br>\n";
      echo "Channels Used:\n<br>\n";

      $j=1;

      foreach ($chanMatch as $chanName => $chanID) {
        //TODO: pre-check if already mapped.
        echo "<input type='checkbox' name='ch" . $j . "' value='" . $chanID . "'>";
        echo($chanName . "\n<br>\n");
        $j++;
      }
      $j--;
      echo "<input type='hidden' name='numchan' value='" . $j . "'>\n";
      echo "</td>\n";

      //TODO parse through each associated profile

      echo "</tr>\n"; 
      echo "</table>\n";
    } else {
      //Show an edit link
      echo "</table>\n";
      echo "<p class='alignright'>\n";
      echo "<a href='" . $_SERVER["SCRIPT_NAME"] . "?mode=source&edit=" . $srcRow["id"] . "'>";
      echo "edit</a>\n";
    }

    echo "</td>\n";
    echo "</tr>\n";
  }

  echo "</table>\n";
  
  

}


function chanConfigForm($mysqli, $debug_mode)
{
  //Paint the page organizing by source
  echo "<h3>\nSort By:\n";
  echo "<table width='100%'>\n";
  echo "<tr>\n";
  echo "<td align='center'>\n";
  echo "<a href='" . $_SERVER["SCRIPT_NAME"] . "?mode=source'>\n";
  echo "Source\n";
  echo "</a>\n";
  echo "</td>\n";
  echo "<td align='center'>\n";
  echo "Channel\n";
  echo "</td>\n";
  echo "<td align='center'>\n";
  echo "<a href='" . $_SERVER["SCRIPT_NAME"] . "?mode=profile'>\n";
  echo "Profile\n";
  echo "</a>\n";
  echo "</td align='center'>\n";
  echo "</tr>\n";
  echo "</table>\n";
  echo "</h3>\n";


  // Grab our existing data
  $knownFn = mysqli_query($mysqli, "SELECT id,name FROM function");
  $knownPts = mysqli_query($mysqli, "SELECT id,value,timeAdj,timeType,timeSE,function FROM point ORDER BY function, timeSE, timeAdj");
  $knownSrc = mysqli_query($mysqli, "SELECT id, name, scale FROM source");
  $knownReact = mysqli_query($mysqli, "SELECT id, action, scale, channel, react FROM reaction");
  $knownChan = mysqli_query($mysqli, "SELECT id, name, type, variable, active, max, min, color, units FROM channel");
  $knownProf = mysqli_query($mysqli, "SELECT id, name, start, end, refresh, scale, reaction, function FROM profile");
  $knownCPS = mysqli_query($mysqli, "SELECT id, scale, channel, profile, source FROM cps");
  $knownSched = mysqli_query($mysqli, "SELECT id, name, type, profile FROM scheduler");

  //TODO

}


function profConfigForm($mysqli, $debug_mode)
{
  //Paint the page organizing by source
  echo "<h3>\nSort By:\n";
  echo "<table width='100%'>\n";
  echo "<tr>\n";
  echo "<td align='center'>\n";
  echo "<a href='" . $_SERVER["SCRIPT_NAME"] . "?mode=source'>\n";
  echo "Source\n";
  echo "</a>\n";
  echo "</td>\n";
  echo "<td align='center'>\n";
  echo "<a href='" . $_SERVER["SCRIPT_NAME"] . "?mode=channel'>\n";
  echo "Channel\n";
  echo "</a>\n";
  echo "</td>\n";
  echo "<td align='center'>\n";
  echo "Profile\n";
  echo "</td>\n";
  echo "</tr>\n";
  echo "</table>\n";
  echo "</h3>\n";


  // Grab our existing data
  $knownFn = mysqli_query($mysqli, "SELECT id,name FROM function");
  $knownPts = mysqli_query($mysqli, "SELECT id,value,timeAdj,timeType,timeSE,function FROM point ORDER BY function, timeSE, timeAdj");
  $knownSrc = mysqli_query($mysqli, "SELECT id, name, scale FROM source");
  $knownReact = mysqli_query($mysqli, "SELECT id, action, scale, channel, react FROM reaction");
  $knownChan = mysqli_query($mysqli, "SELECT id, name, type, variable, active, max, min, color, units FROM channel");
  $knownProf = mysqli_query($mysqli, "SELECT id, name, start, end, refresh, scale, reaction, function FROM profile");
  $knownCPS = mysqli_query($mysqli, "SELECT id, scale, channel, profile, source FROM cps");
  $knownSched = mysqli_query($mysqli, "SELECT id, name, type, profile FROM scheduler");

  //TODO

}


function configRtn($mysqli, $postRet)
{
  // Handle our form

  // Check the token
  if(!\aqctrl\token_check($mysqli, $debug_mode)) {
    //We don't have a good token
    if ($debug_mode) echo "<p>Error: token not accepted</p>\n";
    return($_SERVER["QUERY_STRING"]);
  }

  // First test for the "new" buttons
  if(isset($_POST['new'])) {
    // The only thing that we explicitly create new items of is sources
    $sql = "INSERT INTO source (name, scale, type) VALUES ('new', 1, 'new')";

    if(!mysqli_query($mysqli, $sql)) {
      if ($debug_mode) echo "<p>Error adding new source" . mysqli_error($mysqli) . "\n</p>\n";
    }
    return("mode=source&edit=" . mysqli_insert_id($mysqli)); //We can return because all we're doing is creating a new src.
  }


    

  // Note: return is handled as a query string with checking, so don't return unchecked things from the wild
  return "edit=CreatedNum";
}

