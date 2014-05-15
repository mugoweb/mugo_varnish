<?php

class MugoVarnishEvents
{	
	/**
	 * ezpEvent listener which sets a vuserhash cookie. Please note: it is NOT SECURE
	 * to rely on a client cookie for the user hash. DO NOT USE this feature if you have sensible
	 * data shown on the public siteaccess.
	 * 
	 * @param string $output
	 * @return string
	 */
	public static function preoutput( $output )
	{
		$wwwDir = eZSys::wwwDir();
		// On host based site accesses this can be empty, causing the cookie to be set for the current dir,
		// but we want it to be set for the whole eZ publish site
		$cookiePath = $wwwDir != '' ? $wwwDir : '/';
		
		setcookie( 'vuserhash', self::getUserHash(), 0, $cookiePath );

		return $output;
	}
	
	/**
	 * Gets an instance of the StaticCacheHandler and ask it for the user hash.
	 * 
	 * @return string
	 */
	static function getUserHash()
	{
		$optionArray = array( 'iniFile'      => 'site.ini',
							  'iniSection'   => 'ContentSettings',
							  'iniVariable'  => 'StaticCacheHandler' );

		$options = new ezpExtensionOptions( $optionArray );

		$staticCacheHandler = eZExtension::getHandlerClass( $options );

		return $staticCacheHandler->getUserHash();
	}
}
