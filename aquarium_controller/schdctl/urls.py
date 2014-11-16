from django.conf.urls import patterns, url

from schdctl import views

urlpatterns = patterns('',
    url(r'^$', views.index, name='index'),
    url(r'^source/(?P<Source_id>\d+)/$', views.source, name='source'),
    url(r'^channel/(?P<Channel_id>\d+)/$', views.channel, name='channel'),
    url(r'^channel/new/$', views.channel_new, name='channel_new'),
)
