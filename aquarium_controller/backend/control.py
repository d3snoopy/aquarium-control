import schdctl.models as schdctl
from time import sleep
from django.utils import timezone

def loop():
    #Run the control loop

    cycletime = 10 #TODO move over to db

    chans = schdctl.Channel.objects.filter(hwtype=2)
    profs = schdctl.Profile.objects.all()

    for c in chans:
        c.start()

    while True:
        sleep(cycletime)
        for c in chans:
            c.set(timezone.now())

        for p in profs:
            p.cleanup()


