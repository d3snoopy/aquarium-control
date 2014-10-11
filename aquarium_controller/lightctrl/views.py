from django.shortcuts import render, get_object_or_404
import lightctrl.models as lightctrl


# Create your views here.

def index(request):
    latest_poll_list = Poll.objects.order_by('-pub_date')[:5]
    context = RequestContext(request, {
        'latest_poll_list': latest_poll_list,
    })
    return render(request, 'lightctrl/index.html', context)
