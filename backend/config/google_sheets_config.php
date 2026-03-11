<?php
return [
    'service_account_key_path' => '/var/www/html/backend/config/google-sa-key.json',
    'spreadsheet_id' => '1xCfpolzqQnqOjtDFV3mozpg3C6J02Or7QA6t2kfSLBc',
    'webhook_secret' => 'smt_sheets_webhook_2026_secret',
    'sheets' => [
        [
            'sheet_name'      => '시트1',
            'gid'             => '561055718',
            'col_package_id'  => 'AC',
            'col_date'        => 'C',
            'col_r'           => 'G',
            'col_app'         => 'Z',
            'header_row'      => 9,
            'data_start_row'  => 10,
        ]
    ],
    'token_cache_path' => '/tmp/google_sheets_token.json',
    'sync_lock_path'   => '/tmp/google_sheets_sync.lock',
    'log_path'         => '/var/www/html/logs/sheets_sync.log',
];
