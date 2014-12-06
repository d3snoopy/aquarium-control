from django.conf.urls import patterns, include, url

from django.contrib import admin
admin.autodiscover()

urlpatterns = patterns('',
    # Examples:
    # url(r'^$', 'aquarium_controller.views.home', name='home'),
    # url(r'^blog/', include('blog.urls')),

    url(r'^$', 'graph.views.index', name='graph'),
    url(r'^graph/', include('graph.urls')),
    url(r'^schdctl/', include('schdctl.urls')),
    url(r'^admin/', include(admin.site.urls)),
)
