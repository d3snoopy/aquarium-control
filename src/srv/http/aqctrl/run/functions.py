import sys

from aqctrl.run.helpers import doInterp, cToF


class fnBase:
  def get(self,varname):
    return getattr(self,varname)


class aqFunction(fnBase):
  def __init__(self, f):
    self.name = f['AQname']
    self.rowid = f['rowid']
    self.points = list()
    return


class point(fnBase):
  def __init__(self, p):
    self.value = p['ptValue']
    self.tPct = p['timePct']
    self.tOffset = p['timeOffset']
    self.timeSE = p['timeSE']
    return


class source(fnBase):
  def __init__(self, s):
    self.scale = s['srcScale']
    self.name = s['AQname']
    self.profiles = dict()
    self.chans = set()

  
  def getPoints(self, chanID, start, end, limPts=10000, tShift=0, Tempconv=False):
    #Go through each profile, getting points so we can agregate time points.
    #Swap start/end if necessary.
    #If tShift is non-zero, start and end will NOT be shifted, and the profile points need to be shifted.
    if start > end:
      a = start
      start = end
      end = a

    #In this process, we will also figure out what the max and min time points are.
    tMin = start
    tMax = end
    for p in self.profiles.values():
      if chanID not in p.CPS:
        continue

      tMin = min(start, start-p.CPS[chanID][0]) #minus is right.
      tMax = max(end, end-p.CPS[chanID][0]) #minus is right.

    
    #We have an agregate min/max time. Now, get points for each profile in this range.
    #Note that the agregate min/max is pre-tShift.
    profPts = list()
    tPts = set()
    c = list()
    adj = list()
    for p in self.profiles.values():
      tOff = p.CPS[chanID][0]+tShift
      a = p.getPoints(tMin, tMax, tOff, limPts=limPts) #This will return profile points, with time points shifted.
      #Catch the case where something returns as none.
      if a is None:
        return None

      #Need to aggregate our points to an overall set.
      for i in a: #New method, now that we have dictionaries. Also, not using y at all???
        tPts.add(i['x'])

      #Keep track of these profile points.
      profPts.append(a)
      #Start a counter for these profile points.
      c.append(0)
      adj.append(p.CPS[chanID][1])

    #Convert and sort our tPts.
    #If I did above correctly, start through end should already be covered.
    tPts.add(start+tShift)
    tPts.add(end+tShift) #Make sure we have tPts entries for start and end.
    tPts = list(tPts)
    tPts.sort()

    #Test to see if we have reached our number of points limit.
    if len(tPts)>=limPts:
      tPts = tPts[:limPts]
      end = tPts[-1]

    #Now, run through our tPts and calculate profile values.
    prs = list()
    started = False
    for t in tPts:
      if t < start+tShift:
        continue

      if t > end+tShift:
        #Since we guaranteed that one of the points is end, just break here without checking.
        break

      v = self.scale

      for i, p in enumerate(profPts):
        #Increment c as necessary to get our interp right.
        while p[c[i]+1]['x'] < t:
          c[i] += 1

        v *= doInterp(t, p[c[i]], p[c[i]+1])*adj[i]

      #All of the profile values have been multiplied in.
      if Tempconv:
        v = aqctrl.run.helpers.cToF(v)

      prs.append({'x':t, 'y':v})

    #print(prs)
    return prs


class profile(fnBase):
  def __init__(self, p):
    self.start = p['profStart']
    self.end = p['profEnd']
    self.refresh = p['profRefresh']
    self.CPS = dict()
    self.function = None
    self.points = list()
    self.expire = False
    self.lockout = False
    self.behave = 0


  def doRefresh(self, t):
    if not self.refresh: return False
    n = int((t-self.start)/self.refresh)
    if t < self.start: n -= 1 #Handle backwards refresh correctly.
    self.start += n*self.refresh
    self.end += n*self.refresh
    return bool(n)


  def getPairs(self):
    #function to get the (time, value) pairs for this profile.
    if self.function is None: return None

    #get the values.
    l = list()
    for p in self.function.points:
      pct = (self.end-self.start)*p.tPct/100
      if p.timeSE:
        t = self.end+p.tOffset+pct
      else:
        t = self.start+p.tOffset+pct

      l.append({'x': t, 'y': p.value})

    #sort the list and return.i
    return(sorted(l, key=lambda d: d['x']))


  def getPoints(self, start, end, tOff = 0, limPts=10000):
    #Function to return this profile's time points within a given range.
    #Swap start/end if necessary.
    if start > end:
      a = start
      start = end
      end = a

    #Run refresh to align to start.
    self.doRefresh(start)
    prs = self.getPairs()

    #Catch case where we get no pairs.
    if not prs:
      return None

    #Also, catch the case where there is a single point.
    if len(prs) == 1:
      return [{'x':start+tOff, 'y':prs[0]['y']}, {'x':end+tOff, 'y':prs[0]['y']}]

    #We have more than one point in our pairs.
    c = 0
    started = False
    while True:
      if not started:
        #Need to seed the start value, with interpolation.
        #Test to make sure we're not past the size of our list of pairs.
        if c >= len(prs)-1:
          #First point is beyond the current end.
          self.points = [{'x':start+tOff, 'y':prs[-1]['y']}]
          started = True
          c += 1
        elif prs[c]['x'] == start:
          #Start with the first point.
          self.points = [{'x':start+tOff, 'y':prs[c]['y']}]
          c += 1
          started = True
          continue
        elif prs[c]['x'] < start and prs[c+1]['x'] > start:
          #Interp between these.
          self.points = [{'x':start+tOff, 'y':doInterp(start, prs[c], prs[c+1])}]
          c += 1
          started = True
          continue
        elif prs[c]['x'] > start:
          #start time is smaller than the first point, so add just the first point.
          self.points = [{'x':start+tOff, 'y':prs[c]['y']}]
          started = True
          continue
        else:
          #Need to increment.
          c += 1
          continue

      #Done handling start.
      #Test if we're past our pairs list.
      if c == len(prs):
        #We've reached the end of our pairs list.
        #See if refreshing will help.
        if self.refresh:
          oldPr = prs[-1]
          self.start += self.refresh
          self.end += self.refresh
          prs = self.getPairs()
          c = 0
          if prs[0]['x'] < end: self.points.append({'x':prs[0]['x']-.001+tOff, 'y':oldPr['y']}) #Do this to make the endpoint hold until refresh
          continue
        else:
          #No refresh configured. Just add the end point and return.
          self.points.append({'x':end+tOff, 'y':prs[-1]['y']})
          return self.points
     
      #Need to handle the case where refresh happens before the end of the previous profile.
      if self.refresh:
        if self.start + self.refresh <= prs[c]['x']:
          #Last cycle didn't end yet, but need to refresh.
          #Make sure start + refresh is not beyond end
          if self.start + self.refresh < end:
            #Need to add a point and do the refresh.
            oldp0 = prs[c-1]
            oldp1 = prs[c]
            self.start += self.refresh
            self.end += self.refresh
            prs = self.getPairs()
            c = 0
            newt = prs[0]['x']-.001
            self.points.append({'x':newt+tOff, 'y':doInterp(newt, oldp0, oldp1)}) #Do this to make the endpoint hold until refresh
            continue

 
      #Test if we're up to the end.
      if prs[c]['x'] >= end:
        #Need to add the end and return.
        self.points.append({'x':end+tOff, 'y':doInterp(end, prs[c-1], prs[c])})
        return self.points

      #Test if we reached out points limit.
      if len(self.points)>=limPts:
        return self.points

      #If we got here, just add the next pair point to the list.
      self.points.append({'x':prs[c]['x']+tOff, 'y':prs[c]['y']})
      c += 1

    #I *think* we never actually get to this return.
    return
