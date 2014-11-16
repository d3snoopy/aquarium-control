from django.shortcuts import render, get_object_or_404
from django.http import HttpResponseRedirect, HttpResponse
from django.core.urlresolvers import reverse
import schdctl.models as schdctl
import schdctl.forms as schdforms


#TODO: Do the form validation stuff.

# Create your views here.

def index(request):
    if request.method == 'POST':
        # Grab the form
        form = schdforms.SourceAdd(request.POST)
        # Check validity
        if form.is_valid():
            s = schdctl.Source(
                name = form.cleaned_data['source_name'],
                maxSetting = form.cleaned_data['max_value'])
            s.save()

            return HttpResponseRedirect(reverse('source', args=[s.id]))

    else:
        form = schdforms.SourceAdd()
        source_list = schdctl.Source.objects.all()
        context = { 'source_list': source_list,
                    'form': form }

    return render(request, 'schdctl/index.html', context)


def source(request, Source_id):
    s = schdctl.Source.objects.get(pk=Source_id)

    if request.method == 'POST':
        # Grab the form
        form = schdforms.ChannelAdd(request.POST, chans=[])
        # Check validity
        if form.is_valid():
            c = form.cleaned_data['channel_option']

            return HttpResponse(str(c))

            if not c:
                return HttpResponseRedirect(reverse('channel_new', args=[s.id]))

            else:
                s.channel.add(c)

                return HttpResponse('bla')
                return HttpResponseRedirect(reverse('source', args=[s.id]))

        else:
            return HttpResponse('invalid form')
    #TODO Need an else here?

    channel_list = s.channel_set.all()
    if not len(channel_list):
        chan_other = schdctl.Channel.objects.all()
    else:
        chan_other = schdctl.Channel.objects.all().exclude(channel_list)

    form = schdforms.ChannelAdd(chans=chan_other)

    context = {'source': s,
               'channel_list': channel_list,
               'form': form}

    return render(request, 'schdctl/source.html', context)


def channel_new(request):
    if request.method == 'POST':
        # Grab the form
        form = schdforms.ChannelAdd(request.POST)
        # Check validity for a ChannelAdd Type
        if form.is_valid():
            s = schdctl.Source(
                name = form.cleaned_data['source_name'],
                maxSetting = form.cleaned_data['max_value'])
            s.save()

            return HttpResponseRedirect(reverse('source', args=[s.id]))

    else:
        form = schdforms.SourceAdd()
        source_list = schdctl.Source.objects.all()
        context = { 'source_list': source_list,
                    'form': form }

    return render(request, 'schdctl/index.html', context)









    chan = request.POST['chan_add']
    srcid = request.POST['src']
    src = schdctl.Source.objects.get(pk=srcid)

    if chan is 'new':
        pwm = schdctl.PWM.objects.order_by('frequency')

        context = {
            'srcid': src,
            'pwm': pwm}

        return render(request, 'schdctl/channel_new.html', context)

    elif chan is 'create':
        pwmin = request.POST['pwm']
        if pwmin is 'new':
            freq = request.POST['pwmfreq']
            p = schdctl.PWM(frequency = freq)
            p.save()
        
        else:
            p = schdctl.PWM.objects.get(pk=request.POST['pwm'])

        c = schdctl.Channel(
            name = request.POST['name'],
            hwid = request.POST['hwid'],
            hwtype = request.POST['hwtype'],
            pwm = p,
            maxIntensity = request.POST['maxSetting'])
        c.save()
    
    else:
        c = schdctl.Channel.objects.get(pk=chan)


    c.source.add(src)
    c.save()

    return HttpResponseRedirect(reverse('source', args=[src.id]))
        

def channel(request):
    return HttpResponse('Channel')
