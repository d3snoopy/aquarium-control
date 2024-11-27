CREATE TABLE IF NOT EXISTS AQhost(
    rowid INTEGER PRIMARY KEY,
    readInt INT DEFAULT 60,
    writeInt INT DEFAULT 60,
    checkInt INT DEFAULT 600,
    hostKey TEXT,
    tempUnits INT DEFAULT 1,
    writeLog INT DEFAULT 4,
    readLog INT DEFAULT 4,
    reactLog INT DEFAULT 4,
    lastUpdate INT,
    dbVersion INT DEFAULT 0);
CREATE TABLE IF NOT EXISTS chanType(
    rowid INTEGER PRIMARY KEY,
    chTypeName TEXT,
    chLevelCtl INT DEFAULT 0,
    chVariable INT DEFAULT 1,
    typeInput INT DEFAULT 1,
    chInvert INT DEFAULT 0,
    hideInvert INT DEFAULT 0,
    chMax NUM DEFAULT 1,
    chMin NUM DEFAULT 0,
    chInitialVal NUM DEFAULT 0,
    chScalingFactor NUM DEFAULT 1,
    chUnits TEXT,
    typeTemp INT DEFAULT 0,
    schedType INT DEFAULT 0);
CREATE TABLE IF NOT EXISTS AQchannel(
    rowid INTEGER PRIMARY KEY,
    AQident TEXT,
    AQname TEXT,
    chActive INT DEFAULT 1,
    chColor TEXT,
    chInput INT DEFAULT 1,
    chType INT,
    chDevice INT,
    FOREIGN KEY (chType) REFERENCES chanType(rowid) ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (chDevice) REFERENCES hostDevice(rowid) ON DELETE SET NULL ON UPDATE CASCADE);
CREATE TABLE IF NOT EXISTS AQsource(
    rowid INTEGER PRIMARY KEY,
    AQname TEXT,
    srcScale NUM DEFAULT 1,
    chType INT,
    FOREIGN KEY (chType) REFERENCES chanType(rowid) ON DELETE SET NULL ON UPDATE CASCADE);
CREATE TABLE IF NOT EXISTS AQfunction(
    rowid INTEGER PRIMARY KEY,
    AQname TEXT);
CREATE TABLE IF NOT EXISTS AQreactGrp(
    rowid INTEGER PRIMARY KEY,
    AQname TEXT,
    outChan INT,
    grpBehave INT DEFAULT 0,
    grpDetect INT DEFAULT 0,
    FOREIGN KEY (outChan) REFERENCES AQchannel(rowid) ON DELETE SET NULL ON UPDATE CASCADE);
CREATE TABLE IF NOT EXISTS AQreaction(
    rowid INTEGER PRIMARY KEY,
    criteriaType INT,
    triggerValue NUM,
    rctScale NUM DEFAULT 1,
    rctOffset NUM DEFAULT 0,
    rctDuration NUM,
    willExpire INT DEFAULT 1,
    rctFunct INT,
    monChan INT,
    reactGrp INT,
    FOREIGN KEY (rctFunct) REFERENCES AQfunction(rowid) ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (monChan) REFERENCES AQchannel(rowid) ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (reactGrp) REFERENCES AQreactGrp(rowid) ON DELETE CASCADE ON UPDATE CASCADE);
CREATE TABLE IF NOT EXISTS FnPoint(
    rowid INTEGER PRIMARY KEY,
    ptValue NUM,
    timePct NUM,
    timeOffset NUM,
    timeSE INT,
    parentFn INT,
    FOREIGN KEY (parentFn) REFERENCES AQfunction(rowid) ON DELETE CASCADE ON UPDATE CASCADE);
CREATE TABLE IF NOT EXISTS AQprofile(
    rowid INTEGER PRIMARY KEY,
    profStart INT,
    profEnd INT,
    profRefresh INT,
    parentSource INT,
    parentFunction INT,
    parentReaction INT,
    FOREIGN KEY (parentSource) REFERENCES AQsource(rowid) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (parentReaction) REFERENCES AQreaction(rowid) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (parentFunction) REFERENCES AQfunction(rowid) ON DELETE CASCADE ON UPDATE CASCADE);
CREATE TABLE IF NOT EXISTS AQCPS(
    rowid INTEGER PRIMARY KEY,
    CPSscale NUM DEFAULT 1,
    CPStoff NUM DEFAULT 0,
    CPSchan INT,
    CPSprof INT,
    CPSsrc INT,
    FOREIGN KEY (CPSchan) REFERENCES AQchannel(rowid) ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (CPSprof) REFERENCES AQprofile(rowid) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (CPSsrc) REFERENCES AQsource(rowid) ON DELETE CASCADE ON UPDATE CASCADE);
CREATE TABLE IF NOT EXISTS AQdata(
    rowid INTEGER PRIMARY KEY,
    dataDate NUM,
    logType INT,
    dataVal NUM,
    dataTimebase NUM,
    dataChannel INT,
    FOREIGN KEY (dataChannel) REFERENCES AQchannel(rowid) ON DELETE CASCADE ON UPDATE CASCADE);
CREATE TABLE IF NOT EXISTS AQlog(
    rowid INTEGER PRIMARY KEY,
    logDate NUM,
    assocChan INT,
    assocDev INT,
    logEntry TEXT,
    entryRead INT DEFAULT 0,
    FOREIGN KEY (assocChan) REFERENCES AQchannel(rowid) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (assocDev) REFERENCES hostDevice(rowid) ON DELETE SET NULL ON UPDATE CASCADE);
CREATE TABLE IF NOT EXISTS AQtoken(
    rowid INTEGER PRIMARY KEY,
    tokenTime,
    tokenVal TEXT);
CREATE TABLE IF NOT EXISTS hostDevice(
    rowid INTEGER PRIMARY KEY,
    busType INT,
    devType INT,
    busOrder INT,
    busAddr TEXT,
    scaleFactor NUM DEFAULT 1,
    maxVal NUM DEFAULT 1,
    minVal NUM DEFAULT 0,
    invert INT DEFAULT 0);
CREATE TABLE IF NOT EXISTS AQuser(
    rowid INTEGER PRIMARY KEY,
    userName TEXT,
    passHash TEXT,
    passSalt TEXT,
    authLevel INT);
CREATE TABLE IF NOT EXISTS AQplot(
    rowid INTEGER PRIMARY KEY,
    AQname TEXT,
    onHome INT,
    relStart INT,
    relEnd INT);
CREATE TABLE IF NOT EXISTS plotChan(
    rowid INTEGER PRIMARY KEY,
    AQplot INT,
    chanNum INT,
    axisNum INT,
    FOREIGN KEY (AQplot) REFERENCES AQplot(rowid) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (chanNum) REFERENCES AQchannel(rowid) ON DELETE SET NULL ON UPDATE CASCADE);
CREATE TABLE IF NOT EXISTS homeChan(
    rowid INTEGER PRIMARY KEY,
    chanNum INT,
    FOREIGN KEY (chanNum) REFERENCES AQchannel(rowid) ON DELETE CASCADE ON UPDATE CASCADE);
CREATE TABLE IF NOT EXISTS homeButton(
    rowid INTEGER PRIMARY KEY,
    AQname TEXT);
CREATE TABLE IF NOT EXISTS buttonChan(
    rowid INTEGER PRIMARY KEY,
    homeButton INT,
    outChan INT,
    behave INT,
    duration INT,
    AQfunction INT,
    scale NUM,
    FOREIGN KEY (homeButton) REFERENCES homeButton(rowid) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (outChan) REFERENCES AQchannel(rowid) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (AQfunction) REFERENCES AQfunction(rowid) ON DELETE SET NULL ON UPDATE CASCADE);
