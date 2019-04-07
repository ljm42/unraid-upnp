# Test Case - System A

## Environment

- Client: Unraid with miniupnpc-2.1-x86_64-1_SBo (from /var/log/packages)
- Server: OPNsense 19.1.4 with miniupnpd 2.0.20180503

## Test Case 1

### upnpd disabled on the router, no XML specified

Command

    time upnpc -m br0 -l 2>&1

Results

    upnpc : miniupnpc library test client, version 2.1.
    (c) 2005-2018 Thomas Bernard.
    Go to http://miniupnp.free.fr/ or https://miniupnp.tuxfamily.org/
    for more information.
    List of UPNP devices found on the network :
    desc: http://192.168.10.18:8060/
    st: upnp:rootdevice

    desc: http://192.168.10.19:80/dms/device.xml
    st: upnp:rootdevice

    desc: http://192.168.10.125:50201/dial.xml
    st: upnp:rootdevice

    UPnP device found. Is it an IGD ? : http://192.168.10.18:8060/
    Trying to continue anyway
    Local LAN ip address : unset
    GetConnectionTypeInfo failed.
    GetStatusInfo failed.
    GetLinkLayerMaxBitRates failed.
    GetExternalIPAddress failed. (errorcode=-3)
    i protocol exPort->inAddr:inPort description remoteHost leaseTime
    GetGenericPortMappingEntry() returned -3 (UnknownError)

    real    0m9.791s
    user    0m0.000s
    sys     0m0.006s

## Test Case 2

### upnpd disabled on the router, previously valid XML specified

Command

    time upnpc -u http://192.168.10.1:2189/rootDesc.xml -m br0 -l 2>&1

Results

    upnpc : miniupnpc library test client, version 2.1.
    (c) 2005-2018 Thomas Bernard.
    Go to http://miniupnp.free.fr/ or https://miniupnp.tuxfamily.org/
    for more information.
    ^C

    real    1m33.372s
    user    0m0.000s
    sys     0m0.001s

## Test Case 3

### upnpd disabled on the router, invalid valid XML specified

Command

    time upnpc -u http://192.168.10.2:2189/rootDesc.xml -m br0 -l 2>&1

Results

    upnpc : miniupnpc library test client, version 2.1.
    (c) 2005-2018 Thomas Bernard.
    Go to http://miniupnp.free.fr/ or https://miniupnp.tuxfamily.org/
    for more information.
    connect: No route to host
    No valid UPNP Internet Gateway Device found.

    real    0m3.085s
    user    0m0.000s
    sys     0m0.001s

## Test Case 4

### upnpd enabled on the router, no XML specified

Command

    time upnpc -m br0 -l 2>&1

Results

    upnpc : miniupnpc library test client, version 2.1.
    (c) 2005-2018 Thomas Bernard.
    Go to http://miniupnp.free.fr/ or https://miniupnp.tuxfamily.org/
    for more information.
    List of UPNP devices found on the network :
    desc: http://192.168.10.1:2189/rootDesc.xml
    st: urn:schemas-upnp-org:device:InternetGatewayDevice:1

    Found valid IGD : http://192.168.10.1:2189/ctl/IPConn
    Local LAN ip address : 192.168.10.188
    Connection Type : IP_Routed
    Status : Connected, uptime=61401s, LastConnectionError : ERROR_NONE
      Time started : Sat Apr  6 16:39:36 2019
    MaxBitRateDown : 1000000000 bps (1000.0 Mbps)   MaxBitRateUp 1000000000 bps (1000.0 Mbps)
    ExternalIPAddress = X.X.X.X
    i protocol exPort->inAddr:inPort description remoteHost leaseTime
    0 UDP 60005->192.168.10.188:60005 'libminiupnpc' '' 0
    1 UDP 60009->192.168.10.188:60009 'libminiupnpc' '' 0
    2 UDP 60010->192.168.10.188:60010 'libminiupnpc' '' 0
    3 UDP 60011->192.168.10.188:60011 'libminiupnpc' '' 0
    4 UDP 60012->192.168.10.188:60012 'libminiupnpc' '' 0
    5 UDP 60013->192.168.10.188:60013 'libminiupnpc' '' 0
    6 TCP 10481->192.168.10.51:32400 'Plex Media Server' '' 602820
    GetGenericPortMappingEntry() returned 713 (SpecifiedArrayIndexInvalid)

    real    0m2.067s
    user    0m0.000s
    sys     0m0.004s

## Test Case 5

### upnpd enabled on the router, valid XML specified

Command

    time upnpc -u http://192.168.10.1:2189/rootDesc.xml -m br0 -l 2>&1

Results

    upnpc : miniupnpc library test client, version 2.1.
    (c) 2005-2018 Thomas Bernard.
    Go to http://miniupnp.free.fr/ or https://miniupnp.tuxfamily.org/
    for more information.
    Found valid IGD : http://192.168.10.1:2189/ctl/IPConn
    Local LAN ip address : 192.168.10.188
    Connection Type : IP_Routed
    Status : Connected, uptime=61434s, LastConnectionError : ERROR_NONE
      Time started : Sat Apr  6 16:39:36 2019
    MaxBitRateDown : 1000000000 bps (1000.0 Mbps)   MaxBitRateUp 1000000000 bps (1000.0 Mbps)
    ExternalIPAddress = X.X.X.X
    i protocol exPort->inAddr:inPort description remoteHost leaseTime
    0 UDP 60005->192.168.10.188:60005 'libminiupnpc' '' 0
    1 UDP 60009->192.168.10.188:60009 'libminiupnpc' '' 0
    2 UDP 60010->192.168.10.188:60010 'libminiupnpc' '' 0
    3 UDP 60011->192.168.10.188:60011 'libminiupnpc' '' 0
    4 UDP 60012->192.168.10.188:60012 'libminiupnpc' '' 0
    5 UDP 60013->192.168.10.188:60013 'libminiupnpc' '' 0
    6 TCP 10481->192.168.10.51:32400 'Plex Media Server' '' 602787
    GetGenericPortMappingEntry() returned 713 (SpecifiedArrayIndexInvalid)

    real    0m0.010s
    user    0m0.000s
    sys     0m0.004s

## Test Case 6

### upnpd enabled on the router, invalid valid XML specified

Command

    time upnpc -u http://192.168.10.2:2189/rootDesc.xml -m br0 -l 2>&1

Results

    upnpc : miniupnpc library test client, version 2.1.
    (c) 2005-2018 Thomas Bernard.
    Go to http://miniupnp.free.fr/ or https://miniupnp.tuxfamily.org/
    for more information.
    connect: No route to host
    No valid UPNP Internet Gateway Device found.

    real    0m3.108s
    user    0m0.000s
    sys     0m0.001s
