from django.db import models
from django.utils import timezone
from math import sin,pi,ceil
import Adafruit_BBIO.PWM as PWMctl
from time import sleep
from datetime import timedelta

# Set up the options for some fields
hwChoices = (
    (0, 'GPIO Out'),
    (1, 'OneWire In'),
    (2, 'PWM Out'),
)

shapeChoices = (
    (0, 'Constant'),
    (1, 'Positive Linear'),
    (2, 'Negative Linear'),
    (3, 'Sine'),
    (4, 'Square'),
    (5, '^ Shape'),
)


# Create your models here.

class Source(models.Model):
    name = models.CharField(max_length=20)

    def __unicode__(self):
        return self.name

    def __str__(self):
        return self.name

    def calc(self, calctime=[timezone.now()], prof=0):
        # Pre-fetch all of the data by channel.
        data = []
        name = []
        color = []

        now = timezone.now()

        for c in self.channel_set.all().prefetch_related('chanprofsrc_set__profile'):
            name.append(c.name)
            color.append(c.traceColor)

            #Pre-seed a list for the data.
            if not prof:
                objset = c.chanprofsrc_set.filter(source__id=self.pk)

            else:
                objset = c.chanprofsrc_set.filter(source__id=self.pk,
                                                  profile__id=prof)

            predata = []
            for cps in objset:
                predata.append(
                    [i*cps.scale for i in cps.profile.intensity(calctime)]
                )

            #Now, multiply all of the contributions are make the tuple.
            for i, v in enumerate(calctime):
                t = (v - now).total_seconds() / 3600
                rundata = 1

                #If predata is empty (there are no profiles), 0 rundata.
                if not predata:
                    rundata = 0

                for r in predata:
                    rundata *= r[i]

                #If this is not the first time, add another element.
                if i:
                    tupdata += ((t, rundata), )
                
                #If the first time, create the tuple.
                else:
                    tupdata = ((t, rundata), )

            #Add this channel's data to the data list.
            data.append(tupdata)


        #If this didn't generate data, return blank data.
        if not data:
            return {'name':['Blank'], 'data':[((0, 0), )], 'color':['ffffff']}

        #Return the generated data.
        return {'name':name, 'data':data, 'color':color}


class Profile(models.Model):
    name = models.CharField(max_length=20)
    start = models.DateTimeField()
    stop = models.DateTimeField()
    refresh = models.FloatField(default=24)
    #Note: refresh is the amount of time to add in hours.
    shape = models.IntegerField(default=0, choices=shapeChoices)

    def __unicode__(self):
        return self.name

    def __str__(self):
        return self.name

    def intensity(self, calctime=[timezone.now()]):
        start = self.start
        stop = self.stop
        shape = self.shape
        r = ()
        
        # Shape = 5 is a V shape
        if shape is 5:
            slope = 2/((stop-start).total_seconds())
            shift = 1/slope

            for c in calctime:
                r += ((1 - abs(slope * ((c - start).total_seconds() - shift)))
                    * int(c > start and c < stop), )
        
        # Shape = 4 is a square wave
        elif shape is 4:
            for c in calctime:
                r += (int(c > start and c < stop), )

        # Shape = 3 is a sine curve
        elif shape is 3:
            # Note: Do the max so we avoid returning negative values
            for c in calctime:
                r += (max(sin((c - start).total_seconds() * 
                         pi / (stop - start).total_seconds()),
                    0)*int(c > start and c < stop), )

        # Shape = 2 is a negative linear slope
        elif shape is 2:
            # Calculate the slope & intercept
            slope = -1/((stop-start).total_seconds())
            intercept = 1  # x = 0 is at self.start

            # Zero only if after the stop; if before make one.
            for c in calctime:
                if c < start:
                    r += (1, )

                else:
                    r += ((slope * (c - start).total_seconds() + intercept)
                        * int(c < stop), )

        # Shape = 1 is a positive linear slope
        elif shape is 1:
            # Calculate the slope & intercept
            slope = 1/((stop-start).total_seconds())
            intercept = 0  # x = 0 is at self.start

            # Zero only if before the start
            for c in calctime:
                if c < start:
                    r += (0, )

                elif c > stop:
                    r += (1, )

                else:
                    r += ((slope * (c - start).total_seconds() + intercept), )

        # If all else fails, treat it like a constant, ignoring time
        else:
            for c in calctime:
                r += (1, )

        return r


    def calc(self, calctime=[timezone.now()], p=0):
        # Pre-fetch all of the data by channel.
        name = [self.name]
        color = ['ffffff']

        now = timezone.now()

        #We basically just have to format the data.
        predata = self.intensity(calctime)

        for i, v in enumerate(calctime):
            t = (v - now).total_seconds() / 3600

            if i:
                data += ((t, predata[i]), )

            else:
                data = ((t, predata[i]), )

        data = [data]

        #Return the generated data.
        return {'name':name, 'data':data, 'color':color}


    def cleanup(self):
        now = timezone.now()

        #If the schedule is still running, do nothing.
        if now < self.stop:
            return

        #If refresh is set to 0, delete the profile.
        if not self.refresh:
            for cpr in self.chanprofsrc_set.all():
                cpr.delete()

            self.delete()

            return

        #If the schedule has ended, add the refresh time until it's active again.
        addAmount = timedelta(hours=self.refresh*ceil(
            (now - self.stop).total_seconds()/(3600*self.refresh)))

        self.start = self.start + addAmount
        self.stop = self.stop + addAmount
        self.save()

        return



class Channel(models.Model):
    name = models.CharField(max_length=20)
    hwid = models.CharField(max_length=10)
    hwtype = models.IntegerField(default=2, choices=hwChoices)
    pwm = models.FloatField(default=500)
    source= models.ManyToManyField(Source)
    maxIntensity = models.FloatField(default=1)
    traceColor = models.CharField(default='ffffff', max_length=7)

    def __unicode__(self):
        return self.name

    def __str__(self):
        return self.name

    def start(self):
        if self.hwtype is 2:
            # Start the PWM
            PWMctl.start(self.hwid, 0, self.pwm)
            sleep(1)

        return

    def stop(self):
        if self.hwtype is 2:
            # Stop the PWM
            PWMctl.stop(self.hwid)
            sleep(1)

        return

    def set(self, calctime=0):

        if not calctime:
            calctime = [timezone.now()]

        else:
            calctime = [calctime]

        v = 0

        for s in self.source.all():
            predata = []
            #Pre-seed a list for the data.
            for cps in s.chanprofsrc_set.filter(channel__id=self.pk):
                predata.append(cps.scale * cps.profile.intensity(calctime)[0])

            #Now, multiply all of the contributions for this source.
            srcdata = 1

            #If predata is empty (there are no profiles), 0 rundata.
            if not predata:
                srcdata = 0

            for r in predata:
                srcdata *= r

            #Add this channel's data to the data.
            v += srcdata

        #Make sure v is between 0 and maxIntensity:
        v = max(0.0001, min(v, self.maxIntensity))

        #Return the value
        return self.manualset(v)

    def manualset(self, v):
        if self.hwtype is 2:
            PWMctl.set_duty_cycle(self.hwid, 100 * v)

        return


    def calc(self, calctime=[timezone.now()], p=0):
        # Pre-fetch all of the data by channel.
        chandata = []
        name = [self.name]
        color = [self.traceColor]
        maxInt = self.maxIntensity

        now = timezone.now()

        for s in self.source.all():
            #Pre-seed a list for the data.
            predata = []
            for cps in s.chanprofsrc_set.filter(channel__id=self.pk):
                predata.append(
                    [i*cps.scale for i in cps.profile.intensity(calctime)]
                )

            #Now, multiply all of the contributions for this source.
            for i, v in enumerate(calctime):
                rundata = 1

                #If predata is empty (there are no profiles), 0 rundata.
                if not predata:
                    rundata = 0

                for r in predata:
                    rundata *= r[i]

                #If this is not the first time, add another element.
                if i:
                    srcdata.append(rundata)

                #If the first time, create a list
                else:
                    srcdata = [rundata]

            #Add this channel's data to the data list.
            chandata.append(srcdata)

        #Now, add up the data from all of the sources and tupilize it.
        for i, v in enumerate(calctime):
            t = (v - now).total_seconds() / 3600
            rundata = 0

            #Cycle through the data and add the source contributions.
            for c in chandata:
                rundata += c[i]

            #Make sure the data in between 0 and maxIntensity.
            rundata = max(0, min(rundata, maxInt))

            #If this is not the first time, add another element.
            if i:
                tupdata += ((t, rundata), )

            #If the first time, create the tuple.
            else:
                tupdata = ((t, rundata), )


        #If this didn't generate data, return blank data.
        if not tupdata:
            return {'name':['Blank'], 'data':[((0, 0), )], 'color':['ffffff']}

        #Return the generated data.
        return {'name':name, 'data':[tupdata], 'color':color}


class ChanProfSrc(models.Model):
    channel = models.ForeignKey(Channel)
    profile = models.ForeignKey(Profile)
    source = models.ForeignKey(Source)
    scale = models.FloatField(default=0)


    def calc(self, calctime=[timezone.now()]):
        scale = self.scale
        r = ()

        for i in self.profile.intensity(calctime):
            r += ((i[0], i[1]*scale), )

        return r

    def __unicode__(self):
        return self.channel.name + self.profile.name + self.source.name

    def __str__(self):
        return self.channel.name + self.profile.name + self.source.name
