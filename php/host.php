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

// TODO: Update to handle/check channel-level lastping rather than host-level lastping.

namespace aqctrl;

set_include_path("model");

/* Plan:

1. Parse the message from the host (POST data)
   a. If it's existing & validated: validate the message, save data provided, create return message.
   b. If it's existing & non-validated: do nothing.
   c. If it's new: add all of the info to the dB, mark as unvalidated.

*/

// Pull in the models - general model and hostFn
include 'model.php';
//include 'hostFn.php';

// Find out if we're in debug mode.
$debug_mode = \aqctrl\debug_status();

// Connect to the dB
$mysqli = \aqctrl\db_connect();

if(!$mysqli) {
  //Complain about not being able to connect and give up.
  if ($debug_mode) echo "Could not connect to dB";
  return;
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

  if((int)$data_in[2] >= (int)$lastPing) {
    if(!mysqli_query($mysqli, "UPDATE host SET lastPing = FROM_UNIXTIME(
      " . (int)$data_in[2] . ") WHERE id = "
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
    } else {
      // We've also validated this host.
      // Check the hash.
      $hash_expected = hash_HMAC('sha256', $_POST['host'] . $_POST['time'] . $_POST['data'], $row['auth']);

      if(md5($_POST['HMAC']) === md5($hash_expected)) {
        // We have a valid message.

        $data_out = \aqctrl\host_process($data_in, $data_out, $mysqli, $row);
        mysqli_query($mysqli, "UPDATE host SET status =
          'Success' WHERE id = " . $row['id']);

      } else {
        // Hash failed
        if ($debug_mode) echo "HMAC failed";
        $data_out .= time() + 600;
        $data_out .= ',60,60,;';
        mysqli_query($mysqli, "UPDATE host SET status =
          'Bad Key' WHERE id = " . $row['id']);
      }
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

  echo "\r\n\r\n";
  echo $data_out;
}

// Test to see if we found this host.
if(!$hostFound && isset($_POST['host'])) {
  \aqctrl\host_add($data_in, $mysqli);
  $row['auth'] = 0;
  $data_out .= time() + 600;
  $data_out .= ',60,60,;';
}

$data_out .= ';';

// Disconnect and return
mysqli_close($mysqli);
return;



function host_process($data_in, $data_out, $mysqli, $row)
{
  //Function to process a validated host with a validated message.

  // Get the channel dB objects that match to our host.
  global $debug_mode;

  $chanRes = mysqli_query($mysqli, "SELECT id,name,variable,active,max,min,UNIX_TIMESTAMP(lastPing) FROM channel
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

  // Set up a multiQuery string
  $mQuery = "";

  // Get how many values to limit to
  $chanValLim = (int)$data_in[3];

  // Set up the response array
  $data_out .= time() + min($row['pingInterval'], $chanValLim*$row['inInterval']) . ",";
  $data_out .= $row['inInterval'] . ",";
  $data_out .= $row['outInterval'] . ",";

  // Use the channel in our message
  $lastPing = $chanInfo['UNIX_TIMESTAMP(lastPing)'];

  // Check if this channel is active and for a replay within this second.
  if(($chanInfo['active']) && ((int)$data_in[2] > (int)$lastPing)){
    // Process this channel: Save data provided, generate data.    

    // Get the timestamps and data
    $times = explode(",", $_POST["time"]);
    $values = explode(",", $_POST["data"]);

    foreach($times as $i => $timeStamp) {
      // Add data for this channel.
      if (is_numeric($timeStamp) && is_numeric($values[$i]))
        $mQuery .= "INSERT INTO data (date, value, channel)
          VALUES (FROM_UNIXTIME(" . $timeStamp . "), " . $values[$i] . ", "
          . $chanInfo['id'] . ");";
    }

    // Construct the return.
    $data_out .= \aqctrl\chan_vals($chanInfo, $chanValLim);
  } else {
    //Just construct the "data" portion of the return without populating with data.
    $data_out .= ';';

  }

  // Insert the data.
  if (empty($mQuery)) {
    if ($debug_mode) echo "No channel data provided.\n";
  }
  elseif(!mysqli_multi_query($mysqli, $mQuery)) {
    if ($debug_mode) echo "multiquery: " . $mQuery . " failed.";
  }

  //Flush the results.
  while (mysqli_next_result($mysqli)) {;};

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

  //Add our channel
  $Qstr = "INSERT INTO channel (name, type, variable, active, input, hostChNum, max, min, color, units,
    lastPing, host) VALUES('" . mysqli_real_escape_string($mysqli, $data_in[5]) . "', '"
    . mysqli_real_escape_string($mysqli, $data_in[6]) . "', " 
    . (int)$data_in[7] . ", " . (int)$data_in[8] . ", " . (int)$data_in[13] . ", " . (int)$data_in[4] . ", "
    . (float)$data_in[9] . ", " . (float)$data_in[10] . ", '"
    . mysqli_real_escape_string($mysqli, $data_in[11]) . "', '" 
    . mysqli_real_escape_string($mysqli, $data_in[12]) . "', FROM_UNIXTIME("
    . (int)$data_in[2] . "), " . $hostId . "); ";
    
  // Do the query
 if (!mysqli_query($mysqli, $Qstr)) {
    if ($debug_mode) echo "query: " . $Qstr . " failed. " . mysqli_error($mysqli);
  } else {
    if ($debug_mode) echo "Successfully added channel. ";
  }

  // Return
  return [
    "id" => $chanId,
    "name" => mysqli_real_escape_string($mysqli, $data_in[5]),
    "variable" => (int)$data_in[7],
    "active" => (int)$data_in[8],
    "max" => (float)$data_in[9],
    "min" => (float)$data_in[10],
    "UNIX_TIMESTAMP(lastPing)" => time()-1];
}


function chan_vals($chanInfo, $chanValLim)
{
  //Function to create the return for a host
  global $debug_mode;

  $retInfo = "";

  $now = time();

  for($i=0;$i<$chanValLim;$i++) {
    $t = $now + $i;
    $retInfo .= "$t,";
  }

  $retInfo .= ';';

  for($i=0;$i<$chanValLim;$i++) {
    $v = mt_rand() / mt_getrandmax();
    $retInfo .= "$v,";
  }

  return $retInfo;
}


