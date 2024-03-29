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

include_once("calcFn.php");


function configForm($mysqli, $debug_mode, $myTimes)
{

  // Show the form
  echo "<h2>Configure</h2>";

  // Determine how to organize the data
  // Organize either by source, channel, or profile
  if (isset($_GET["mode"]) && ($_GET["mode"] == "channel")) {
    \aqctrl\chanConfigForm($mysqli, $debug_mode, $myTimes);
  } else {
    \aqctrl\srcConfigForm($mysqli, $debug_mode, $myTimes);
  }

  echo "<p>\n<input type='submit' name='new' value='Create New Source'>\n</p>\n";

  //Add some csrf/replay protection
  echo \aqctrl\token_insert($mysqli, $debug_mode);
}


function srcConfigForm($mysqli, $debug_mode, $myTimes)
{
  //Paint the page organizing by source
  echo "<h3>\nSort By:\n";
  echo "<table>\n";
  echo "<tr>\n";
  echo "<td align='center'>\n";
  echo "Source\n";
  echo "</td>\n";
  echo "<td align='center'>\n";
  echo "<a href='" . \aqctrl\retGen(false, -1, 'channel', false, false) . "'>\n";
  echo "Channel\n";
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
  $knownFn = mysqli_query($mysqli, "SELECT * FROM function");
  $knownPts = mysqli_query($mysqli, "SELECT * FROM point ORDER BY function, timeSE, timeAdj");
  $knownReact = mysqli_query($mysqli, "SELECT * FROM reaction");
  $knownChan = mysqli_query($mysqli, "SELECT * FROM channel WHERE input=0 AND active=1");
  $knownProf = mysqli_query($mysqli, "SELECT id, name, UNIX_TIMESTAMP(start), UNIX_TIMESTAMP(end), refresh, reaction, function FROM profile");
  $knownCPS = mysqli_query($mysqli, "SELECT * FROM cps ORDER BY source, channel, profile");

  // Warn if we don't know about any channels
  if(!mysqli_num_rows($knownChan)) {
    echo "<h3>Warning: no channels found.</h3>\n";
    echo "<p>Channels are created to connecting hardware hosts via the setup page, you can't manually create channels.</p>\n";
    echo "<p>You won't be able to properly configure sources until the hardware hosts connect and inform the system about their channels. Click <a href=./setup.php>here</a> to go to the setup page.</p>\n";
  }

  $startTime = $myTimes['startTime'];
  $endTime = $myTimes['endTime'];
  $scale = $myTimes['scale'];
  $timeUnits = $myTimes['timeUnits'];

  // Calculate our data.
  $inputData = \aqctrl\doCalc($mysqli, $knownSrc, $knownChan, $knownFn, $knownPts, $knownProf, $knownCPS, 1000, $startTime, $endTime);

  //Collect the profile names.
  $profNames = array();
  foreach($knownProf as $profRow) {
    $profNames[$profRow['id']] = $profRow['name'];
  }

  // Cycle through all of our sources
  echo "<table>\n";

  foreach($knownSrc as $srcRow) {
    echo "<tr>\n";
    echo "<td>\n";
    echo "<h3>" . $srcRow["name"] . "</h3>\n";
    echo "<table>\n";

    $assocProf = array();
    $CPSfound = false;
    //Build list of profiles associated with this source.
    foreach($knownCPS as $CPSRow) {
      //Test if this CPS is for this source
      if ($CPSRow["source"] == $srcRow["id"]) {
        $CPSfound = true;
        if($CPSRow["profile"]) $assocProf[] = $CPSRow["profile"]; //If it's Null, it isn't added.
      }
    }

    $assocProf = array_unique($assocProf);

    if(!$CPSfound) {
      echo "<tr>\n<td>\nNothing associated with this source yet, edit it to add associations\n</td>\n</tr>\n";
    } else {
      // We found associations, do stuff!
      echo "<tr>\n<td>\n";
      \aqctrl\sourcePlot($inputData, $srcRow, $knownChan, $scale, $timeUnits);
      echo "</td>\n";
      echo "<td>\n<h1>=</h1>\n</td>\n";

      foreach($assocProf as $profID) {
        echo "<td>\n";
        \aqctrl\profilePlot($inputData, $srcRow, $profID, $profNames[$profID], $knownChan, $scale, $timeUnits);
        echo "</td>\n";
        
        if($profID != end($assocProf)) echo "<td>\n<h1>*</h1>\n</td>\n";
      }

      echo "</tr>\n";
    }

    if(isset($_GET['edit']) && $_GET['edit'] == $srcRow["id"]) {
      //Give all of the controls
      echo "<div id='edit'>\n";
      echo "<input type='hidden' name='editID' value=" . $srcRow["id"] . ">\n";
      echo "<tr>\n<td>\n";

      //Populate a list of potential types
      $knownTypes = array();
      $chanMatch = array();

      foreach($knownChan as $chanRow) {
        $knownTypes[] = $chanRow['type'];

        if($chanRow["type"] == $srcRow["type"]) $chanMatch[$chanRow['name']] = $chanRow['id'];
      }

      $knownTypes = array_unique($knownTypes);

      echo "Source Info\n<br>\n";
      echo "Name: \n";
      echo "<input type='text' name='name' value='" . $srcRow["name"] . "'>\n<br>\n";

      echo "Source Scale: \n";
      echo "<input type='number' name='scale' value='" . $srcRow["scale"] . "' step='any'>\n<br>\n";

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

      $j=0;

      $chansSel = array();

      foreach ($chanMatch as $chanName => $chanID) {
        $chSel = "";

        foreach($knownCPS as $CPSRow) {
          if (($CPSRow['channel'] == $chanID) && ($CPSRow['source'] == $srcRow["id"])) {
            $chSel = "checked";
            $chansSel[] = $CPSRow['channel'];
            break; //We're finished here, so don't bother finishing the iteration.
          }
        }

        echo "<input type='checkbox' name='ch" . $j . "' value='" . $chanID . "' " . $chSel . ">";
        echo($chanName . "\n<br>\n");
        $j++;
      }
      echo "Add profile: \n";
      echo "<select name='profSel'>\n";
      echo "<option value='New'>New</option>\n";


      foreach ($knownProf as $prof) {
        if(!in_array($prof['id'],$assocProf)) { 
          echo "<option value='" . $prof['id'] . "'>" . $prof['name'] . "</option>\n";
        }
      }

      echo "<input type='submit' name='profAdd' value='Add' />\n";
      echo "<input type='hidden' name='numchan' value='$j'>\n";
      echo "<br>";
      echo "<input type='submit' name='delSrc' value='Delete Source' />\n";
      echo "</td>\n<td>\n</td>\n";

      $j = 0;

      foreach ($assocProf as $profNum => $profID) {
        echo "<td>\n";
        echo "Channels:\n";

        foreach ($chanMatch as $chanName => $chanID) {
          //Go through our channels; they're not necessarily all mapped.
          $chanFound = "";

          //First, check if this channel is selected for the source.
          if(!in_array($chanID, $chansSel)) continue;

          foreach ($knownCPS as $CPSRow) {
            if(($CPSRow['source'] == $srcRow['id']) && ($CPSRow['profile'] == $profID) &&
              ($CPSRow['channel'] == $chanID)) {
                //We have a CPS for this mapping.
                $chanFound = 'checked';
                break;
            }
          }

          echo "<br>\n<input type='checkbox' name='check$j' value='$profID" . "_$chanID' $chanFound>\n";
          echo $chanName;

          if($chanFound) {
            echo " scale: \n";
            echo "<input type='number' name='scale$j' value='" . $CPSRow['scale'] .
              "' step='any' size='1'>\n";
            echo "& offset: \n";
            echo "<input type='number' name='offset$j' value='" . $CPSRow['offset'] .
              "' step='any' size='1'>\n";
            echo " sec.\n";
          }
          $j++;
        }

        echo "<br>\n<a href='" . \aqctrl\retGen('profile.php', $profID, 'single', false, false)
          . "'>Edit Profile</a>\n<br>\n";
        echo "<input type='submit' name='delProf$profNum' value='Remove Profile from Source' />\n";
        echo "<input type='hidden' name='prof$profNum' value='$profID'>\n";
        echo "</td>\n";

        if($profID != end($assocProf)) echo "<td>\n</td>\n";
      }

      echo "<input type='hidden' name='numcheck' value='$j'>\n";
      echo "</tr>\n"; 
      echo "</table>\n";
      echo "<p class='alignright'>\n";
      echo "<input type='submit' name='update' value='Update' />\n";
      echo "</div>\n";
    } else {
      //Show an edit link
      echo "</table>\n";
      echo "<p class='alignright'>\n";
      $extraOpts = \aqctrl\preserveMyTimes();
      echo "<a href='" . \aqctrl\retGen(false, $srcRow["id"], false, $extraOpts, false) . "'>";
      echo "edit</a>\n";
    }

    echo "</td>\n";
    echo "</tr>\n";
  }

  echo "</table>\n";
  
  

}


function chanConfigForm($mysqli, $debug_mode, $myTimes)
{
  //Paint the page organizing by channel
  echo "<h3>\nSort By:\n";
  echo "<table>\n";
  echo "<tr>\n";
  echo "<td align='center'>\n";
  echo "<a href='" . \aqctrl\retGen(false, -1, 'source', false, false) . "'>\n";
  echo "Source\n";
  echo "</a>\n";
  echo "</td>\n";
  echo "<td align='center'>\n";
  echo "Channel\n";
  echo "</td>\n";
  echo "</tr>\n";
  echo "</table>\n";
  echo "</h3>\n";

  // Grab our data and plot (for now don't provide editing.
  \aqctrl\configIndex($mysqli, $debug_mode, $myTimes);
}

function configIndex($mysqli, $debug_mode, $myTimes=false)
{
  // Grab our data and plot (for now don't provide editing.
  $knownChan = mysqli_query($mysqli, "SELECT id, name, type, variable, active, max, min, color, units FROM channel WHERE input=0 AND active=1 ORDER BY type, id");

  $numChan = mysqli_num_rows($knownChan);

  if(!$numChan) {
    echo "<p>No Channels Known.</p>\n";
    return;
  }

  if($myTimes) {
    $startTime = $myTimes['startTime'];
    $endTime = $myTimes['endTime'];
    $scale = $myTimes['scale'];
    $timeUnits = $myTimes['timeUnits'];
  } else {
    $startTime = false;
    $endTime = false;
    $scale = 3600;
    $timeUnits = "hr from now";
  }

  $stageData = \aqctrl\doCalc($mysqli, 0, $knownChan, 0, 0, 0, 0, 1000, $startTime, $endTime);

  //Convert our timePts to hours from now.
  $timeNow = time();
  foreach($stageData['timePts'] as $i => $v) {
    $stageData['timePts'][$i] = ($v-$timeNow)/$scale;
  }

  \aqctrl\plotChanByType($stageData, $knownChan, "Schedule", $timeUnits);
}


function configRtn($mysqli, $debug_mode)
{
  // Handle our form

  // Check the token
  if(!\aqctrl\token_check($mysqli, $debug_mode)) {
    //We don't have a good token
    if ($debug_mode) echo "<p>Error: token not accepted</p>\n";
    return;
  }

  // First test for the "New Source" button
  if(isset($_POST['new'])) {
    // The only thing that we explicitly create new items of is sources
    $sql = "INSERT INTO source (name, scale, type) VALUES ('new', 1, 'new')";

    if(!mysqli_query($mysqli, $sql)) {
      if ($debug_mode) echo "<p>Error adding new source" . mysqli_error($mysqli) . "\n</p>\n";
    }
    return(['edit' => mysqli_insert_id($mysqli)]); //We can return because all we're doing is creating a new src.
  }

  // Next, test for the "Delete Source" button
  if(isset($_POST["delSrc"])) {
    $sql = "DELETE FROM source WHERE id = " . (int)$_POST['editID'];

    if(!mysqli_query($mysqli, $sql)) {
      if ($debug_mode) echo "<p>Error deleting source" . mysqli_error($mysqli) . "\n</p>\n";
    }
    return; //We can return
  }

  // Next, test for the "Remove Profile from source" button
  $i = 0;

  while(isset($_POST["prof$i"])) {
    if(isset($_POST["delProf$i"])) {
      //We want to delete this profile association.
      $sql = "DELETE FROM cps WHERE profile = " . (int)$_POST["prof$i"] . " AND source = " . (int)$_POST['editID'] ;

      if(!mysqli_query($mysqli, $sql)) {
        if ($debug_mode) echo "<p>Error deleting profile CPSes " . mysqli_error($mysqli) . "\n</p>\n";
      }

      return(['edit' => (int)$_POST['editID']]); //We can return
    }
    $i++;
  }


  // Next, test for the "Add a profile" button
  if(isset($_POST['profAdd'])) {
    $retArgs = false;
    // Test to see if "New" was selected.
    if($_POST["profSel"] == "New") {
      //Create a new profile
      $now = time();
      $sql = "INSERT INTO profile (name, start, end, refresh) VALUES ('new', FROM_UNIXTIME($now),
        FROM_UNIXTIME($now+3600), 3600)";

      if(!mysqli_query($mysqli, $sql)) {
        if ($debug_mode) echo "<p>Error adding new profile " . mysqli_error($mysqli) . "\n</p>\n";
      }

      $addID = mysqli_insert_id($mysqli);

      $sql = "INSERT INTO cps (scale, 'offset', profile, source) VALUES (1, 0, " . $addID
        . ", " . (int)$_POST['editID'] . ")";

      if(!mysqli_query($mysqli, $sql)) {
        if ($debug_mode) echo "<p>Error adding CPS " . mysqli_error($mysqli) . "\n</p>\n";
      }

      //Redirect to the page to edit our new profile.
      $retArgs = ['loc' => 'profile.php', 'edit' => $addID, 'mode' => 'single'];

    } else {
      //Associate existing profile with this source.
      $addID = (int)$_POST['profSel'];

      $sql = "INSERT INTO cps (scale, `offset`, profile, source) VALUES (1, 0, " . $addID
        . ", " . (int)$_POST['editID'] . ")";

      if(!mysqli_query($mysqli, $sql)) {
        if ($debug_mode) echo "<p>Error adding CPS" . mysqli_error($mysqli) . "\n</p>\n";
      }

      //Just keep editing this one.
      $retArgs = ['edit' => (int)($_POST['editID'])];

    }

    //Add a CPS for each channel already associated.
    $sql = "SELECT channel FROM cps WHERE source = " . (int)$_POST['editID'];

    $selCPS = mysqli_query($mysqli, $sql);

    if(!$selCPS) {
      if ($debug_mode) echo "<p>Error getting CPSes" . mysqli_error($mysqli) . "\n</p>\n";
    }

    $assocChan = [];

    foreach($selCPS as $thisCPS) {
      if($thisCPS['channel']) $assocChan[] = $thisCPS['channel']; //Don't add Nulls
    }

    $assocChan = array_unique($assocChan);

    foreach($assocChan as $thisChan) {
      $sql = "INSERT INTO cps (scale, `offset`, profile, channel, source) VALUES (1, 0, $addID, $thisChan, "
        . (int)$_POST['editID'] . ")";

      if(!mysqli_query($mysqli, $sql)) {
        if ($debug_mode) echo "<p>Error adding CPS" . mysqli_error($mysqli) . " " . $sql . "\n</p>\n";
      }
    }

    //Return (we no always have define retargs)
    if($retArgs) return($retArgs);
  }

  // Next, test for a change in type; if so, clear all CPS with a channel associated with this source
  $sql = "SELECT id, name, scale, type FROM source WHERE id = " . (int)$_POST['editID'];

  $srcRet = mysqli_query($mysqli, $sql);

  if(!$srcRet) {
    if ($debug_mode) echo "<p>Error getting source" . mysqli_error($mysqli) . "\n</p>\n";
  }

  $srcInfo = mysqli_fetch_array($srcRet);

  if($srcInfo["type"] != $_POST["srcType"]) {
    $sql = "DELETE FROM cps WHERE source =" . $srcInfo['id'] . " AND channel IS NOT NULL";
    
    if(!mysqli_query($mysqli, $sql)) {
      if ($debug_mode) echo "<p>Error deleting CPSes" . mysqli_error($mysqli) . "\n</p>\n";
    }
  }

  // Next, update the name, type, scale
  $stmt = $mysqli->prepare("UPDATE source SET name = ?, scale = ?, type = ? WHERE id = ?");

  $stmt-> bind_param("sdsi", $nameStr, $scaleInt, $typeStr, $srcID);

  $nameStr = $_POST["name"];
  $scaleInt = (float)$_POST["scale"];
  $typeStr = $_POST["srcType"];
  $srcID = $_POST["editID"];

  if(!$stmt->execute() && $debug_mode) {
    echo "Execute failed: (" . $stmt->errno . ") " . $stmt->error;
  }


  // Next, check and update our channel maps
  // Get all CPSes associated with this source
  $sql = "SELECT id, scale, 'offset', channel, profile, source FROM cps WHERE source = " . $srcInfo['id']
     . " ORDER BY channel, profile";

  $selCPS = mysqli_query($mysqli, $sql);

  if(!$selCPS) {
    if ($debug_mode) echo "<p>Error getting CPSes" . mysqli_error($mysqli) . "\n</p>\n";
  }

  // Build a list of the channels checked for this source.
  $srcChans = array();

  for($i=0; $i<(int)$_POST['numchan']; $i++) {
    //Test if the channel associated with the CPS is selected & add to list
    if(isset($_POST["ch$i"])) $srcChans[] = (int)$_POST["ch$i"];
  }

  // Prepare the three queries: update, delete, add
  $stmtUpdate = $mysqli->prepare("UPDATE cps SET scale = ?, `offset` = ? WHERE id = ?");
  $stmtDel = $mysqli->prepare("DELETE FROM cps WHERE id = ?");
  $stmtAdd = $mysqli->prepare("INSERT INTO cps (scale, `offset`, source, profile, channel) VALUES (1, 0, ?, ?, ?)");
  
  $stmtUpdate-> bind_param("ddi", $scaleNum, $offsetNum, $CPSID);
  $stmtDel-> bind_param("i", $CPSID);
  $stmtAdd-> bind_param("iii", $SrcID, $ProfID, $ChanID);

  // Build a list of the checkboxes checked and do 4. (below - add CPSes necessary)
  $checks = array();

  for($i=0; $i<(int)$_POST['numcheck']; $i++) {
    //Test if this check is set & add it to our list.
    if(isset($_POST["check$i"])) $checks[$i] = $_POST["check$i"];

    //Test if we need to add a CPS for this because it's a newly added check
    if(!isset($_POST["scale$i"]) && isset($_POST["check$i"])) {
      //Need to create this CPS
      $addInfo = explode("_", $_POST["check$i"]);
      $SrcID = $_POST['editID'];
      $ProfID = $addInfo[0];
      $ChanID = $addInfo[1];

      if(!$stmtAdd->execute() && $debug_mode) {
        echo "Execute failed: (" . $stmtAdd->errno . ") " . $stmtAdd->error;
      }
    }
  }

  //Add a tracker to make sure we found all of the channels checked
  //This will catch the case were we have the channel picked but not check for any profiles (or we have no profiles)

  $chanfound = array();

  foreach($selCPS as $CPSRow) {
    //We have four things to do:
    //1. See if the channel for this CPS is checked for the source, if it isn't delete it.
    //2. See if the channel, profile combo for this CPS is checked, if not delete it.
    //3. If we meet both criteria to keep it, update the scale and offset.
    //4. Check for missing CPSes that should be there (criteria 1 and 2 met, but the CPS is missing)
    //   and add the missing ones.
    //   This can be done by checking for check$i that exists but ID$i and scale$i don't.

    $chanfound[] = $CPSRow['channel'];
    $checkVar = (string)$CPSRow['profile'] . "_" . (string)$CPSRow['channel'];
    $checkFound = array_search($checkVar, $checks);

    if(in_array($CPSRow['channel'], $srcChans) && $checkFound !== false) {
      //3. Need to update the CPS
      $scaleNum = $_POST["scale$checkFound"];
      $offsetNum = $_POST["offset$checkFound"];
      $CPSID = $CPSRow['id'];

      if(!$stmtUpdate->execute() && $debug_mode) {
        echo "Execute failed: (" . $stmtUpdate->errno . ") " . $stmtUpdate->error;
      }
    } elseif($CPSRow['profile'] && $CPSRow['channel']) { //Make sure it's supposed to be aligned.
      //1. or 2. Need to delete this CPS
      $CPSID = $CPSRow['id'];

      if(!$stmtDel->execute() && $debug_mode) {
        echo "Execute failed: (" . $stmtDel->errno . ") " . $stmtDel->error;
      }
        
    }
  }


  //Check and make sure we have all channels desired, if not, add some with NULL for profile.
  $stmt = $mysqli->prepare("INSERT INTO cps (scale, `offset`, source, channel) VALUES (1, 0, ?, ?)");
  $stmt-> bind_param("ii", $SrcID, $ChanID);
  $SrcID = $_POST['editID'];

  foreach($srcChans as $chan) {
    if(!in_array($chan, $chanfound)) {
      //Add a CPS for this channel
      $ChanID = $chan;
      
      if(!$stmt->execute() && $debug_mode) {
        echo "Execute failed: (" . $stmt->errno . ") " . $stmt->error;
      }

    }
  }

  //Do a callback to our affected hosts.
  
  //Get the host ips.
  $IPs = mysqli_query($mysqli, "SELECT DISTINCT ip FROM host WHERE id IN (SELECT DISTINCT host FROM channel WHERE id IN (".implode(',', $srcChans)."))");

  //Pings the affected hosts.
  if(mysqli_num_rows($IPs)) {
    foreach($IPs as $ip) {
      $curl_handle = curl_init();
      curl_setopt( $curl_handle, CURLOPT_URL, $ip['ip'] );
      curl_exec( $curl_handle ); // Execute the request
      curl_close( $curl_handle );
    }
  }

  // Note: return is handled as a query string with checking, so don't return unchecked things from the wild
  return(['edit' => (int)($_POST['editID'])]);
}

