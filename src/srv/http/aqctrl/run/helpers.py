# Some general purpose functions that we expect to use often.

def adjVal(inVal, thisMin, thisMax, thisScale, thisInvert, thisVariable):
  #Returns the adjusted value and a boolean if the max/min was applied.
  # as [value, adjusted?]
  #Immediately return is the input is None.
  if inVal is None:
   return [inVal, True]

  wasMaxMin = False
  #If not variable, force the inval to either the max or the min, depending on which is closer.
  if not thisVariable:
    dMax = abs(inVal - thisMax)
    dMin = abs(inVal - thisMin)
    if dMax > dMin:
      v = thisMin
    else:
      v = thisMax

  else:
    #First, scale
    v = inVal*thisScale
    #Second, apply min/max
    if v > thisMax:
      v = thisMax
      wasMaxMin = True
    elif v < thisMin:
      v = thisMin
      wasMinMax = True

  #Third, invert if needed
  if thisInvert:
    v = -v + thisMax + thisMin

  return [v, wasMaxMin]


def doInterp(x, pr0, pr1):
  if x == pr1['x']: return pr1['y']
  if x == pr0['x']: return pr0['y']
  if pr0['y'] == pr1['y']: return pr0['y']
  if pr0['x'] == pr1['x']: return pr0['y']
  a = (pr1['y']-pr0['y'])/(pr1['x']-pr0['x'])
  b = pr1['y']-a*pr1['x']
  return a*x+b

def cToF(c):
  if c is None: return None
  return (c*9/5)+32

def fToC(f):
  if f is None: return None
  return (f-32)*5/9

def findXscale(start, end, scaleX, units='Seconds'):
  if not scaleX:
    return 1, 'Seconds'

  if scaleX is not True:
    return scaleX, units

  #We got a "True" and need to figure out what number to use.
  d = abs(end-start)
  thres = 2
  if d<60*thres: #not enough to go to minutes.
    return 1, 'Seconds'
  elif d<60*60*thres: #go to minutes
    return 1/60, 'Minutes'
  elif d<60*60*24*thres: #go to hours
    return 1/(60*60), 'Hours'
  elif d<60*60*24*365*thres: #go to days
    return 1/(60*60*24), 'Days'
  else: #go to years
    return 1/(60*60*24*365), 'Years'
