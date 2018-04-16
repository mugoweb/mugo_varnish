<div class="ui-varnish-main">
    <h1>Purge Varnish Cache</h1>
    
    {if count( $purged_urls )}
        <h2>Purged URLs</h2>
        <p style="color: red; background-color: #eee; padding: 7px; border: 1px solid black;">
            {foreach $purged_urls as $url}
                {$url|wash()}<br />
            {/foreach}
        </p>
    {/if}

    <div>
        <form method="post" action={'/varnish/main'|ezurl()}>

            <p>
                Specify purge conditions. Examples:
            </p>
            <ul>
                <li>obj.http.X-Ban-Url ~ ^/.*</li>
                <li>obj.http.X-Ban-Url ~ ^/Blog/.* && obj.http.X-Ban-Host ~ www\.mugo\.ca</li>
            </ul>
            <textarea name="urllist" style="width: 100%; height: 200px;"></textarea>

            <div style="float: right">
                <input class="defaultbutton" type="submit" value="Submit" />
            </div>
        </form>
    </div>
</div>