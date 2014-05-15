#
# This is ONLY an example configuration. The import part is in sub vcl_hash:
# The config reads a cookie key 'vuserhash' and creates cache object variations
# based on the cookie value.
#

# Uncomment this if you need to write to the syslog file
#import std;

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
    # But we exclude page assests like images, css and javascript
    if( req.url !~ "\.(jpeg|jpg|png|gif|ico|swf|js|css)(\?.*|)$" && req.http.cookie ~ "vuserhash=" ) {
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


sub vcl_fetch {
    
    # Backend only sends vuserhash cookie for HTML responses
    if( beresp.http.set-cookie ~ ".*vuserhash=([^;]+);.*" )
    {
        # Check if client has invalid user hash value
        # Comparing client cookie with server response cookie
        if( regsub( beresp.http.set-cookie, ".*vuserhash=([^;]+);.*", "\1" ) != regsub( req.http.cookie, ".*vuserhash=([^;]+);.*", "\1" ) )
        {
            #std.syslog(180, "VARNISH: Invalid cookie found." );
            return( hit_for_pass );
        }
        else
        {
            # Making sure object gets cached -- even with set-cookie header
            return( deliver );
        }
    }

    #no return value in order to trigger default behavior
}
