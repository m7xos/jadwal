<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('wa-inbox', function ($user) {
    return $user !== null;
});
