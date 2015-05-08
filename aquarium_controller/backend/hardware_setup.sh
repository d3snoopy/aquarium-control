#!/bin/sh

dtc -O dtb -o AQ-SPI0-00A0.dtbo -b 0 -@ AQ-SPI0-00A0.dts
dtc -O dtb -o AQ-W1-00A0.dtbo -b 0 -@ AQ-W1-00A0.dts

cp AQ-SPI0-00A0.dtbo /lib/firmware
cp AQ-W1-00A0.dtbo /lib/firmware

echo AQ-SPI0 > /sys/devices/bone_capemgr.9/slots
echo AQ-W1 > /sys/devices/bone_capemgr.9/slots


