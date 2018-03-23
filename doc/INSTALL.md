INSTALL
=======

eZ Publish
----------
- Enable extension
- Regenerate autoloads mapping file
- Clear cache
- edit the configuration in the extension mugo_varnish
  mugo_varnish.ini -- see inline doc for more details
- Make sure that content caching is enabled
   
Note: This extension is not working with very old version of eZ Publish. The static content cache handling is not flexible enough in those old versions of eZ Publish

Varnish
-------
In your varnish config file:

Add ACLs (root context - Varnish 2.1 and 3.0 syntax)
```
acl purge
{
	"127.0.0.1";
}
```
Add following varnish config to the top of the _sub vcl_recv_ routine

Varnish >= 3.0 syntax:
```
if( req.request == "PURGE" )
{
    # Limit access for security reasons
    if( !client.ip ~ purge )
    {
        error 405 "Not allowed.";
    }

    if( req.http.X-Ban-Condition )
    {
        ban( req.http.X-Ban-Condition );
        error 200 "Purged: " + req.http.X-Ban-Condition;
    }

    error 500 "Missing X-Ban-Condition header.";
}
```       

You need to store the URL on the cache object - ban lurker friendly
(see https://www.varnish-cache.org/docs/3.0/tutorial/purging.html#bans).
Therefore add the following in the _sub vcl_fetch_:
```
set beresp.http.X-Ban-Url = req.url;
set beresp.http.X-Ban-Host = req.http.host;
```
     
And to avoid adding an unneeded header to the response, add the following in the _sub vcl_deliver_.
It also contains optional debug config.

```
sub vcl_deliver
{
    # Comment out if you'd like to send those response headers
    unset resp.http.X-Ban-Url;
    unset resp.http.X-Ban-Host;

    # Debug headers    
    set resp.http.X-Served-By = server.hostname;
    if (obj.hits > 0)
    {
        set resp.http.X-Cache = "HIT";	
        set resp.http.X-Cache-Hits = obj.hits;
    }
    else
    {
        set resp.http.X-Cache = "MISS";	
    }
}
```

Control varnish caching
----
There are many ways to define if and how long a request is cached in varnish.
A simple way is to send the Cache-Control HTTP header. In eZ Publish you can
configure the site.ini file to send such a header:
```
[HTTPHeaderSettings]
CustomHeader=enabled
Cache-Control[]
Cache-Control[/]=public, must-revalidate, max-age=600
```
