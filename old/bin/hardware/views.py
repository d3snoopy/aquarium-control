from django.shortcuts import render
from django.http import HttpResponseRedirect, HttpResponse
from django.core.urlresolvers import reverse
from django.contrib.auth.decorators import login_required

import hardware.models as hardware
import hardware.forms as hardforms


# Create your views here.
@login_required
def output(request, Out_id):
    # Figure out what type of hardware we have and call the right function.
    h = hardware.Output.objects.get(pk=Out_id)

    if h.hwType is 2:
        ret = TLC59711(request, h)


    else:
        ret =  HttpResponse('Hw type not implemented yet')

    return ret


def output_error(request, Out_id):
    return render(request, 'hardware/output_error.html', {'h': Out_id})


# Not directly accessed by urls
def TLC59711(request, h): 
    if request.method == 'POST':
        # Grab the form
        form = hardforms.TLC59711(request.POST)
        # Check validity
        if form.is_valid():
            #Test if the channel is already in use.
            if hardware.TLC59711Chan.objects.filter(
                devNum=form.cleaned_data['devNum']
               ).filter(
                chanNum=form.cleaned_data['chanNum']):
                return HttpResponseRedirect(reverse('hardware_output_error', args=[h.id]))
 
            #See if an object already exists.
            if not hasattr(h, 'tlc59711chan'):
                #Create an object
                t = hardware.TLC59711Chan(
                    out=h,
                    devNum=form.cleaned_data['devNum'],
                    chanNum=form.cleaned_data['chanNum'],
                )
                t.save()

            else:
                h.tlc59711chan.devNum=form.cleaned_data['devNum']
                h.tlc59711chan.chanNum=form.cleaned_data['chanNum']
                h.tlc59711chan.save()

            #TODO: Implement some kind of tracking so we get back to a place that makes more sense.
            return HttpResponseRedirect(reverse('index'))

    else:
        if hasattr(h, 'tlc59711chan'):
            default_data = {'devNum': h.tlc59711chan.devNum,
                            'chanNum': h.tlc59711chan.chanNum
                           }
            form = hardforms.TLC59711(initial=default_data)

        else:
            form = hardforms.TLC59711()

    context = {'h': h, 
               'form': form }

    return render(request, 'hardware/TLC59711.html', context)

