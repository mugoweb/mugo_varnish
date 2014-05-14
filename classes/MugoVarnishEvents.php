<?php

class MugoVarnishEvents
{
	/**
	 * ezpEvent listener which sets/unsets a vuserhash cookie. Please note: it is NOT SECURE
	 * to rely on a client cookie for the userhash. DO NOT USE this feature if you have sensible
	 * data shown on the public siteaccess.
	 * 
	 * @param string $oldSession
	 * @param string $newSession
	 */
	public static function regenerateSession( $oldSession, $newSession )
	{
		$wwwDir = eZSys::wwwDir();
		// On host based site accesses this can be empty, causing the cookie to be set for the current dir,
		// but we want it to be set for the whole eZ publish site
		$cookiePath = $wwwDir != '' ? $wwwDir : '/';

		if( eZUser::isCurrentUserRegistered() )
		{
			setcookie( 'vuserhash', self::getUserHash( $newSession ), 0, $cookiePath );
		}
		else
		{
			//removes cookie
			setcookie( 'vuserhash', '0', 1, $cookiePath );
		}
	}
	
	/**
	 * Gets an instance of the StaticCacheHandler and ask it for the user hash.
	 * 
	 * @param string $newSession
	 * @return string
	 */
	static function getUserHash( $newSession )
	{
		$optionArray = array( 'iniFile'      => 'site.ini',
							  'iniSection'   => 'ContentSettings',
							  'iniVariable'  => 'StaticCacheHandler' );

		$options = new ezpExtensionOptions( $optionArray );

		$staticCacheHandler = eZExtension::getHandlerClass( $options );

		return $staticCacheHandler->getUserHash( $newSession );
	}
}
