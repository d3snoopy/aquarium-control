from django.db import models
import hardware.driver.TLC59711 as TLC59711

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

outputChoices = ((0, 'BBB PWM Out'),
                 (1, 'BBB GPIO Out'),
                 (2, 'SPI TLC59711 Out'),
                )

inputChoices = ((0, 'BBB GPIO In'),
                (1, 'BBB Analog In'),
                (2, 'BBB Counter In'),
                (3, 'Dallas W1 In'),
               )


class Output(models.Model):
    hwType= models.IntegerField(default=0,
        choices=outputChoices)

    def cleanup(self):
        if hasattr(self, 'tlc59711chan'):
            self.tlc59711chan.delete()

        elif hasattr(self, 'gpiooutchan'):
            self.gpiooutchan.delete()

        elif hasattr(self, 'pwmchan'):
            self.pwmchan.delete()

        return


class Input(models.Model):
    hwType = models.IntegerField(default=0,
        choices=inputChoices)


class TLC59711Chan(models.Model):
    out = models.OneToOneField(Output)
    devNum = models.IntegerField(default=0)
    chanNum = models.IntegerField(default=0,
        choices=TLC59711.chanChoice
    )
    #note: the choice numbering is significant, it's the index when building
    #the data for output.

    def __unicode__(self):
        return str(self.devNum) + '_' + str(self.chanNum)


    def __str__(self):
        return str(self.devNum) + '_' + str(self.chanNum)


