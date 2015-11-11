# -*- coding: utf-8 -*-
from __future__ import unicode_literals

from django.db import models, migrations


class Migration(migrations.Migration):

    dependencies = [
        ('hardware', '0001_initial'),
    ]

    operations = [
        migrations.AlterField(
            model_name='tlc59711chan',
            name='chanNum',
            field=models.IntegerField(choices=[(26, 'R0'), (24, 'G0'), (22, 'B0'), (20, 'R1'), (18, 'G1'), (16, 'B1'), (14, 'R2'), (12, 'G2'), (10, 'B2'), (8, 'R3'), (6, 'G3'), (4, 'B3')], default=0),
            preserve_default=True,
        ),
    ]
