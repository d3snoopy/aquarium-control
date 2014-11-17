from django import forms
import schdctl.models as schdctl

class SourceAdd(forms.Form):
    source_name = forms.CharField(label='Name', max_length=100)
    max_value = forms.FloatField(label='Max Value',
                                 max_value=1,
                                 min_value=0,
                                 initial=1)


class ChannelAdd(forms.Form):
    channel_option = forms.ModelChoiceField(
        label='Add a Channel',
        queryset=schdctl.Channel.objects.all(),
        empty_label="New Channel")


class ChannelNew(forms.Form):
    name = forms.CharField(label='Name', max_length=20)
    hwid = forms.CharField(label='Hardware ID', max_length=10)

    hwChoices = (
        (0, 'GPIO Out'),
        (1, 'OneWire In'),
        (2, 'PWM Out'),
    )
    hwtype = forms.ChoiceField(label='Type of Channel',
                               choices=hwChoices,
                               initial=2)

    pwm = forms.FloatField(
        label='PWM Frequency',
        max_value=2000,
        min_value=200,
        initial=500)
    
    maxIntensity = forms.FloatField(label='Max Value',
                                 max_value=1,
                                 min_value=0,
                                 initial=1)


