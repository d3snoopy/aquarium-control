from django import forms

class SourceAdd(forms.Form):
    source_name = forms.CharField(label='Name', max_length=100)
    max_value = forms.FloatField(label='Max Value', max_value=1, min_value=0, initial=1)

class ChannelAdd(forms.Form):
    channel_option = forms.ModelChoiceField(label='Add a Channel', queryset=..., empty_label="New Channel")

    def __init__(self, *args, **kwargs):
        co = kwargs.pop('chans')
        super(ChannelAdd, self).__init__(*args, **kwargs)
        self.fields['channel_option'].queryset = co

