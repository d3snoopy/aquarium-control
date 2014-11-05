from django.shortcuts import render, get_object_or_404
from django.http import HttpResponseRedirect, HttpResponse
from django.core.urlresolvers import reverse
import schdctl.models as schdctl


# Create your views here.

def index(request):
    source_list = schdctl.Source.objects.all()
    context = { 'source_list': source_list }
    return render(request, 'schdctl/index.html', context)


def source_add(request):
    newName = request.POST['name']
    newMaxSetting = request.POST['maxSetting']

    s = schdctl.Source(name=newName, maxSetting=newMaxSetting)
    s.save()

    return HttpResponseRedirect(reverse('index'))


def source(request, Source_id):
    src = schdctl.Source.objects.get(pk=Source_id)
    channel_list = src.channel_set.all()
    if not len(channel_list):
        chan_other = schdctl.Channel.objects.all()
    else:
        chan_other = schdctl.Channel.objects.all().exclude(channel_list)

    context = {'source': src,
               'channel_list': channel_list,
               'chan_other': chan_other}
    return render(request, 'schdctl/source.html', context)

def channel_add(request, Source_id
