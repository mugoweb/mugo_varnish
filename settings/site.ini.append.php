<?php /*

[ContentSettings]
StaticCache=enabled
StaticCacheHandler=StaticCacheMugoVarnish

# Use these event listeners if you would like to set the vuserhash cookie.
# For more details see
# - the example varnish config file in doc/examples/userhash.vcl
# - a blog post describing the feature
# https://www.mugo.ca/Blog/Varnish-caching-of-non-sensitive-content-for-logged-in-users
[Event]
#Listeners[]=response/preoutput@MugoVarnishEvents::preoutput
#Listeners[]=session/regenerate@MugoVarnishEvents::regenerateSession

*/
