#import sys
# Handle adding the path where we're going to store the scripts.
#sys.path.insert(0, '/srv/http/aqctrl/')

from aqctrl import app

application = app
