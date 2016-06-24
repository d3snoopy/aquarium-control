from django import forms
import hardware.driver.TLC59711 as TLC59711


class TLC59711(forms.Form):
    devNum = forms.IntegerField(label='Device Number', initial=0)
    chanNum = forms.ChoiceField(label='Device Channel',
                                choices=TLC59711.chanChoice,
                                initial=1)

