<?php 
// conn.php    register_shutdown_function 
register_shutdown_function(function() {
    error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);
    ini_set("display_errors", 0);
});
//  
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);
ini_set("display_errors", 0);
?>
