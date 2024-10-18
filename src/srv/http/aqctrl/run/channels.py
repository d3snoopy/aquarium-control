from aqctrl.run.helpers import adjVal, doInterp, cToF, findXscale
from datetime import datetime, UTC
from aqctrl.run.db import query_db


#import sys
import time
#tl = 0
#tc = 0

#Note: with decision not to automatically call None and min/max errors,
# I think self.withErr is pretty much always going to stay False.
# Leaving it here for future use.

class aqChan:
  def get(self,varname):
    return getattr(self,varname)

  def __init__(self, c):
    self.rowid = c['rowid']
    self.devnum = c['chDevice']
    self.ident = c['AQident']
    self.name = c['AQname']
    self.active = c['chActive']
    self.variable = c['chVariable']
    self.isInput = c['chInput']
    self.isOutput = c['chOutput']
    self.invert = c['chInvert']
    self.max = c['chMax']
    self.min = c['chMin']
    self.initVal = c['chInitialVal'] #This is also treated as a default value and value when inactive.
    self.scale = c['chScalingFactor']
    self.adjust = c['chLevelCtl']
    self.color = c['chColor']
    self.tempType = c['typeTemp']
    self.limPts = 10000
    self.scaleName = 'Seconds'
    self.chgInSkipped = False #This is to handle change logging when every point changes.
    self.chgOutSkipped = False #Same.
    self.hideInvert = c['hideInvert']
    self.sources = set()
    self.reactProf = None
    self.NERProf = None
    self.dataPts = list()
    self.lastInVal = False #Making this distinct from inVal so an initial error triggers a reaction.
    self.inVal = None
    self.errStr = str()
    self.withErr = False
    self.valCalc = False #Note: this keeps track of whether the default value should be used or not.
    self.reactOverride = False #Use this to track if a reaction has overridden.
    self.outVal = None
    self.lastOutVal = None

    return


  def getOut(self, dev=False, logging=None, defaults=False):
    #First, get our value and apply channel-level adjust.
    self.withErr = False
    self.shiftVals()
    self.outVal = self.getCalc()
    if self.active and not defaults and self.outVal is not None:
      v = self.outVal
    else:
      v = self.testAdj(self.initVal)

    #Now, test for device-level adjust.
    if dev:
      ret = adjVal(v, dev.Min, dev.Max, dev.Scale, dev.Inv, True)
      v = ret[0]
      #Decided not to log min/max as notable.
      #if ret[1]:
      #  self.errStr += 'device beyond min/max.'
      #  self.withErr = True

    if logging is not None:
        logging.doLog(self)

    return v


  def pushIn(self, v, devErr=False, logging=None):
    self.withErr = False
    #if v is None:
    #  self.withErr = True

    #if devErr:
    #  self.errStr = devErr

    self.lastInVal = self.inVal
    self.inVal = self.testAdj(v)

    if logging is not None:
       logging.doLog(self)

    return


  def getLogVal(self, var):
    a = getattr(self, var)
    if a is None: return a
    if self.hideInvert and self.invert:
      #need to re-invert the logged value.
      return -a + self.max + self.min
    else:
      return a


  def testAdj(self, inVal):
    #Returns [The output value, bool was the value beyond min/max?]
    self.withErr = False
    if not self.adjust:
      return inVal

    #We are adjusting the value.
    r = adjVal(inVal, self.min, self.max, self.scale, self.invert, self.variable)
    #Note: decided the min/max adjustment is not notable.
    #if r[1]:
    #  self.errStr += ', chan beyond min/max.'
    #  self.withErr = True
    
    return r[0]


  def shiftVals(self):
    #If this is an output channel, Shift our values, and zero out the value.
    self.lastOutVal = self.outVal
    self.outVal = 0
    self.valCalc = False
    return

  
  def getCalc(self, start=None, end=None, tShift=0, Tempconv=False, scaleX=1):
    #Function to get calculated values for time range.
    #If start is not provided, it will calculate the value for now.
    #If only start is provided, it will return values for the next 24 hrs.
    if Tempconv and self.tempType:
      self.units = '°F'
    elif self.tempType:
      self.units = '°C'

    single = False
    if start is None: 
      start = datetime.now(UTC).timestamp()-1 #Doing the minus one to try to fix interpolation issues with the first point.
      single = True

    if end is None:
      end = start+86400

    #If this channel is not a temptype, don't convert even if requested.
    if not self.tempType:
      Tempconv = False

    if not self.dataPts or not self.dataPts[0]['x'] <= start+tShift or not self.dataPts[-1]['x'] >= end+tShift:
      #We need to get some calculated data.
      self.calcPts(start, end, tShift=tShift, Tempconv=Tempconv, scaleX=scaleX)

    #Catch the None case.
    if self.dataPts is None or not self.dataPts:
      return None

    #We are only returning a single point.
    if single:
      c = 0
      while self.dataPts[c+1]['x'] < (start+tShift)*scaleX:
        c += 1

      return doInterp((start+tShift)*scaleX, self.dataPts[c], self.dataPts[c+1])

    #We're returning more than one point if we got here
    #Just return the dataPoints.
    return self.dataPts


  def getLog(self, db=None, start=None, end=None, tShift=0, Tempconv=False, scaleX=False):
    #Function to get log values for the time range.
    #If start and stop not provided, will return data for the last 24hours.
    if end is None: end = datetime.now(UTC).timestamp()
    if start is None: start = end-86400

    #Query the DB. Note: limiting number of returns.
    r = query_db('SELECT * FROM AQdata WHERE dataChannel = ? AND dataDate >= ? AND dataDate <= ? ORDER BY dataDate LIMIT ' + str(self.limPts), (self.rowid, start, end), db=db)

    scaleX, self.scaleName = findXscale(start, end, scaleX, self.scaleName)

    ret = list()
    if Tempconv and self.tempType:
      self.units = '°F'
      for i in r:
        y = cToF(i['dataVal'])
        ret.append({'x':(i['dataDate']+tShift)*scaleX, 'y':y})
    else:
      if self.tempType: self.units = '°C'
      for i in r:
        y = i['dataVal']
        ret.append({'x':(i['dataDate']+tShift)*scaleX, 'y':y})

    return ret


  def getCombo(self, db=None, start=None, end=None, tShift=0, Tempconv=False, scaleX=False):
    #function to get the combination of logged values (in the past) and calculated values (in the future)
    #If start/end not provided, will return the calculated value for now.
    if Tempconv and self.tempType:
      self.units = '°F'
    elif self.tempType:
      self.units = '°C'

    if start is None and end is None: return self.getCalc(tShift=tShift, Tempconv=Tempconv, scaleX=scaleX)
    if start is None: return self.getCombo(db=db, start=end-86400, end=end, tShift=tShift, Tempconv=Tempconv, scaleX=scaleX)
    if end is None: return self.getCombo(db=db, start=start, end=start+86400, tShift=tShift, Tempconv=Tempconv, scaleX=scaleX)

    #Ok, now we have both a start and an end.
    now = datetime.now(UTC).timestamp()
    if start >= now-1: #Changed to now-1 so we catch the latency of this running.
      #All calculated
      t1 = time.time()
      ret = self.getCalc(start=start, end=end, tShift=tShift, Tempconv=Tempconv, scaleX=scaleX)
      t2 = time.time()
      l1 = ', logtimeNA'
      l2 = ', calctime ' + str(t2-t1)
      return (ret, l1, l2)
     

    if end < now:
      #All logged
      t1 = time.time()
      ret = self.getLog(start=start, end=end, tShift=tShift, Tempconv=Tempconv, scaleX=scaleX)
      t2 = time.time()
      l1 = ', logtime ' + str(t2-t1)
      l2 = ', calctimeNA'
      return (ret, l1, l2)


    #Split of calc and log.
    scaleX, self.scaleName = findXscale(start, end, scaleX, self.scaleName)

    t1 = time.time()
    ret = self.getLog(db=db, start=start, end=now, tShift=tShift, Tempconv=Tempconv, scaleX=scaleX)
    t2 = time.time()
    a = self.getCalc(start=now, end=end, tShift=tShift, Tempconv=Tempconv, scaleX=scaleX)
    t3 = time.time()
    l1 = str(', logtime ' + str(t2-t1))
    l2 = str(', calctime ' + str(t3-t2))
    if a is not None:
      ret.extend(a)

    return (ret, l1, l2)


  def calcPts(self, start=None, end=None, withAdj=True, inclRct=True, tShift=0, Tempconv=False, scaleX=False):
    #Function to calculate both the react and source points for this channel.
    #If no start and stop provided, will calculate for from now to 24hrs from now.
    #Note: if we have received a start and an end, assume they are already shifted by tShift.
    if start is None: start = datetime.now(UTC).timestamp()
    if end is None: end = start+86400
    limPts = self.limPts

    #Get our X scale.
    scaleX, self.scaleName = findXscale(start, end, scaleX, self.scaleName)

    #First, handle the reaction portion.
    rctP = None

    #Need to handle reaction expiration.
    self.testReactExp(start)

    if self.reactProf and inclRct:
      p = self.reactProf
      if self.reactProf.expire:
        thisEnd = p.end
      else:
        thisEnd = end

      rctP = p.getPoints(start, thisEnd, limPts=limPts, tOff=tShift) #Do the thisEnd so we catch expiration.


      if self.reactProf.behave == 0:
        #Reaction replaces. Calc reaction points and return.
        if withAdj:
          self.dataPts = list()
          for pr in rctP:
            if Tempconv: #Convert these react points if requested.
              self.dataPts.append({'x':pr['x']*scaleX, 'y':cToF(self.testAdj(pr['y']))})
            else:
              self.dataPts.append({'x':pr['x']*scaleX, 'y':self.testAdj(pr['y'])})
        else:
          if Tempconv: #Convert these react points if requested.
            self.dataPts = list()
            for pr in rctP:
              self.dataPts.append({'x':pr['x']*scaleX, 'y':cToF(pr['y'])})
          else:
            for pr in rctP:
              self.dataPts.append({'x':pr['x']*scaleX, 'y':pr['y']})

        return end#Returns here if reaction replaces.

    #Second, assemble all of the points for all of our sources. CPS shift is already done.
    srcPts = list()
    tPts = set()
    c = list()
    rc = 0
    for s in self.sources:
      a = s.getPoints(self.rowid, start, end, limPts=limPts, tShift=tShift, Tempconv=Tempconv)
      #Catch the case where something returns as None.
      if a is None:
        self.dataPts = None
        return end

      srcPts.append(a)
      c.append(0)
      #Agregate time points to an overall set.
      for i in a:
        tPts.add(i['x'])

    #Also, add in times from rctP.
    if rctP:
      for i in rctP:
        tPts.add(i['x'])

      #Add one more point if the reaction profile ends before end.
      #This accounts for the reaction profile expiring, and going back to no reaction after.
      if rctP[-1]['x'] < end:
        tPts.add(rctP[-1]['x']+.001)

    #Convert and sort our tPts.
    tPts = list(tPts)
    tPts.sort()

    #Limit the number of points to keep computing time reasonable.
    if len(tPts)>=limPts:
      tPts = tPts[:limPts]
      end = tPts[-1]

    #Go through each source, adding in values.
    self.dataPts = list()
    for t in tPts:
      v = 0
      for i, s in enumerate(srcPts):
        #Increment c as necessary to get our interp right.
        while s[c[i]+1]['x'] < t: #I don't think this will ever exceed the list length.
          c[i] += 1

        v += doInterp(t, s[c[i]], s[c[i]+1])

      #Add reaction if it adds.
      if rctP:
        if rc < len(rctP)-1: #Once we're past our rctP points, the react no longer adds. (it's marked to expire)
          while rctP[rc+1]['x'] < t and rc < len(rctP)-2: #Align our points.
            rc += 1

          #Make sure our point is still before expire.
          if t <= rctP[rc+1]['x']: v += doInterp(t, rctP[rc], rctP[rc+1])


      #All of the source values have been added in.
      if withAdj:
        v = self.testAdj(v)

      if Tempconv:
        self.dataPts.append({'x':t*scaleX, 'y':cToF(v)})
      else:
        self.dataPts.append({'x':t*scaleX, 'y':v})

    return end


  def replaceReact(self, p=None):
    #Need to hand this a profile object of the new profile.
    if p:
      #Only do the special keeping track if new is expire and old isn't
      if self.reactProf and not self.reactProf.expire and p.expire:
        #Need to store the old non-expiring, and replace with the new expiring.
        self.NERProf = self.reactProf
        self.reactProf = p
      else:
        self.reactProf = p

      self.dataPts = list()
    return
      

  def testReactExp(self, start):
    if self.reactProf and self.reactProf.expire and self.reactProf.end < start:
      if self.NERProf:
        self.reactProf = self.NERProf
        self.NERProf = None
      else:
        self.reactProf = None

    return
