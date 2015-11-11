import schdctl.models as schdctl
import hardware.driver.TLC59711 as TLC59711
from django.utils import timezone
#import sched
import time


def run(pwm):
    #Stuff to do every time
    out = TLC59711.set(pwm)
    #print(str(timezone.now().minute) + ":" +
    #    str(timezone.now().second) + ": " + str(out))

def loop():
    #Run the control loop

    cycletime = 10 #TODO move over to db?  Or a config file.

    #TODO also move this over to a config file or something.

    #Enable our capes
    f = open("/sys/devices/platform/bone_capemgr/slots", "rb")
    capes = f.read()
    f.close()

    if not "AQ-SPI0" in capes:
        f = open("/sys/devices/platform/bone_capemgr/slots", "wb")
        f.write("AQ-SPI0")
        f.close()

    if not "AQ-W1" in capes:
        f = open("/sys/devices/platform/bone_capemgr/slots", "wb")
        f.write("AQ-W1")
        f.close()

    #TODO Temperature probe stuff.
    #cat /sys/devices/w1_bus_master/*

    #Grab our profiles, to be cleaned up.
    profs = schdctl.Profile.objects.all()

    #Start the TLC driver
    pwm = TLC59711.start()

    while True:
        [p.cleanup() for p in schdctl.Profile.objects.all()]
        
        for i in range(360):
            run(pwm)
            time.sleep(cycletime)

