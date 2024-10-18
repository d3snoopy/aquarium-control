from aqctrl.run.db import query_db
from aqctrl.run.db import modify_db
from aqctrl.aqctrl import app
from flask import request
import bcrypt

def check_password(environ, user, password):
    with app.app_context():
        userObj = query_db('SELECT * FROM AQuser WHERE userName = ?', (user, ), one=True)

        if userObj is None:
            return None
        else:
            plaintext = password.encode('utf-8')
            hashtext = userObj['passHash']
            if bcrypt.checkpw(plaintext, hashtext):
                return userObj['rowid']
            else:
                return False


def change_user(userName, newPassword, userLevel, new):
    #Function to create or update user credentials.
    #Make sure to verify that it's OK for your user to do this BEFORE you call this function.
    with app.app_context():
        userObj = query_db('SELECT rowid, passSalt FROM AQuser WHERE username = ?', (userName, ), one=True)


        if not new and userObj is None:
            return "Username " + userName + " was not found, "

        if new and userObj is not None:
            return "Username " + userName + " already exists and cannot be created, "
        nPWD=newPassword.encode('utf-8')
        PassHash = bcrypt.hashpw(nPWD, bcrypt.gensalt())
        if new:
            qRes = modify_db('INSERT INTO AQuser(userName, passHash, passSalt, authLevel) VALUES (?, ?, ?, ?)', (userName, PassHash, '', userLevel))

            return "Added new user " + userName + ", "
        elif newPassword:
            qRes = modify_db('UPDATE AQuser SET passHash = ?, authLevel = ? WHERE rowid = ?',
                    (PassHash, userLevel, userObj['rowid']))
            return "Updated password for " + userName + ", "
        else:
            modify_db('UPDATE AQuser SET authLevel = ? WHERE rowid = ?',
                    (userLevel, userObj['rowid']))
            return "Updated privileges for " + userName + ", "


def processForm(isAdmin, username):
  with app.app_context():
    status = ''
    if 'Change' in request.form.keys():
      #Handle self password change.
      if check_password(None, username, request.form['currPass']):
        if request.form['newPass1'] == request.form['newPass2']:
          status += "Updated your password, "
          usrLvl = query_db("SELECT * FROM AQuser WHERE userName = ?",
            (username, ), one=True)['authLevel']
          change_user(username,
            request.form['newPass1'], usrLvl, False)
        else:
          status += "New password fields did not match, "
      else:
        status += "Current password provided not correct, "

      return status

    if isAdmin:
      users = query_db("SELECT rowid, userName, authLevel FROM AQuser WHERE userName != ?",
            (username, ))
      if request.form['newUserPass'] and request.form['newUsername']:
        #Admin has entered info asking for a new user with password.
        status += change_user(request.form['newUsername'],
          request.form['newUserPass'],
          request.form['newUserPriv'],
          True)

      for u in users:
        passStr = 'pass' + str(u['rowid'])
        if request.form[passStr]:
          status += change_user(u['userName'], request.form[passStr], u['authLevel'], False)

        privLvl = 'priv' + str(u['rowid'])
        if request.form[privLvl] != str(u['authLevel']):
           status += change_user(u['userName'], False, request.form[privLvl], False)

        delStr = 'delete' + str(u['rowid'])
        if delStr in request.form:
          modify_db('DELETE FROM AQuser WHERE rowid = ?', (u['rowid'], ))
          status += "Deleted user: " + u['userName'] + ", "

    return status


def createForm(isAdmin, status, username):
  with app.app_context():
    if not isAdmin: return None, status
    userList = list()
    users = query_db("SELECT rowid, userName, authLevel FROM AQuser WHERE userName != ?",
            (username, ))

    for u in users:
      userList.append({'Name':u['userName'], 'Number':u['rowid'], 'Priv':u['authLevel']})

    return userList, status
