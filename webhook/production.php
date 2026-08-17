<?php

$payload = file_get_contents('php://input');

$data = json_decode($payload, true);

if (!isset($data['ref'])) {
    exit('Invalid request');
}

if ($data['ref'] !== 'refs/heads/main') {
    exit('Not the main branch');
}

$output = shell_exec(
    'cd /home/simtkdco/public_html && git pull origin main 2>&1'
);

file_put_contents(
    '/home/simtkdco/webhook/production.log',
    date('Y-m-d H:i:s')."\n".$output."\n\n",
    FILE_APPEND
);

echo $output;