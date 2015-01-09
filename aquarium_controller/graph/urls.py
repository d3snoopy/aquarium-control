from django.conf.urls import patterns, url

from graph import views

urlpatterns = patterns('',
    url(r'^$', views.index, name='index'),
    url(r'^source/(?P<Source_id>\d+)/$', views.source, name='source_graph'),
    url(r'^channel/(?P<Channel_id>\d+)/$', views.channel, name='channel_graph'),
    url(r'^profile/(?P<Profile_id>\d+)/$', views.profile, name='profile_graph'),
    url(r'^source_profile/(?P<Source_id>\d+)/(?P<Profile_id>\d+)/$',
        views.source_profile, name='source_profile_graph'),
    url(r'^probe/(?<Probe_id>\d+)/$', views.probe, name='probe_graph'),
)
