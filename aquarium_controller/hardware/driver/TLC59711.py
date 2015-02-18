import hardware.models

# Driver to send outputs to TLC59711's.
# Note: The set function expects to receive an spidev object to transfer to.
# Also, note: Currently written to handle onle one SPI channel.

# Map of the necessary data:
# Starting with the MSB:
# MSB -> LSB  224 bits
#Header:  (bits 224-213)
#100101  write command
#0 timing on falling edge (doesn’t really matter)
#0 internal clock
#0 timing reset disabled
#1 auto repeat enabled
#0 blanking disabled (output enabled)
#=(to be ored with next)
#
#Global Brightness Control (sink current control) – 3 banks, maximize all (bits 212-206, 205-199, 198-192)
#7 bits per bank
#1111111 x3
#
#Total header:  945FFFFF
#
#Level settings: (MSB: B3, G3, R3, … B0, G0, R0 LSB), bits 212-206, 205-199, …, 15-0
#
#Min: 0000, max: FFFF; since I’m driving a digital ctrl w/ pullup: FFFF = off; 0000 = 100%
#
#[FFFF] *  12

# Channel choices:
chanChoice=(
    (12, 'R0'),
    (11, 'G0'),
    (10, 'B0'),
    (9, 'R1'),
    (8, 'G1'),
    (7, 'B1'),
    (6, 'R2'),
    (5, 'G2'),
    (4, 'B2'),
    (3, 'R3'),
    (2, 'G3'),
    (1, 'B3'),
)
#note: the choice numbering is significant, it's the index when building
#the data for output.

def calc(invert=True, v=0):
    # Function to take a percentage input and outputs a Hex value to write.
    bits = 16

    if invert:
        v = 1-v

    n=hex(max(min(v,1),0)*(2**bits-1))

    return([int(n[:-2], 16), int(n[-2:], 16)])


def set(spi):
    # Header to write per device
    header = [0x94, 0x5F, 0xFF, 0xFF]
    # Invert logic since it's "on" pulls down
    invert = True
    # Number of channels per device
    numchan = 12
    # Num of bytes per device
    numDevBytes = 2*numchan + len(header)
    
    # Get the configured channels
    data = hardware.models.TLC59711Chan.objects.all().prefetch_related('out__channel')

    #Find out how many devices are on SPI0
    numDev = int(data.filter(SPIdev=0).order_by(-devNum)[0])+1

    #Seed the data with all channels set to off.
    out = (header + calc(invert)*numchan)*numSPI0

    #Iterate through each object and set the appropriate fields.
        for d in data:
            out((numDev-d.devNum-1)*numDevBytes+len(header)+2*d.chanNum-1) =
                calc(invert)[0]
            out((numDev-d.devNum-1)*numDevBytes+len(header)+2*d.chanNum) =
                calc(invert)[1]

    #Send the data down to the device.
    spi.xfer2(SPI0)

    return(SPI0)
