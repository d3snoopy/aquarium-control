from django.db import models

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

tlc59711Choices = ((12, 'R0'),
                   (11, 'G0'),
                   (10, 'B0'),
                   (9, 'R1'),
                   (8, 'G1'),
                   (7, 'B1'),
                   (6, 'R2'),
                   (5, 'G2'),
                   (4, 'B2'),
                   (3, 'R3'),
                   (2, 'G3'),
                   (1, 'B3'),
                  )


class Output(models.Model):
    hwType= models.IntegerField(default=0,
        choices=outputChoices)

    def cleanup(self):
        if self.hwType is 2:
            self.tlc59711chan.delete()

        elif self.hwType is 1:
            self.gpiooutchan.delete()

        else:
            self.pwmchan.delete()

        return


class Input(models.Model):
    hwType = models.IntegerField(default=0,
        choices=inputChoices)


class TLC59711Chan(models.Model):
    out = models.OneToOneField(Output)
    devNum = models.IntegerField(default=0)
    chanNum = models.IntegerField(default=0,
        choices=tlc59711Choices
    )
    #note: the choice numbering is significant, it's the index when building
    #the data for output.

    def __unicode__(self):
        return str(self.devNum) + '_' + str(self.chanNum)


    def __str__(self):
        return str(self.devNum) + '_' + str(self.chanNum)


