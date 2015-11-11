import os
from datetime import timedelta

from django.db import models
from django.utils import timezone
from django.conf import settings


# Set up the options for the hardware types.
hwChoices = (
    (0, 'Digital Temperature'),
    (1, 'Reserved'),
    (2, 'Reserved'),
)


# Create your models here.

class Probe(models.Model):
    name = models.CharField(max_length=20)
    hwtype = models.IntegerField(default=0, choices=hwChoices)
    hwid = models.CharField(max_length=20)
    running = models.BooleanField(default=False)
    save = models.FloatField(default=24) #Note: Interpreted as hours

    def __unicode__(self):
        return self.name

    def __str__(self):
        return self.name

    def get(self):
        if not self.running:
            self.start()

        if self.hwtype is 0:
            path = "/sys/bus/w1/devices/" + self.hwid + "/w1_slave"
            raw = open(path, "r").read()
            
            #Note: this is in farenheit.  Sometime allow for C or K?
            return(float(raw.split("t=")[-1])*9/5000 + 32)

        return


    def cleanup(self):
        cutoff = timezone.now() - timedelta(hours=self.save)
        for s in self.sample_set.objects.filter(datetime__lte=cutoff):
            s.delete()

        return

    def calc(self, t=False, d=False):
        r = ()
        now = timezone.now()

        for i in self.sample_set.order_by('datetime'):
            r += ((i.datetime - now, i.value), )

        return r


class Sample(models.Model):
    probe = models.ForeignKey(Probe)
    datetime = models.DateTimeField(default=timezone.now())
    value = models.FloatField(default=0)

    def __unicode__(self):
        return self.value

    def __str__(self):
        return self.value


