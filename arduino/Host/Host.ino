//#include <PipedStream.h>
#include <ESP8266WiFi.h>
#include <sha256.h>
//#include <ArduinoJson.h>
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
const boolean chanIn[] = {true, false}; //Flag whether we read or write to channel.  If we read, true; if we write: false.
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

// Data arrays.
unsigned long timeStamps[numChan][maxVals];
float chanVals[numChan][maxVals];

unsigned int chanReg[] = {0, 0}; //Register tracker to log which value applies next
boolean chanRot[] = {false, false}; //Register tracker - detect if we're overwriting data.




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
void writeChannels() {
  // Output to our output channels.
}



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

  // Send and receive for each channel
  for(int i=0;i<numChan; i++) {
    // Send and receive.
    sendPost(client, i);
    rxPost(client, i);
  }

  // Close the connection.
  client.stop();
}



void sendPost(WiFiClient client, int i) {
  // We now create a URI for the request
  Serial.print("Requesting URL: ");
  Serial.println(url);
  Serial.println("Sending request");

  //Buffer the floating point prints.
  char buffer[20]; 

  // Send the message
  client.print("POST ");
  client.print(url);
  client.print(" HTTP/1.1\r\n");
  client.print("Host: ");
  client.print(serverAddr);
  client.print("\r\nConnection: keep-alive\r\n");
  client.print("Content-Type: application/x-www-form-urlencoded\r\n");
  client.print("Content-length: ");

  // Calculate the length of our message.
  // Fixed numbers: 13 commas in "host" + 5 chars for "host=" + 6 chars for "&time=" +
  // 6 chars for "&data=" + 6 chars for "&HMAC=" + 64 chars for HMAC + 
  // 5x10 = 50 int characters + 2*13 = 26 float characters = 176
  client.print(176+strlen(hostID)+strlen(hostName)+strlen(chanNames[i])+strlen(chanTypes[i])
    +strlen(chanColors[i])+strlen(chanUnits[i])+((chanReg[i])*25));
    
  Sha256.initHmac(key,20);
  
  client.print("\r\n\r\n");
  client.print("host=");

  // Print host info
  client.print(hostID);
  Sha256.print(hostID);
  client.print(",");
  Sha256.print(",");
  client.print(hostName);
  Sha256.print(hostName);
  client.print(",");
  Sha256.print(",");
  client.printf("%010d", Ltime);
  Sha256.printf("%010d", Ltime);
  client.print(",");
  Sha256.print(",");
  client.printf("%010d", maxVals);
  Sha256.printf("%010d", maxVals);
  client.print(",");
  Sha256.print(",");
  client.printf("%010d", i);
  Sha256.printf("%010d", i);
  client.print(",");
  Sha256.print(",");
  client.print(chanNames[i]);
  Sha256.print(chanNames[i]);
  client.print(",");
  Sha256.print(",");
  client.print(chanTypes[i]);
  Sha256.print(chanTypes[i]);
  client.print(",");
  Sha256.print(",");
  client.printf("%010d", chanVars[i]);
  Sha256.printf("%010d", chanVars[i]);
  client.print(",");
  Sha256.print(",");
  client.printf("%010d", chanActives[i]);
  Sha256.printf("%010d", chanActives[i]);
  client.print(",");
  Sha256.print(",");
  dtostrf(chanMaxs[i],13,3, buffer);
  client.print(buffer);
  Sha256.print(buffer);
  client.print(",");
  Sha256.print(",");
  dtostrf(chanMins[i],13,3, buffer);
  client.print(buffer);
  Sha256.print(buffer);
  client.print(",");
  Sha256.print(",");
  client.print(chanColors[i]);
  Sha256.print(chanColors[i]);
  client.print(",");
  Sha256.print(",");
  client.print(chanUnits[i]);
  Sha256.print(chanUnits[i]);
  client.print(",");
  Sha256.print(",");

  //Print the time data
  client.print("&time=");

  for(int j=0;j<chanReg[i];j++){
    client.printf("%010d", timeStamps[i][j]);
    Sha256.printf("%010d", timeStamps[i][j]);
    client.print(",");
    Sha256.print(","); 
  }

  //Print the values
  client.print("&data=");

  for(int j=0;j<chanReg[i];j++){
    dtostrf(chanVals[i][j],13,3, buffer);
    client.print(buffer);
    Sha256.print(buffer);
    client.print(",");
    Sha256.print(","); 
  }

  //Print the HMAC
  uint8_t* hash = Sha256.resultHmac();
  client.print("&HMAC=");
  

  for (int j=0; j<32; j++) {
    client.print("0123456789abcdef"[hash[j]>>4]);
    client.print("0123456789abcdef"[hash[j]&0xf]);
  }
}



void rxPost(WiFiClient client, int i) {
  // Read all the lines of the reply from server.
  Serial.println("\r\nServer Response:");

  //uint8_t hash[65];
  int count = 0;
  uint8_t jsonData[500];

  // Init our buffers.
  Sha256.initHmac(key,20);

  uint8_t ring[] = "000000";

  
  while(client.available()){
    uint8_t c = client.read();
    Serial.write(c);
  }

  nextPing = Ltime + 30;

  return;

    //We're counting empty lines
    //after the first: our debug line
    //after the second: our HMAC
    //after the third: the JSON data.

    //switch (count) {
      //case 0:
        //if(lastByte == '\n' && c == '\r'){
          //Increase our count
        //  count++;
      //  }
      //  break;
     // case 1:
     //   if(lastByte == '\n' && c == '\r'){
          //Increase our count
      //    count++;
      //  }
     //   break;
     // case 2:
        //We are now reading the HMAC
     //   if(c != '\r' && c != '\n'){
          //Byte we want to keep
          //hash[i] = c;
      //    i++;
     //   } else {
      //    if(lastByte == '\n' && c == '\r'){
            //Increase our count
       //     count++;
            //Read one more byte.
      //      c = client.read();
       //     Serial.write(c);
      //      i = 0;
     //     }
    //    }
    //    break;
   //   case 3:
        //We are reading the JSON
        //Sha256.write(c);
   //     jsonData[i] = c;
   //     i++;
  //      break;
  //  }
  //  lastByte = c;
 // }

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
  //JsonObject& root = jsonBuffer.parseObject(jsonData);

  //Toffset = atol(root["date"]) - millis()/1000;
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

  // Start the hardware
  startChannels();

  // We start by connecting to a WiFi network
  startWIFI();

  delay(1000);

  // Bootstrap our info
  post();
}


void loop() {
  //Check our WIFI connection.
  if (WiFi.status() != WL_CONNECTED) {
    delay(1);
    startWIFI();
  }
  
  if (Ltime >= nextRead) {
    readChannels();
    nextRead = Ltime + inInterval;
  }

  if (Ltime >= nextWrite) {
    writeChannels();
    nextRead = Ltime + outInterval;
  }

  //Test our JSON gen
  if (Ltime >= nextPing) {
    post();
  }

  //update our time.
  Ltime = millis()/1000 + Toffset;

  boolean full = false;
  //Test our chanReg for maxing out size; if so, trigger a ping.  Hopefully this never has to trigger.
  for (int i = 1; i < numChan; i++){
    if (chanReg[i] == maxVals) {
      full = true;
    }
  }

  if (full){
    post();
  }
}



