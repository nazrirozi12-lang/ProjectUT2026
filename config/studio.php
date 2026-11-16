<?php

return [
    // Jam mulai sesi yang ditawarkan setiap hari (format JJ.MM, WIB)
    'slots' => ['09.00', '13.00', '15.30'],

    // Pemesanan paling cepat H+N dari hari ini, dan paling jauh N bulan ke depan
    'min_lead_days' => 1,
    'max_months_ahead' => 4,

    // Status pesanan yang masih "memakai" slot jadwal
    'occupying_statuses' => ['Permintaan', 'Dikonfirmasi', 'Selesai'],
];
