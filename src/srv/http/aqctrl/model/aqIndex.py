#import time
#t1 = time.time()
from aqctrl.run.db import query_db
from aqctrl.aqctrl import app
from aqctrl.run.plotting import chanPlot
from os.path import exists
from json import load
from datetime import datetime, UTC
#t2 = time.time()
#Function to process our Index page.

def doIndex():
  #t3 = time.time()
  with app.app_context():
    #See if we have anything in our dB, if we don't, then we want to send people to the right places.
    thisHost = query_db('SELECT * FROM AQhost', one=True)
    if thisHost is None:
      seedVals = {'newdb': True}
      return seedVals

    #If not a new host, process our home page.
    seedVals = dict()
    seedVals['plots'] = dict()
    dbPlots = query_db('SELECT * FROM AQplot WHERE onHome=1')

    #Develop our list of onHome plots:
    if dbPlots is not None:
      for p in dbPlots:
        seedVals['plots'][p['rowid']]={'id':p['rowid'], 'name':p['AQname'], 'start':p['relStart'], 'end':p['relEnd']}

      #Get the list of plot Chans.
      s1 = 'SELECT * FROM plotChan WHERE AQplot IN ('
      s2 = '?, '
      s3 = ')'
      dbChans = query_db(s1+(s2*len(seedVals['plots']))[:-2] + s3, list(seedVals['plots'].keys()))

      #Seed the chans into right plots.
      chanLists = dict()
      for c in dbChans:
        if c['AQplot'] not in chanLists:
          chanLists[c['AQplot']] = dict()
          chanLists[c['AQplot']][1] = list()
          chanLists[c['AQplot']][2] = list()

        chanLists[c['AQplot']][c['axisNum']].append(c['chanNum'])

      #Now, do the plots.
      timing = str()
      for k, p in seedVals['plots'].items():
        now = datetime.now(UTC).timestamp()
        seedVals['plots'][k]['data'], seedVals['plots'][k]['xName'], s1 = chanPlot(chanLists[k][1], chanLists[k][2], now+p['start'], now+p['end'])
        timing += s1

    #Plots should be done.
    #seedVals['timing'] = timing

    #Now, If any channel statuses are requested, show those.
    seedVals['currVals'] = dict()
    dbChans = query_db('SELECT * FROM homeChan')
    v = dict()
    if dbChans is not None:
      if exists('/tmp/aqctrl'):
        with open('/tmp/aqctrl', 'r') as f:
          currVals = load(f)
          v = dict()
        for c in dbChans:
          #print(c['chanNum'])
          #print(currVals)
          v[c['chanNum']] = currVals[str(c['chanNum'])]

      seedVals['currVals'] = v


    #Finally, generate any requested buttons.
    dbButtons = query_db('SELECT * FROM homeButton')
    bt = list()
    if dbButtons is not None:
      for b in dbButtons:
        bt.append({'name':b['AQname'], 'id':b['rowid']})

    seedVals['buttons'] = bt
    #t4 = time.time()
    #seedVals['timing'] += ', Total Time: ' + str(t4-t1)
    #seedVals['timing'] += ', Import Time: ' + str(t2-t1)
    #seedVals['timing'] += ', Start doIndex: ' + str(t3-t2)
    #seedVals['timing'] += ', doIndex: ' + str(t4-t3)
    seedVals['timing'] = timing

    return seedVals
