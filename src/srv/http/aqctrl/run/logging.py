from aqctrl.run.db import query_db, modify_db
from datetime import datetime, UTC
#from aqctrl.aqctrl import app
#import sys

def readOpts():
  return (noLog, errLog, noneLog, chgLog, allLog) #Add more operators if desired. Order matters, so make sure not to re-order. Saved in the DB based on order.
def writeOpts():
  return (noLog, errLog, noneLog, inflLog, chgLog, allLog)
def reactOpts():
  return (noLog, chgLog, allLog)



class logging:
  def __init__(self, readNum, writeNum, reactNum, db, withStdOut=False):
    self.read = readOpts()[readNum]()
    self.read.db = db
    self.read.logVar = 'inVal'
    self.read.lastLogVar = 'lastInVal'
    self.read.chgSkip = 'chgInSkipped'
    self.read.withStdOut = withStdOut
    self.write = writeOpts()[writeNum]()
    self.write.db = db
    self.write.logVar = 'outVal'
    self.write.lastLogVar = 'lastOutVal'
    self.write.chgSkip = 'chgOutSkipped'
    self.write.withStdOut = withStdOut
    self.react = reactOpts()[reactNum]()
    self.react.db = db
    self.react.logVar = 'outVal'
    self.react.lastLogVar = 'lastOutVal'
    self.react.chgSkip = 'chsOutSkipped'
    self.react.withStdOut = withStdOut


class logType:
  def get(self,varname):
    return getattr(self,varname)

  def getListNum(self):
    for i, l in enumerate(listOpts()):
      if type(l()) == type(self):
        return i

  def doLog(self, c=None, errMsg=None):
    return None

  
  def save(self, c, logTime=False):
    s = 'INSERT INTO AQdata (dataDate, logType, dataVal, dataTimebase, dataChannel) VALUES (?, ?, ?, ?, ?)'
    if not logTime:
      logTime = int(datetime.now(UTC).timestamp())
      
    if getattr(c, self.chgSkip):
      #Saving both the previous value and the current value. This makes it evident how long the previous value held before this value arrived.
      v = (logTime-1, self.logType, c.getLogVal(self.lastLogVar), None, c.rowid)
      modify_db(s, v, logUpdate=False, db=self.db)

    v = (logTime, self.logType, c.getLogVal(self.logVar), self.timebase, c.rowid)
    modify_db(s, v, logUpdate=False, db=self.db)
    if self.withStdOut: print('Chan ' + str(c.rowid) + ' value: ' + str(c.getLogVal(self.logVar)))
    #Decide if we are saving with or without error.
    if c.withErr:
      self.logError(c.errStr, c=c.rowid)

    return


  def logError(self, errStr, c=None, d=None):
    if self.logType == -1: return
    i = (int(datetime.now(UTC).timestamp()), c, d, errStr)
    modify_db('INSERT INTO AQlog (logDate, assocChan, assocDev, logEntry) VALUES (?, ?, ?, ?)', i, logUpdate=False, db=self.db)
    if self.withStdOut: print(errStr)

    return


  def logComment(self, cmtStr, c=None, d=None):
    #Same functionality as logErr, but marks the entry as read so it doesn't show up as a new entry that has to be read.
    if self.logType == -1: return
    i = (int(datetime.now(UTC).timestamp()), c, d, cmtStr, 1)
    modify_db('INSERT INTO AQlog (logDate, assocChan, assocDev, logEntry, entryRead) VALUES (?, ?, ?, ?, ?)', i, logUpdate=False, db=self.db)
    if self.withStdOut: print(cmtStr)

    return


class noLog(logType):
  name='none'
  logType=-1

class errLog(logType):
  name='errors'
  logType=0

  def doLog(self, c):
    if c.withErr: #Only log if channel is flagged as withErr
      self.save(c)

    return
      

class allLog(logType):
  name='everything'
  logType=3

  def doLog(self, c):
    #Log every time this is called. Only log comments if our value is None.
    self.save(c)
    return
    

class chgLog(logType):
  name='changes'
  logType=2

  def doLog(self, c):
    val = getattr(c, self.logVar)
    #Only log if val is different from lastVal.
    if val != getattr(c, self.lastLogVar):
      self.save(c) #Within here, 
      setattr(c, self.chgSkip, False)
    else:
      setattr(c, self.chgSkip, True) #Mark that we've skipped logging at least once.

    return

class noneLog(logType):
  name='only none values'
  logType=1

  def doLog(self, c):
    val = getattr(c, self.logVar)
    #Only log if val is None
    if val is None:
      self.save(c)

    return

class inflLog(logType):
  name='Function Points'
  logType=4

  def doLog(self, c):
    #Log a None if this is a new channel.
    newLog = False
    if c.lastFnVal is None:
      #Don't log None values
      return

    if c.lastLogVal is None:
      self.logVar = 'lastLogVal'
      self.save(c)
      c.lastLogVal = c.lastFnVal
      newLog = True

    #This is a special exception to the values to use, so overwrite self.logVar
    self.logVar = 'lastFnVal'
      
    #Log if the point x value has changed.
    if c.lastFnVal['x'] > c.lastLogVal['x'] or newLog:
      c.lastLogVal = c.lastFnVal
      self.save(c)

    return
