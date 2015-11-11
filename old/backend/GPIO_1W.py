#Module to initialize, setup, and read the one wire protocol on a beagle bone black
fileinfo = 



class OneWire():
    hwid = 'P8_1'


    def start(self):
        #Output a dts file, compile it, and load it.
        #There's probably a better way to do this....
        fA = '''/dts-v1/;
/plugin/;

/ {
    compatible = "ti,beaglebone", "ti,beaglebone-black";

    part-number = "BB-W1";
    version = "00A0";

    /* state the resources this cape uses */
    exclusive-use =
        /* the pin header uses */'''
        fB = ''',
        /* the hardware IP uses */'''

        fC = ''';

        fragment@0 {
            target = <&am33xx_pinmux>;
               __overlay__ {
                   dallas_w1_pins: pinmux_dallas_w1_pins {
                       pinctrl-single,pins = <0x150 0x37>;
                       };
               };
        };

        fragment@1 {
            target = <&ocp>;
            __overlay__ {
                onewire@0 {
                    compatible      = "w1-gpio";
                    pinctrl-names   = "default";
                    pinctrl-0       = <&dallas_w1_pins>;
                    status          = "okay";

                    gpios = <&gpio1 2 0>;
                };
            };
        };
};'''
