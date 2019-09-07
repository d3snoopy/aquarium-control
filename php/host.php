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

// host.php

// Module to handle interactions with the hosts

// TODO: Add in a last known address portion so we can potentially call in to a host

namespace aqctrl;

set_include_path("model");

/* Plan:

1. Parse the message from the host (POST data)
   a. If it's existing & validated: validate the message, save data provided, create return message.
   b. If it's existing & non-validated: do nothing.
   c. If it's new: add all of the info to the dB, mark as unvalidated.

*/

// Pull in the models - general model and calcFn
include 'model.php';
include 'calcFn.php';

// Find out if we're in debug mode.
$debug_mode = \aqctrl\debug_status();

// Connect to the dB
$mysqli = \aqctrl\db_connect();

if(!$mysqli) {
  //Complain about not being able to connect and give up.
  if ($debug_mode) echo "Could not connect to dB";
  return;
}


if ($debug_mode) {
  // Log our input for debugging purposes.
  $stmt = $mysqli->prepare("INSERT INTO hostlog (host, value) VALUES (?, ?)");
  $stmt-> bind_param("is", $fakeHost , $myQuery);

  $fakeHost = 0;
  $myQuery = var_export($_POST, true);

  if(!$stmt->execute() && $debug_mode) {
    echo "Execute failed: (" . $stmt->errno . ") " . $stmt->error;
  }
}



// Explode the host data from the host.
$data_in = explode(",", $_POST['host']);

// Decide whether we know about this host yet.
$hostIdent = mysqli_real_escape_string($mysqli, $data_in[0]);

$hostRet = mysqli_query($mysqli, "SELECT id,ident,auth,UNIX_TIMESTAMP(lastPing),inInterval,outInterval,pingInterval FROM host WHERE ident = '$hostIdent'");
$hostFound = (boolean)mysqli_num_rows($hostRet);

// Start our response data
$data_out =  (string)time();
$data_out .= ",";

if($hostFound) {
  // We've seen this host before.
  $row = mysqli_fetch_assoc($hostRet);

  // Check date against lastping, make sure we're not seeing a replay.
  $lastPing = $row["UNIX_TIMESTAMP(lastPing)"];
  // Update lastping if this is really a new ping.
  $timeNow = time();

  if((int)$data_in[2] >= (int)$lastPing && (int)$data_in[2] <= ($timeNow+10)) { //Allow for 10 seconds drift
    $timeNow = (int)$data_in[2];
    if(!mysqli_query($mysqli, "UPDATE host SET lastPing = FROM_UNIXTIME(
      " . $timeNow . ") WHERE id = "
      . $row['id'])) {
        if ($debug_mode) echo "Error updating ping: " . mysqli_error($mysqli);
        $data_out .= time() + 600;
        $data_out .= ',60,60,;';
    }

    if(!$row['auth']) {
       mysqli_close($mysqli);
       if ($debug_mode) echo "Known, non-validated host";
       $data_out .= time() + 600;
       $data_out .= ',60,60,;';
       mysqli_query($mysqli, "UPDATE host SET status =
         'Not Validated' WHERE id = " . $row['id']);
    } elseif(isset($_POST['time']) && isset($_POST['data']) && isset($_POST['HMAC'])) {
      // We've also validated this host.
      // Check the hash.
      $hash_expected = hash_HMAC('sha256', $_POST['host'] . $_POST['time'] . $_POST['data'], $row['auth']);

      if(md5($_POST['HMAC']) === md5($hash_expected)) {
        // We have a valid message.

        $data_out = \aqctrl\host_process($data_in, $data_out, $mysqli, $row);

      } else {
        // Hash failed
        if ($debug_mode) echo "HMAC failed";
        $data_out .= time() + 600;
        $data_out .= ',60,60,;';
        mysqli_query($mysqli, "UPDATE host SET status =
          'Bad Key' WHERE id = " . $row['id']);
      }
    } else {
      mysqli_query($mysqli, "UPDATE host SET status =
          'Malformed Message' WHERE id = " . $row['id']);
    }

  } else {
    if ($debug_mode) echo "Check your date";
    $data_out .= time() + 600;
    $data_out .= ',60,60,;';
    mysqli_query($mysqli, "UPDATE host SET status =
      'Bad Date (Host not accepting key)' WHERE id = " . $row['id']);
  }

  //Add the HMAC
  $data_out .= ";";
  $HMAC = hash_HMAC('sha256', $data_out, $row['auth']);

  $data_out .= $HMAC;

  //echo "\r\n\r\n";
  //echo $data_out;
  $hostID = $row['id'];

}

// Test to see if this is a new host.
if(!$hostFound && isset($_POST['host'])) {
  \aqctrl\host_add($data_in, $mysqli);
  $row['auth'] = 0;
  $data_out .= time() + 600;
  $data_out .= ',60,60,;';
  $hostID = 0;
}

echo "\r\n\r\n";
echo $data_out;

if ($debug_mode) {
  // Log our output for debugging purposes.
  $stmt = $mysqli->prepare("INSERT INTO hostlog (host, value) VALUES (?, ?)");
  $stmt-> bind_param("is", $hostID, $data_out);

  if(!$stmt->execute() && $debug_mode) {
    echo "Execute failed: (" . $stmt->errno . ") " . $stmt->error;
  }
}

// Disconnect and return
mysqli_close($mysqli);
return;



function host_process($data_in, $data_out, $mysqli, $row)
{
  //Function to process a validated host with a validated message.

  // Get the channel dB objects that match to our host.
  global $debug_mode;

  $chanRes = mysqli_query($mysqli, "SELECT id,name,variable,active,input,max,min,UNIX_TIMESTAMP(lastPing) FROM channel
    WHERE host = " . $row['id'] . " AND hostChNum = " . (int)$data_in[4]);

  if(!$chanRes) {
    if ($debug_mode) echo("Could not get channel list for host in host_process" . mysqli_error($mysqli));
    return;
  }

  // Test if we found this channel.
  if(!mysqli_num_rows($chanRes)){
    $chanInfo = \aqctrl\channel_add($data_in, $mysqli, $row['id']);
  } else {
    $chanInfo = mysqli_fetch_assoc($chanRes);
  }

  // Track if data is dropped
  $dataDropped = 0;

  // Get how many values to limit to
  $chanValLim = (int)$data_in[3];

  // Set up the response array
  $data_out .= time() + $row['pingInterval'] . ",";
  $data_out .= $row['inInterval'] . ",";
  $data_out .= $row['outInterval'] . ",";

  // Use the channel in our message
  $lastPing = $chanInfo['UNIX_TIMESTAMP(lastPing)'];

  // Check if this channel is active and for a replay within this second.
  if(($chanInfo['active']) && ((int)$data_in[2] > (int)$lastPing)){
    // Process this channel: Save data provided if input, otherwise generate data.
    if((int)$chanInfo['input']){

      // Get the timestamps and data
      $times = explode(",", $_POST["time"]);
      $values = explode(",", $_POST["data"]);

      $stmt = $mysqli->prepare("INSERT INTO data (date, value, channel) VALUES (FROM_UNIXTIME(?), ?, ?)");

      $stmt-> bind_param("idi", $insTime, $insValue, $insChan);

      foreach($times as $i => $timeStamp) {
        // Add data for this channel.
        // Enforce the min, max specified for the channel; drop the data if it's out of range.

        if($values[$i]>$chanInfo['max'] || $values[$i]<$chanInfo['min']) {
          $dataDropped = 1;
          continue;
        }

        $insTime = $timeStamp;
        $insValue = $values[$i];
        $insChan = $chanInfo['id'];

        if(!$stmt->execute() && $debug_mode) {
          echo "Execute failed: (" . $stmt->errno . ") " . $stmt->error;
        }
      }

      // This is an input channel, so we don't need to return any data.
      $data_out .= ';';
    } else {
      // Output channel; return data
      //reset the result pointer
      mysqli_data_seek($chanRes, 0);

      $calcData = \aqctrl\channelCalc($mysqli, $chanRes, 0, 0, 0, 0, 0, $chanValLim);

      foreach($calcData['timePts'] as $timePt) {
        $data_out .= "$timePt,";
      }

      $data_out .= ';';

      foreach($calcData['data0'] as $dataPt) {
        $data_out .= "$dataPt,";
      }

    }

    $timeNow = time();
    $sql = "UPDATE channel SET lastPing = FROM_UNIXTIME($timeNow) WHERE id = " . $chanInfo['id'];

    if (!mysqli_query($mysqli, $sql)) {
      if ($debug_mode) echo "query: " . $sql . " failed. " . mysqli_error($mysqli);
    } 

  } else {
    //Channel inactive or replay.
    if ($debug_mode) echo "Channel Inactive or replay.\n";
    $data_out .= ';';
  }

  //Update the last status
  $statusMsg = 'Success';

  if ($dataDropped) $statusMsg = $statusMsg . ", Some Data Dropped.";

  mysqli_query($mysqli, "UPDATE host SET status =
    '$statusMsg'  WHERE id = " . $row['id']);

  mysqli_query($mysqli, "UPDATE host SET ip = '" . $_SERVER['REMOTE_ADDR'] . "' WHERE id = "
    . $row['id']);

  return $data_out;
}



function host_add($data_in, $mysqli)
{
  //Function to add a newly discovered host to the dB.
  global $debug_mode;

  //Add the host
  $sqlQuery = "INSERT INTO host (ident, name, auth, lastPing, inInterval, outInterval,
    pingInterval, status) VALUES ('"
    . mysqli_real_escape_string($mysqli, $data_in[0]) . "', '"
    . mysqli_real_escape_string($mysqli, $data_in[1]) . "',
    0, FROM_UNIXTIME(" . time() . "), 60, 60, 300, 'New Host')";

  if(!mysqli_query($mysqli, $sqlQuery)) {
    if ($debug_mode) echo "Error inserting host: " . mysqli_error($mysqli);
  } else {
    if ($debug_mode) echo "Successfully added host";
  }
}



function channel_add($data_in, $mysqli, $hostId)
{
  //Function to add channels to our host, called the first time the host pings
  global $debug_mode;

  $stmt = $mysqli->prepare("INSERT INTO channel (name, type, variable, active, input, hostChNum,
    max, min, color, units, lastPing, host) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, FROM_UNIXTIME(?), ?)");

  $stmt-> bind_param("ssiiiiddssii", $newName, $newType, $newVariable, $newActive, $newInput, $newHostCh,
    $newMax, $newMin, $newColor, $newUnits, $newLastping, $newHost);

  $newName = $data_in[5];
  $newType = $data_in[6];
  $newVariable = $data_in[7];
  $newActive = $data_in[8];
  $newInput = $data_in[13];
  $newHostCh = $data_in[4];
  $newMax = $data_in[9];
  $newMin = $data_in[10];
  $newColor = $data_in[11];
  $newUnits = $data_in[12];
  $newLastping = $data_in[2];
  $newHost = $hostId;
    
  // Do the query
  if(!$stmt->execute() && $debug_mode) {
      echo "Execute failed: (" . $stmt->errno . ") " . $stmt->error;
  } else {
    if ($debug_mode) echo "Successfully added channel. ";
  }

  // Return
  return [
    "id" => mysqli_insert_id($mysqli),
    "name" => $newName,
    "type" => $newType,
    "variable" => $newVariable,
    "active" => $newActive,
    "input" => $newInput,
    "hostChNum" => $newHostCh,
    "max" => $newMax,
    "min" => $newMin,
    "color" => $newColor,
    "units" => $newUnits,
    "UNIX_TIMESTAMP(lastPing)" => $newLastping-1,
    "host" => $hostId];
}
