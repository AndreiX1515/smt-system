<?php
// 세션 유효시간 (초) - 4시간
define('SESSION_LIFETIME_SECONDS', 14400);

// 세션 유효시간 (MySQL INTERVAL)
define('SESSION_LIFETIME_INTERVAL', 'INTERVAL 4 HOUR');

// 세션 시작 (세션이 아직 시작되지 않은 경우에만)
if (session_status() === PHP_SESSION_NONE) {
    // 세션 시작 전에 ini_set으로 설정 적용
    ini_set('session.gc_maxlifetime', SESSION_LIFETIME_SECONDS);
    ini_set('session.cookie_lifetime', SESSION_LIFETIME_SECONDS);
    ini_set('session.cookie_path', '/');
    session_start();
}
