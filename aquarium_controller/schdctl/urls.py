from django.conf.urls import patterns, url

from schdctl import views

urlpatterns = patterns('',
    url(r'^$', views.index, name='index'),

    url(r'^hdwr_config/$', views.hdwr_config, name='hdwr_config'),

    url(r'^source/(?P<Source_id>\d+)/$', views.source, name='source'),

    url(r'^source/(?P<Source_id>\d+)/(?P<Channel_id>\d+)/$',
        views.channel, name='channel'),

    url(r'^schedule/source/(?P<Source_id>\d+)/$',
        views.source_schedule, name='source_schedule'),

    url(r'^by_channel/$', views.by_channel, name='by_channel'),

    url(r'^schedule/source/(?P<Source_id>\d+)/(?P<Profile_id>\d+)/$',
        views.profile, name='profile'),

    url(r'^source/(?P<Source_id>\d+)/delete',
        views.source_delete, name='source_delete'),

    url(r'^channel/(?P<Channel_id>\d+)/delete',
        views.channel_delete, name='channel_delete'),

    url(r'^profile/(?P<Profile_id>\d+)/delete', 
        views.profile_delete, name='profile_delete'),
)
