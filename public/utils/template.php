<?php
require_once __DIR__ . '/../inc/general/error_logger.php';




const LOG_SOURCE = 'template.php';

function render_template(string $layout, array $vars = [])
{
    try {
        // ── Resolve path safely ─────────────────────────────────────────────
        $layoutPath = __DIR__ . "/../public/templates/{$layout}.php";

        // ── Validate template existence ────────────────────────────────────
        if (!file_exists($layoutPath)) {
            AppLogger::error(
                "Template not found",
                LOG_SOURCE,
                ['layout' => $layout, 'path' => $layoutPath]
            );

            echo "Template error: {$layout}";
            return;
        }

        // ── Extract variables safely ───────────────────────────────────────
        if (!empty($vars)) {
            extract($vars, EXTR_SKIP);
        }

        // ── Include template with output buffering (optional safety) ───────
        ob_start();

        try {
            include $layoutPath;
        } catch (Throwable $e) {
            ob_end_clean();

            AppLogger::error(
                "Template execution failed",
                LOG_SOURCE,
                [
                    'layout' => $layout,
                    'error'  => $e->getMessage(),
                    'file'   => $e->getFile(),
                    'line'   => $e->getLine()
                ]
            );

            echo "An error occurred while rendering the page.";
            return;
        }

        ob_end_flush();
    } catch (Throwable $e) {
        // ── Catch unexpected system-level failures ─────────────────────────
        AppLogger::error(
            "Fatal error in render_template",
            LOG_SOURCE,
            [
                'error' => $e->getMessage(),
                'file'  => $e->getFile(),
                'line'  => $e->getLine()
            ]
        );

        echo "Critical rendering error.";
    }
}
