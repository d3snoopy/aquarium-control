<?php

/* setupFn.php

Functions to support the setup.php page.

setupForm - function to generate the form to fill out

setupRtn - function to handle the return a POST of SetupForm

*/

namespace aqctrl;

// Db functions

function setupForm($mysqli, $debug_mode)
{

    // Show the form
    echo "<h2>Hardware Hosts</h2>";

    // Iterate through each host found
    $knownHosts = mysqli_query($mysqli, "SELECT id,ident,name,auth,UNIX_TIMESTAMP(lastPing),inInterval,outInterval,pingInterval FROM host");

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
            <th>Read Interval (sec)</th>
            <th>Write Interval (sec)</th>
            <th>Ping Interval (sec)</th>
          <tr>
        <?php

        for($i=1; $i <= $numHosts; $i++) {
            $row = mysqli_fetch_array($knownHosts);
        
            echo "<tr>";
            echo "<td>" . $row["name"] . "</td>";
            echo "<td>" . $row["ident"] . "</td>";
            echo "<td><input type='text' name='auth" . $i . "' value='" . $row["auth"] . "'></td>";
            echo "<td>" . time_elapsed_string($row["UNIX_TIMESTAMP(lastPing)"]) . " ago</td>";
            echo "<td><input type='text' name='inInt" . $i . "' value='" . $row["inInterval"] . "'></td>";
            echo "<td><input type='text' name='outInt" . $i . "' value='" . $row["outInterval"] . "'></td>";
            echo "<td><input type='text' name='pingInt" . $i . "' value='" . $row["pingInterval"] . "'></td>";
            echo "</tr>";

        }

    echo "</table>";
    }
}


function setupRtn($mysqli, $postRet, $debug_mode)
{
    // Handle our form

    $i = 1;
    $mQuery = "";

    while (true) {
        // Iterate through all of the auth fields given.
        $keyName = "auth" . $i;

        if(array_key_exists($keyName, $_POST)) {
            $mQuery .= "UPDATE host SET auth = '" . mysqli_real_escape_string($mysqli, $_POST["$keyName"])
              .  "' WHERE id = $i;";

            $keyName = "inInt" . $i;
            $mQuery .= "UPDATE host SET inInterval = '" . mysqli_real_escape_string($mysqli, $_POST["$keyName"])
              .  "' WHERE id = $i;";

            $keyName = "outInt" . $i;
            $mQuery .= "UPDATE host SET outInterval = '" . mysqli_real_escape_string($mysqli, $_POST["$keyName"])
              .  "' WHERE id = $i;";

            $keyName = "pingInt" . $i;
            $mQuery .= "UPDATE host SET pingInterval = '" . mysqli_real_escape_string($mysqli, $_POST["$keyName"])
              .  "' WHERE id = $i;";

        } else {
            break;
        }
        $i += 1;
    }
    
    // Do the updates
    if (!mysqli_multi_query($mysqli, $mQuery)) {
        if ($debug_mode) echo "multiquery: " . $mQuery . " failed.";
        //Flush the results.
        while (mysqli_next_result($mysqli)) {;};
        return;
    }

    while (mysqli_next_result($mysqli)) {;};
}



function time_elapsed_string($tm, $rcs = 0) {
    //TODO: Color code our return, too.
    $cur_tm = time();
    $dif = $cur_tm - $tm;

    $pds = array('second','minute','hour','day','week','month','year','decade');
    $lngh = array(1,60,3600,86400,604800,2630880,31570560,315705600);

    for($v = sizeof($lngh)-1; ($v >= 0)&&(($no = $dif/$lngh[$v])<=1); $v--); if($v < 0) $v = 0; $_tm = $cur_tm-($dif%$lngh[$v]);
    $no = floor($no);
    if($no <> 1)
        $pds[$v] .='s';
    $x = sprintf("%d %s ",$no,$pds[$v]);
    if(($rcs == 1)&&($v >= 1)&&(($cur_tm-$_tm) > 0))
        $x .= time_ago($_tm);
    return $x;
}



