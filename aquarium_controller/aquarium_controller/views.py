
from django.shortcuts import render

#Just do the index here, linking to other apps.
def home(request):
    return render(request, 'base.html')
