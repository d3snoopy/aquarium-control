#!/usr/bin/python
import sys, os

sys.path.append(os.path.dirname(__file__))

from aqctrl.run import host
a = host.aqHost()
a.loop()
