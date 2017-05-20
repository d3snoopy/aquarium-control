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
unsigned long Mtime;
unsigned long nextPing = 5;
unsigned long nextRead = 10;
unsigned long nextWrite = 10;
unsigned long lastRem = 999;

// Initialize data for our channels.
// If a channel is an output, this gets populated from the server on pings.
// If a channel is an input, this gets uploaded to the server on pings.

unsigned long timeStamps[numChan][maxVals]; //Note: one thousand values per channel
float chanVals[numChan][maxVals]; //Note: again, one thousand values per channel

// make this {0, 0}
unsigned int chanReg[] = {0, 0}; //Register tracker to log which value applies next


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

  
  
  // Fake the subsequent channels.
  for(int i = 1; i < numChan; i++){
    timeStamps[i][chanReg[i]] = Ltime;
    chanVals[i][chanReg[i]] = millis();
    chanVals[i][chanReg[i]] /= 1000000;
    chanReg[i]++;
  }
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
  Serial.print("connecting to ");
  Serial.println(serverAddr);

  // Use WiFiClient class to create TCP connections
  WiFiClient client;
  if (!client.connect(serverAddr, httpPort)) {
    Serial.println("connection failed");
    return;
  }
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
  
  //Test to see if we need a timehack, note we have 1000 sec to acquire it.
  if (Ltime < 1000) {
    //Ping for a timehack
    client.print("6\r\n\r\n");
    client.print("init=1\r\n");

    delay(100);

    // Read one line and use it if it's all numeric
    char line[12];
    boolean isNum = true;
    int i = 0;
    //String line;
    
    // Read all the lines of the reply from server and print them to Serial, saving the last.
    while(client.available()){
      String nline = client.readStringUntil('\r');
      Serial.print(nline);
      nline.toCharArray(line, 12);
    }

    for (i=1; i < strlen(line); i++) { 
      if(!isDigit(line[i])) {
        isNum = false;
        break;
      }
    }

    if(isNum) {
      Ltime = atol(line);
    }
  
    Serial.println();
    Serial.println("closing connection");
    //client.close();

    return;
    
  } else {
    // Generate & print our data.
    // Start the pool
    DynamicJsonBuffer jsonBuffer;
  
    JsonObject& root = jsonBuffer.createObject();

    // Setup our data
    root["id"] = hostID;
    root["name"] = hostName;
    root["date"] = Ltime;

    for(int i = 0; i < numChan; i++){
      JsonObject& chans = root.createNestedObject(chanNames[i]);
        
      chans["type"] = chanTypes[i];
      chans["variable"] = chanVars[i];
      chans["active"] = chanActives[i];
      chans["max"] = chanMaxs[i];
      chans["min"] = chanMins[i];
      chans["color"] = chanColors[i];
      chans["units"] = chanUnits[i];

      JsonArray& times = chans.createNestedArray("times");
      JsonArray& values = chans.createNestedArray("values");

      for(int j = 0; j < chanReg[i]; j++){
        times.add(timeStamps[i][j]);
        values.add(chanVals[i][j], 6);
        }
      //
      // Testing: reset the chanReg - in the long run do this only after a successful upload
      chanReg[i] = 0;
      //
      //
    }

    ////// Print the output for debug purposes
    //root.prettyPrintTo(Serial);
    //Serial.println();
    ///////

    Sha256.initHmac(key,20);

    // Feed the JSON into the hash
    root.printTo(Sha256);

    client.print(root.measureLength() + 75);
    client.print("\r\n\r\n");
    client.print("host=");
    root.printTo(client);
    client.print("&HMAC=");
    int i;
    uint8_t* hash = Sha256.resultHmac();
    
    for (i=0; i<32; i++) {
      client.print("0123456789abcdef"[hash[i]>>4]);
      client.print("0123456789abcdef"[hash[i]&0xf]);
    }

    delay(100);

    // Read all the lines of the reply from server and print them to Serial
    while(client.available()){
      String line = client.readStringUntil('\r');
      Serial.print(line);
      //TODO: Parse the response.
    }
  
    Serial.println();
    Serial.println("closing connection");
  }
  
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

  //Get our time.
  Mtime = millis()/1000;

  //Go through our tests to see if we need to do actions.
  //if (Mtime >= nextPing) {
  //  post();
  //}
  
  if (Mtime >= nextRead) {
    readChannels();
    nextRead += 60;
  }

  //if (Mtime >= nextWrite) {
  //  readChannels();
  //  nextRead += 5;
  //}

  //Test our JSON gen
  if (Mtime >= nextPing) {
    post();
    nextPing += 300; //TODO change this to let the server drive our next ping
  }

  //See if we need to update our Ltime.
  if (millis() % 1000 < lastRem) {
    //We've rolled over another second
    Ltime++;
    //Serial.println(Ltime);
  } 

  //Update our last remainder.
  lastRem = millis() % 1000;

  //Test our chanReg for maxing out size; if so, trigger a ping.  Hopefully this never has to trigger.
  for (int i = 1; i < numChan; i++){
    if (chanReg[i] == maxVals) {
      post();
    }
  }
}



