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

	{if $purged_reg}
		<h2>Purged Regular Expression</h2>
		<p style="color: red; background-color: #eee; padding: 7px; border: 1px solid black;">
			{$purged_reg|wash()}<br />
		</p>
	{/if}
	
	<div style="width: 400px;">
		<form method="post" action={'/varnish/main'|ezurl()}>

			<label>List of URLs</label>
			<p>
				Specify full URLs like:<br />
				http://www.example.com/about/us
			</p>
			<textarea name="urllist" style="width: 100%; height: 200px;"></textarea>

			<label>Regular Expression</label>
			<p>
				Specify a regular expression that matches the entire URL path, excluding the host part:<br />
				^/about.*<br />
			</p>
			
			<input type="text" name="regex" style="width: 100%;" />

			<div style="float: right">
				<input type="submit" value="Submit" />
			</div>
		</form>
	</div>
</div>