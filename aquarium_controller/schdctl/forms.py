from django import forms

class SourceAdd(forms.Form):
    source_name = forms.CharField(label='Name', max_length=100)
    max_value = forms.FloatField(label='Max Value', max_value=1, min_value=0, initial=1)

class ChannelAdd(forms.Form):
    channel_options = forms.ModelChoiceField(label='Channel', queryset=..., empty_label="New Channel")
    ref_source = forms.ModelChoiceField(queryset=..., empty_label=None, widget=forms.HiddenInput())
