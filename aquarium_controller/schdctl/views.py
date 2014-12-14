from django.shortcuts import render, get_object_or_404
from django.http import HttpResponseRedirect, HttpResponse
from django.core.urlresolvers import reverse
from django.forms.formsets import formset_factory
from django.contrib.auth.decorators import login_required

import schdctl.models as schdctl
import schdctl.forms as schdforms

from django.utils import timezone
from datetime import timedelta

#TODO: Update views to use form is not valid for a second chance.

# Create your views here.
def index(request):
    source_list = schdctl.Source.objects.all()
    context = { 'source_list': source_list }

    return render(request, 'schdctl/index.html', context)


def by_channel(request):
    channel_list = schdctl.Channel.objects.all()
    context = { 'channel_list': channel_list }

    return render(request, 'schdctl/by_channel.html', context)


@login_required
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


@login_required
def source(request, Source_id):
    s = schdctl.Source.objects.get(pk=Source_id)

    if request.method == 'POST':
        # Grab the form
        form = schdforms.ChannelAdd(request.POST)
        # Check validity
        if form.is_valid():
            c = form.cleaned_data['channel_option']

            s.channel_set.add(c)

            # Check to see if any profiles have been associated with source.
            # If so, create a cps object for this new channel in all profiles.
            p_list = schdctl.Profile.objects.filter(
                chanprofsrc__source__id=Source_id).distinct()

            for p in p_list:
                cps = schdctl.ChanProfSrc(channel=c, profile=p, source=s)
                cps.save()

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


@login_required
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
                maxIntensity = form.cleaned_data['maxIntensity'],
                traceColor = form.cleaned_data['traceColor'])
            c.save()

            s.channel_set.add(c)

            # Check to see if any profiles have been associated with source.
            # If so, create a cps object for this new channel in all profiles.
            p_list = schdctl.Profile.objects.filter(
                chanprofsrc__source__id=Source_id).distinct()

            for p in p_list:
                cps = schdctl.ChanProfSrc(channel=c, profile=p, source=s)
                cps.save()


            return HttpResponseRedirect(reverse('source', args=[s.id]))

    else:
        form = schdforms.ChannelNew()

    context = { 'form': form, 
                'source': s }

    return render(request, 'schdctl/channel_new.html', context)


@login_required
def channel(request, Channel_id):
    #TODO
    return HttpResponse('Channel')


def source_schedule(request, Source_id):
    s = schdctl.Source.objects.get(pk=Source_id)

    p_list = schdctl.Profile.objects.filter(
        chanprofsrc__source__id=Source_id).distinct()

    context = {'profile_list': p_list,
                'source': s }
        
    return render(request, 'schdctl/source_schedule.html', context)


@login_required
def profile(request, Source_id, Profile_id):
    s = schdctl.Source.objects.get(pk=Source_id)
    Profile_id = int(Profile_id)

    if Profile_id:
        p = schdctl.Profile.objects.get(pk=Profile_id)
        # We have an existing profile, so we'll set initial to existing.
        profileFormset = formset_factory(schdforms.ChannelSchedule, extra=0)
    else:
        # We don't have an existing profile, so we need new values.
        count = len(s.channel_set.all())
        profileFormset = formset_factory(schdforms.ChannelSchedule, extra=count)


    if request.method == 'POST':
        # Grab the formset and form
        formset = profileFormset(request.POST, prefix='channel')
        form = schdforms.Profile(request.POST, prefix='profile')

        # Check validity
        if form.is_valid() and formset.is_valid():
            if not Profile_id:
                newProfile(s, form, formset)

            else:
                updateProfile(s, p, form, formset)

            return HttpResponseRedirect(reverse('source_schedule', args=[s.id]))

        else:
            form = schdforms.Profile(form, prefix='profile')
            formset = profileFormset(formset, prefix='channel')

    else:
        #If we have an existing profile, populate the forms with existing data.
        #Otherwise, profile a blank form.
        if Profile_id:
            default_data = {'name': p.name,
                            'start':p.start,
                            'stop':p.stop,
                            'shape':p.shape,
                            'refresh':p.refresh}

            form = schdforms.Profile(initial = default_data, prefix='profile')

            formset_initial = []
            for c in s.channel_set.all():
                formset_initial.append(
                    {'scale':schdctl.ChanProfSrc.objects.filter(
                        profile__id=p.pk,
                        channel__id=c.pk,
                        source__id=s.pk)[0].scale})

            formset = profileFormset(initial=formset_initial, prefix='channel')
        
        else:
            #Set up initial values for the start and stop.
            initial_data = {'start':timezone.now(),
                            'stop':timezone.now() + timedelta(hours=8)}

            form = schdforms.Profile(prefix='profile', initial=initial_data)
            formset = profileFormset(prefix='channel')

    c = s.channel_set.all()

    context = { 'form': form,
                'formset': formset,
                'source': s, 
                'profileid': Profile_id,
                'chans': c }

    return render(request, 'schdctl/profile.html', context)


#Function to make a new profile, not accessed directly.
def newProfile(s, form, formset):
    #Create the new profile object.
    p = schdctl.Profile(
        name = form.cleaned_data['name'],
        start = form.cleaned_data['start'],
        stop = form.cleaned_data['stop'],
        refresh = form.cleaned_data['refresh'],
        shape = form.cleaned_data['shape'])
    p.save()

    #Add the necessary ChanProfSrc objects.
    clist = s.channel_set.all()

    idx = 0

    for f in formset:
        if f.cleaned_data:
            scl = f.cleaned_data['scale']

        else:
            scl = 0

        cs = schdctl.ChanProfSrc(
            channel = clist[idx],
            profile = p,
            source = s,
            scale = scl)
        cs.save()
        idx += 1

    return


#Function to update a profile, not accessed directly.
def updateProfile(s, p, form, formset):
    #Update the profile
    p.name = form.cleaned_data['name']
    p.start = form.cleaned_data['start']
    p.stop = form.cleaned_data['stop']
    p.refresh = form.cleaned_data['refresh']
    p.shape = form.cleaned_data['shape']
    p.save()

    #Update the ChanProfSrces.
    cslist = schdctl.ChanProfSrc.objects.filter(
        profile__id=p.pk, source__id=s.pk)
    for f, cs in zip(formset,cslist):
        cs.scale = f.cleaned_data['scale']
        cs.save()

    return


@login_required
def source_delete(request, Source_id):
    #Find all of the profiles for this source and delete them.
    plist = schdctl.Profile.objects.filter(
                chanprofsrc__source__id=Source_id).distinct()

    for p in plist:
        p.delete()

    #Find all of the CPSes for this source and delete them.
    cpslist = schdctl.ChanProfSrc.objects.filter(
                source__id=Source_id)

    for c in cpslist:
        c.delete()

    #Finally, delete the source.
    schdctl.Source.objects.get(pk=Source_id).delete()

    #Redirect to the hdwr_config view.
    return HttpResponseRedirect(reverse('hdwr_config'))


@login_required
def channel_delete(request, Channel_id):
    #Find all of the CPSes for this channel and delete them.
    cpslist = schdctl.ChanProfSrc.objects.filter(
                channel__id=Channel_id)

    for c in cpslist:
        c.delete()

    #Delete the channel.
    schdctl.Channel.objects.get(pk=Channel_id).delete()

    #Redirect to the hdwr_config view.
    return HttpResponseRedirect(reverse('hdwr_config'))


@login_required
def profile_delete(request, Profile_id):
    #Grab the referencing source for redirect.
    #Note the lookup will return n of the same so just get the first one.
    s = schdctl.Source.objects.filter(
                chanprofsrc__profile__id=Profile_id)[0]

    #Find all of the CPSes for this profile and delete them.
    cpslist = schdctl.ChanProfSrc.objects.filter(
                profile__id=Profile_id)

    for c in cpslist:
        c.delete()

    #Delete the profile.
    schdctl.Profile.objects.get(pk=Profile_id).delete()

    #Redirect to the index view.
    return HttpResponseRedirect(reverse('source_schedule', args=[s.id]))
