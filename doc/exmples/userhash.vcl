#
# This is ONLY an example configuration. The import part is in sub vcl_hash:
# The config reads a cookie key 'vuserhash' and creates cache object variations
# based on the cookie value.
#

backend default {
     .host = "127.0.0.1";
     .port = "80";
}
 
sub vcl_recv {
  if (req.restarts == 0) {
 	if (req.http.x-forwarded-for) {
 	    set req.http.X-Forwarded-For =
 		req.http.X-Forwarded-For + ", " + client.ip;
 	} else {
 	    set req.http.X-Forwarded-For = client.ip;
 	}
  }
     if (req.request != "GET" &&
       req.request != "HEAD" &&
       req.request != "PUT" &&
       req.request != "POST" &&
       req.request != "TRACE" &&
       req.request != "OPTIONS" &&
       req.request != "DELETE") {
         /* Non-RFC2616 or CONNECT which is weird. */
         return (pipe);
     }
     if (req.request != "GET" && req.request != "HEAD") {
         /* We only deal with GET and HEAD by default */
         return (pass);
     }
     if (req.http.Authorization ) {
         /* Not cacheable by default */
         return (pass);
     }
     return (lookup);
}
 
sub vcl_hash {
    hash_data(req.url);

    if (req.http.host) {
        hash_data(req.http.host);
    } else {
        hash_data(server.ip);
    }

    # Cache variations based on a provided cookie value
    if (req.http.cookie ~ "vuserhash=") {
        hash_data( regsub( req.http.cookie, ".*vuserhash=([^;]+);.*", "\1" ) );
    }

    return (hash);
}
 
sub vcl_deliver {
  set resp.http.X-Served-By = server.hostname;
  if (obj.hits > 0) {
    set resp.http.X-Cache = "HIT";	
    set resp.http.X-Cache-Hits = obj.hits;
  } else {
    set resp.http.X-Cache = "MISS";	
  }

  return( deliver );
}
 