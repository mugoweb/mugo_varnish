<?php /*

[ContentSettings]
StaticCache=enabled
StaticCacheHandler=StaticCacheMugoVarnish

# Use these event listeners if you would like to set the vuserhash cookie.
# For more details see the example varnish config file in doc/examples/userhash.vcl
[Event]
#Listeners[]=response/preoutput@MugoVarnishEvents::preoutput
#Listeners[]=session/regenerate@MugoVarnishEvents::regenerateSession

*/