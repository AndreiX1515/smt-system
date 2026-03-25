<?php
function render_template(string $layout, array $vars = []) {
    $layoutPath = __DIR__ . "../../templates/{$layout}.php";

    
    if (!file_exists($layoutPath)) {
        error_log("Template not found: {$layoutPath}");
        echo "Template error: {$layout}";
        return;
    }

    // Extract variables safely for use in template
    extract($vars, EXTR_SKIP);

    include $layoutPath;
}