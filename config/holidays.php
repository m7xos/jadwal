<?php

return [
    // Sumber kalender hari libur (termasuk cuti bersama) dalam format ICS.
    'source' => 'https://calendar.google.com/calendar/ical/id.indonesian%23holiday%40group.v.calendar.google.com/public/basic.ics',
    'cache_ttl' => 43200,
    'dates' => [
        // Fallback manual (opsional) format Y-m-d, contoh: '2025-01-01',
    ],
];
