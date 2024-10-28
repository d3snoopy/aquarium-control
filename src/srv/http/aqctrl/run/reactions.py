from aqctrl.run.functions import profile
from datetime import datetime, UTC
#from aqctrl.aqctrl import app
#import sys


def listTypes():
  return (errType, gtType, ltType) #Add more operators if desired. Order matters, so make sure not to re-order. Saved in the DB based on order.
def listBehave():
  return ('replace scheduled values', 'modify scheduled values')  #Add more operators if desired. Order matters, so make sure not to re-order. Saved in the DB based on order.
def listDetect():
  return ('edge detect', 'level detect')


class reactBase:
  def get(self,varname):
    return getattr(self,varname)


class reactType(reactBase):
  def getListNum(self):
    for i, l in enumerate(listOpts()):
      if type(l()) == type(self):
        return i

  def test(self, monVal, trigVal=0):
    return None


class errType(reactType):
  name='error'
  valActive = False
  def test(self, monVal, trigVal=0):
    if monVal is None:
      #print('Reaction Error is True')
      return True
    return False

class gtType(reactType):
  name='greater than'
  valActive = True
  def test(self, monVal, trigVal=0):
    if monVal is not None and trigVal is not None and monVal > trigVal:
      #print('Reaction GT is True')
      return True
    return False

class ltType(reactType):
  name='less than'
  valActive = True
  def test(self, monVal, trigVal=0):
    if monVal is not None and trigVal is not None and monVal < trigVal:
      #print('Reaction LT is True')
      return True
    return False


class reaction(reactBase):
  def __init__(self, r):
    if r['criteriaType'] is None:
      self.criteriaType = False
    else:
      self.criteriaType = listTypes()[r['criteriaType']]()

    self.trigVal = r['triggerValue']
    self.scale = r['rctScale']
    self.offset = r['rctOffset']
    self.duration = r['rctDuration']
    self.expire = r['willExpire']
    self.monChan = None
    self.function = None
    self.new = True

  def test(self, detType=0):
    if detType or self.new:
      #Level detection.
      self.new = False
      return self.criteriaType.test(self.monChan.inVal, self.trigVal)
    else:
      #Edge detection.
      now = self.criteriaType.test(self.monChan.inVal, self.trigVal)
      before = self.criteriaType.test(self.monChan.lastInVal, self.trigVal)
      return (now and not before)


class reactGroup(reactBase):
  def __init__(self, r, withStdOut=False):
    self.name = r['AQname']
    self.behave = r['grpBehave']
    self.detType = r['grpDetect']
    self.expire = False
    self.reactions = list()
    self.withStdOut = withStdOut


  def process(self, logging=None):
    #Need to process this group to see about creating a profile.
    for r in self.reactions:
      if r.test(self.detType):
        now = int(datetime.now(UTC).timestamp())
        #We DO need to react by creating a profile.
        #Note: if multiple reactions return a True, only the last will stay!
        #Create a dictionary of our desired values to hand to the init function.
        p = dict()
        now = int(datetime.now(UTC).timestamp())
        p['profStart'] = now+r.offset
        p['profEnd'] = now+r.duration+r.offset
        p['profRefresh'] = False
        thisP = profile(p)
        thisP.expire = r.expire
        thisP.function = r.function
        thisP.scale = r.scale
        thisP.behave = self.behave
        if self.outChan.reactProf is None or not self.outChan.reactProf.lockout:
          self.outChan.replaceReact(thisP)
          if logging:
            logging.logError('Created a new reaction on ' + r.criteriaType.name + ' test of channel ' + self.outChan.name + '.', self.outChan.rowid)
          return True #return true so we can trigger a write.

    else:
      if logging:
        if logging.logType == 3:
          logging.logError('Teasted reaction ' + r.criteriaType.name + '.', self.outChan.rowid)
    return False
