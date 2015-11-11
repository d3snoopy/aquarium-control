# -*- coding: utf-8 -*-
from __future__ import unicode_literals

from django.db import models, migrations


class Migration(migrations.Migration):

    dependencies = [
    ]

    operations = [
        migrations.CreateModel(
            name='Input',
            fields=[
                ('id', models.AutoField(primary_key=True, verbose_name='ID', auto_created=True, serialize=False)),
                ('hwType', models.IntegerField(choices=[(0, 'BBB GPIO In'), (1, 'BBB Analog In'), (2, 'BBB Counter In'), (3, 'Dallas W1 In')], default=0)),
            ],
            options={
            },
            bases=(models.Model,),
        ),
        migrations.CreateModel(
            name='Output',
            fields=[
                ('id', models.AutoField(primary_key=True, verbose_name='ID', auto_created=True, serialize=False)),
                ('hwType', models.IntegerField(choices=[(0, 'BBB PWM Out'), (1, 'BBB GPIO Out'), (2, 'SPI TLC59711 Out')], default=0)),
            ],
            options={
            },
            bases=(models.Model,),
        ),
        migrations.CreateModel(
            name='TLC59711Chan',
            fields=[
                ('id', models.AutoField(primary_key=True, verbose_name='ID', auto_created=True, serialize=False)),
                ('devNum', models.IntegerField(default=0)),
                ('chanNum', models.IntegerField(choices=[(12, 'R0'), (11, 'G0'), (10, 'B0'), (9, 'R1'), (8, 'G1'), (7, 'B1'), (6, 'R2'), (5, 'G2'), (4, 'B2'), (3, 'R3'), (2, 'G3'), (1, 'B3')], default=0)),
                ('out', models.OneToOneField(to='hardware.Output')),
            ],
            options={
            },
            bases=(models.Model,),
        ),
    ]
