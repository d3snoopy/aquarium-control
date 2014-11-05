from django.db import models
from datetime import datetime
import math
import Adafruit_BBIO.PWM as PWMctl
from time import sleep

# Create your models here.

# TODO: A method to re-roll the same cycle to a another day.


class Source(models.Model):
    name = models.CharField(max_length=20)
    maxSetting = models.FloatField(default=1)

    def start(self):
        for d in self.channel_set.all():
            d.start()

        return

    def stop(self):
        for d in self.channel_set.all():
            d.stop()

        return

    def set(self, calctime=datetime.utcnow()):
        for d in self.channel_set.all():
            d.set()

        return

    def manualset(self, v):
        # Sets all of the colors in this light to v
        for d in self.channel_set.all():
            d.manualset(v)

        return

    def __unicode__(self):
        return self.name


class PWM(models.Model):
    frequency = models.FloatField(default=500)

class Profile(models.Model):
    name = models.CharField(max_length=20)
    start = models.DateTimeField()
    stop = models.DateTimeField()
    shape = models.IntegerField(default=0)
    # Shapes: 0-constant, 1-linear slope, 2- sine curve, 3- square wave
    scale = models.FloatField(default=1)

    linstart = models.FloatField(default=0)
    linend = models.FloatField(default=1)


    def __unicode__(self):
        return self.name

    def intensity(self, calctime=datetime.utcnow()):
        # Shape = 3 is a square wave
        if self.shape == 3:
            calcnum = int(calctime > self.start and calctime < self.stop)

        # Shape = 2 is a sine curve
        elif self.shape == 2:
            # Note: Do the max so we avoid returning negative values
            calcnum = max(
                math.sin(
                    (calctime - self.start).seconds * math.pi /
                    (self.stop - self.start).seconds
                ),
                0
            )

        # Shape = 1 is a linear slope
        elif self.shape == 1:
            # Calculate the slope & intercept
            slope = (self.linend-self.linstart)/((self.stop-self.start).seconds)
            intercept = self.linstart  # x = 0 is at self.start
            calcnum = slope * (calctime - self.start).seconds + intercept

        # If all else fails, treat it like a constant
        else:
            calcnum = 1

        return int(self.gain) + self.scale*calcnum



class Channel(models.Model):
    name = models.CharField(max_length=20)
    hwid = models.CharField(max_length=5)
    source= models.ManyToManyField(Source)
    pwm = models.ForeignKey(PWM)
    maxIntensity = models.FloatField(default=1)

    def __unicode__(self):
        return self.name

    def start(self):
        # Start the PWM
        PWMctl.start(self.pin, 0, self.pwm.frequency)
        sleep(1)

        return

    def stop(self):
        # Stop the PWM
        PWMctl.stop(self.pin)
        sleep(1)

        return

    def set(self, calctime=datetime.utcnow()):
        # Within my stuff, use 0 - 1; this function expects 0 - 100 as a float.
        PWMctl.set_duty_cycle(self.pin, 100 * self.calc(calctime) *
                           self.light.maxBrightness)

        return self.calc(calctime) * self.light.maxBrightness

    def manualset(self, v):
        PWMctl.set_duty_cycle(self.pin, 100 * v)

        return

    def calc(self, calctime=datetime.utcnow()):
        # multiply up all of the contributing time profiles
        v = 1
        for s in self.colorschedule_set.all():
            v = v * s.profile.intensity(calctime) * s.scale
        
        return v


class ChannelSchedule(models.Model):
    channel = models.ForeignKey(Channel)
    profile = models.ForeignKey(Profile)
    scale = models.FloatField(default=1)
