<?php

/* setupFn.php

Functions to support the setup.php page.

setupForm - function to generate the form to fill out

setupRtn - function to handle the return a POST of SetupForm

*/

namespace aqctrl;

// Db functions

function setupForm($mysqli, $nextUrl)
{

    // Show the form
    echo "<h2>Hardware Hosts</h2>";

    // Iterate through each host found
    $knownHosts = mysqli_query($mysqli, "SELECT * FROM host");

    $numHosts = mysqli_num_rows($knownHosts);

    if(!$numHosts) {
        echo "<p>No Hosts Configured.</p>";
        $host= gethostname();
        $ip = gethostbyname($host);
        echo "<p>Configure your hosts to point to ";
        echo $ip;
        echo " and turn them on now then ";

        echo "<a href=".">refresh</a> the page.</p>";
        
    } else {
        ?>
        <table>
          <tr>
            <th>Name</th>
            <th>ID</th>
            <th>Key</th>
            <th>Last Ping</th>
          <tr>
        <?php

        for($i=1; $i <= $numHosts; $i++) {
            $row = mysqli_fetch_array($knownHosts);
        
            echo "<tr>";
            echo "<td>" . $row["name"] . "</td>";
            echo "<td>" . $row["ident"] . "</td>";
            echo "<td><input type='text' name='auth" . $i . "' value='" . $row["auth"] . "'></td>";
            echo "<td>" . time_elapsed_string(strtotime($row["lastPing"])) . "</td>";
            echo "</tr>";

        }

    echo "</table>";
    }
}


function setupRtn($mysqli, $postRet)
{
    // Handle our form

    $i = 1;
    $mQuery = "";

    while (true) {
        // Iterate through all of the auth fields given.
        $keyName = "auth" . $i;

        if(array_key_exists($keyName, $_POST)) {
            $mQuery .= "UPDATE host SET auth = '" . $_POST["$keyName"] .  "' WHERE id = $i";
        } else {
            break;
        }
        $i += 1;
    }
    
    // Do the updates
    if (!mysqli_multi_query($mysqli, $mQuery)) {
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
}



function time_elapsed_string($ago, $full = false) {
    $now = time();
    $diff = $now - $ago;

    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = array(
        'y' => 'year',
        'm' => 'month',
        'w' => 'week',
        'd' => 'day',
        'h' => 'hour',
        'i' => 'minute',
        's' => 'second',
    );
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' ago' : 'just now';
}


