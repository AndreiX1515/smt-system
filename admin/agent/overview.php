<?php
// Backward-compat redirect for legacy path.
// Preserve query string and send user to the current agent overview page.
header('Location: ./overview.html' . (isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== '' ? ('?' . $_SERVER['QUERY_STRING']) : ''), true, 302);
exit;


