//Copyright 2017 Stuart Asp: d3snoopy AT gmail

//This file is part of Aqctrl.

//Aqctrl is free software: you can redistribute it and/or modify
//it under the terms of the GNU General Public License as published by
//the Free Software Foundation, either version 3 of the License, or
//(at your option) any later version.

//Aqctrl is distributed in the hope that it will be useful,
//but WITHOUT ANY WARRANTY; without even the implied warranty of
//MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//GNU General Public License for more details.

//You should have received a copy of the GNU General Public License
//along with Aqctrl.  If not, see <http://www.gnu.org/licenses/>.

//TODO: add a server to make able to receive a ping to queue action.


#include <ESP8266WiFi.h>
#include "sha256.h"
#include "Adafruit_TLC59711.h"
#include <SPI.h>




// Info about connecting: our wireless access point, server hostname
const char ssid[]     = "wireless1";
const char password[] = "f10b5403c12645c4a50a1a6cf84789ad";
const char serverAddr[] = "athena";
const char url[] = "/php/host.php";
const int httpPort = 80;

// Info about this hardware host
const char hostID[] = "49f14021562849fbb1c8"; //Unique ID of the host, random 20 characters
const uint8_t key[] = "ad79ce6be591412ea3a1"; //Secret key to validate messages, 20 characters
const char hostName[] = "Lego Lights"; //Human Readable name for this host.

const int numChan = 12; //Number of hardware channels this host handles.
//Each of these next arrays needs to have the number of elements equal to numChan.

//Flag whether we read or write to channel.  If we read, true; if we write: false.
const boolean chanIn[] = {false, false, false, false, false, false, false, false, false, false, false, false};
//Register tracker to log which value applies next (init at all 0's)
unsigned int chanReg[] = {0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0};
//Data return register tracker: this allows us to overwrite data and still return everything.
unsigned long chanPing[] = {0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0};
//Max number of readings to hold in memory reduce this if you get crashes.
const unsigned int maxVals = 100;

// Setup info for each channel
// Names: the human-readible names for each channel
// Types: the "type" of the channel (used to group common channels)
// Vars: true if the channel is variable, false if the channel is on/off
// Actives: true if we use this channel, false if we don't use (I.E. reserved channel, if there's a set, but some aren't used)
// Maxs: the maximum value allowed for each channel, in channel units
// Mins: the minimum value allowed for each channel, in channel units
// Colors: the color for each channel (relevant to the web interface, sets the color of plots)
// Units: the units of the channel, in a human readible name
// Scales: the scale to use to convert from "units" to whatever the hardware actually uses
// Inits: the value to initiate to

const char * chanNames[] = {"Light1", "Light2", "Light3", "Light4", "Light5", "Light6", "Light7", "Light8", "Light9", "Light10", "Light11", "Light12"};
const char * chanTypes[] = {"light", "light", "light", "light", "light", "light", "light", "light", "light", "light", "light", "light"};
const boolean chanVars[] = {true, true, true, true, true, true, true, true, true, true, true, true};
const boolean chanActives[] = {true, true, true, true, true, true, true, true, true, true, true, true};
const float chanMaxs[] = {63.62, 63.62, 63.62, 63.62, 63.62, 63.62, 63.62, 63.62, 63.62, 63.62, 63.62, 63.62};
const float chanMins[] = {0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0};
const char * chanColors[] = {"FFFFFF", "FFFFFF", "FFFFFF", "FFFFFF", "FFFFFF", "FFFFFF", "FFFFFF", "FFFFFF", "FFFFFF", "FFFFFF", "FFFFFF", "FFFFFF"};
const char * chanUnits[] = {"Lumens", "Lumens", "Lumens", "Lumens", "Lumens", "Lumens", "Lumens", "Lumens", "Lumens", "Lumens", "Lumens", "Lumens"};
const float chanScales[] = {1030, 1030, 1030, 1030, 1030, 1030, 1030, 1030, 1030, 1030, 1030, 1030};
const float chanInits[] = {0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0};



// Also, setup our hardware needs
Adafruit_TLC59711 tlc = Adafruit_TLC59711(1, 14, 13);
WiFiServer server(80);


// Initialize our time variables to track current linux time.
unsigned long Ltime = 0;
unsigned long Toffset = 0;
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

// Startup Function.

void setup() {
  Serial.begin(115200);
  delay(1000);

  // Start the hardware
  startChannels();

  // Connect to the WiFi network
  startWIFI();

  delay(1000);

  // Initiate Ltime and Toffset
  Ltime = 0;
  Toffset = 0;

  // Bootstrap our info
  postData();
  
  // Run a second time to make sure we init our channels.
  postData();

}


void loop() {
  //Check our WIFI connection.
  if (WiFi.status() != WL_CONNECTED) {
    delay(1);
    startWIFI();
  }

  //update our time.
  unsigned long oldTime = Ltime;
  
  Ltime = millis()/1000 + Toffset;

  if(oldTime != Ltime) {
    Serial.println(Ltime);
  }

  //read from our input channels if it's time
  if (Ltime >= nextRead) {
    Serial.println("Reading:");
    readChannels();
    nextRead = Ltime + inInterval;
  }

  //write to our output channels if it's time
  if (Ltime >= nextWrite) {
    Serial.println("Writing:");
    writeChannels();
    nextWrite = Ltime + outInterval;
  }

  //upload to the server if it's time
  if (Ltime >= nextPing) {
    postData();
  }

  boolean full = false;
  //Test our chanReg for maxing out size; if so, trigger a ping.
  for (int i = 1; i < numChan; i++){
    if (chanReg[i] == maxVals) {
      full = true;
    }
  }

  if (full){
    postData();
  }

  WiFiClient client = server.available();
  if (client)
  {
    Serial.println("Someone is pinging");
    client.stop();
    postData();
  }

}


// Function to start the hardware
void startChannels() {
  pinMode(14, OUTPUT);
  pinMode(13, OUTPUT);
  tlc.begin();
  tlc.write();
}



// Function to drive output hardware
void writeChannels() {
  // Output to our output channels.

  // We know that channels 0 through 11 are TLC59711 channels.
  for (int j=0; j<12; j+=3) {
    tlc.setLED(j/3, PWMCalc(j), PWMCalc(j+1), PWMCalc(j+2));
  }

  tlc.write();
  
}



// function to read input hardware
void readChannels() {
  // Add actions for your input channels.
}


//////Support Functions for your reads/writes

uint16_t PWMCalc(int i) {
  //We need to:
  //1. Increment our channel index until index and index+1 span our current time
  //2. Find the linear interpolant of the values for these two indexes at our time
  //3. Scale to the machine value

  uint16_t calcAns;

  if(!timeStamps[i][chanReg[i]+1]) {
    //We are at the end of our valid data, use the value from i without linear interpolation
    //Note: this should catch blank data, too, since i and i+1 will be 0
    calcAns = chanScales[i] * chanVals[i][chanReg[i]];
  } else {
    //Increment our chanReg to the right time.
    while( (Ltime>timeStamps[i][chanReg[i]+1]) && (chanReg[i]<maxVals-1) ) {
      chanReg[i]++;
    }
    //Do our linear interpolation and scale
    float a;
    a = (chanVals[i][chanReg[i]+1]-chanVals[i][chanReg[i]])/(timeStamps[i][chanReg[i]+1]-timeStamps[i][chanReg[i]]);

    float intAns;
    intAns = _max(chanMins[i], _min(chanMaxs[i], ((a*(Ltime-timeStamps[i][chanReg[i]]))+chanVals[i][chanReg[i]])));
    
    calcAns = chanScales[i] * intAns;
    
//    Serial.print("LED ");
//    Serial.print(i);
//    Serial.print(" chanReg: ");
//    Serial.print(chanReg[i]);
//    Serial.print(" %: ");
//    Serial.print(intAns,6);
//    Serial.print(" Value: ");
//    Serial.print(calcAns);
//    Serial.print(" Inputs: ");
//    Serial.print(chanVals[i][chanReg[i]],6);
//    Serial.print(" and ");
//    Serial.print(chanVals[i][chanReg[i]+1],6);
//    Serial.print(" Times: ");
//    Serial.print(timeStamps[i][chanReg[i]]);
//    Serial.print(" and ");
//    Serial.print(timeStamps[i][chanReg[i]+1]);
//    Serial.print(" a: ");
//    Serial.println(a,6);
  }

  return calcAns;
}












// Functions to talk to the server, listen for ping, etc.  These shouldn't need to be changed.

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

  server.begin();
}



// Function to POST to the server
void postData() {
  WiFiClient client;

  Serial.print("connecting to ");
  Serial.println(serverAddr);

  // Use WiFiClient class to create TCP connections
  if (!client.connect(serverAddr, httpPort)) {
    Serial.println("connection failed");
    return;
  }

  // Send and receive for each channel
  for(int i=0;i<numChan;i++) {
    // Send and receive.
    sendPost(client, i);
    rxPost(client, i);
    // Pause 100ms.
    delay(100);
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
  // Fixed numbers: 14 commas in "host" + 5 chars for "host=" + 6 chars for "&time=" +
  // 6 chars for "&data=" + 6 chars for "&HMAC=" + 64 chars for HMAC + 
  // 6x10 = 60 int characters + 2*13 = 26 float characters = 187
  int dataLen;
  if (chanIn[i]) {
    dataLen = (chanReg[i])*25; //10 int chars, 13 float chars, 2 commas
  } else {
    dataLen = 0;
  }
  
  client.print(187+strlen(hostID)+strlen(hostName)+strlen(chanNames[i])+strlen(chanTypes[i])
    +strlen(chanColors[i])+strlen(chanUnits[i])+dataLen);
    
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
  client.printf("%010d", chanIn[i]);
  Sha256.printf("%010d", chanIn[i]);
  client.print(",");
  Sha256.print(",");

  //Print the time data
  client.print("&time=");

  if(chanIn[i]) {
    for(int j=0;j<chanReg[i];j++){
      client.printf("%010d", timeStamps[i][j]);
      Sha256.printf("%010d", timeStamps[i][j]);
      client.print(",");
      Sha256.print(",");
    }
  }

  //Print the values
  client.print("&data=");

  if(chanIn[i]) {
    for(int j=0;j<chanReg[i];j++){
      dtostrf(chanVals[i][j],13,3, buffer);
      client.print(buffer);
      Sha256.print(buffer);
      client.print(",");
      Sha256.print(","); 
    }
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

  //Counter to track count.
  int count = 0;
  int dataCount = 0;
  int section = 0;
  char lastByte;

  // Init our buffers.
  Sha256.initHmac(key,20);
  unsigned long times[maxVals];
  float data[maxVals];
  unsigned long newDate;
  unsigned long newPing;
  unsigned long newIn;
  unsigned long newOut;

  char reads[65];

  int wait=0;

  // Wait for either a response or timeout (give it 5 seconds).
  while (!client.available()) {
    delay(100);
    wait+=100;
    if (wait>5000) {
      Serial.println(">>>Server Timeout!");
      return;
    }
  }

  delay(10);
  
  while(client.available()){
    char c = client.read();

    switch (section) {
    case 0:
      {
      Serial.write(c);
      // We have not reached the data yet.
      if(lastByte == '\n' && c == '\r'){
        // We have found an empty line
        if(count > 0) {
          // We're ready to flag that we're in data.
          section++;
          count = 0;
        } else {
          count++;
        }
      }
      lastByte = c;
      }
      break;

    case 1:
      {
      // We are looking at the initial data

      // Print to Hash if not a newline or carriage feed
      if (c != '\r' && c != '\n') {
        Sha256.write(c);
      }

      if (c == ','){
        // Reached the end of our data point.
        reads[count] = '\0';
        
        switch (dataCount) {
          case 0:
            {
            //Date data
            newDate = atol(reads);
            dataCount++;
            count = 0;
            }
            break;

          case 1:
            {
            //nextPing data
            newPing = atol(reads);
            dataCount++;
            count = 0;
            }
            break;

          case 2:
            {
            //inInterval data
            newIn = atol(reads);
            dataCount++;
            count = 0;
            }
            break;

          case 3:
            {
            //outInterval data
            reads[count] = '\0';
            newOut = atol(reads);
            section++;
            count = 0;
            dataCount = 0;
            }
            break;
        }
      } else {
        //Skip newlines and carriage feeds
        if (c != '\r' && c != '\n') {
        //Update our reads.
        reads[count] = c;
        count++;
        }
      }
      }
      break;

    case 2:
      {
      // Time data

      //Print into our hash calculation
      Sha256.write(c);

      //Test for transition to data
      if (c == ';') {
        for (int j=dataCount; j<maxVals; j++) {
          times[j] = 0;
        }
        section++;
        count = 0;
        dataCount = 0;
      } else {
        if (c == ','){
          //Read the data in
          reads[count] = '\0';
          if (dataCount < maxVals){
            times[dataCount] = atol(reads);
          }
          dataCount++;
          count = 0;
        } else {
          //Update the reads.
          reads[count] = c;
          count++;
        }
      }
      }
      break;

    case 3:
      {
      // Data

      //Print into our hash calculation
      Sha256.write(c);

      //Test for transition to data
      if (c == ';') {
        for (int j=dataCount; j<maxVals; j++) {
          data[j] = chanInits[i];
        }
        //TODO Do something about default filling the rest of the data
        section++;
        count = 0;
        dataCount = 0;
      } else {
        if (c == ','){
          //Read the data in
          reads[count] = '\0';
          data[dataCount] = atof(reads);
          dataCount++;
          count = 0;
        } else {
          //Update the reads.
          reads[count] = c;
          count++;
        }
      }
    }
    break;

    case 4:
      {
      //HMAC
      if (c != ';') {
        reads[count] = c;
        count++;
      }
      }
      break;
        
    }
    //Insert a slight delay - we were getting responses fragmented across requests.
    delay(1);
  }

  reads[count] = '\0';

  //Process our local hash
  uint8_t* hash = Sha256.resultHmac();

  char localHash[65];
  
  for (int j=0; j<32; j++) {
    localHash[j*2] = ("0123456789abcdef"[hash[j]>>4]);
    localHash[j*2+1] = ("0123456789abcdef"[hash[j]&0xf]);
  }

  localHash[64] = '\0';

  Serial.println(" ");
  Serial.println("******End of Server Response******");
  Serial.println(" ");
  Serial.println("Hashes:");

  Serial.println(reads);
  Serial.println(localHash);

  Serial.println(" ");
  Serial.print("Last Channel Ping: ");
  Serial.println(chanPing[i]);
  Serial.print("This Channel Ping: ");
  Serial.println(newDate);
  Serial.println(" ");
  

  if(!strcmp(reads, localHash) && newDate > chanPing[i]) {
    Serial.println("Hashes matched and we have a newer ping.");
    //Update our clock (we trust the server clock to more stable than ours).
    Toffset = newDate - millis()/1000;
    Ltime = millis()/1000 + Toffset;
    chanPing[i] = newDate;
    Serial.print("Time Updated to: ");
    Serial.println(Ltime);
    nextPing = newPing;
    inInterval = newIn;
    outInterval = newOut;
    if (!chanIn[i]) {
      memcpy(timeStamps[i], times, sizeof(times[0])*maxVals);
      memcpy(chanVals[i], data, sizeof(data[0])*maxVals);
      Serial.println("New Data Received");
      //Serial.println("Time  Data");

      //for (int j=0; j<maxVals; j++) {
      //  Serial.print(times[j]);
      //  Serial.print("  ");
      //  Serial.println(data[j]);
      //}
    }
  } else {
    Serial.println("Hash Mismatch");
    nextPing = Ltime + 120;
  }

  chanReg[i] = 0; //Reset our register for this channel whether we successfully pinged or not.

  Serial.print("NextPing: ");
  Serial.println(nextPing);
  Serial.println(" ");
  Serial.println("******End of Channel Query******");
  Serial.println(" ");
}


