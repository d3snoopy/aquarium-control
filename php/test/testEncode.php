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
  "id" => "ABCDEFG",
  "name" => "arduino_host",
  "chanA" => [
    "type" => "testa",
    "variable" => 1,
    "active" => 1,
    "max" => 100,
    "min" => 0,
    "color" => "000000",
    "units" => "blunits",
  ]
];

echo(json_encode($JSON_data));


