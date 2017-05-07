<?php

// Test code to generate a fake host, designed to test out the host interaction code

$time1 = time();
usleep (1000000);
$time2 = time();
usleep (1000000);
$time3 = time();

$JSON_data = [
  "id" => "TEST25987y56",
  "name" => "test_host",
  "channel1" => [
    "type" => "testa",
    "variable" => 1,
    "active" => 1,
    "max" => 100,
    "min" => 0,
    "color" => "000000",
    "units" => "testunits",
  ],
  "channel2" => [
    "type" => "testa",
    "variable" => 1,
    "active" => 1,
    "max" => 50,
    "min" => 10,
    "color" => "FFFFFF",
    "units" => "test2",
  ],
  "channel3" => [
    "type" => "testb",
    "variable" => 0,
    "active" => 1,
    "max" => 100,
    "min" => 0,
    "color" => "999999",
    "units" => "bla",
    $time1 => 48.456,
    $time2 => 5.4325,
    $time3 => 345.24,
  ],
  "channel4" => [
    "type" => "inactive",
    "variable" => 1,
    "active" => 0,
    "max" => 100,
    "min" => 0,
    "color" => "999999",
    "units" => "bla"
  ]
];

$JSON_string = json_encode($JSON_data);



echo $JSON_string;


