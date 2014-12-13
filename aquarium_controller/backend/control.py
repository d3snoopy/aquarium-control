import schdctl.models as schdctl
from time import sleep
from django.utils import timezone

def loop():
    #Run the control loop

    cycletime = 10 #TODO move over to db

    for c in schdctl.Channel.objects.filter(hwtype=2):
        c.start()

    while True:
        sleep(cycletime)
        for c in schdctl.Channel.objects.filter(hwtype=2):
            c.set(timezone.now())

        for p in schdctl.Profile.objects.all():
            p.cleanup()


