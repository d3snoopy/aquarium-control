import os
import sys

sys.path.append('/aquarium-ctrl/aquarium_controller/')

os.environ['PYTHON_EGG_CACHE'] = '/aquarium-ctrl/aquarium_controller/.python-egg'
os.environ['DJANGO_SETTINGS_MODULE'] = 'aquarium_controller.settings'

import django.core.handlers.wsgi
application = django.core.handlers.wsgi.WSGIHandler()
