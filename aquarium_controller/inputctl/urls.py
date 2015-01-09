from django.conf.urls import patterns, url

from inputctl import views

urlpatterns = patterns('',
    url(r'^$', views.index, name='index'),
    url(r'^probe/(?P<Probe_id>\d+)/$', views.probe_config, name='probe_config'),
    url(r'^probe_all/$', views.probe_add, name='probe_add'),
)
