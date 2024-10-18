import sys
from aqctrl.run.db import query_db, modify_db
from aqctrl.aqctrl import app
from aqctrl.run import channels, functions, helpers, host
#from matplotlib.figure import Figure
#from io import BytesIO
#import matplotlib
from datetime import datetime, UTC
#matplotlib.use('Agg')
#import matplotlib.style as mplstyle
#import matplotlib.pyplot as plt
#imgPath = 'aqctrl/static/'
import time


def fnPlot(fn):
  #fn should be the rowid of the function to plot.
  with app.app_context():
    fnstr = 'SELECT * from AQfunction WHERE rowid = ?'
    ptstr = 'SELECT * from FnPoint WHERE parentFn = ?'

    dbFunct = query_db(fnstr, fn)
    dbPts = query_db(ptstr, fn)

    #Create function objects.
    pctpad = 2 #% to pad the length to make the function plot nicely.

    for f in dbFunct:
      funct = functions.aqFunction(f)

    #Add points to functions.
    for p in dbPts:
      funct.points.append(functions.point(p))

    #Now, go through each one and generate the plots.
    fPts = dict()
    maxPct = 0
    minPct = 0
    maxOff = 0
    minOff = 0
    for p in funct.points:
      maxPct = max(maxPct, p.tPct)
      minPct = min(minPct, p.tPct)
      maxOff = max(maxOff, p.tOffset)
      minOff = min(minOff, p.tOffset)

    Offsets = maxOff - minOff
    Pcts = max((maxPct - minPct), pctpad)
    tDelta = (1+Offsets)*Pcts

    p = functions.profile({'profStart':0, 'profEnd':tDelta, 'profRefresh':0})
    p.function = funct
    fPts = p.getPairs()

  return fPts




def srcPlot(src):
  with app.app_context():
    #src should be a single source rowid to plot.
    chans = getSrcChan(src)

    now = datetime.now(UTC).timestamp()
    #imgName = 'source'+str(src)+'.png'
    return chanPlot(chans.keys(), c=chans, start=now, end=now+86400)


def chanPlot(c1, c2=False, start=None, end=None, c=None, limPts=100000):
  t0 = time.time()
  with app.app_context():
    #Function to create a plot based on the channels provided.
    #Note that c1 and c2 can be a list or a single entry.
    if not hasattr(c1, '__iter__'): c1 = [c1]
    if c2 and not hasattr(c2, '__iter__'): c2 = [c2]
    if c2:
      ct = c1 + c2
    else:
      ct = c1

    now = datetime.now(UTC).timestamp()
    if start is None: start = now
    if end is None: end = start+86400
    #Get the DB info and create a few objects.
    if c is None:
      chans = getChans(ct)
    else:
      chans = c

    #Swap start/end if applicable.
    if start > end:
      a = start
      start = end
      end = a

    #Find out if our host is set to faranheit units.
    dbHost = query_db('SELECT tempUnits FROM AQhost', one=True)

    scaleX, scaleName = helpers.findXscale(start, end, True)

    timingLog = 'Setup time: ' + str(time.time()-t0)
    #Find out if we are pulling log data - if so, do it all in one big query.
    logData = dict()
    if start <= now-1:
      #Get all applicable log data in a single query.
      ct = list(ct)
      s2 = '?, '
      s1 = 'SELECT dataDate, dataVal, dataChannel FROM AQdata WHERE dataChannel IN ('
      s3 = ') AND dataDate >= ? AND dataDate <= ? ORDER BY dataChannel, dataDate LIMIT ' + str(limPts)
      dbData = query_db(s1 + (s2*len(ct))[:-2] + s3, ct+[start, min(end, now)])

      #Populate a convert index for us to use.
      tConv = dict()
      for c in chans.values(): #Note: this overwrites the c we got in the call; I think that's fine since we're done with it.
        logData[c.rowid] = list()

        #Special temperature units logic.
        if c.tempType:
          if dbHost['tempUnits']:
            tConv[c.rowid]=True
            c.units='°F'
          else:
            c.units='°C'
            tConv[c.rowid]=False
        else:
          tConv[c.rowid]=False

      timingLog += ', Log Query time: ' + str(time.time()-t0)
          
      #Run through our return, allocating the data out by channel.
      for d in dbData:
        if tConv[d['dataChannel']]:
          y=helpers.cToF(d['dataVal'])
        else:
          y=d['dataVal']

        logData[d['dataChannel']].append({'x':(d['dataDate']-now)*scaleX, 'y':y})

    timingLog += ', Post Log time: ' + str(time.time()-t0)
    #Now, get calc data if applicable.
    calcData = dict()
    if end >= now+1: #Note: this logic means if the request in within +/- 1 second of now, no return.
      #We're considering that if the user is asking for data within that 2-second gap, the request is nonsensical.
      for c in ct:
        #If we're hiding the invert, simply set invert to False here.
        if chans[c].hideInvert: chans[c].invert = False

        calcData[c] = chans[c].getCalc(max(start, now), end, -now, dbHost['tempUnits'], scaleX)
    
    timingLog += ', Post Calc time: ' + str(time.time()-t0)
    #Now, assemble the plot data.
    lines = dict()
    templ = list()
    for c in c1:
      c = int(c)
      l = dict()

      if logData and calcData:
        l['data'] = logData[c]+calcData[c]
      elif logData:
        l['data'] = logData[c]
      elif calcData:
        l['data'] = calcData[c]
      else:
        l['data'] = None
        
      l['name']=chans[c].name
      l['color']=chans[c].color
      l['units']=chans[c].units
      if l['data']: templ.append(l)

    if templ: lines[0] = templ
    templ = None

    if c2:
      templ=list()
      for c in c2:
        c = int(c)
        l = dict()

        if logData and calcData:
          l['data'] = logData[c]+calcData[c]
        elif logData:
          l['data'] = logData[c]
        elif calcData:
          l['data'] = calcData[c]
        else:
          l['data'] = None

        l['name']=chans[c].name
        l['color']=chans[c].color
        l['units']=chans[c].units
        if l['data']: templ.append(l)

    if templ: lines[1] = templ
    timingLog += ', Return time: ' + str(time.time()-t0) + '; '

    return lines, scaleName, timingLog
  




def getChans(chanList = False):
  s2 = '?, '
  if not chanList:
    dbChan = query_db('SELECT * FROM AQchannel LEFT JOIN chanType ON chanType.rowid = AQchannel.chType ORDER BY chDevice')
  else:
    s1 = 'SELECT * FROM AQchannel LEFT JOIN chanType ON chanType.rowid = AQchannel.chType WHERE AQchannel.rowid IN ('
    s3 = ') ORDER BY chDevice'
    dbChan = query_db(s1 + (s2*len(chanList))[:-2] + s3, chanList)

  #Now, get our applicable CPS.
  if not chanList:
    rCPS = query_db('SELECT * FROM AQCPS')
  else:
    s1 = 'SELECT * FROM AQCPS WHERE CPSchan IN ('
    s3 = ')'
    rCPS = query_db(s1 + (s2*len(chanList))[:-2] + s3, chanList)
  
  #Figure out what sources and profiles we need to pull and populate.
  Slist = list()
  Plist = list()
  for i in rCPS:
    if i['CPSsrc']:
      Slist.append(i['CPSsrc'])
    
    if i['CPSprof']:
      Plist.append(i['CPSprof'])

  #Now, Get the sources and profiles from the DB.
  s1 = 'SELECT * FROM AQsource WHERE rowid IN ('
  s3 = ')'
  dbSrc = query_db(s1 + (s2*len(Slist))[:-2] + s3, Slist)

  #Profiles
  s1 = 'SELECT * FROM AQprofile WHERE rowid IN ('
  dbProf = query_db(s1 + (s2*len(Plist))[:-2] + s3, Plist)

  #Also, need to get functions and points.
  dbFunct = query_db('SELECT * FROM AQfunction')
  dbPts = query_db('SELECT * FROM FnPoint')

  #Now, use the helper to get our sources dictionary with populated objects.
  sources, funct = host.popSrcs(dbSrc, dbProf, rCPS, dbFunct, dbPts)

  #Use the source data to assign sources to our channels.
  chans = host.popChans(dbChan, sources)
  
  return chans


def getSrcChan(src):
  s2 = '?, '
  s1 = 'SELECT * FROM AQsource WHERE rowid=? '
  dbSrc = query_db(s1, (src, ))

  #Now, get our applicable CPS.
  s1 = 'SELECT * FROM AQCPS WHERE CPSsrc=?'
  rCPS = query_db(s1, src)

  #Figure out what channels and profiles we need to pull and populate.
  Clist = list()
  Plist = list()
  for i in rCPS:
    if i['CPSchan']:
      Clist.append(i['CPSchan'])

    if i['CPSprof']:
      Plist.append(i['CPSprof'])

  #Now, Get the sources and profiles from the DB.
  s1 = 'SELECT * FROM AQchannel LEFT JOIN chanType ON chanType.rowid = AQchannel.chType WHERE AQchannel.rowid IN ('
  s3 = ')'
  dbChan = query_db(s1 + (s2*len(Clist))[:-2] + s3, Clist)

  #Profiles
  s1 = 'SELECT * FROM AQprofile WHERE rowid IN ('
  dbProf = query_db(s1 + (s2*len(Plist))[:-2] + s3, Plist)

  #Also, need to get functions and points. (just get them all)
  dbFunct = query_db('SELECT * FROM AQfunction')
  dbPts = query_db('SELECT * FROM FnPoint')

  #Now, use the helper to get our sources dictionary with populated objects.
  sources, funct = host.popSrcs(dbSrc, dbProf, rCPS, dbFunct, dbPts)

  #print(sources)
  #Use the source data to assign sources to our channels.
  chans = host.popChans(dbChan, sources)
  #print(chans)

  return chans
