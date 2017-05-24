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

// Decode the JSON data from the host.
$JSON_in = json_decode($_POST['host'], true);

//Test for decode error, if we have an error, print and error and return
$JSON_err = json_last_error_msg();

if($JSON_err != "No error") {
  //Print the error
  if ($debug_mode) echo "JSON decode error: " . $JSON_err;
  mysqli_close($mysqli);
  return;
}


// Decide whether we know about this host yet.
$knownHosts = mysqli_query($mysqli, "SELECT * FROM host");

$numHosts = mysqli_num_rows($knownHosts);

$hostFound = false;

// Start our JSON response
$JSON_data = [];
$JSON_data["date"] = time();

for($i=1; $i <= $numHosts; $i++) {
  $row = mysqli_fetch_array($knownHosts);
  if($row['ident'] == $JSON_in['id']) {
    // We've seen this host before.
    $hostFound = true;

    // Check date against lastping, make sure we're not seeing a replay.
    $res = mysqli_query($mysqli, "SELECT UNIX_TIMESTAMP(lastping) FROM host WHERE ident = '"
       . mysqli_real_escape_string($mysqli, $JSON_in['id']) . "'");

    $lastPing = mysqli_fetch_row($res)[0];
    // Update lastping if this is really a new ping.

    if((int)$JSON_in['date'] >= (int)$lastPing) {
      if(!mysqli_query($mysqli, "UPDATE host SET lastPing = FROM_UNIXTIME(
          " . (int)$JSON_in['date'] . ") WHERE ident = '"
          . mysqli_real_escape_string($mysqli, $JSON_in['id']) . "'")) {
            if ($debug_mode) echo "Error updating ping date: " . mysqli_error($mysqli);
      }

      if(!$row['auth']) {
         mysqli_close($mysqli);
         if ($debug_mode) echo "Known, non-validated host";

      } else {
        // We've also validated this host.
        // Check the hash.
        $hash_expected = hash_HMAC('sha256', $_POST['host'], $row['auth']);

        if(md5($_POST['HMAC']) === md5($hash_expected)) {
          // We have a valid message.

          $JSON_data = \aqctrl\host_process($JSON_in, $JSON_data, $mysqli);

        } else {
          // Hash failed
          if ($debug_mode) echo "HMAC failed";
                
        }
      }

    } else {
      mysqli_close($mysqli);
      if ($debug_mode) echo "Check your date";
    }
  }
}

// Test to see if we found this host.
if(!$hostFound) \aqctrl\host_add($JSON_in, $mysqli);

// Encode our JSON, make HMAC, echo our response.
$respString = json_encode($JSON_data);

echo "\r\n\r\n";
if(isset($row)) {
  echo hash_HMAC('sha256', $respString, $row['auth']);
  echo "\r\n\r\n";
  echo $respString;
}

// Disconnect and return
mysqli_close($mysqli);
return;


function host_process($JSON_in, $JSON_data, $mysqli)
{
    //Function to process a validated host with a validated message.

    // Get the channel dB objects that match to our host.
    global $JSONServerSkip, $debug_mode;

    $res = mysqli_query($mysqli, "SELECT id, auth, inInterval, outInterval, pingInterval FROM host WHERE ident = '"
    . mysqli_real_escape_string($mysqli, $JSON_in['id']) . "'");

    if(!$res) {
        if ($debug_mode) echo("Could not find host in host_process");
        return;
    }

    $hostInfo = mysqli_fetch_row($res);

    $chanRes = mysqli_query($mysqli, "SELECT * FROM channel WHERE host = " . $hostInfo[0]);

    if(!$chanRes) {
        if ($debug_mode) echo("Could not get channel list for host in host_process");
        return;
    }

    // Test for an empty set: if so, call channels_add and re-query.
    if(!mysqli_num_rows($chanRes)) {
        \aqctrl\channels_add($JSON_in, $mysqli, $hostInfo[0]);
        
        // Not sure why - a select right behind an insert/update isn't working.

        $chanRes = mysqli_query($mysqli, "SELECT * FROM channel WHERE host = " . $hostInfo[0]);

        if(!$chanRes) {
            if ($debug_mode) echo("Could not get channel list for host in host_process");
            return;
        }
    }

    // Set up a multiQuery string
    $mQuery = "";

    // Get how many values to limit to
    $chanValLim = (int)$JSON_in['maxVals'];

    // Set up the response array
    $JSON_data["nextPing"] = time() + min($hostInfo[4], $chanValLim*$hostInfo[2]);
    $JSON_data["inInterval"] = $hostInfo[2];
    $JSON_data["outInterval"] = $hostInfo[3];

    // Loop through the channels in our message
    foreach($JSON_in["channels"] as $chNum => $channel) {
        $chanInfo = mysqli_fetch_assoc($chanRes);

        // Check if this channel is active - if not parse response but not data.
        if($channel['active']) {
            #TODO: check to make sure we have a new date.
            // Get the timestamps and data
            $times = $channel['times'];
            $values = $channel['values'];

            foreach($times as $i => $timeStamp) {
                // Add data for this channel.
                if (is_numeric($timeStamp) && is_numeric($values[$i]))
                    $mQuery .= "INSERT INTO data (date, value, channel)
                        VALUES (FROM_UNIXTIME(" . $timeStamp . "), " . $values[$i] . ", "
                        . $chanInfo['id'] . ");";
            }
        }

        // Construct the return.
        $JSON_data['channels'][$chNum] = \aqctrl\chan_vals($chanInfo['id'], $chanValLim);
    }

    // Insert the data.
    if (empty($mQuery)) {
        if ($debug_mode) echo "No channel data provided.\n";
    }
    elseif(!mysqli_multi_query($mysqli, $mQuery)) {
        if ($debug_mode) echo "multiquery: " . $mQuery . " failed.";
        //Flush the results.
        while (mysqli_next_result($mysqli)) {;};
        return;
    }

    while (mysqli_next_result($mysqli)) {;};

    return $JSON_data;
}


function host_add($JSON_in, $mysqli)
{
    //Function to add a newly discovered host to the dB.
    //Do this via a multiQuery too, since we'll be adding multiple entries.
    global $JSONServerSkip, $debug_mode;
    $mQuery = "";

    //Parse through each line of JSON_in
    $sqlQuery = "INSERT INTO host (ident, name, auth, lastPing, inInterval, outInterval, pingInterval)
      VALUES ('"
      . mysqli_real_escape_string($mysqli, $JSON_in["id"]) . "', '"
      . mysqli_real_escape_string($mysqli, $JSON_in["name"]) . "',
      0, FROM_UNIXTIME(" . time() . "), 60, 60, 600)";

    if(!mysqli_query($mysqli, $sqlQuery)) {
        if ($debug_mode) echo "Error inserting host: " . mysqli_error($mysqli);
        return;
    }

    if ($debug_mode) echo "Successfully added host";
}


function channels_add($JSON_in, $mysqli, $hostId)
{
    //Function to add channels to our host, called the first time the host pings
    global $debug_mode;

    $mQuery = "";

    // Loop through the channels in our message
    foreach($JSON_in["channels"] as $channel) {
        $mQuery .= "INSERT INTO channel (name, type, variable, active, max, min, color, units, host)
            VALUES('" . mysqli_real_escape_string($mysqli, $channel["name"]) . "', '"
            . mysqli_real_escape_string($mysqli, $channel["type"]) . "', " 
            . (int)$channel["variable"] . ", " . (int)$channel["active"] . ", " 
            . (float)$channel["max"] . ", " . (float)$channel["min"] . "
            , '" . mysqli_real_escape_string($mysqli, $channel["color"]) . "', '" 
            . mysqli_real_escape_string($mysqli, $channel["units"]) . "', " . $hostId . "); ";
    }
    
    // Do the query
    if (empty($mQuery)) {
        if ($debug_mode) echo "No channels provided.";
    }
    elseif (!mysqli_multi_query($mysqli, $mQuery)) {
        if ($debug_mode) echo "multiquery: " . $mQuery . " failed.";
        // Flush the results
        while (mysqli_next_result($mysqli)) {;};
        return;
    }

    while (mysqli_next_result($mysqli)) {;};

    // Return
    if ($debug_mode) echo "Successfully added channels. ";

}


function chan_vals($chanId, $chanValLim)
{
    //Function to create the return for a host
    global $debug_mode;

    $retInfo['times'] = array(1, 2, 3); #timestamps for the data
    $retInfo['values'] = array(3.4, 4.5, 5.6); #values for the data

    return $retInfo;
}


