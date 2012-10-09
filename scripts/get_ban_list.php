<?php 

$host = '127.0.0.1';
$port = 6082;

$fp = fsockopen( $host, $port, $errno, $errstr, 3);

if( !$fp )
{
	echo "$errstr ($errno)<br />\n";
}
else
{
    $out = 'ban.list' . "\n";
    $out .= 'quit' . "\n";

    fwrite( $fp, $out );
    
    while( !feof( $fp ) )
    {
        echo fgets($fp, 128);
    }
    
    fclose($fp);
}

?>
