from django.conf.urls import patterns, url

from schdctl import views

urlpatterns = patterns('',
    url(r'^$', views.index, name='index'),
    url(r'^source/(?P<Source_id>\d+)/$', views.source, name='source'),
    url(r'^source/new/$', views.source_add, name='source_add'),
    url(r'^channel/new/$', views.channel_add, name='channel_add'),
)
