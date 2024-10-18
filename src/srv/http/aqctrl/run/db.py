import sqlite3
from os.path import exists
from datetime import datetime, UTC
import time

DATABASE = 'db/aqctrl.db'
SCHEMA = 'db/db.sql'

#DB Related functions.
def get_db(newdb = False, appContext = True):
  if appContext:
    from flask import g
    db = getattr(g, '_database', None)
  else:
    db = None

  if db is None:
    if not exists(DATABASE): newdb = True
    if appContext:
      db = g._database = sqlite3.connect(DATABASE)
    else:
      db = sqlite3.connect(DATABASE)
    db.execute("PRAGMA foreign_keys=ON")
    db.execute('pragma journal_mode=wal')
    if newdb:
      with open(SCHEMA, 'r') as f:
        db.cursor().executescript(f.read())
      db.commit()

    db.row_factory = sqlite3.Row
  return db

def init_db():
    db = get_db(newdb = True)

def query_db(query, args=(), one=False, db=False):
    if not db: db = get_db()
    
    for i in range(10): #ten retries
      try:
        cur = db.execute(query, args)
  
        rv = cur.fetchall()
        cur.close()
        break
      except sqlite3.OperationalError:
        time.sleep(0.01)
        rv = None


    return (rv[0] if rv else None) if one else rv

def modify_db(query, args=(), logUpdate=True, db=False, doCommit=True):
    if not db: db = get_db()
    now = datetime.now(UTC)

    for i in range(10): #ten retries
      try:
        cur = db.execute(query, args)
        if logUpdate: db.execute('UPDATE AQhost SET lastUpdate = ? WHERE rowid = 1', (int(now.timestamp()), ))
        if doCommit: db.commit()
        rv = cur.fetchall()
        cur.close()
        break
      except sqlite3.OperationalError:
        time.sleep(0.01)

    return rv

def update_vals(dbTypesMaps, TableName, rowid, checkBoxMaps=tuple(), appendRow=True, defVal=0):
    # Function to update our db based on the request form
    from flask import request
    i = rowid
    query_str='UPDATE ' + TableName + ' SET '
    newVals=list()
    doQuery=False
    for v in dbTypesMaps:
      if appendRow:
        thisField = str(v[0])+'_'+str(i)
      else:
        thisField = str(v[0])

      if thisField in request.form.keys():
        a = request.form[thisField]
        if a=='': a=defVal
        newVals.append(a)
        query_str += v[1]+ '=?, '
        doQuery=True

    if checkBoxMaps:
      for n in checkBoxMaps:
        if appendRow:
          thisField = n[0]+'_'+str(i)
        else:
          thisField = n[0]

        query_str += n[1]+ '=?, '
        doQuery=True
        if thisField not in request.form.keys():
          #Checkbox is not checked, need to set accordingly.
          newVals.append(0)
        else:
          a = request.form[thisField]
          if a=='': a=defVal
          newVals.append(a)

    if doQuery:
      query_str = query_str[:-2]
      query_str += ' WHERE rowid = ?'
      newVals.append(i)
      #print(query_str, file=sys.stderr)
      #print(newVals, file=sys.stderr)
      modify_db(query_str, newVals)

    return doQuery

def seed_vals(dbTypesMaps, query_data, single=False):
    # function to seed data to send to a template.
    # By default:
    # Creates a list of dictionaries with keys according to dbTypesMaps
    # If single is True:
    # Creates a dictionary with keys according to dbTypesMaps for the first entry in query_data.
    # dbTypesMaps is a tuple of tuples with (fieldName, dbColumnName)
    if single:
      d = dict()
      d['rowid'] = str(query_data[0]['rowid'])
      for m in dbTypesMaps:
        d[m[0]]=query_data[0][m[1]]

    else:
      d = list()

      for i in query_data:
        a = dict()
        a['rowid']=str(i['rowid'])
        for m in dbTypesMaps:
          a[m[0]]=i[m[1]]
        d.append(a)

    return d

