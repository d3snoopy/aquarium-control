from django.shortcuts import render, get_object_or_404
from django.http import HttpResponseRedirect, HttpResponse
from django.core.urlresolvers import reverse
import schdctl.models as schdctl
import schdctl.forms as schdforms


#TODO: Do the form validation stuff.

# Create your views here.
def index(request):
    source_list = schdctl.Source.objects.all()
    context = { 'source_list': source_list }

    return render(request, 'schdctl/index.html', context)


def hdwr_config(request):
    if request.method == 'POST':
        # Grab the form
        form = schdforms.SourceAdd(request.POST)
        # Check validity
        if form.is_valid():
            s = schdctl.Source(
                name = form.cleaned_data['source_name'])
            s.save()

            return HttpResponseRedirect(reverse('source', args=[s.id]))

    else:
        form = schdforms.SourceAdd()
        source_list = schdctl.Source.objects.all()
        context = { 'source_list': source_list,
                    'form': form }

    return render(request, 'schdctl/hdwr_config.html', context)


def source(request, Source_id):
    s = schdctl.Source.objects.get(pk=Source_id)

    if request.method == 'POST':
        # Grab the form
        form = schdforms.ChannelAdd(request.POST)
        # Check validity
        if form.is_valid():
            c = form.cleaned_data['channel_option']

            s.channel_set.add(c)

            return HttpResponseRedirect(reverse('source', args=[s.id]))

        else:
            return HttpResponseRedirect(reverse('channel_new', args=[s.id]))


    channel_list = s.channel_set.all()

    chan_other = schdctl.Channel.objects.exclude(source__id=Source_id)

    form = schdforms.ChannelAdd()
    form.fields['channel_option'].queryset = chan_other

    context = {'source': s,
               'channel_list': channel_list,
               'form': form}

    return render(request, 'schdctl/source.html', context)


def channel_new(request, Source_id):
    s = schdctl.Source.objects.get(pk=Source_id)
    if request.method == 'POST':
        # Grab the form
        form = schdforms.ChannelNew(request.POST)
        # Check validity

        if form.is_valid():
            c = schdctl.Channel(
                name = form.cleaned_data['name'],
                hwid = form.cleaned_data['hwid'],
                hwtype = form.cleaned_data['hwtype'],
                pwm = form.cleaned_data['pwm'],
                maxIntensity = form.cleaned_data['maxIntensity'])
            c.save()

            s.channel_set.add(c)

            return HttpResponseRedirect(reverse('source', args=[s.id]))

    form = schdforms.ChannelNew()
    context = { 'form': form, 
                'source': s }

    return render(request, 'schdctl/channel_new.html', context)


def channel(request, Channel_id):
    return HttpResponse('Channel')


def source_schedule(request, Source_id):
    s = schdctl.Source.objects.get(pk=Source_id)

    p_list = schdctl.Profile.objects.filter(
        channelschedule__channel__source__id=Source_id)

    context = {'profile_list': p_list,
                'source': s }
        
    return render(request, 'schdctl/source_schedule.html', context)


def by_channel(request):
    return HttpResponse('Schedules by channel')


def channel_schedule(request, Channel_id):
    return HttpResponse('Channel Schedule')


def source_profile(request, Source_id, Profile_id):
    if request.method == 'POST':
        # Grab the form
        form = schdforms.ChannelNew(request.POST)
        # Check validity

        if form.is_valid():
            c = schdctl.Channel(
                name = form.cleaned_data['name'],
                hwid = form.cleaned_data['hwid'],
                hwtype = form.cleaned_data['hwtype'],
                pwm = form.cleaned_data['pwm'],
                maxIntensity = form.cleaned_data['maxIntensity'])
            c.save()

            s.channel_set.add(c)

            return HttpResponseRedirect(reverse('source', args=[s.id]))

    form = schdforms.ChannelNew()
    context = { 'form': form,
                'source': s }

    return render(request, 'schdctl/channel_new.html', context)



    return HttpResponse('Source Profile')


def channel_profile(request, Source_id, Profile_id):
    return HttpResponse('Channel Profile')
