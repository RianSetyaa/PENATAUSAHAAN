<?php

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit('Webhook only');
}

$output = shell_exec(
    'cd /home/simtkdco/public_html && git pull origin main 2>&1'
);

echo '<pre>'.$output.'</pre>';

?>