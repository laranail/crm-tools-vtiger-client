<?php

declare(strict_types=1);

return [

    // Interaction
    'non_interactive_required' => "A value for ':label' is required, but the console is running non-interactively. Provide it via a command option or environment variable.",
    'invalid_input' => 'Invalid input. Please try again.',

    // Errors
    'render_failed' => 'The :widget widget could not render: :reason',
    'invalid_color' => "':value' is not a valid hex colour or known colour name.",
    'tui_unavailable' => 'The TUI integration requires symfony/tui (PHP >= 8.4.1). Install it with: composer require symfony/tui',

    // Fonts (Figlet)
    'font_unknown' => "Unknown bundled font ':name'.",
    'font_unreadable' => "Unreadable FIGlet font ':path'.",
    'font_invalid' => "':path' is not a FIGlet font (missing flf2a header).",

    // Widget strings (resolved via Support\Lang with the configured locale)
    'widgets' => [
        'summary' => [
            'title' => 'EXECUTION SUMMARY',
            'statistics' => 'Execution Statistics:',
            'total' => 'Total',
            'successful' => 'Successful',
            'failed' => 'Failed',
            'performance' => 'Performance Metrics:',
            'failed_items' => 'Failed Items:',
            'total_time' => 'Total Time:',
            'average_time' => 'Average Time:',
            'fastest' => 'Fastest:',
            'slowest' => 'Slowest:',
            'success_rate' => 'Success Rate:',
            'badge_all_completed' => 'ALL COMPLETED',
            'badge_completed_with_errors' => 'COMPLETED WITH ERRORS',
            'badge_failed' => ':count FAILED',
            'badge_all_failed' => 'ALL FAILED',
        ],
        'header' => [
            'items' => 'items',
        ],
        'menu' => [
            'exit' => 'Exit',
            'select' => 'Select',
        ],
        'task_progress' => [
            'succeeded' => ':done/:total tasks succeeded',
            'eta' => 'ETA ',
        ],
        'callout' => [
            'success' => 'Success',
            'error' => 'Error',
            'warning' => 'Warning',
            'info' => 'Info',
        ],
    ],

];
