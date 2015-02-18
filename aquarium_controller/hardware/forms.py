from django import forms
import hardware.models as hardware


class TLC59711(forms.Form):
    devNum = forms.IntegerField(label='Device Number', initial=0)
    chanNum = forms.ChoiceField(label='Device Channel',
                                choices=hardware.tlc59711Choices,
                                initial=1)

