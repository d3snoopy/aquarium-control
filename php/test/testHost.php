<?php
header('charset=utf-8');
// Test code to generate a fake host, designed to test out the host interaction code

$time1 = time();
usleep (1000000);
$time2 = time();
usleep (1000000);
$time3 = time();
$num1 = rand(0,100000)/1000;
$num2 = rand(0,100000)/1000;
$num3 = rand(0,100000)/1000;
$num4 = rand(0,100000)/1000;
$num5 = rand(0,100000)/1000;
$num6 = rand(0,100000)/1000;

$JSON_data = [
  "id" => "TEST25987y56",
  "name" => "test_host",
  "date" => time(),
  "maxVals" => 1000,
  "channels" => [
    [
      "name" => "channel1",
      "type" => "testa",
      "variable" => 1,
      "active" => 1,
      "max" => 100,
      "min" => 0,
      "color" => "000000",
      "units" => "testunits",
      "times" => array(),
      "values" => array()],
    [
      "name" => "channel2",
      "type" => "testa",
      "variable" => 1,
      "active" => 1,
      "max" => 50,
      "min" => 10,
      "color" => "FFFFFF",
      "units" => "test2",
      "times" => array(),
      "values" => array()],
    [
      "name" => "channel3",
      "type" => "testb",
      "variable" => 0,
      "active" => 1,
      "max" => 100,
      "min" => 0,
      "color" => "999999",
      "units" => "bla",
      "times" => array($time1, $time2, $time3),
      "values" => array($num1, $num2, $num3)],
    [
      "name" => "channel4",
      "type" => "inactive",
      "variable" => 1,
      "active" => 0,
      "max" => 100,
      "min" => 0,
      "color" => "999999",
      "units" => "bla",
      "times" => array(),
      "values" => array()],
    [
      "name" => "channel5",
      "type" => "testa",
      "variable" => 1,
      "active" => 1,
      "max" => 90,
      "min" => 0,
      "color" => "FFFFFF",
      "units" => "testunits",
      "times" => array($time1, $time2, $time3),
      "values" => array($num4, $num5, $num6)]
  ]
];

$JSON_string = json_encode($JSON_data);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Aquarium Controller: test host</title>
</head>

<body>
    <div>
        <form action="../host.php" method="post">
            <textarea style="display:none" name="host"><?php echo $JSON_string; ?></textarea>
        
            <input type="hidden" name="HMAC" value="<?php
                $auth = "abcdefgh12345678";
                echo hash_hmac('sha256', $JSON_string, $auth);
                ?>">
            <input type="submit" value="Submit">
        </form>

    <?php
    echo $JSON_string;
    ?>
    </div>
</body>
</html>
