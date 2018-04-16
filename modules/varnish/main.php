<?php 

$tpl = eZTemplate::factory();

$purgeConditions = array();

if( isset( $_REQUEST[ 'urllist' ] ) )
{
    $lines = explode( "\n", $_REQUEST[ 'urllist' ] );
    
    if( !empty( $lines ) )
    {
        foreach( $lines as $line )
        {
            if( trim( $line ) )
            {
                $purgeConditions[] = trim( $line );
            }
        }
    }
}

$vp = VarnishPurger::Instance();

if( !empty( $purgeConditions ) )
{
    foreach( $purgeConditions as $condition )
    {
        $vp->purge( $condition );
    }
}

$tpl->setVariable( 'purged_urls', $purgeConditions );

$Result = array();
$Result[ 'content' ] = $tpl->fetch( 'design:modules/varnish/main.tpl' );
$Result[ 'left_menu' ] = false;
$Result[ 'path' ] =
    array(
        array( 'url' => false,
               'text' => 'Varnish' ),
        array( 'url' => false,
               'text' => 'Main' )
);
