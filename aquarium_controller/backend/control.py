import schdctl.models as schdctl
from time import sleep

def loop():
    #Run the control loop

    cycletime = 1 #TODO move over to db

    for c in schdctl.Channel.objects.filter(hwtype=2):
        c.start()

    while True:
        sleep(cycletime)
        for c in schdctl.Channel.objects.filter(hwtype=2):
            c.set()


