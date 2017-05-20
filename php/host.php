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

// Connect to the dB
$mysqli = \aqctrl\db_connect();

if(!$mysqli) {
    //Complain about not being able to connect and give up.
    echo "<p>Could not connect to dB, check /etc/aqctrl.ini</p>";
    echo "<p>Or, try going to the <a href=quickstart.php>quickstart page.</a></p>";
    return;
}

// Decode the JSON data from the host.
$JSON_in = json_decode($_POST['host'], true);

//Test for decode error, if we have an error, print and error and return
$JSON_err = json_last_error_msg();

if($JSON_err != "No error") {
  //Print the error
  echo "JSON decode error: ";
  echo $JSON_err;
  return;
}


// Decide whether we know about this host yet.
$knownHosts = mysqli_query($mysqli, "SELECT * FROM host");

$numHosts = mysqli_num_rows($knownHosts);

for($i=1; $i <= $numHosts; $i++) {
    $row = mysqli_fetch_array($knownHosts);
    if($row['ident'] == $JSON_in['id']) {
        // We've seen this host before.

        // Update lastping.
        if(!mysqli_query($mysqli, "UPDATE host SET lastPing = FROM_UNIXTIME(
            " . time() . ") WHERE ident = '" . $JSON_in['id'] . "'")) {
            echo "Error updating ping date: " . mysqli_error($mysqli);
            mysqli_close($mysqli);
            return;
        }

        if(!$row['auth']) {
            mysqli_close($mysqli);
            echo "Known, non-validated host";
            return;
            
        } else {
            // We've also validated this host.
            // Check the hash.
            $hash_expected = hash('sha1', $_POST['host'] . $row['auth']);

            if(md5($_POST['HMAC']) === md5($hash_expected)) {
                // We have a valid message.

                \aqctrl\host_process($JSON_in, $mysqli);

                // Disconnect and return
                mysqli_close($mysqli);
                return;
            } else {
                // Hash failed
                echo "HMAC failed";

                mysqli_close($mysqli);
                return;
            }
        }

    } 
}

// We made it through the loop without finding the host, this must be a new host.

\aqctrl\host_add($JSON_in, $mysqli);

// Disconnect and return
mysqli_close($mysqli);
return;



function host_process($JSON_in, $mysqli)
{
    //Function to process a validated host with a validated message.

    // Get the channel dB objects that match to our host.
    $res = mysqli_query($mysqli, "SELECT id FROM host WHERE ident = '" . $JSON_in['id'] . "'");

    if(!$res) {
        die("Could not find host in host_process");
    }

    $hostId = mysqli_fetch_row($res)[0];

    $chanRes = mysqli_query($mysqli, "SELECT * FROM channel WHERE host = " . $hostId);

    if(!$chanRes) {
        die("Could not get channel list for host in host_process");
    }

    // Set up a multiQuery string
    $mQuery = "";

    // Set up the response array
    $JSON_data = [];

    // Drop the first two elements of the $JSON_in array
    $JSON_in = array_slice($JSON_in, 2);

    // Loop through the channels in our message
    foreach($JSON_in as $channel) {
        $chanInfo = mysqli_fetch_assoc($chanRes);

        $chanId = $chanInfo['id'];
        // Check if this channel is active - if not parse response but not data.
        if($channel['active']) {
            // Drop the first 7 elements of the array.
            $chanData = array_slice($channel, 7, NULL, true);

            foreach($chanData as $dataDate => $data) {
                // Add data for this channel.
                $mQuery .= "INSERT INTO data (date, value, channel)
                    VALUES (FROM_UNIXTIME(" . $dataDate . "), " . $data . ", " . $chanId . ");";
            }
        }

        // Construct the return.
        $JSON_data[$chanInfo['name']] = \aqctrl\chan_vals($chanId);
    }

    // Insert the data.
    if (empty($mQuery)) {
        echo "No channel data provided.\n";
    }
    elseif(!mysqli_multi_query($mysqli, $mQuery)) {
        echo "multiquery: " . $mQuery . " failed.";
        return;
    }

    // Now, encode the response.
    echo json_encode($JSON_data);
}


function host_add($JSON_in, $mysqli)
{
    //Function to add a newly discovered host to the dB.
    //Do this via a multiQuery too, since we'll be adding multiple entries.
    $mQuery = "";

    //Parse through each line of JSON_in
    $sqlQuery = "INSERT INTO host (ident, name, auth, lastPing)
      VALUES ('" . $JSON_in["id"] . "', '" . $JSON_in["name"] . "',
      0, FROM_UNIXTIME(" . time() . "))";

    if(!mysqli_query($mysqli, $sqlQuery)) {
        echo "Query: " . $sqlQuery;
        echo "Error inserting host: " . mysqli_error($mysqli);
        return;
    }

    $mQuery = "";

    $JSON_in = array_slice($JSON_in, 2, NULL, true);

    $hostId = mysqli_insert_id($mysqli);

    // Loop through the channels in our message
    foreach($JSON_in as $chanName => $channel) {
        $mQuery .= "INSERT INTO channel (name, type, variable, active, max, min, color, units, host)
            VALUES('" . $chanName . "', '" . $channel["type"] . "', " . $channel["variable"] . "
            , " . $channel["active"] . ", " . $channel["max"] . ", " . $channel["min"] . "
            , '" . $channel["color"] . "', '" . $channel["units"] . "', " . $hostId . "); ";
    }
    
    // Do the query
    if (empty($mQuery)) {
        echo "No channels provided.";
    }
    elseif (!mysqli_multi_query($mysqli, $mQuery)) {
        echo "multiquery: " . $mQuery . " failed.";
        return;
    }

    // Return
    echo "Successfully added host";

}


function chan_vals($chanId)
{
    //Function to create the return for a host
    return $chanId;
}
