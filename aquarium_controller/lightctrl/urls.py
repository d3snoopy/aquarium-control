from django.conf.urls import patterns, url

from lightctrl import views

urlpatterns = patterns('',
    url(r'^$', views.index, name='index')
)
