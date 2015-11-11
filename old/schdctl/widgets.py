from django import forms
from django.conf import settings
from django.template.loader import render_to_string


class ColorPickerWidget(forms.TextInput):
    class Media:
        js = (settings.STATIC_URL + 'cssjs/jscolor.js', )

    def render(self, name, value, attrs=None):
        return render_to_string('schdctl/color.html', locals())
