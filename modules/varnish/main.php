<?php 

$tpl = eZTemplate::factory();

$purge_urls = array();
$purge_reg = null;

if( isset( $_REQUEST[ 'urllist' ] ) )
{
    $lines = explode( "\n", $_REQUEST[ 'urllist' ] );
    
    if( !empty( $lines ) )
    {
        foreach( $lines as $line )
        {
            if( trim( $line ) )
            {
                $purge_urls[] = '/' . preg_replace( '/^https?:\/\/.*\//U', '', trim( $line ) );
            }
        }
    }
}

if( isset( $_REQUEST[ 'regex' ] ) )
{
    $regex = trim( $_REQUEST[ 'regex' ] );
    
    if( $regex )
    {
        $purge_reg = $regex;
    }
}


$vp = VarnishPurger::instance();

if( !empty( $purge_urls ) )
{
    foreach( $purge_urls as $url )
    {
        $vp->purge( $url );
    }
}

if( $purge_reg )
{
    $vp->purge( $purge_reg, true );
}

$tpl->setVariable( 'purged_urls', $purge_urls );
$tpl->setVariable( 'purged_reg',  $purge_reg );

$Result = array();
$Result['content'] = $tpl->fetch( "design:modules/varnish/main.tpl" );
$Result['left_menu'] = false;
$Result['path'] = array( array( 'url' => false,
                                'text' => 'Varnish' ),
                         array( 'url' => false,
                                'text' => 'Main' ) );
                       
?>