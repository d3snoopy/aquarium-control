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
$JSON_in = json_decode($_POST['host']);

// Decide whether we know about this host yet.
$knownHosts = mysqli_query($mysqli, "SELECT * FROM host");

$numHosts = mysqli_num_rows($knownHosts);

for($i=1; $i <= $numHosts; $i++) {
    $row = mysqli_fetch_array($knownHosts);
    if($row['ident'] == $JSON_in['id']) {
        // We've seen this host before.

        // Update lastping.
        if(!mysqli_query($mysqli, "UPDATE host SET lastPing = FROM_UNIXTIME(
            " . time() . ") WHERE ident = " $JSON_in['id'])) {
            mysqli_close($mysqli);
            echo "Error updating ping date: " . mysqli_error($mysqli);
            return;
        }

        if(!$row['auth']) {
            mysqli_close($mysqli);
            die("<p>Known, non-validated host</p>");
            
        } else {
            // We've also validated this host.
            // Check the hash.
            $hash_expected = hash_hmac('sha256', $_POST['host'], $row['auth']);

            if(md5($_POST['HMAC']) === md5($hash_expected)) {
                // We have a valid message.

                \aqctrl\host_process($JSON_in);

                // Disconnect and return
                mysqli_close($mysqli);
                return();
            }
        }

    }
}

// We made it through the loop without finding the host, this must be a new host.

\aqctrl\host_add($JSON_in);

// Disconnect and return
mysqli_close($mysqli);
return();



function host_process($JSON_in)
{
    //Function to process a validated host with a validated message.

    // Drop the first two elements of the $JSON_in array
    $JSON_in = array_slice($JSON_in, 2);

    // Get the channel dB objects that match to our host.
    $res = mysqli_query($mysqli, "SELECT id FROM host WHERE ident = " . $JSON_in['id']);

    if(!$res) {
        die("Could not find host in host_process");
    }

    $hostId = mysqli_fetch_row($res)[0];

    $chanRes = mysqli_query($mysqli, "SELECT * FROM channel WHERE host = " . $hostId;

    if(!$chanRes) {
        die("Could not get channel list for host in host_process");
    }

    // Set up a multiQuery string
    $mQuery = "";

    // Set up the response array
    $JSON_data = [];

    // Loop through the channels in our message
    foreach($JSON_in as $channel) {
        $chanInfo = mysqli_fetch_row($chanRes);
        $chanId = $chanInfo['id'];
        // Check if this channel is active - if not parse response but not data.
        if($channel['active']) {
            // Drop the first 7 elements of the array.
            $chanData = array_slice($channel, 7);

            foreach($chanData as $dataDate => $data) {
                // Add data for this channel.
                $mQuery .= "INSERT INTO data (date, value, channel)
                    VALUES (FROM_UNIXTIME(" . $dataDate . "), " . $data . ", " . $chanId ");";
            }
        }

        // Construct the return.
        $JSON_data[$chanInfo['name']] = \aqctrl\chan_vals($chanInfo);
    }

    // Insert the data.
    if (mysqli_multi_query($mysqli, $mQuery)) {
        do {
            /* store first result set */
            if ($result = mysqli_store_result($link)) {
                while ($row = mysqli_fetch_row($result)) {
                    printf("%s\n", $row[0]);
                }
                mysqli_free_result($result);
            }
            /* print divider */
            if (mysqli_more_results($link)) {
                printf("-----------------\n");
            }
        } while (mysqli_next_result($link));

    return;
    }    


    // Now, encode the response.
    echo json_encode($JSON_data);
}


function host_add($JSON_in)
{
    //Function to add a newly discovered host to the dB.
    //Do this via a multiQuery too, since we'll be adding multiple entries.
    $mQuery = "";

    //Parse through each line of JSON_in
    if(!mysqli_query($mysqli, "INSERT INTO host (ident, name, auth, lastPing)
      VALUES(" . $JSON_in["id"] . ", " . $JSON_in["name"] . ",
      0, FROM_UNIXTIME(" . time() . ")")) {
        mysqli_close($mysqli);
        echo "Error inserting host: " . mysqli_error($mysqli);
        return;
    }

    $mQuery = "";

    $JSON_in = array_slice($JSON_in, 2);

    $hostId = mysqli_insert_id($mysqli);

    // Loop through the channels in our message
    foreach($JSON_in as $chanName => $channel) {
        $mQuery .= "INSERT INTO channel (name, type, variable, active, max, min, color, units, host)
            VALUES(" . $chanName . ", " . $channel["type"] . ", " . $channel["variable"] . "
            , " . $channel["active"] . ", " . $channel["max"] . ", " . $channel["min"] . "
            , " . $channel["color"] . ", " . $channel["units"] "; "
    }
    





$time1 = time();
usleep (1000000);
$time2 = time();
usleep (1000000);
$time3 = time();

$JSON_data = [
  "id" => uniqid(),
  "name" => "test_host",
  "channel1" => [
    "type" => "testa",
    "variable" => "true",
    "active" => "true",
    "max" => 100,
    "min" => 0,
    "color" => "000000",
    "units" => "testunits",
  ],
  "channel2" => [
    "type" => "testa",
    "variable" => "true",
    "active" => "true",
    "max" => 50,
    "min" => 10,
    "color" => "FFFFFF",
    "units" => "test2",
  ],
  "channel3" => [
    "type" => "testb",
    "variable" => "false",
    "active" => "true",
    "max" => 100,
    "min" => 0,
    "color" => "999999",
    "units" => "bla",
    $time1 => 48.456,
    $time2 => 5.4325,
    $time3 => 345.24,
  ],
];



$JSON_string = json_encode($JSON_data);

echo $JSON_string;


