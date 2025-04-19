<?php
if (function_exists('mysqli_connect')) {
    echo "The mysqli extension is enabled and working.";
} else {
    echo "The mysqli extension is not enabled.";
}
phpinfo();
?>