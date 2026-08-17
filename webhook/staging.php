<?php

$payload = file_get_contents('php://input');

$data = json_decode($payload, true);

if (!isset($data['ref'])) {
    exit('Invalid request');
}

if ($data['ref'] !== 'refs/heads/Staging') {
    exit('Not the staging branch');
}

$output = shell_exec(
    'cd /home/simtkdco/public_html/testing.simtkd.com && git pull origin Staging 2>&1'
);

file_put_contents(
    '/home/simtkdco/webhook/staging.log',
    date('Y-m-d H:i:s')."\n".$output."\n\n",
    FILE_APPEND
);

echo $output;