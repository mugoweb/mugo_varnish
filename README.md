mugo_varnish
============
This extension integrates eZ Publish operations with Varnish; for example, it will purge the relevant Varnish caches when content is edited in eZ Publish

Big Picture
============

The extension only helps to purge varnish cache. So it expects a site that is already configured to run with
varnish. In other words, this extension will NOT handle what's getting cached in varnish and for how long but
it helps to purge varnish cache:

There are 3 user actions that trigger a purge:
1) It automatically purge cache after updating a content node (purge on publish)
2) It allows administrators to submit a list of URLs or regular expressions to purge varnish cache
3) User clears all "Static" cache in the admin UI (the CLI script might trigger it as well)


Purge on publish
=================
The extension code hocks into the ContentCache handling. After publishing a node, eZ Publish ContentCache
handling is smart enough to compile a list of related node ids.
The ContentCache manager submits all compiled node ids to a StaticCache handler. The StaticCache handler is responsible to resolve
the node ids to URLs and then purge those URLs in Varnish.

A node id is resolved to

a) Node URL
b) System URLs (optional)
c) Object translations URLs
d) Custom URL aliases

The process to resolve a node id to URLs is configurable to incoperate different system setups. See mugo_varnish.ini for details.

The StaticCache handler collects those URLs and register a CleanUp handler that gets executed at the end of the pageload.
The CleanUp handler loops over all collected URLs and uses the VarnishPurger to send the purge requests to varnish servers.

The varnish configuration knows 3 different ways to purge cache:

1) Purge a given URL
2) Purge a given Regular Expresion
3) Purge a given list of URLs

In case of 1) and 3) the varnish configuration is building 2 Regular Expressions for each given URL. Here an example:

Given URL: /about_us/contact

1st RegEx: ^/about_us/contact$

2nd RegEx: ^/about_us/contact/\\(

The 2nd rule is responsible to clear all view parameter variations for a node.


Cache variations based on an user hash
=======================================
This is an optional feature and ONLY works for some websites. The idea is to have different cache object in varnish
based on the logged in user. The user differ in the roles (permissions) they have assigned to them. Based on those
roles, you can build a user hash and use it for the cache object varations in varnish.

There are 2 parts to this feature:
1) You need to have a special varnish configuration. An example is in doc/examples/userhash.vcl
2) You need to enable an ezpEvent listener that will calculate and write the user hash whenever ez publish creates a
new session ID. Please see site.ini and classes/MugoVarnishEvents.php

Why does it only works for some websites? Well, it's not secure -- you cannot rely on a client cookie for the roles
and permissions. It's hard/impossible to adjust the user hash cookie - but you could save and share a user hash cookie
with other users.

Installation instructions
==
[See doc/INSTALL.md](doc/INSTALL.md)