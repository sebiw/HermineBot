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
    "allowed_intervals" => [
        "PT1M" => "Jede Minute", // Only for DEV
        "P1D" => "Jeden Tag",
        "P1W" => "Jede Woche",
        "P2W" => "Alle 2 Wochen",
        "P4W" => "Alle 4 Wochen",
        "P1M" => "Jeden Monat",
        "P1Y" => "Jedes Jahr",
        "P2Y" => "Alle 2 Jahre",
        "P3Y" => "Alle 3 Jahre"
    ]
];