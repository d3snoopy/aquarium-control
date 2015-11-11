#!/usr/bin/env python
# vim:tabstop=4:softtabstop=4:shiftwidth=4:expandtab:filetype=python:


# Stuart Asp <d3snoopy@gmail.com>
# light_init.py
# Module to import the PWMs for the lights and get them going.
# Uses Adafruit_BBIO.PWM.


import Adafruit_BBIO.PWM as PWM
import math
from operator import add
import datetime
import time


# Function to initialize the PWM's

def startPWM():
    # Initialize the lights.
    # TODO Eventually move these static definitions over to a database.
    RB = 'P8_13'
    FAN = 'P8_19'
    W = 'P9_14'
    B = 'P9_21'
    Freq = 500

    PWMs = [W, B, RB]

    for i in PWMs:
        print(i)
        PWM.start(i, 0.0, Freq)
        time.sleep(1.0)

    return PWMs

# Function to noramlize the hue. 
def huenorm(hue):
    # Find the largest value of the three, then divide all values by that.
    return [a/max(hue, key=float) for a in hue]


# Function for the time-based intensity
def timeintensity(now, moonrise, moonset, sunrise, sunset, sunintensity, moonintensity):
    # Split the task into a couple steps

    # Calculate the sun's contribution (sin curve)
    sun = sunintensity * math.sin((now - sunrise).seconds * math.pi / (sunset - sunrise).seconds)
    # Change to a zero if negative
    if sun < 0.0:
        sun = 0.0

    # Calculate the moon's contribution
    moon = moonintensity * math.sin((now - moonrise).seconds * math.pi / (moonset - moonrise).seconds)
    # Change to a zero if negative
    if moon < 0.0:
        moon = 0.0

    # return the two
    return sun + moon


# Function for the time-based hue
def timehue(now, moonrise, moonset, sunrise, sunset):
    # TODO Need to decide about some kind of a function eventually.
    # For now, just have a hue for the sun and a different one for the moon.
    # [white, blue, royalblue]

    # TODO: Make this into a database thing
    sunhue =  [1.0, 0.5, 0.5]

    # TODO: Make this into a database thing
    moonhue = [0.1, 1.0, 0.5]

    # Start with a 0 hue
    hue = [0.0, 0.0, 0.0]
    # For both sun and moon, add in their Hue if they're up.
    if (now - sunrise).seconds > 0 and (sunset - now).seconds > 0:
        hue = map(add, hue, sunhue)

    if (now - moonrise).seconds > 0 and (moonset - now).seconds > 0:
        hue = map(add, hue, moonhue)

    # Return the normalized hue
    return huenorm(hue)


# Function to set the PWMs
def PWMset(PWMs, values):
    # Push the desired values
    for i, j in zip(PWMs, values):
        PWM.set_duty_cycle(i, float(j))


    return
    




def testloop():
    # Test function for the PWM control
    oldnow = datetime.datetime.utcnow()

    sunrise = oldnow + datetime.timedelta(seconds = 5)
    sunset = oldnow + datetime.timedelta(seconds = 120)
    moonrise = oldnow + datetime.timedelta(seconds = 100)
    moonset = oldnow + datetime.timedelta(seconds = 220)
    sunintensity = 100
    moonintensity = 1

    #RB = "P8_13"
    #FAN = "P8_19"
    #W = "P9_14"
    #B = "P9_21"
    #Freq = 500

    PWMs = startPWM()

    #PWMs = [W, B, RB, FAN]

    #for i in PWMs:
    #    print(i)
    #    PWM.start(i, 0.0, Freq)
    #    time.sleep(1.0)

    # end insertion

    now = datetime.datetime.utcnow()

    while (now - oldnow).seconds < (5*60):
        now = datetime.datetime.utcnow()

        hue = timehue(now, moonrise, moonset, sunrise, sunset)
        intensity = timeintensity(now, moonrise, moonset, sunrise, sunset, sunintensity, moonintensity)

        values = [intensity*i for i in hue]

        PWMset(PWMs, values)
        #for i, j in zip(PWMs, values):
        #    time.sleep(1)
        #    PWM.set_duty_cycle(i, float(j))

        # end insertion


        print(values)
        time.sleep(1)


    PWMend

    return



def PWMend(PWMs):
    # Stop all of the PWMs and cleanup
    for i in PWMs:
        PWM.stop(i)    

    PWM.cleanup()

    return
