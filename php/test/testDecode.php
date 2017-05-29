<?php
header('charset=utf-8');
// Test code to generate a fake host, designed to test out the host interaction code

$JSON_string = '{"id":"TEST25987y56",
  "name":"test_host",
  "date":1234567890,
  "maxVals":1000,
  "channels":[
    {
      "name":"channel1",
      "type":"testa",
      "variable":1,
      "active":1,
      "max":100,
      "min":0,
      "color":"000000",
      "units":"testunits",
      "times": [],
      "values": []},
    {
      "name":"channel2",
      "type":"testa",
      "variable":1,
      "active":1,
      "max":50,
      "min":10,
      "color":"FFFFFF",
      "units":"test2",
      "times": [],
      "values": []},
    {
      "name":"channel3",
      "type":"testb",
      "variable":0,
      "active":1,
      "max":100,
      "min":0,
      "color":"999999",
      "units":"bla",
      "times": [
        1495385884,
        1495385885,
        1495385886],
      "values": [
        63.596,
        45.875,
        3.23]}
    ]
  }';

var_dump(json_decode($JSON_string));


