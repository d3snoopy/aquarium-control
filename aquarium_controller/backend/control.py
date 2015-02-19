import schdctl.models as schdctl
import hardware.driver.TLC59711 as TLC59711
from time import sleep
from django.utils import timezone
import spidev


def loop():
    #Run the control loop

    cycletime = 10 #TODO move over to db?  Or a config file.

    #TODO also move this over to a config file or something.

    #Enable our capes
    f = open("/sys/devices/bone_capemgr.9/slots", "rb")
    capes = f.read()
    f.close()

    if not "AQ-SPI0" in capes:
        f = open("/sys/devices/bone_capemgr.9/slots", "wb")
        f.write("AQ-SPI0")
        f.close()

    if not "AQ-W1" in capes:
        f = open("/sys/devices/bone_capemgr.9/slots", "wb")
        f.write("AQ-W1")
        f.close()
    
    #TODO Temperature probe stuff.
    #cat /sys/devices/w1_bus_master/*

    #Initialize the SPI device.
    spi = spidev.SpiDev()
    spi.open(1, 0)

    #Grab our profiles, to be cleaned up.
    profs = schdctl.Profile.objects.all()

    while True:
        test = TLC59711.set(spi)
        print(test)

        for p in profs:
            p.cleanup()

        sleep(cycletime)

