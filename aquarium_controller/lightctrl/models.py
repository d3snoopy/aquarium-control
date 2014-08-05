from django.db import models

# Create your models here.

class Light(models.Model):
    name = models.CharField(max_length=20)
    pwm = models.ForeignKey(PWM)

class Color(models.Model):
    color = models.CharField(max_length=20)
    pin = models.CharField(max_length=5)
    light = models.ForeignKey(Light)
    duty_cycle = models.FloatField()

class PWM(models.Model):
    frequency = models.FloatField()

class Sleep(models.Model):
    sleep = models.FloatField()

class Lunisolar(models.Model):
    sunrise = models.TimeField()
    sunset = models.TimeField()
    moonrise = models.TimeField()
    moonset = models.TimeField()
    moonphase = models.FloatField()

class Intensity(models.Model):
    sunintensity = models.FloatField()
    moonintensity = models.FloatField()

class Weather(models.Model):
    
