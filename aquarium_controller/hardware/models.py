from django.db import models
import hardware.driver.TLC59711

# Create your models here.

# Make models for each of the different types of hardware device that we want to use
# BBB PWM out
# BBB GPIO out
# SPI TLC59711 out
# W1 in
# BBB GPIO in
# BBB analog in
# BBB counter in


# Channel -> Output <- Device Type

class Output(models.Model):
    hwClass = models.IntegerField(default=0,
        choices=((0, 'BBB PWM Out'),
                 (1, 'BBB GPIO Out'),
                 (2, 'SPI TLC59711 Out'),
                )
        


class Input(models.Model):


class TLC59711Chan(models.Model):
    out = models.OneToOneField(Output)
    SPIdev = models.IntegerField(default=0, choices=((0, 'SPI0'), (1, 'SPI1')))
    devNum = models.IntegerField(default=0)
    chanNum = models.IntegerField(default=0,
        choices=hardware.driver.TLC59711.chanChoice)


    def __unicode__(self):
        return self.SPIdev + '_' + self.devNum + '_' + self.chanNum


    def __str__(self):
        return self.SPIdev + '_' + self.devNum + '_' + self.chanNum


