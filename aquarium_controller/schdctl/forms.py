from django import forms
import schdctl.models as schdctl
import hardware.models as hardware
#from datetime import datetime
from datetime import timedelta

from schdctl.widgets import ColorPickerWidget

from django.utils import timezone

class SourceAdd(forms.ModelForm):
    class Meta:
        model = schdctl.Source
        fields = ['name']


class ChannelAdd(forms.Form):
    channel_option = forms.ModelChoiceField(
        label='Add a Channel',
        queryset=schdctl.Channel.objects.all(),
        empty_label="New Channel")


class Channel(forms.Form):
    name = forms.CharField(label='Name', max_length=20)
    maxIntensity = forms.FloatField(label='Max Value',
                                 max_value=1,
                                 min_value=0,
                                 initial=1)

    traceColor = forms.CharField(label='Trace Color',
                                 max_length=7,
                                 widget=ColorPickerWidget)

    hwtype = forms.ChoiceField(label='Hardware Type',
                               choices=hardware.outputChoices,
                               initial=2)


class Profile(forms.Form):
    name = forms.CharField(label='Name', max_length=20)
    start = forms.DateTimeField(label='Start Date & Time',
        initial=timezone.now())

    stop = forms.DateTimeField(label='Stop Date & Time',
        initial=timezone.now() + timedelta(hours=8))
    
    refresh = forms.FloatField(
        label='Refresh Hours',
        initial=0)

    shape = forms.ChoiceField(label='Shape',
                               choices=schdctl.shapeChoices,
                               initial=0)
    

class ChannelSchedule(forms.Form):
    scale = forms.FloatField(
        label='scale',
        max_value=1,
        min_value=0,
        initial=0)    
