//#include <PipedStream.h>
#include <ESP8266WiFi.h>
#include <sha256.h>
#include <ArduinoJson.h>
#include <Wire.h>
#include "Adafruit_TSL2591.h"



// Info about connecting: our wireless access point, server hostname
const char ssid[]     = "wireless1";
const char password[] = "f10b5403c12645c4a50a1a6cf84789ad";
const char serverAddr[] = "athena";
const char url[] = "/php/host.php";
const int httpPort = 80;

// Info about this hardware host
const char hostID[] = "5824926006ca4c6fa3f8"; //Unique ID of the host, random 20 characters
const uint8_t key[] = "9eafc21877e34241b206"; //Secret key to validate messages, 20 characters
const char hostName[] = "Light Sensor 1"; //Human Readable name for this host.

const int numChan = 2; //Number of hardware channels this host handles.
const unsigned int maxVals = 1000; //Max number of readings to hold in memory.

// Info about each channel
const char chanName1[] = "Light Sensor";
const char chanType1[] = "lux";
const int chanVar1 = 1;
const int chanActive1 = 1;
const float chanMax1 = 1; //Applicable to outputs, not inputs.
const float chanMin1 = 0; //Same note.
const char chanColor1[] = "FFFFFF";
const char chanUnits1[] = "Lux";

const char chanName2[] = "Dummy";
const char chanType2[] = "fake";
const int chanVar2 = 1;
const int chanActive2 = 1;
const float chanMax2 = 1; //Applicable to outputs, not inputs.
const float chanMin2 = 0; //Same note.
const char chanColor2[] = "000000";
const char chanUnits2[] = "fakes";

// Repeat for subsequent channels I.E.:
//const char chanName3[] = "wooo";
//const char chanType3[] = "bla";
// etc.

// Now, build our channel array.  Note: it's up to you to do this the correct number of times.
const char * chanNames[] =
{
  chanName1,
  chanName2
};

const char * chanTypes[] =
{
  chanType1,
  chanType2
};

const int chanVars[] = 
{
  chanVar1,
  chanVar2
};

const int chanActives[] = 
{
  chanActive1,
  chanActive2
};

const float chanMaxs[] = 
{
  chanMax1,
  chanMax2
};

const float chanMins[] = 
{
  chanMin1,
  chanMin2
};

const char * chanColors[] = 
{
  chanColor1,
  chanColor2
};

const char * chanUnits[] = 
{
  chanUnits1,
  chanUnits2
};

// Also, setup our hardware needs
Adafruit_TSL2591 tsl = Adafruit_TSL2591(2591);

// Initialize our time variables to track current linux time.
unsigned long Ltime = 0;
unsigned long Toffset;
unsigned long nextPing = 5;
unsigned long nextRead = 10;
unsigned long nextWrite = 10;
unsigned long inInterval = 60;
unsigned long outInterval = 60;

// Initialize data for our channels.
// If a channel is an output, this gets populated from the server on pings.
// If a channel is an input, this gets uploaded to the server on pings.

unsigned long timeStamps[numChan][maxVals]; //Note: one thousand values per channel
float chanVals[numChan][maxVals]; //Note: again, one thousand values per channel

unsigned int chanReg[] = {0, 0}; //Register tracker to log which value applies next
boolean chanRot[] = {false, false}; //Register tracker - detect if we're overwriting data.

//PipedStreamPair pipes;
//PipedStream& streamIn = pipes.first;
//PipedStream& streamOut = pipes.second;

// Function to start the hardware
void startChannels() {
  // Channel 1: lux sensor.  
  if (tsl.begin()) 
    {
      Serial.println("Found a TSL2591 sensor");
      tsl.setGain(TSL2591_GAIN_LOW); // _GAIN_MED or _GAIN_HIGH or _GAINMAX
      tsl.setTiming(TSL2591_INTEGRATIONTIME_100MS); //200MS, 300MS, ... 600MS
      
    } 
    else 
    {
      Serial.println("No lux sensor found ... check your wiring?");
    }
}


// Function to drive output hardware


// function to read input hardware
void readChannels() {
  // Channel1: Really Measure the lux.
  uint32_t lum = tsl.getFullLuminosity();
  uint16_t ir, full;
  ir = lum >> 16;
  full = lum & 0xFFFF;

  timeStamps[0][chanReg[0]] = Ltime;
  chanVals[0][chanReg[0]] = tsl.calculateLux(full, ir);
  chanReg[0]++;

  // Do additional channels here.
  // In this case, act like channel 2 is an output, so ignore it here
}


// Function to connect to the WIFI.
void startWIFI() {
  Serial.println();
  Serial.println();
  Serial.print("Connecting to ");
  Serial.println(ssid);
  
  WiFi.begin(ssid, password);
  
  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(".");
  }

  Serial.println("");
  Serial.println("WiFi connected");  
  Serial.println("IP address: ");
  Serial.println(WiFi.localIP());
}


// Function to POST to the server
void post() {
  WiFiClient client;

  Serial.print("connecting to ");
  Serial.println(serverAddr);

  // Use WiFiClient class to create TCP connections
  if (!client.connect(serverAddr, httpPort)) {
    Serial.println("connection failed");
    return;
  }
  
  sendPost(client);
  //delay(1000);
  rxPost(client);
}

void sendPost(WiFiClient client) {
  // We now create a URI for the request
  Serial.print("Requesting URL: ");
  Serial.println(url);
  Serial.println("Sending request");

  // Send the common part
  client.print("POST ");
  client.print(url);
  client.print(" HTTP/1.1\r\n");
  client.print("Host: ");
  client.print(serverAddr);
  client.print("\r\nConnection: close\r\n");
  client.print("Content-Type: application/x-www-form-urlencoded\r\n");
  client.print("Content-length: ");
  
  // Generate & print our data.
  // Start the pool
  DynamicJsonBuffer jsonBuffer;
  
  JsonObject& root = jsonBuffer.createObject();

  // Setup our data
  root["id"] = hostID;
  root["name"] = hostName;
  root["date"] = Ltime;
  root["maxVals"] = maxVals;

  JsonArray& chans = root.createNestedArray("channels");

  for(int i = 0; i < numChan; i++){
    JsonObject& chan = chans.createNestedObject();

    chan["name"] = chanNames[i];
    chan["type"] = chanTypes[i];
    chan["variable"] = chanVars[i];
    chan["active"] = chanActives[i];
    chan["max"] = chanMaxs[i];
    chan["min"] = chanMins[i];
    chan["color"] = chanColors[i];
    chan["units"] = chanUnits[i];

    JsonArray& times = chan.createNestedArray("times");
    JsonArray& values = chan.createNestedArray("values");

    int chLim = chanReg[i];

    if(chanRot[i]) {
      chLim = maxVals;
    }

    for(int j = 0; j < chLim; j++){
      times.add(timeStamps[i][j]);
      values.add(chanVals[i][j], 6);
      }
    //
    // Testing: reset the chanReg - in the long run do this only after a successful upload
    chanReg[i] = 0;
    //
    //
  }

  Sha256.initHmac(key,20);

  // Feed the JSON into the hash
  root.printTo(Sha256);

  client.print(root.measureLength() + 75);
  client.print("\r\n\r\n");
  client.print("host=");
  root.printTo(client);
  client.print("&HMAC=");
  uint8_t* hash = Sha256.resultHmac();
   
  for (int i=0; i<32; i++) {
    client.print("0123456789abcdef"[hash[i]>>4]);
    client.print("0123456789abcdef"[hash[i]&0xf]);
  }
}

void rxPost(WiFiClient client) {
  // Read all the lines of the reply from server.
  Serial.println("Server Response:");

  //uint8_t hash[65];
  int count = 0;
  uint8_t lastByte = 0;
  int i = 0;
  uint8_t jsonData[500];

  // Init our buffers.
  //Sha256.initHmac(key,20);
  DynamicJsonBuffer jsonBuffer;
  
  
  while(client.connected()){
    uint8_t c = client.read();
    Serial.write(c);

    //We're counting empty lines
    //after the first: our debug line
    //after the second: our HMAC
    //after the third: the JSON data.

    switch (count) {
      case 0:
        if(lastByte == '\n' && c == '\r'){
          //Increase our count
          count++;
        }
        break;
      case 1:
        if(lastByte == '\n' && c == '\r'){
          //Increase our count
          count++;
        }
        break;
      case 2:
        //We are now reading the HMAC
        if(c != '\r' && c != '\n'){
          //Byte we want to keep
          //hash[i] = c;
          i++;
        } else {
          if(lastByte == '\n' && c == '\r'){
            //Increase our count
            count++;
            //Read one more byte.
            c = client.read();
            Serial.write(c);
            i = 0;
          }
        }
        break;
      case 3:
        //We are reading the JSON
        //Sha256.write(c);
        jsonData[i] = c;
        i++;
        break;
    }
    lastByte = c;
  }

  //Test if our hashes match
  //uint8_t* myHash = Sha256.resultHmac();

  //if(myHash == hash){
  //  Serial.println("hashes matched!");
  //} else {
  //  Serial.println("hashes did not match");
  //}

  
  //for (int i=0; i<32; i++) {
  //  Serial.print("0123456789abcdef"[hash[i]>>4]);
  //  Serial.print("0123456789abcdef"[hash[i]&0xf]);
  //}

  //Serial.println("...");

  //for (int i=0; i<32; i++) {
  //  Serial.print("0123456789abcdef"[myHash[i]>>4]);
  //  Serial.print("0123456789abcdef"[myHash[i]&0xf]);
  //}

  //Serial.println("...");

  //for (int i=0; i<64; i++) {
  //  Serial.write(myHash[i]);
  //}

  //Parse the JSON
  JsonObject& root = jsonBuffer.parseObject(jsonData);

  Toffset = atol(root["date"]) - millis()/1000;
  //nextPing = root["nextPing"];
  //inInterval = root["inInterval"];
  //outInterval = root["outInterval"];

  //for (int i = 1; i < numChan; i++){
  //  if (chanReg[i] == maxVals) {
  //    chanReg[i] = 0;
  //    chanRot[i] = false;
  //  }
  //}

  //Also, handle inserting data.
  //TODO
  
}


void setup() {
  Serial.begin(115200);
  delay(1000);

  // We start by connecting to a WiFi network
  startWIFI();

  delay(1000);

  // Bootstrap our info
  // Ping twice: the first time should get a time hack
  // The second time should get our initial values
  post();

  // Start the hardware
  startChannels();
  
}


void loop() {
  //Check our WIFI connection.
  if (WiFi.status() != WL_CONNECTED) {
    delay(1);
    startWIFI();
  }

  //Go through our tests to see if we need to do actions.
  //if (Ltime >= nextPing) {
  //  post();
  //}
  
  if (Ltime >= nextRead) {
    readChannels();
    nextRead = Ltime + 60;
  }

  //if (Ltime >= nextWrite) {
  //  readChannels();
  //  nextRead += 5;
  //}

  //Test our JSON gen
  if (Ltime >= nextPing) {
    post();
    nextPing = Ltime + 600; //TODO change this to let the server drive our next ping
  }

  //update our time.
  Ltime = millis()/1000 + Toffset;

  //Test our chanReg for maxing out size; if so, trigger a ping.  Hopefully this never has to trigger.
  for (int i = 1; i < numChan; i++){
    if (chanReg[i] == maxVals) {
      post();
      //Reset the chanReg whether it sucessfully uploaded data or not.
      //Note: this effectively rotates data, so set a flag for that.
      chanReg[i] = 0;
      chanRot[i] = true;
    }
  }
}



