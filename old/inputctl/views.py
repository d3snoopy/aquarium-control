from django.shortcuts import render

import inputctl.models as inputctl


# Create your views here.
def index(request):
    probe_list = inputctl.Probe.objects.all()
    context = { 'probe_list': probe_list }

    return render(request, 'inputctl/index.html', context)

def probe_config(request):
    return

def probe_all(request):
    return
