from django import forms
import schdctl.models as schdctl

class SourceAdd(forms.ModelForm):
    class Meta:
        model = schdctl.Source
        fields = ['name']


class ChannelAdd(forms.Form):
    channel_option = forms.ModelChoiceField(
        label='Add a Channel',
        queryset=schdctl.Channel.objects.all(),
        empty_label="New Channel")


class ChannelNew(forms.Form):
    name = forms.CharField(label='Name', max_length=20)
    hwid = forms.CharField(label='Hardware ID', max_length=10)
    hwtype = forms.ChoiceField(label='Type of Channel',
                               choices=schdctl.hwChoices,
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


class Profile(forms.Form):
    name = forms.CharField(label='Name', max_length=20)
    start = forms.DateTimeField(label='Start Date & Time')
    stop = forms.DateTimeField(label='Stop Date & Time')
    shape = forms.ChoiceField(label='Shape',
                               choices=schdctl.shapeChoices,
                               initial=0)

    scale = forms.FloatField(
        label='Scale',
        max_value=1,
        min_value=0,
        initial=1)
    
    linstart = forms.FloatField(
        label='Linear Shape Start Value',
        max_value=1,
        min_value=0,
        initial=0)

    linend = forms.FloatField(
        label='Linear Shape End Value',
        max_value=1,
        min_value=0,
        initial=0)

class ChannelSchedule(forms.Form):
    scale= forms.FloatField(
        label='Channel Scale',
        max_value=1,
        min_value=0,
        initial=1)    
