from django.db import models

from hardware.widgets import ColorPickerWidget

# Import our hardware drivers
import hardware.driver.TLC59711 as TLC59711
#import hardware.driver.DS18B20 as DS18B20


# List of our available hardware resource type choices
hwChoices = ((0, 'GPIO Out'),
             (1, 'GPIO In'),
             (2, 'GPIO In/Out'),
             (3, 'SPI'),
             (4, 'I2C'),
             (5, 'W1'),
             (6, 'Analog In'),
             (7, 'BBB PWM Out'),
            )

hwTypes = ((0, 'Light'),
           (1, 'Pump'),
           (2, 'Doser'),
           (3, 'Sensor'),
          )

# Enabled drivers
enDrivers = (TLC59711,
            )


# The driver names
driverChoices = tuple(enumerate([i.name() for i in enDrivers]))


# Create your models here.

# Set up the script for the colorpicker
class ColorField(models.CharField):
    def __init__(self, *args, **kwargs):
        kwargs['max_length'] = 10
        super(ColorField, self).__init__(*args, **kwargs)

    def formfield(self, **kwargs):
        kwargs['widget'] = ColorPickerWidget
        return super(ColorField, self).formfield(**kwargs)


# We are defining hosts resources, devices, and channels.  Here's how each relates:

# Host: a set of resources related to this particular machine.
# Since we plan to support several machines.

# Resource: a bucket of objects which represents everything that the host
# (The BBB) has to offer.  GPIO pins, SPI pins, I2C addresses, etc.
# These objects should be automatically created when we init the system,
# And from there they just get consumed to depletion. Doesn't really do
# anything other than keep us from over-defining a single pin or bus.
# Since our busses can support many things being connected to them, we allow
# Many to one relationships, but check for instance limits.

# Device: a thing that you connect to a resource.  Related to resources in a one
# to one relationship, but you only have as many of these as you have things
# physically connected to your hardware.  This would be a chip or a sensor or
# another device connected over one of the busses.

# Channel: a subdivision of a device, e.g. a PWM chip output


# Host class.  Each object in this class is a computer that the system is syncing.
class Host(models.Model):



# Resource class.  Each object of this class is a single hardware resource.
class Resource(models.Model):
    hwType = models.IntegerField(default=0, choices=hwChoices)
    IDnum = models.IntegerField(default=0) #I.E. SPI0 vs SPI1
    numAllow = models.IntegerField(default=1)

    def __str__(self):
        return "%s %s" % (hwChoices[self.hwType][1], self.IDnum)

    def checkAvail(self):
        # Check to see if this resource can still support more load
        return len(self.device_set.all()) < self.numAllow


# Device class
class Device(models.Model):
    resource = models.ForeignKey(Resource, on_delete=models.CASCADE) #In interface, when selecting, filter to compatible with driver.
    driver = models.IntegerField(default=0, choices=driverChoices) #In interface set this first
    busID = models.IntegerField(default=0) #Know how to handle translation, etc - I.E. for chained SPI, tell the driver where you are
    
    def __str__(self):
        return "%s %s" % (self.resource, driverChoices[self.driver][1])

    def devType(self):
        #return 0 for sensor or 1 for control, depending on driver response (hopefully this still allows for filtering)
        return enDrivers[self.driver].devType()

    def start(self):
        #call driver init-to kick things off when the device is first created
        #also called when the program is first started
        return enDrivers[self.driver].start(self.resource.IDnum, self.busID)

    def numAllow(self):
        #call the driver to find out how many channels each device supports
        return 0

    def get(self):
        #call the driver to read from it (return False if a output device)
        #Only applies to sensors
        if not enDrivers[self.driver].devType():
            return enDrivers[self.driver].get(self.resource.IDnum, self.busID)

        return False

    def set(self):
        #call the driver to write to it (return False if an input device)
        #Only applies to controls
        if enDrivers[self.driver].devType():
            return False

        return enDrivers[self.driver].set(self.resource.IDnum, self.busID, 'all')


# Channel class
class Channel(models.Model):
    device = models.ForeignKey(Device, on_delete=models.CASCADE)
    chanID = models.CharField(max_length=20, blank=True)
    chType = models.IntegerField(default=0, choices=hwTypes)
    traceColor = ColorField(blank=True)
    
    def __str__(self):
        return "%s %s %s" % (self.chanID, self.device, self.chanID)

    def getChoices(self):
        return enDrivers[self.device.driver].choices()

    def getObj(self):
        # Code to return an object that will get values
        # For a sensor, this will be the device.get function which reads from the hardware
        # For a control, this will be a calculator which will gen needed values without doing
        # DB queries unless absolutely necessary
        # TODO think about how to streamline this with the fact that reads are per-device?
        return 0

    def saveValue(self):
        # Save the value for this channel.  (Note: just queue, return the object
        # to be saved so writes can be queued up more efficiently.)
        return 0

