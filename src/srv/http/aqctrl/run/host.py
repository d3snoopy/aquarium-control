from aqctrl.run.db import get_db, query_db, modify_db
from aqctrl.run import busses, devices, channels, logging, reactions, functions, helpers
from datetime import datetime, timedelta, UTC
import time
import json
from os.path import exists
from os import remove, system
#from aqctrl.aqctrl import app
#import sys

#Here is where we code up the object for the deamon to run.
class aqHost:
  def __init__(self, withStdOut=False):
    #now = datetime.now(UTC)
    self.db = get_db(newdb = True, appContext = False) #New so db updates will get captured.
    self.getDB(withStdOut)
    self.withStdOut=withStdOut
    self.buttonInt = timedelta(seconds=5) #Hard coding this.
    return

  def loop(self):
    #Log the startup. This will help keep track of when we have unplanned restarts.
    self.logging.read.logError('Starting loop.') #Could pick any of our types.
    #Do an initial read and write.
    self.read() #within this, update our next read time.
    self.write() #within this, update our next write time.
    self.checkDB()
    self.storeCurr()
    self.checkButtons()

    #Start our loop.
    while True:
      #Sleep until the next action.
      now = datetime.now(UTC)
      dButton = self.nextButtonTime-now
      dButton = dButton.total_seconds()
      dRead = self.nextReadTime-now
      dRead = dRead.total_seconds()
      dWrite = self.nextWriteTime-now
      dWrite = dWrite.total_seconds()
      dCheck = self.nextCheckTime-now
      dCheck = dCheck.total_seconds()
      t = max(0,min(dRead, dWrite, dCheck, dButton))
      if self.withStdOut: print('Sleep for ' + str(t) + ' seconds')
      time.sleep(t)

      #We are ready for something... whatever it is, do it!
      now = datetime.now(UTC)
      if now>=self.nextButtonTime:
        if not self.checkButtons(): continue #If the button check didn't return anything, don't do the rest.

      if now>=self.nextCheckTime:
        self.checkDB()

      if now>=self.nextReadTime:
        self.read()

      if now>=self.nextWriteTime:
        self.write()

      #Output our current channel values to a dictionary and JSON file.
      self.storeCurr()

      #That should be it for the loop.

  def checkButtons(self):
    #reboot
    fName = '/tmp/reboot'
    if exists(fName):
      remove(fName)
      self.write(defaults=True)
      system('sudo systemctl reboot -i')

    #shutdown
    fName = '/tmp/shutdown'
    if exists(fName):
      remove(fName)
      self.write(defaults=True)
      system('sudo systemctl halt -i')

    #home button
    fName = '/tmp/aqButton'
    self.nextButtonTime = datetime.now(UTC)+self.buttonInt
    if not exists(fName): return False

    with open(fName, 'r') as f:
      bID = int(f.read())

    remove(fName)

    #Need to create a profile with lockout for the channels in the button.
    dbB = query_db('SELECT * FROM buttonChan WHERE homeButton=?', (bID, ), db=self.db)
    if dbB is not None:
      now = int(datetime.now(UTC).timestamp())
      self.withStdOut: print('Creating Button Reactions.')
      for c in dbB:
        p = dict()
        p['profStart'] = now
        p['profEnd'] = now+int(c['duration'])
        p['profRefresh'] = False
        thisP = functions.profile(p)
        thisP.expire = True
        thisP.function = self.functions[int(c['AQfunction'])]
        thisP.scale = float(c['scale'])
        thisP.lockout = True
        thisP.behave = c['behave']
        
        self.channels[int(c['outChan'])].replaceReact(thisP)

        
      self.nextReadTime = datetime.now(UTC)
      self.nextWriteTime = datetime.now(UTC)
      return True

    return False


  def storeCurr(self):
    #Output our current channel values to a dictionary and JSON file.
    currVals = dict()
    modes = {0:'replace', 1:'add'}
    for i, c in self.channels.items():
      currVals[i] = dict()
      currVals[i]['name']=c.name
      currVals[i]['variable']=c.variable
      if self.tempUnits and c.tempType:
        currVals[i]['out']=helpers.cToF(c.getLogVal('outVal'))
        currVals[i]['in']=helpers.cToF(c.getLogVal('inVal'))
      else:
        currVals[i]['out']=c.getLogVal('outVal')
        currVals[i]['in']=c.getLogVal('inVal')
      if c.reactProf is None:
        currVals[i]['react']=None
        currVals[i]['mode']=None
      else:
        currVals[i]['react']=c.reactProf.function.name
        currVals[i]['mode']=modes[c.reactProf.behave]

    with open('/tmp/aqctrl', 'w') as f:
      json.dump(currVals, f)

    return


  def checkDB(self):
    if self.withStdOut: print('Performing a DB check')
    self.nextCheckTime = datetime.now(UTC)+self.checkInt
    ret = query_db('SELECT lastUpdate FROM AQhost WHERE rowid = 1', one=True, db=self.db)
    if int(ret['lastUpdate']) > self.lastKnownDBUpdate:
      self.getDB(self.withStdOut)
      return True
    else:
      return False
   

  def read(self):
    #Read all of our inputs.
    if self.withStdOut: print('Performing a read')
    for bus in self.busses.values():
      bus.read(self.logging.read)
    #Also, handle all reactions.
    reactTrig = False
    for r in self.reactGroups.values():
      if r.process(self.logging.react):
        reactTrig = True

    if reactTrig:
      self.write()

    #Record the next time to read.
    self.nextReadTime = datetime.now(UTC)+self.readInt
    return

  def write(self, defaults=False):
    now = datetime.now(UTC).timestamp()
    if self.withStdOut: print('Performing a write')
    #Start by flushing all of our channel values.
    #for c in self.channels.values():
    #  c.shiftVals()
    #Calculate all of our sources and seed values into the channel objects.
    #for s in self.sources.values():
    #  s.doCalc(now, chans=self.channels)
    #Now, need to do the reaction calculations.
    #for r in self.reactGroups.values():
    #  r.doCalc(now)
 
    #Write all of our outputs.
    for bus in self.busses.values():
      bus.write(self.logging.write, defaults=defaults)

    #Record the next time to write.
    self.nextWriteTime = datetime.now(UTC)+self.writeInt
    return


  def getDB(self, withStdOut=False):
    #Load the basics about this whole host.
    dbHost = query_db('SELECT * FROM AQhost', one=True, db=self.db)

    if dbHost is None:
      #Fresh db without an entry for the basic info.
      modify_db('INSERT INTO AQhost DEFAULT VALUES', db=self.db)
      dbHost=query_db('SELECT * FROM AQhost', one=True, db=self.db)

    self.readInt = timedelta(seconds = int(dbHost['readInt']))
    self.writeInt = timedelta(seconds = int(dbHost['writeInt']))
    self.checkInt = timedelta(seconds = int(dbHost['checkInt']))
    self.logging = logging.logging(dbHost['readLog'], dbHost['writeLog'], dbHost['reactLog'], self.db, withStdOut)
    self.tempUnits = dbHost['tempUnits']

    #Save the logging timebases.
    self.logging.read.timebase = int(dbHost['readInt'])
    self.logging.write.timebase = int(dbHost['writeInt'])
    self.logging.react.timebase = int(dbHost['readInt'])

    #Grab the last time the DB was updated.
    self.lastKnownDBUpdate = int(dbHost['lastUpdate'])

    #Populate our device/channel info:
    #A dict of busses containing devices containing a channels
    #Each as an object.
    dbChan = query_db('SELECT * FROM AQchannel LEFT JOIN chanType ON chanType.rowid = AQchannel.chType ORDER BY chDevice', db=self.db)
    dbDev = query_db('SELECT * FROM hostDevice ORDER BY busType, busOrder', db=self.db)

    # Need to stage the devices.
    ddb = dict()
    self.busses = dict()
    self.channels = None
    for i in dbDev:
      ddb[i['rowid']] = i


    #populate sources
    dbSrc = query_db('SELECT * FROM AQsource', db=self.db)
    dbProf = query_db('SELECT * FROM AQprofile', db=self.db)
    dbCPS = query_db('SELECT * FROM AQCPS', db=self.db)
    dbFunct = query_db('SELECT * FROM AQfunction', db=self.db)
    dbPts = query_db('SELECT * FROM FnPoint', db=self.db)

    self.sources, self.functions = popSrcs(dbSrc, dbProf, dbCPS, dbFunct, dbPts)

    #Now, build up the busses, channels.
    self.channels = popChans(dbChan, self.sources)

    for c in self.channels.values():
      d = ddb[c.devnum]
      
      #Add the device/bus if necessary.
      #Note: Only one bus per type is supported.
      if d['busType'] not in self.busses:
        self.busses[d['busType']] = busses.listOpts()[d['busType']]()
        self.busses[d['busType']].devices = dict()

      if d['rowid'] not in self.busses[d['busType']].devices:
        #print('device ID is: ' + str(d['rowid']))
        #print('bus ID is: ' + str(d['busType']))
        a = devices.listOpts()[d['devType']](d)
        #print(vars(a))
        self.busses[d['busType']].devices[d['rowid']] = a
        #print('added dev ' + str(d['rowid']) + ' to bus ' + str(d['busType']))
        self.busses[d['busType']].devices[d['rowid']].channels = dict()

      #Add this channel info
      self.busses[d['busType']].devices[d['rowid']].channels[c.rowid] = c


    #populate react groups
    dbRctGrp = query_db('SELECT * FROM AQreactGrp', db=self.db)
    self.reactGroups = dict()
    #Once processing, reactProf is where we house active reaction profiles.
    for r in dbRctGrp:
      self.reactGroups[r['rowid']] = reactions.reactGroup(r, withStdOut)
      self.reactGroups[r['rowid']].outChan = self.channels[r['outChan']]
      #Add this react group to the output channel.
      #self.channels[r['outChan']].reactGrp = self.reactGroups[r['rowid']]

    #populate reactions into groups
    dbReact = query_db('SELECT * FROM AQreaction', db=self.db)
    for r in dbReact:
      #Create the reaction object
      a = reactions.reaction(r)
      if a.criteriaType:
        #Link to the monitor channel and function objects
        a.monChan = self.channels[r['monChan']]
        a.function = self.functions[r['rctFunct']]
        #Add to the right group's reaction list
        self.reactGroups[r['reactGrp']].reactions.append(a)

    return



#A couple functions to populate things.
def popSrcs(dbSrc, dbProf, dbCPS, dbFunct, dbPts):
  #Function to populate sources.
  #Create functions.
  funct = dict()
  for f in dbFunct:
    funct[f['rowid']] = functions.aqFunction(f)

  #Add points to functions.
  for p in dbPts:
    funct[p['parentFn']].points.append(functions.point(p))

  src = dict()
  for s in dbSrc:
    src[s['rowid']] = functions.source(s)

  for p in dbProf:
    # Create the profile object.
    a = functions.profile(p)
    if p['parentFunction'] is not None: a.function = funct[p['parentFunction']]
    #Add to the source.
    src[p['parentSource']].profiles[p['rowid']] = a

  #Need to manage CPSes.
  for c in dbCPS:
    #Populate a tuple containing (offset, scale) into a dictionary with the channel ID as key.
    if c['CPSsrc'] is not None and c['CPSprof'] is not None and c['CPSchan'] is not None:
      src[c['CPSsrc']].profiles[c['CPSprof']].CPS[c['CPSchan']] = (c['CPStoff'], c['CPSscale'])
      #Also, add this chan to the channel set so we can easily know how to add later.
      src[c['CPSsrc']].chans.add(c['CPSchan'])

  return src, funct


def popChans(dbChan, src):
  #Function to get our channel and populate sources to them.
  #Src can be the return of popSrcs.
  chan = dict()

  for c in dbChan:
    chan[c['rowid']] = channels.aqChan(c)
    chan[c['rowid']].units = c['chUnits']

  #Now, add sources to each channel.
  for s in src.values():
    for c in s.chans:
      chan[c].sources.add(s)

  #This should produce a fully populated dictionary of channels, with applicable sources attached.
  return chan

