<?php

return [
    'server_key' => env('FCM_SERVER_KEY', ''),
    'sender_id' => env('FCM_SENDER_ID', ''),
    'endpoint' => env('FCM_ENDPOINT', 'https://fcm.googleapis.com/fcm/send'),
];
