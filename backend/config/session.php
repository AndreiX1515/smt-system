<?php
// 세션 유효시간 (초) - 4시간
define('SESSION_LIFETIME_SECONDS', 14400);

// 세션 유효시간 (MySQL INTERVAL)
define('SESSION_LIFETIME_INTERVAL', 'INTERVAL 4 HOUR');

// 세션 시작
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
