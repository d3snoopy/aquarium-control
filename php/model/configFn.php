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


TODO: rework using foreach loops rather than manually fetching/processing.
TODO: Don't include inactive channels

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
  if (isset($_GET["mode"]) && ($_GET["mode"] == "channel")) {
    \aqctrl\chanConfigForm($mysqli, $debug_mode);
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
  $knownFn = mysqli_query($mysqli, "SELECT id,name FROM function");
  $knownPts = mysqli_query($mysqli, "SELECT id,value,timeAdj,timeType,timeSE,function FROM point ORDER BY function, timeSE, timeAdj");
  $knownReact = mysqli_query($mysqli, "SELECT id, action, scale, channel, react FROM reaction");
  $knownChan = mysqli_query($mysqli, "SELECT id, name, type, variable, active, max, min, color, units FROM channel WHERE input=0 AND active=1");
  $knownProf = mysqli_query($mysqli, "SELECT id, name, UNIX_TIMESTAMP(start), UNIX_TIMESTAMP(end), refresh, reaction, function FROM profile");
  $knownCPS = mysqli_query($mysqli, "SELECT id, scale, channel, profile, source FROM cps ORDER BY source, channel, profile");

  // Warn if we don't know about any channels
  if(!mysqli_num_rows($knownChan)) {
    echo "<h3>Warning: no channels found.</h3>\n";
    echo "<p>Channels are created to connecting hardware hosts via the setup page, you can't manually create channels.</p>\n";
    echo "<p>You won't be able to properly configure sources until the hardware hosts connect and inform the system about their channels. Click <a href=./setup.php>here</a> to go to the setup page.</p>\n";
  }

  // Cycle through all of our sources
  echo "<table>\n";

  foreach($knownSrc as $srcRow) {
    echo "<tr>\n";
    echo "<td>\n";
    echo "<h3>" . $srcRow["name"] . "</h3>\n";
    echo "<table>\n";

    $assocProc = array();
    $CPSfound = false;
    //Build list of profiles associated with this source.
    foreach($knownCPS as $CPSRow) {
      //Test if this CPS is for this source
      if ($CPSRow["source"] == $srcRow["id"]) {
        $CPSfound = true;
        if($CPSRow["profile"]) $assocProf[] = $CPSRow["profile"]; //If it's Null, it isn't added.
      }

      $assocProf = array_unique($assocProf);

      if(!$CPSfound) {
        echo "<tr>\n<td>\nNothing associated with this source yet, edit it to add associations\n</td>\n</tr>\n";
      } else {
        // We found associations, do stuff!
        echo "<tr>\n<td>\n";
        $plotData = \aqctrl\sourceCalc($knownChan, $srcRow["id"], $knownFn, $knownPt, $knownProf,
          $knownCPS);
        
        \aqctrl\plotData($plotData);
        echo "</td>\n";
        echo "<td>\n=\n</td>\n";

        foreach($assocProf as $profID) {
          // TODO
          echo "<td>\nPlot for profile goes here\n</td>\n";
        
          if($profID != end($assocProf)) echo "<td>\n*\n</td>\n";
        }

        echo "</tr>\n";
      }

      if(isset($_GET['edit']) && $_GET['edit'] == $srcRow["id"]) {
        //Give all of the controls
        echo "<input type='hidden' name='editID' value=" . $srcRow["id"] . ">\n";
        echo "<tr>\n<td>\n";

        //Populate a list of potential types
        $chanRow = mysqli_fetch_array($knownChan);
        $knownTypes = array();
        $chanMatch = array();

        foreach($knownChan as $chanRow) {
          $knownTypes[] = $chanRow['type'];

          if($chanRow["type"] == $srcRow["type"]) $chanMatch[$chanRow['name']] = $chanRow['id'];

          $chanRow = mysqli_fetch_array($knownChan);
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
      $CPSRow = mysqli_fetch_assoc($knownCPS);

      foreach ($chanMatch as $chanName => $chanID) {
        $chSel = "";

        while (($CPSRow['source'] < $srcRow["id"]) && $CPSRow) {
          $CPSRow = mysqli_fetch_array($knownCPS);
        }

        while (($CPSRow['channel'] < $chanID) && $CPSRow) {
          $CPSRow = mysqli_fetch_array($knownCPS);
        }
        
        if (($CPSRow['channel'] == $chanID) && ($CPSRow['source'] == $srcRow["id"])) $chSel = "checked";

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
      echo "<input type='hidden' name='numchan' value='" . $j . "'>\n";
      echo "<br>";
      echo "<input type='submit' name='delSrc' value='Delete Source' />\n";
      echo "</td>\n<td>\n</td>\n";

      $j = 0;

      foreach ($assocProf as $profNum => $profID) {
        echo "<td>\n";
        echo "Channels:\n<br>\n";
        
        foreach ($knownCPS as $CPSRow) {
          if(($CPSRow['source'] == $srcRow['id']) && ($CPSRow['profile'] == $profID) && ($CPSRow['channel'])) {
            //This is a profile to list here.
            //Get the channel name for this CPS.
            foreach ($knownChan as $chan) {
              if($CPSRow['channel'] == $chan['id']) {
                echo $chan['name'] . " scale: \n";
                break;
              }
            }

            echo "<input type='number' name='scale$j' value='" . $CPSRow['scale'] . "' step='any'>\n<br>\n";
            echo "<input type='hidden' name='ID$j' value='" . $CPSRow['id'] . "'>\n";
            $j++;
          }
        }

        echo "<a href='" . \aqctrl\retGen('profile.php', $profID, 'single', false, false)
          . "'>Edit Profile</a>\n<br>\n";
        echo "<input type='submit' name='delProf$profNum' value='Remove Profile from Source' />\n";
        echo "<input type='hidden' name='prof$profNum' value='$profID'>\n";
        echo "</td>\n";

        if($profID != end($assocProf)) echo "<td>\n</td>\n";
      }

      echo "</tr>\n"; 
      echo "</table>\n";
      echo "<p class='alignright'>\n";
      echo "<input type='submit' name='update' value='Update' />\n";
    } else {
      //Show an edit link
      echo "</table>\n";
      echo "<p class='alignright'>\n";
      echo "<a href='" . \aqctrl\retGen(false, $srcRow["id"], false, false, false) . "'>";
      echo "edit</a>\n";
    }

    echo "</td>\n";
    echo "</tr>\n";
  }

  echo "</table>\n";
  
  

}


function chanConfigForm($mysqli, $debug_mode)
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

  //TODO

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
      $sql = "DELETE FROM cps WHERE profile = " . (int)$_POST["prof$i"];

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

      $sql = "INSERT INTO cps (scale, profile, source) VALUES (1, " . $addID
        . ", " . (int)$_POST['editID'] . ")";

      if(!mysqli_query($mysqli, $sql)) {
        if ($debug_mode) echo "<p>Error adding CPS " . mysqli_error($mysqli) . "\n</p>\n";
      }

      //Redirect to the page to edit our new profile.
      $retArgs = ['loc' => 'profile.php', 'edit' => $addID, 'mode' => 'single'];

    } else {
      //Associate existing profile with this source.
      $addID = (int)$_POST['profSel'];

      $sql = "INSERT INTO cps (scale, profile, source) VALUES (1, " . $addID
        . ", " . (int)$_POST['editID'] . ")";

      if(!mysqli_query($mysqli, $sql)) {
        if ($debug_mode) echo "<p>Error adding CPS" . mysqli_error($mysqli) . "\n</p>\n";
      }

    }

    //Add a CPS for each channel already associated.
    $sql = "SELECT channel FROM cps WHERE source = " . (int)$_POST['editID'];

    $selCPS = mysqli_query($mysqli, $sql);

    if(!$selCPS) {
      if ($debug_mode) echo "<p>Error getting CPSes" . mysqli_error($mysqli) . "\n</p>\n";
    }

    $assocChan = [];

    foreach($selCPS as $thisCPS) {
      $assocChan[] = $thisCPS['channel'];
    }

    $assocChan = array_unique($assocChan);

    foreach($assocChan as $thisChan) {
      $sql = "INSERT INTO cps (scale, profile, channel, source) VALUES (1, $addID, $thisChan, "
        . (int)$_POST['editID'] . ")";

      if(!mysqli_query($mysqli, $sql)) {
        if ($debug_mode) echo "<p>Error adding CPS" . mysqli_error($mysqli) . "\n</p>\n";
      }
    }

    //Only return here if we're creating a new Profile.
    if($retArgs) return($retArgs);
  }

  // Next, test for a change in type; if so, clear all CPS associated with this source
  $sql = "SELECT id, name, scale, type FROM source WHERE id = " . (int)$_POST['editID'];

  $srcRet = mysqli_query($mysqli, $sql);

  if(!$srcRet) {
    if ($debug_mode) echo "<p>Error getting source" . mysqli_error($mysqli) . "\n</p>\n";
  }

  $srcInfo = mysqli_fetch_array($srcRet);

  if($srcInfo["type"] != $_POST["srcType"]) {
    $sql = "DELETE FROM cps WHERE source =" . $srcInfo['id'];
    
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
  $sql = "SELECT id, scale, channel, profile, source FROM cps WHERE source = " . $srcInfo['id']
     . " ORDER BY channel, profile";

  $selCPS = mysqli_query($mysqli, $sql);

  if(!$selCPS) {
    if ($debug_mode) echo "<p>Error getting CPSes" . mysqli_error($mysqli) . "\n</p>\n";
  }

  $CPSRow = mysqli_fetch_array($selCPS);
  $foundChans = array();

  // Now, check on our mapping.  Account for new checks and new check removals.
  while ($CPSRow) {
    $chanSet = false;
    for($i=0; $i<(int)$_POST['numchan']; $i++) {
      //Test if the channel associated with the CPS is selected
      if(isset($_POST["ch$i"]) && ($_POST["ch$i"] == $CPSRow['channel'])) {
        $chanSet = true;
        $foundChans[] = $CPSRow['channel'];
      }
    }

    //If we didn't catch that the channel is set, and it's associated with a channel, delete.
    if(!is_null($CPSRow["channel"]) && !$chanSet) {
      $sql = "DELETE FROM cps WHERE id = " . $CPSRow['id'];

      if(!mysqli_query($mysqli, $sql)) {
        if ($debug_mode) echo "<p>Error deleting CPS" . mysqli_error($mysqli) . "\n</p>\n";
      }
    }
  $CPSRow = mysqli_fetch_array($selCPS);
  }

  //Get profiles already associated.
  $sql = "SELECT profile FROM cps WHERE source = " . (int)$_POST['editID'];

  $selCPS = mysqli_query($mysqli, $sql);

  if(!$selCPS) {
    if ($debug_mode) echo "<p>Error getting CPSes" . mysqli_error($mysqli) . "\n</p>\n";
  }

  $assocProf = [];

  foreach($selCPS as $thisCPS) {
    $assocProf[] = $thisCPS['profile'];
  }

  $assocProf = array_unique($assocProf);

  //Add missing channel associations
  for($i=0; $i<(int)$_POST['numchan']; $i++) {
    if(isset($_POST["ch$i"]) && !in_array($_POST["ch$i"],$foundChans)) {
      //Need to add a CPS for this channel.
      $thisChan = (int)$_POST["ch$i"];

      $sql = "INSERT INTO cps (scale, channel, source) VALUES (1, " . $thisChan
        . ", " . $srcInfo['id'] . ")";

      if(!mysqli_query($mysqli, $sql)) {
        if ($debug_mode) echo "<p>Error adding CPS" . mysqli_error($mysqli) . "\n</p>\n";
      }

      foreach($assocProf as $thisProf) {
        $sql = "INSERT INTO cps (scale, profile, channel, source) VALUES (1, $thisProf, $thisChan, "
          . $srcInfo['id'] . ")";

        if(!mysqli_query($mysqli, $sql)) {
          if ($debug_mode) echo "<p>Error adding CPS " . mysqli_error($mysqli) . "\n</p>\n";
        }
      }
    }
  }

  //Update all of our CPS scales.
  $i = 0;

  $stmt = $mysqli->prepare("UPDATE cps SET scale = ? WHERE id = ?");

  $stmt-> bind_param("di", $cpsScale, $cpsID);

  while(isset($_POST["ID$i"])) {
    $cpsScale = $_POST["scale$i"];
    $cpsID = $_POST["ID$i"];

    if(!$stmt->execute() && $debug_mode) {
      echo "Execute failed: (" . $stmt->errno . ") " . $stmt->error;
    }

    $i++;
  }

  // Note: return is handled as a query string with checking, so don't return unchecked things from the wild
  return(['edit' => (int)($_POST['editID'])]);
}

