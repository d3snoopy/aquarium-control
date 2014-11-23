from django.shortcuts import render, get_object_or_404
from django.http import HttpResponseRedirect, HttpResponse
from django.core.urlresolvers import reverse
from django.forms.formsets import formset_factory
import schdctl.models as schdctl
import schdctl.forms as schdforms


#TODO: Do the form validation stuff.

# Create your views here.
def index(request):
    source_list = schdctl.Source.objects.all()
    context = { 'source_list': source_list }

    return render(request, 'schdctl/index.html', context)

def by_channel(request):
    return HttpResponse('schedules by channel')

def hdwr_config(request):
    if request.method == 'POST':
        # Grab the form
        form = schdforms.SourceAdd(request.POST)
        # Check validity
        if form.is_valid():
            s = schdctl.Source(
                name = form.cleaned_data['name'])
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


def channel_schedule(request, Channel_id):
    return HttpResponse('Schedules by Channel')


def source_profile(request, Source_id, Profile_id):
    profileFormset = formset_factory(schdforms.ChannelSchedule, extra=0)
    s = schdctl.Source.objects.get(pk=Source_id)
    if Profile_id:
        p = schdctl.Profile.objects.get(pk=Source_id)

    if request.method == 'POST':
        # Grab the formset and form
        formset = profileFormset(request.POST, prefix='channel')
        form = schdforms.Profile(request.POST, prefix='profile')
        # Check validity
        pnew = 0

        if form.is_valid():
            if not Profile_id:
                pnew = schdctl.Profile(
                    name = form.cleaned_data['name'],
                    start = form.cleaned_data['start'],
                    stop = form.cleaned_data['stop'],
                    shape = form.cleaned_data['shape'],
                    scale = form.cleaned_data['scale'],
                    linstart = form.cleaned_data['linstart'],
                    linstop = form.cleaned_data['linstop'])
                pnew.save()
            else:
                p.name = form.cleaned_data['name']
                p.start = form.cleaned_data['start']
                p.stop = form.cleaned_data['stop']
                p.shape = form.cleaned_data['shape']
                p.scale = form.cleaned_data['scale']
                p.linstart = form.cleaned_data['linstart']
                p.linstop = form.cleaned_data['linstop']
                p.save()

        if formset.is_valid():
            if pnew:
                for f in form.cleaned_data:
                    cs = schdctl.ChannelSchedule(
                        channel = c,
                        profile = p,
                        scale = f['scale'])
                    cs.save()
            elif p:
                cslist = schdctl.ChannelSchedule.objects.filter(
                    profile__id=p.pk)
                for f, cs in zip(form.cleaned_data,cslist):
                    cs.scale = f['scale']

            return HttpResponseRedirect(reverse('source_schedule', args=[s.id]))

    if p:
        default_data = {'name': p.name,
                        'start':p.start,
                        'stop':p.stop,
                        'shape':p.shape,
                        'scale':p.scale,
                        'linstart':p.linstart,
                        'linstop':p.linend}

        form = schdforms.Profile(default_data, prefix='profile')
    else:
        form = schdforms.Profile(prefix='profile')

    #Set up the formset
    if Profile_id:
        formset_initial = []
        for c in s.channel_set.all():
            formset_initial.append(
                {'scale':schdctl.ChannelSchedule.objects.filter(
                    profile__id=p.pk, channel_id=c.pk)})

#TODO: this is kind of a hack, I don't really need to iterate.
    else:
        formset_initial = []
        for c in s.channel_set.all():
            formset_initial.append(
                {'scale':1}) #TODO this over-rides the defaults elsewhere


    formset = profileFormset(initial=formset_initial, prefix='channel')

    clist = s.channel_set.all()

    context = { 'form': form,
                'formset': formset,
                'source': s, 
                'profileid': Profile_id }

    return render(request, 'schdctl/source_profile.html', context)


#Function to make a new profile, not accessed directly.
def new_profile(source, form):
    #Create the new profile object
    pnew = schdctl.Profile(
        name = form.cleaned_data['name'],
        start = form.cleaned_data['start'],
        stop = form.cleaned_data['stop'],
        shape = form.cleaned_data['shape'],
        scale = form.cleaned_data['scale'],
        linstart = form.cleaned_data['linstart'],
        linstop = form.cleaned_data['linstop'])
    pnew.save()



#Function to update a profile, not accessed directly.
def update_profile(profile, form):
    


def channel_profile(request, Source_id, Profile_id):
    return HttpResponse('Channel Profile')


