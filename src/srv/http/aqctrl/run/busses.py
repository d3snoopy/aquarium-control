from aqctrl.run.db import query_db, modify_db
from os.path import exists
import spidev
#from aqctrl.aqctrl import app
#import sys

def listOpts():
  return (SPI, I2C, OneW, GPIO, virtBus)

class aqBus:
  name = ''
  path = ''
  canDetect = False
  devOrdered = False
  devices = dict()
  allowedAddr = False
  def get(self,varname):
    return getattr(self,varname)

  def retOpts(self):
    inputIDs = (not self.canDetect) & self.devIDs
    return {'name':self.name, 'canDetect':self.canDetect, 'detected':self.detectDevs(), 'ordered':self.devOrdered, 'hasIDs':self.devIDs, 'InputIDs':inputIDs}

  def detectDevs(self):
    return False

  def getListNum(self):
    for i, l in enumerate(listOpts()):
      if type(l()) == type(self):
        return i

  def addNewDev(self, n=0):
    thisBusNum = self.getListNum()
    existingDev = query_db('SELECT busAddr,busOrder FROM hostDevice WHERE busType=?', (thisBusNum, ))
    knownID=list()
    maxOrder=0
    for d in existingDev:
      knownID.append(d['busAddr'])
      maxOrder=max(maxOrder, int(d['busOrder'])+1)

    myQuery = 'INSERT INTO hostDevice (busType, devType, busOrder, busAddr) VALUES '
    myVals = list()
    detDevs = self.detectDevs()
    newFound=False
    if self.canDetect and detDevs['numDet']:
      for i, d in enumerate(detDevs['detIDs']):
        if d not in knownID:
          newFound=True
          myQuery += '(?, ?, ?, ?), '
          typeNum = detDevs['detTypes'][i]().getListNum()
          myVals.extend([thisBusNum, typeNum, maxOrder, d])
          maxOrder += 1

    elif not self.canDetect:
      for i in range(maxOrder,maxOrder+n):
        newFound=True
        myQuery += '(?, ?, ?, ?), '
        import aqctrl.run.devices as devices
        typeNum=None
        for k in devices.listOpts():
            #print(type(k().busType()), file=sys.stderr)
            #print(type(self), file=sys.stderr)
            if type(k().busType()) == type(self):
              typeNum=k().getListNum()
              break
        myVals.extend([thisBusNum, typeNum, i, None]) 

    if newFound:
      myQuery = myQuery[:-2]
      modify_db(myQuery, myVals)

    return
    
    
  def read(self, logging=None):
    #Method to read the data from our devices.
    for dev in self.devices.values():
      dev.read(self.path, logging)

    return


  def write(self, logging=None, defaults=False):
    #Method to write the data to our devices.
    for dev in self.devices.values():
      dev.write(logging, defaults)

    return


class SPI(aqBus):
  name='SPI'
  canDetect = False
  devOrdered = True
  devIDs = False
  devices = dict()
  #path = '/dev/spidev0.0'

  def write(self, logging=None, defaults=False):
    outStr = ''
    #Note: the populated devices should already be in the correct order.
    for dev in self.devices.values():
      #Expects a string of hex values (with no leading 0x) from each device.
      #print(outStr)
      #print(dev.write(logging))
      outStr += dev.write(logging, defaults=defaults)

    #Now, write to the bus.
    if logging is not None:
      if logging.logType == 3: logging.logError('SPI bus output: ' + outStr)
      elif logging.withStdOut: print('SPI bus output: ' + outStr)
    try:
      spi = spidev.SpiDev()
      spi.open(0, 0)
      spi.xfer(bytes.fromhex(outStr), 10000000)
      spi.close()
      #print('SPI Write!!!!!') #TODO
    except:
      logging.logError('Bus Error: ' + self.name + ' write failed.')

    return


class I2C(aqBus):
  name='I2C'
  canDetect = False #Not sure if this is a yes?
  devOrdered = False
  devIDs = True
  devices = dict()
  path = ''

  def detectDevs(self):
    return False #Maybe change if it can.

class OneW(aqBus):
  name='1W'
  canDetect = True
  devOrdered = False
  devIDs = True
  devices = dict()
  path = '/sys/bus/w1/devices/'

  def detectDevs(self):
    import aqctrl.run.devices as devices
    t = devices.listOpts()[3]
    if exists(self.path + 'w1_bus_master1/w1_master_slaves'):
      with open(self.path + 'w1_bus_master1/w1_master_slaves', 'r') as infile:
        myRead = infile.read()

      detDevs = myRead.split('\n')[:-1]
      numRet = len(detDevs)
      detTypes = [t]*numRet
      return {'numDet':numRet, 'detIDs':detDevs, 'detTypes':detTypes}

    else:
      myExample = ['idnum1', 'idnum2', 'idnum3', 'idnum4', 'idnum5', 'idnum6']
      myTypes= [t, t, t, t, t, t]
      import random
      numRet = random.randrange(6)
      return {'numDet':numRet, 'detIDs':myExample[:numRet], 'detTypes':myTypes[:numRet]}

  def read(self, logging=None):
    #Method to read the data from our devices.
    for dev in self.devices.values():
      dev.read(self.path, logging)

    return

class GPIO(aqBus):
  name='GPIO'
  canDetect = False
  devOrdered = False
  devIDs = True
  devices = dict()
  allowedAddr = ('5', '6', '12', '13', '16', '17', '22', '23', '24', '25', '26', '27')

  #Send for each device.
  def write(self, logging=None, defaults=False):
    for dev in self.devices.values():
      try:
        dev.write(logging, defaults=defaults)
      except:
        logging.logError('GPIO Error: ' + dev.name + ' write failed.')

    return


class virtBus(aqBus):
  name='Virtual'
  canDetect = False #Not sure if this is a yes?
  devOrdered = False
  devIDs = False
  devices = dict()

  def detectDevs(self):
    return False #Maybe change if it can.

