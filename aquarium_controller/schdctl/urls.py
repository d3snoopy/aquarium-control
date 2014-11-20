from django.conf.urls import patterns, url

from schdctl import views

urlpatterns = patterns('',
    url(r'^$', views.index, name='index'),
    url(r'^hdwr_config/$', views.hdwr_config, name='hdwr_config'),
    url(r'^source/(?P<Source_id>\d+)/$', views.source, name='source'),
    url(r'^source/(?P<Source_id>\d+)/newchan', views.channel_new, name='channel_new'),
    url(r'^channel/(?P<Channel_id>\d+)/$', views.channel, name='channel'),
    url(r'^schedule/source/(?P<Source_id>\d+)/$',
        views.source_schedule, name='source_schedule'),

    url(r'^by_channel/$', views.by_channel, name='by_channel'),
    url(r'^schedule/channel/(?P<Channel_id>\d+)/$',
        views.channel_schedule, name='channel_schedule'),

    url(r'^schedule/source/(?P<Source_id>\d+)/(?P<Profile_id>\d+)/$',
        views.source_profile, name='source_profile'),

    url(r'^schedule/channel/(?P<Channel_id>\d+)/(?P<Profile_id>\d+)/$',
        views.channel_profile, name='channel_profile'),

)
