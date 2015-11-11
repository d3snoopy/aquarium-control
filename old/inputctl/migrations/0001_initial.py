# -*- coding: utf-8 -*-
from __future__ import unicode_literals

from django.db import models, migrations
import datetime
from django.utils.timezone import utc


class Migration(migrations.Migration):

    dependencies = [
    ]

    operations = [
        migrations.CreateModel(
            name='Probe',
            fields=[
                ('id', models.AutoField(primary_key=True, verbose_name='ID', auto_created=True, serialize=False)),
                ('name', models.CharField(max_length=20)),
                ('hwtype', models.IntegerField(choices=[(0, 'Digital Temperature'), (1, 'Reserved'), (2, 'Reserved')], default=0)),
                ('hwid', models.CharField(max_length=20)),
                ('running', models.BooleanField(default=False)),
                ('save', models.FloatField(default=24)),
            ],
            options={
            },
            bases=(models.Model,),
        ),
        migrations.CreateModel(
            name='Sample',
            fields=[
                ('id', models.AutoField(primary_key=True, verbose_name='ID', auto_created=True, serialize=False)),
                ('datetime', models.DateTimeField(default=datetime.datetime(2015, 2, 18, 18, 23, 51, 741141, tzinfo=utc))),
                ('value', models.FloatField(default=0)),
                ('probe', models.ForeignKey(to='inputctl.Probe')),
            ],
            options={
            },
            bases=(models.Model,),
        ),
    ]
