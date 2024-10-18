import aqctrl.run.busses as busses
from aqctrl.run.db import query_db, modify_db
from os import path
from aqctrl.run.helpers import adjVal
try:
  import RPi.GPIO as RPIGPIO
  #from gpiozero import LED
except ModuleNotFoundError:
  RPIGPIO = False
  
#from aqctrl.aqctrl import app
#import sys
#import time

def listOpts():
  return (TLC59711, TLC5947, TSL2591, DS18B20, GPIO, VirtDev) #Add more devices as we program more. Order matters, so append new devices to the end of the list or you might mess up DB associations.

# Make sure the name for each device is unique, otherwise you might get undefined behavior.


class aqDevice:
  name='generic'
  inDev=False
  outDev=False
  channels=dict()
  grpDef=dict()

  def get(self,varname):
    return getattr(self,varname)

  def __init__(self, d=None):
    if d is not None:
      self.rowid = d['rowid']
      self.Scale = d['scaleFactor']
      self.Max = d['maxVal']
      self.Min = d['minVal']
      self.Inv = d['invert']
      self.Addr = d['busAddr']
    return


  def initChans(self, rowid):
    #Delete any channels that might exist for this device.
    modify_db('DELETE FROM AQchannel WHERE chDevice=?', (rowid, ))

    #Create the default channel type if it doesn't already exist.
    grpID = 0
    if self.grpDef:
      r = 'SELECT * FROM chanType WHERE '
      v = list()
      for k, val in self.grpDef.items():
        r += k + '=? AND '
        v.append(val)

      r = r[:-5]
      a = query_db(r, v)
      for i in a:
        grpID = i['rowid']
      

    if not grpID:
      if not self.grpDef:
        #Need to create a new channel type with defaults.
        r = 'INSERT INTO chanType DEFAULT VALUES'
        modify_db(r, tuple())
      else:
        #Create a new channel type with our provided values.
        r1 = 'INSERT INTO chanType ('
        r2 = ') VALUES ('
        v = list()
        for k, val in self.grpDef.items():
          r1 += k + ', '
          r2 += '?, '
          v.append(val)

        r = r1[:-2] + r2[:-2] + ')'
        modify_db(r, v)

      grpID = query_db('SELECT last_insert_rowid()', one=True)['last_insert_rowid()']


    #Add the correct number of new channels.
    myQuery = 'INSERT INTO AQchannel (AQname, chActive, chDevice, chType) VALUES '
    myValues = list()

    for i in range(self.numChan):
      myQuery += '(?, ?, ?, ?), '
      myValues.extend([self.name+'_ch'+str(i), 1, rowid, grpID])

    myQuery = myQuery[:-2]
    #print(myQuery, file=sys.stderr)
    #print(myValues, file=sys.stderr)
    modify_db(myQuery, myValues)

    return

  def getListNum(self):
    for i, l in enumerate(listOpts()):
      if type(l()) == type(self):
        return i


  def read(self, p, logging=None):
    #Need to override this method for each unique type of device.
    return self.inDev

  def write(self, logging=None, defaults=False):
    #Need to override this method for each unique type of device.
    return self.outDev


class TLC59711(aqDevice):
  name='TLC59711'
  defscale=65535
  defmax=65535
  defmin=0
  definv=0
  inDev=False
  outDev=True
  numChan=12
  busType=busses.SPI
  channels=dict()

  grpDef = {'chTypeName':'Light', 'chInput':0}


  def write(self, logging=None, defaults=False):
    #queue up our string for the device.
    WC = '100101'
    CM = '10010' #OUTTMG=1, EXTGCK=0, TMGRST=0, DSPRPT=1, BLANK=0
    BC = '1111111' #Maximum output for the BC data

    s = WC+CM+BC+BC+BC #This is everything up to the channel values
    n = int(s, 2)
    n = hex(n)[2:]
    
    #Now, need to assemble the data from each channel
    for c in self.channels.values():
      #c.errStr = 'Chan ' + str(c.rowid) + ' write: '
      #Do the channel test.
      #t0= time.time()
      v = int(c.getOut(self, logging, defaults=defaults))
      #t1= time.time()
      #print('getout timing: ' + str(t1-t0))

      n += f"{v:#0{6}x}"[2:] #this adds 4 hex digits to our string
    
    #print(n)
    return n


class TLC5947(aqDevice):
  name='TLC5947'
  defscale=4095
  defmax=4095
  defmin=0
  definv=0
  inDev=False
  outDev=True
  numChan=24
  busType=busses.SPI

  grpDef = {'chTypeName':'Light', 'chInput':0}


  def write(self, logging=None, defaults=False):
    #queue up our string for the device.
    n = ''
    
    #Now, need to assemble the data from each channel
    for c in self.channels.values():
      #c.errStr = 'Chan ' + str(c.rowid) + ' write: '
      #Do the channel test.
      v = int(c.getOut(self, logging, defaults=defaults))
  
      n += f"{v:#0{5}x}"[2:] #this adds 3 hex digits to our string

    #print(n)
    return n


class GPIO(aqDevice):
  name='GPIO Output'
  defscale=1
  defmax=1
  defmin=0
  definv=0
  inDev=False
  outDev=True
  numChan=1
  busType=busses.GPIO
  setup=False

  grpDef = {'chTypeName':'GPIO', 'chInput':0, 'chVariable':0}


  def write(self, logging=None, defaults=False):
    #Get the data for the channel. We should only have a single channel.
    for c in self.channels.values():
      #c.errStr = 'Chan ' + str(c.rowid) + ' write: '
      #Do the channel test, and return the value.
      v = int(c.getOut(self, logging, defaults=defaults))

      if RPIGPIO and self.Addr: #Allow for testing on non-rpi HW
        if not self.setup:
          RPIGPIO.setmode(RPIGPIO.BCM)
          RPIGPIO.setup(int(self.Addr), RPIGPIO.OUT)
          self.setup=True

        RPIGPIO.output(int(self.Addr), v)

    return



class TSL2591(aqDevice):
  name='TSL2591'
  defscale=1
  defmax=1
  defmin=0
  definv=0
  inDev=True
  outDev=False
  numChan=3
  busType=busses.I2C
  channels=dict()

  grpDef = {'chTypeName':'Light Sense', 'chLevelCtl':0, 'chOutput':0}
  

  def read(self, p, logging=None):
    if logging is not None: logging.logError('TSL2591 read not yet implemented', d=self.rowid)
    for c in self.channels.values():
      c.pushIn(None, logging=logging)
    return

class DS18B20(aqDevice):
  name='DS18B20'
  defscale=1/1000
  defmax=125
  defmin=-55
  definv=0
  inDev=True
  outDev=False
  numChan=1
  busType=busses.OneW
  channels=dict()

  grpDef = {'chTypeName':'Temperature', 'chLevelCtl':0, 'typeTemp':1, 'chVariable':1, 'chOutput':0}

  def read(self, p, logging=None):
    #See if our directory structure exists, and this device can be found.
    for c in self.channels.values(): #expect only one, but don't know what key to expect.
      if not c.active:
        return #The channel is marked as inactive.

      chid = self.Addr
      errStr = 'Error reading temp sensor: ' + chid

      if not path.exists(p + chid + '/w1_slave'):
        if logging is not None: logging.logError(errStr + ' file not found.', d=self.rowid)
        c.pushIn(None, logging=logging)
        continue

      with open(p + chid + '/w1_slave', 'r') as f:
        ret = f.readlines()

      if ret[0].strip()[-3:] != 'YES':
        if logging is not None: logging.logError(errStr + ' CRC not valid.', d=self.rowid)
        c.pushIn(None, logging=logging)
        continue
      
      r = ret[1].find('t=')
      if r == -1:
        if logging is not None: logging.logError(errStr + ' temperature value not read.', d=self.rowid)
        c.pushIn(None, logging=logging)
        continue

      v = ret[1][r+2:]
      #First, do the device-level adjust.
      dv = adjVal(float(v), self.Min, self.Max, self.Scale, self.Inv, True)
      c.pushIn(dv[0], logging=logging)
      #if dv[1]:
      #  c.pushIn(dv[0], errStr + ' read adjusted at device', logging)
      #else:
      #  c.pushIn(dv[0], logging=logging)

    return


class VirtDev(aqDevice):
  name='Virtual'
  defscale=1
  defmax=1
  defmin=0
  definv=0
  inDev=True
  outDev=True
  numChan=1
  busType=busses.virtBus
  channels=dict()

  grpDef = {'chTypeName':'Virtual', 'chLevelCtl':0}
  #Do more to add functions here!!!

  def read(self, p, logging=None):
    #Transfer the outVal to the inVal.
    for c in self.channels.values():
      #Should be just one.
      #If the value isn't calculated, make the response None.
      if not c.active: c.pushIn(None, logging=logging)
      else: c.pushIn(c.getOut(self), logging=logging)

    return

  def write(self, logging=None, defaults=False):
    for c in self.channels.values():
      return c.getOut(self, logging=logging, defaults=defaults)
