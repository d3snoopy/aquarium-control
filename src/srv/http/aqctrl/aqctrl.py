from flask import render_template, Flask, request, redirect, url_for, flash, g
#import sqlite3
#from flask import g
#from os.path import exists
#import sys
#from flask_debugtoolbar import DebugToolbarExtension
from flask_login import LoginManager, UserMixin, login_required, fresh_login_required, login_user, logout_user, current_user
from datetime import datetime
import aqctrl.run.db as db
from aqctrl.run.db import query_db
#Make sure the script location is in python path.
#For development, made symbolic links.
#import time

app = Flask(__name__)

# debug toolbar
#app.debug = True


app.secret_key = b'' ######Make sure to set this!!!

#toolbar = DebugToolbarExtension(app)
login_manager = LoginManager(app)
login_manager.login_view = "login"

# A couple db housekeeping items
@app.cli.command('initdb')
def initdb_command():
    """Initializes the database."""
    db.init_db()
    print('Initialized the database.')


@app.teardown_appcontext
def close_connection(exception):
    d = getattr(g, '_database', None)
    if d is not None:
        d.close()

def get_run_status():
  #TODO: Read status from our tmp file.
  #If exists /run/user/1000/aqctrl
  #with open /run/user/1000/aqctrl as f:
  #Read in the channel info and the run info.
  #Note, our loop function needs to update this regularly.
  return


# Our User class.
class User(UserMixin):
  def __init__(self, id, username, passHash, passSalt, authLevel):
    self.id = str(id)
    self.username = username
    self.passHash = passHash
    self.passSalt = passSalt
    self.authLevel = authLevel
    self.authenticated = False

  def is_active(self):
    return True

  def is_anonymous(self):
     return False

  def is_authenticated(self):
    return self.authenticated

  def get_id(self):
    return self.id

  #TODO: more here

# Our user manager callback.
@login_manager.user_loader
def load_user(user_id):
  u = query_db('SELECT * FROM AQuser WHERE rowid = ?', (user_id, ), one=True)
  if u is None:
    return None
  else:
    return User(int(u['rowid']), u['userName'], u['passHash'], u['passSalt'], u['authLevel'])

# Login page for users.
@app.route('/login', methods=['GET', 'POST'])
def login():
  if current_user.is_authenticated:
    return redirect(url_for('index'))

  import aqctrl.model.aqAuth
  error = ''
  if request.method == 'POST':
    user = request.form['username']
    password = request.form['password']

    #Decide if this is the initial user setup.
    userRes = query_db('SELECT rowid FROM AQuser', ())
    if not userRes:
      pass2 = request.form['password2']
      if password == pass2 and password and user:
        aqctrl.model.aqAuth.change_user(user, password, 4, True)
      else:
        error = 'Passwords do not match or fields blank'

    else:
      myUser = aqctrl.model.aqAuth.check_password(None, user, password)
      if myUser:
        if 'remCheck' in request.form.keys():
          remCheck = True
        else:
          remCheck = False

        login_user(load_user(myUser), remember=remCheck)
        return redirect(url_for('index'))
      else:
        error = 'Invalid Username/Password'

  #Need to decide if this is a fresh db, if so, we're asking the user to create an initial user
  userRes = query_db('SELECT rowid FROM AQuser', ())
  if not userRes:
    #There are no users in the DB, need to get the user to create one.
    headStr = 'Create Admin User'
    double = True
  else:
    headStr = 'AQCtrl Login'
    double = False

  return render_template('login.html', error=error, headStr=headStr, double=double)


@login_manager.needs_refresh_handler
def refresh():
  logout_user()
  return redirect('login')


@app.route('/logout')
@login_required
def logout():
  logout_user()
  return redirect('login')
 

# Now, our other pages
@app.route('/')
@login_required
def index():
    #t1 = time.time()
    if current_user.authLevel < 1: return render_template('badprivilges.html')
    import aqctrl.model.aqIndex
    # Process our Index data.
    seedVals = aqctrl.model.aqIndex.doIndex()
    # Number of new errors in the error log.
    numNew = query_db('SELECT count(*) from AQlog WHERE entryRead = 0', one=True)['count(*)']
    #t2 = time.time()
    #seedVals['timing'] += 'Index page: ' + str(t2-t1)
    return render_template('index.html', seedVals=seedVals, numNew = numNew)


@app.route('/setup/index', methods=['GET', 'POST'])
@login_required
def indexSetup():
    if current_user.authLevel < 3: return render_template('badprivilges.html')
    import aqctrl.model.aqEditIndex
    errors = dict()
    if request.method == 'POST':
         errors = aqctrl.model.aqEditIndex.processForm()

    seedVals, errors = aqctrl.model.aqEditIndex.createForm(errors)
    return render_template('editIndex.html', seedVals=seedVals, errors=errors)


@app.route('/doButton', methods=['POST'])
@login_required
def doButton():
  for k, v in request.form.items():
    buttonID = k.split('_')[1]
  flash('Activated button: ' + v)
  with open('/tmp/aqButton', 'w') as f:
    f.write(buttonID)

  return redirect(url_for('index'))


@app.route('/setup', methods=['GET', 'POST'])
@login_required
def setup():
    if current_user.authLevel < 3: return render_template('badprivilges.html')
    import aqctrl.model.aqSetup
    errors = dict()
    errors['devices'] = dict()
    errors['devices']['addr'] = dict()
    # Process our Setup Model.
    if request.method == 'POST':
         errors = aqctrl.model.aqSetup.processForm() #ProcRes will return complaints about mis-filled form.

    seedVals, errors = aqctrl.model.aqSetup.createForm(errors)
    return render_template('setup.html', seedVals=seedVals, errors=errors)
	
@app.route('/setup/channels/<devID>', methods=['GET', 'POST'])
@login_required
def chanSetup(devID):
    if current_user.authLevel < 3: return render_template('badprivilges.html')
    import aqctrl.model.aqSetupChan
    errors = dict()
    # Process the model.
    if request.method == 'POST':
      errors = aqctrl.model.aqSetupChan.processForm(devID)

    seedVals, errors = aqctrl.model.aqSetupChan.createForm(devID, errors)

    if seedVals is None:
      return redirect(url_for('setup'))

    return render_template('setupChan.html', seedVals=seedVals, errors=errors, devID=devID)

@app.route('/setup/chantypes', methods=['GET', 'POST'])
@login_required
def chanTypes():
    if current_user.authLevel < 3: return render_template('badprivilges.html')
    import aqctrl.model.aqChanTypes
    errors = dict()
    # Process the model.
    if request.method == 'POST':
      errors = aqctrl.model.aqChanTypes.processForm()

    seedVals, errors = aqctrl.model.aqChanTypes.createForm(errors)
    return render_template('ChanTypes.html', seedVals=seedVals, errors=errors)


@app.route('/schedule', methods=['GET', 'POST'])
@login_required
def sched():
    if current_user.authLevel < 2: return render_template('badprivilges.html')
    import aqctrl.model.SchedList
    errors = dict()
    # Process the model.
    if request.method == 'POST':
      errors = aqctrl.model.SchedList.processForm('AQsource')

    seedVals, errors = aqctrl.model.SchedList.createForm('AQsource', errors)

    return render_template('SchedList.html', name='Sources', target='schedSrc', subSel=(True, False, False, False), seedVals=seedVals, errors=errors)


@app.route('/schedule/<ID>', methods=['GET', 'POST'])
@login_required
def schedSrc(ID):
    if current_user.authLevel < 2: return render_template('badprivilges.html')
    import aqctrl.model.aqSchedSrc
    errors = dict()
    # Process the model.
    if request.method == 'POST':
      errors = aqctrl.model.aqSchedSrc.processForm(ID)

    seedVals, errors = aqctrl.model.aqSchedSrc.createForm(ID, errors)
    if seedVals is None:
      #We didn't find the requested srcID and redirected to srcID=0
      return redirect(url_for('sched'))
    
    return render_template('schedSrc.html', seedVals=seedVals, errors=errors, ID=ID)


@app.route('/schedule/channels', methods=['GET', 'POST'])
@login_required
def channels():
    if current_user.authLevel < 2: return render_template('badprivilges.html')
    import aqctrl.model.SchedList
    errors = dict()
    # Process the model.
    if request.method == 'POST':
      errors = aqctrl.model.SchedList.processForm('AQchannel')

    Qryflt = ' INNER JOIN chanType ON AQchannel.chType = chanType.rowid WHERE chActive=1 AND chanType.chOutput=1'
    seedVals, errors = aqctrl.model.SchedList.createForm('AQchannel', errors, Qryflt=Qryflt)
    
    return render_template('SchedList.html', name='Channels', target='schedChan', subSel=(False, True, False, False), seedVals=seedVals, errors=errors, hideAddDel=True)


@app.route('/schedule/chan/<ID>', methods=['GET', 'POST'])
@login_required
def schedChan(ID):
    if current_user.authLevel < 2: return render_template('badprivilges.html')
    import aqctrl.model.aqSchedChan
    errors = dict()
    # Process the model.
    if request.method == 'POST':
      errors = aqctrl.model.aqSchedChan.processForm(ID)

    seedVals, errors = aqctrl.model.aqSchedChan.createForm(ID, errors)
    if seedVals is None:
      #We didn't find the requested srcID and redirected to srcID=0
      return redirect(url_for('channels'))

    return render_template('schedChan.html', seedVals=seedVals, errors=errors, ID=ID)


@app.route('/schedule/reaction', methods=['GET', 'POST'])
@login_required
def reactions():
    if current_user.authLevel < 2: return render_template('badprivilges.html')
    import aqctrl.model.SchedList
    errors = dict()
    # Process the model.
    if request.method == 'POST':
        errors = aqctrl.model.SchedList.processForm('AQreactGrp')

    seedVals, errors = aqctrl.model.SchedList.createForm('AQreactGrp', errors)

    return render_template('SchedList.html', name='Reactions', target='schedReact', subSel=(False, False, True, False), seedVals=seedVals, errors=errors)


@app.route('/schedule/reaction/<ID>', methods=['GET', 'POST'])
@login_required
def schedReact(ID):
    if current_user.authLevel < 2: return render_template('badprivilges.html')
    import aqctrl.model.aqReactEdit
    errors = dict()
    # Process the model.
    if request.method == 'POST':
        errors = aqctrl.model.aqReactEdit.processForm(ID)

    seedVals, errors = aqctrl.model.aqReactEdit.createForm(ID, errors)
    if seedVals is None:
        return redirect(url_for('reactions'))

    return render_template('reactEdit.html', seedVals=seedVals, errors=errors, ID=ID)



@app.route('/schedule/function', methods=['GET', 'POST'])
@login_required
def functions():
    if current_user.authLevel < 2: return render_template('badprivilges.html')
    import aqctrl.model.SchedList
    errors = dict()
    # Process the model.
    if request.method == 'POST':
        errors = aqctrl.model.SchedList.processForm('AQfunction')

    seedVals, errors = aqctrl.model.SchedList.createForm('AQfunction', errors)

    return render_template('SchedList.html', name='Functions', target='schedfn', subSel=(False, False, False, True), seedVals=seedVals, errors=errors)


@app.route('/schedule/function/<ID>', methods=['GET', 'POST'])
@login_required
def schedfn(ID):
    if current_user.authLevel < 2: return render_template('badprivilges.html')
    import aqctrl.model.aqFunctEdit
    errors = dict()
    # Process the model.
    if request.method == 'POST':
        errors = aqctrl.model.aqFunctEdit.processForm(ID)

    seedVals, errors = aqctrl.model.aqFunctEdit.createForm(ID, errors)
    if seedVals is None:
        return redirect(url_for('functions'))

    return render_template('functEdit.html', seedVals=seedVals, errors=errors, ID=ID)


@app.route('/status', methods=['GET', 'POST'])
@login_required
def status():
    import aqctrl.model.aqStatus
    errors = dict()
    # Process our favorite plots
    if request.method == 'POST':
      errors, getStr = aqctrl.model.aqStatus.processForm()
      return redirect(url_for('status')+'?'+getStr)

    # TODO: Process if a favorite has been picked. If so, get the values and redirect.
    i = request.args.get('fav', default=0, type=int)
    if i:
      return redirect(url_for('status')+aqctrl.model.aqStatus.buildFav(i))

    seedVals = aqctrl.model.aqStatus.createForm()
    #print(seedVals, file=sys.stderr)
    
    return render_template('status.html', seedVals=seedVals, errors=errors)
	
	
@app.route('/admin', methods=['GET', 'POST'])
@fresh_login_required
def admin():
    if current_user.authLevel < 2: return render_template('badprivilges.html')

    import aqctrl.model.aqAuth
    errors = dict()
    # Process the model.
    isAdmin = (current_user.authLevel > 3)
    isConfig = True
    status = ''
    if request.method == 'POST':
      status = aqctrl.model.aqAuth.processForm(isAdmin, current_user.username)

    userList, status = aqctrl.model.aqAuth.createForm(isAdmin, status, current_user.username)

    return render_template('admin.html', status=status, userList=userList, admin=isAdmin, config=isConfig)


@app.route('/reboot', methods=['POST'])
@login_required
def reboot():
  if current_user.authLevel < 2: return render_template('badprivilges.html')

  with open('/tmp/reboot', 'w') as f:
    f.write('reboot')

  return 'Rebooting Controller'

@app.route('/shutdown', methods=['POST'])
@login_required
def shutdown():
  if current_user.authLevel < 2: return render_template('badprivilges.html')

  with open('/tmp/shutdown', 'w') as f:
    f.write('shutdown')

  return 'Shutting Down Controller'  
