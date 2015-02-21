from django.conf.urls import patterns, url

from hardware import views

urlpatterns = patterns('',
    url(r'^output/(?P<Out_id>\d+)/$', views.output, name='hardware_output'),
    url(r'^output/error/(?P<Out_id>\d+)/$', views.output_error, name='hardware_output_error'),
)
