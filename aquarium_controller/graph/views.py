from django.http import HttpResponse

#from django.shortcuts import render
from django.utils import timezone
from datetime import timedelta

import schdctl.models as schdctl

def index(request):
    s = schdctl.Source.objects.get(pk=1)

    binaryStuff = generate(s)

    return HttpResponse(binaryStuff, 'image/png')


def source(request, Source_id):
    s = schdctl.Source.objects.get(pk=Source_id)

    binaryStuff = generate(s)

    return HttpResponse(binaryStuff, 'image/png')

def channel(request, Channel_id):
    s = schdctl.Channel.objects.get(pk=Channel_id)

    binaryStuff = generate(s)

    return HttpResponse(binaryStuff, 'image/png')

def profile(request, Profile_id):
    s = schdctl.Profile.objects.get(pk=Profile_id)

    binaryStuff = generate(s)

    return HttpResponse(binaryStuff, 'image/png')

def source_profile(request, Source_id, Profile_id):
    s = schdctl.Source.objects.get(pk=Source_id)

    binaryStuff = generate(s, Profile_id)

    return HttpResponse(binaryStuff, 'image/png')


#This is an internally used function to generate the plot.
def generate(s, pid=0):

    import graph.mycharts as mycharts

    d = mycharts.MyLineChartDrawing()

    d.XLabel._text = 'Hours (from now)'
    d.YLabel._text = 'Light Intensity'

    #Find the maximum possible value and set the plot name.
    if not pid:
        #scales = [p.scale for p in s.chanprofsrc_set.all()]
        d.title.text = s.name

    else:
        qset = s.chanprofsrc_set.filter(profile__id=pid)
        #scales = [p.scale for p in qset]
        pname = schdctl.Profile.objects.get(pk=pid).name
        d.title.text = s.name + ' - ' + pname

    #if not scales:
        #yMax = 0.01

    #else:
        #yMax = max(scales)

    #d.chart.yValueAxis.valueMax = yMax

    tplot = list(range(0,24*60,5))

    for i, t in enumerate(tplot):
        tplot[i] = timezone.now()+timedelta(minutes=t)

    data = s.calc(tplot, pid)

    labels = data['name']
    labelColors = data['color']
    data = data['data']

    d.chart.data = data

    if labels:
        # set colors in the legend
        d.Legend.colorNamePairs = []
        for cnt,label in enumerate(labels):
                d.chart.lines[cnt].strokeColor = mycharts.colors.HexColor('#' + labelColors[cnt])
                d.Legend.colorNamePairs.append(
                    (mycharts.colors.HexColor('#' + labelColors[cnt]),label))

    #get a GIF (or PNG, JPG, or whatever)
    return d.asString('png')
