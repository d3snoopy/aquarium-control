<?php

// host.php

// Module to handle interactions with the hosts

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
include 'hostFn.php';

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
$knownHosts = mysqli_query($mysqli, "SELECT id,ident,auth,UNIX_TIMESTAMP(lastPing),inInterval,outInterval,pingInterval FROM host");

$numHosts = mysqli_num_rows($knownHosts);

$hostFound = false;

// Start our response data
$data_out = sprintf("%010d", time()) ;
$data_out .= ",";

for($i=1; $i <= $numHosts; $i++) {
  $row = mysqli_fetch_array($knownHosts);
  if($row['ident'] == $data_in[0]) {
    // We've seen this host before.
    $hostFound = true;

    // Check date against lastping, make sure we're not seeing a replay.
    $lastPing = $row["UNIX_TIMESTAMP(lastPing)"];
    // Update lastping if this is really a new ping.

    if((int)$data_in[2] >= (int)$lastPing) {
      if(!mysqli_query($mysqli, "UPDATE host SET lastPing = FROM_UNIXTIME(
          " . (int)$data_in[2] . ") WHERE id = '"
          . $row['id'] . "'")) {
            if ($debug_mode) echo "Error updating ping date: " . mysqli_error($mysqli);
            $data_out .= time() + 600;
            $data_out .= ',60,60,;';
      }

      if(!$row['auth']) {
         mysqli_close($mysqli);
         if ($debug_mode) echo "Known, non-validated host";
         $data_out .= time() + 600;
         $data_out .= ',60,60,;';
      } else {
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
        }
      }

    } else {
      if ($debug_mode) echo "Check your date";
      $data_out .= time() + 600;
      $data_out .= ',60,60,;';
    }
  }
}

// Test to see if we found this host.
if(!$hostFound && array_key_exists('host', $_POST)) {
  \aqctrl\host_add($data_in, $mysqli);
  $row['auth'] = 0;
  $data_out .= time() + 600;
  $data_out .= ',60,60,;';
}

$data_out .= ';';

// Encode our JSON, make HMAC, echo our response.
$HMAC = hash_HMAC('sha256', $data_out, $row['auth']);

$data_out .= $HMAC;

echo "\r\n\r\n";
echo $data_out;

// Disconnect and return
mysqli_close($mysqli);
return;



function host_process($data_in, $data_out, $mysqli, $row)
{
  //Function to process a validated host with a validated message.

  // Get the channel dB objects that match to our host.
  global $debug_mode;

  // TODO: Update what we get based on our calculation needs
  $chanRes = mysqli_query($mysqli, "SELECT id,variable,active,max,min,UNIX_TIMESTAMP(lastPing) FROM channel
    WHERE host = " . $row['id']);

  if(!$chanRes) {
    if ($debug_mode) echo("Could not get channel list for host in host_process");
    return;
  }

  // Test for how many channels we have, if we don't have enough call channels_add.
  if(mysqli_num_rows($chanRes) <= (int)$data_in[5]){
    $chanInfo = \aqctrl\channel_add($data_in, $mysqli, $row['id'], mysqli_num_rows($chanRes));
  } else {
    mysqli_data_seek($chanRes, $data_in[5]);
    $chanInfo = mysqli_fetch_assoc($mysqli);

    // Test for NULLs in out channel info, if so we added this channel before without details about it.
    if(is_null($chanInfo['name']))
      $chanInfo = \aqctrl\channel_add($data_in, $mysqli, $row['id'], mysqli_num_rows($chanRes));
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
  $sqlQuery = "INSERT INTO host (ident, name, auth, lastPing, inInterval, outInterval, pingInterval)
    VALUES ('"
    . mysqli_real_escape_string($mysqli, $data_in[0]) . "', '"
    . mysqli_real_escape_string($mysqli, $data_in[1]) . "',
    0, FROM_UNIXTIME(" . time() . "), 60, 60, 600)";

  if(!mysqli_query($mysqli, $sqlQuery)) {
    if ($debug_mode) echo "Error inserting host: " . mysqli_error($mysqli);
  } else {
    if ($debug_mode) echo "Successfully added host";
  }
}



function channel_add($data_in, $mysqli, $hostId, $numRows)
{
  //Function to add channels to our host, called the first time the host pings
  global $debug_mode;

  $mQuery = "";

  //We need to keep the channels for this host in order, so if this channel skips numbers, create intermediate ones.
  if($numRows < $data_in[4]) {
    for ($i=$numRows; $i<$data_in[4]; $i++) {
      //Make a channel placeholder.
      $mQuery .= "INSERT INTO channel (lastPing, host)
        VALUES(FROM_LINUXTIME(0),$hostID); ";
    }
  }

  //Add our channel
  $mQuery .= "INSERT INTO channel (name, type, variable, active, max, min, color, units, lastPing, host)
    VALUES('" . mysqli_real_escape_string($mysqli, $data_in[5]) . "', '"
    . mysqli_real_escape_string($mysqli, $data_in[6]) . "', " 
    . (int)$data_in[7] . ", " . (int)$data_in[8] . ", " 
    . (float)$data_in[9] . ", " . (float)$data_in[10] . ", '"
    . mysqli_real_escape_string($mysqli, $data_in[11]) . "', '" 
    . mysqli_real_escape_string($mysqli, $data_in[12]) . "', FROM_LINUXTIME("
    . (int)$data_in[2] . "), " . $hostId . "); ";
    
  // Do the query
  if (empty($mQuery)) {
    if ($debug_mode) echo "Chan mquery empty.";
  } elseif (!mysqli_multi_query($mysqli, $mQuery)) {
    if ($debug_mode) echo "multiquery: " . $mQuery . " failed.";
  } else {
    if ($debug_mode) echo "Successfully added channel. ";
  }

  //Flush the results
  do {
    $chanId = mysqli_insert_id($mysqli);
    mysqli_next_result($mysqli);
  } while (mysqli_more_results($mysqli));

  // Return
  return [
    "id" => $chanId,
    "variable" => (int)$data_in[7],
    "active" => (int)$data_in[8],
    "max" => (float)$data_in[9],
    "min" => (float)$data_in[10]];
}


function chan_vals($chanId, $chanValLim)
{
  //Function to create the return for a host
  global $debug_mode;

  $retInfo = "";

  $now = time();

  for($i=0;$i<$chanValLim;$i++) {
    $t = $now + $i;
    $retInfo .= "$t,";
  }

  $retInfo = ';';

  for($i=0;$i<$chanValLim;$i++) {
    $v = mt_rand() / mt_getrandmax();
    $retInfo .= "$v,";
  }

  return $retInfo;
}


