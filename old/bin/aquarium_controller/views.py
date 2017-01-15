from django.shortcuts import render

#Just do the index here, linking to other apps.
def index(request):
    return render(request, 'base.html')
