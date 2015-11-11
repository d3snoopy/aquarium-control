# -*- coding: utf-8 -*-
from __future__ import unicode_literals

from django.db import models, migrations
from django.utils.timezone import utc
import datetime


class Migration(migrations.Migration):

    dependencies = [
        ('inputctl', '0001_initial'),
    ]

    operations = [
        migrations.AlterField(
            model_name='sample',
            name='datetime',
            field=models.DateTimeField(default=datetime.datetime(2015, 2, 18, 18, 24, 16, 413047, tzinfo=utc)),
            preserve_default=True,
        ),
    ]
