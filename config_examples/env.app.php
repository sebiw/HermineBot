<?php

return [
    "dev_mode" => true,
    "data_path" => BASE_PATH . "/data/",
    "stashcat_event_company" => "MyCompany",
    "auto_append_to_messages" => "\r\n\r\n🤖 AUTOMATISCH ERZEUGTE NACHRICHT 🤖",
    "allowed_channel" => [ "ChannelName" ],
    "date_format" => "d.m.Y",
    "time_format" => "H:i",
    "base_url" => "http://localhost:8080/",
    "user" => [
        'admin' => 'admin'
    ],
    "allowed_intervals" => [
        "P1W" => "Jede Woche",
        "P2W" => "Alle 2 Wochen",
        "P1Y" => "Jedes Jahr",
        "P2Y" => "Alle 2 Jahre"
    ]
];