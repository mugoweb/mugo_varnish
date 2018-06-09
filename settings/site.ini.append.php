<?php
/* #?ini charset="utf-8"?

[ContentSettings]
StaticCache=enabled
StaticCacheHandler=StaticCacheMugoVarnish

[Event]
# Use these event listeners if you would like to set the vuserhash cookie.
# For more details see
# - the example varnish config file in doc/examples/userhash.vcl
# - a blog post describing the feature
# https://www.mugo.ca/Blog/Varnish-caching-of-non-sensitive-content-for-logged-in-users
#Listeners[]=response/preoutput@MugoVarnishEvents::preoutput
#Listeners[]=session/regenerate@MugoVarnishEvents::regenerateSession

# Use this listener to append custom http header X-Location-Id in node view
# @see https://www.mugo.ca/Blog/Built-in-Varnish-Cache-purge-on-publish-support-in-eZ-Publish
#Listeners[]=content/view@MugoVarnishEvents::addXLocationIdHeader

# Use this listener to clear all content cache (only from web interface)
#Listeners[]=content/cache/all@MugoVarnishEvents::purgeAll

*/ ?>

