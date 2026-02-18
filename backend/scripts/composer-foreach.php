#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Execute Composer commands across all workspaces
 *
 * This script discovers all composer.json files in services/, packages/, and tools/
 * directories and executes the provided Composer command in each workspace sequentially.
 *
 * Usage: php scripts/composer-foreach.php [command] [args...]
 * Examples:
 *   php scripts/composer-foreach.php install --no-interaction
 *   php scripts/composer-foreach.php update
 *   php scripts/composer-foreach.php test
 *   php scripts/composer-foreach.php validate --strict
 */

// ANSI color codes for better output
const COLOR_RESET = "\033[0m";
const COLOR_BLUE = "\033[34m";
const COLOR_GREEN = "\033[32m";
const COLOR_RED = "\033[31m";
const COLOR_YELLOW = "\033[33m";

// Workspace patterns to search
$workspacePatterns = [
    'services/*',
    'packages/*',
    'tools/*',
];

// Get the command from arguments
if ($argc < 2) {
    echo COLOR_RED . "Error: No command provided" . COLOR_RESET . "\n";
    echo "Usage: php scripts/composer-foreach.php [command] [args...]\n";
    echo "Example: php scripts/composer-foreach.php install --no-interaction\n";
    exit(1);
}

$command = implode(' ', array_slice($argv, 1));
$exitCode = 0;
$successCount = 0;
$failCount = 0;
$skippedCount = 0;
$workspaces = [];

// Change to backend directory (parent of scripts/)
$backendDir = dirname(__DIR__);
chdir($backendDir);

echo COLOR_BLUE . "====================================================" . COLOR_RESET . "\n";
echo COLOR_BLUE . "  Composer Foreach: composer {$command}" . COLOR_RESET . "\n";
echo COLOR_BLUE . "====================================================" . COLOR_RESET . "\n\n";

// Discover all workspaces
foreach ($workspacePatterns as $pattern) {
    $dirs = glob($pattern, GLOB_ONLYDIR);

    foreach ($dirs as $dir) {
        $composerJson = $dir . '/composer.json';

        if (!file_exists($composerJson)) {
            continue;
        }

        $workspaces[] = $dir;
    }
}

if (empty($workspaces)) {
    echo COLOR_YELLOW . "No workspaces found with composer.json" . COLOR_RESET . "\n";
    exit(0);
}

echo "Found " . count($workspaces) . " workspace(s)\n\n";

// Execute command in each workspace
foreach ($workspaces as $index => $workspace) {
    $num = $index + 1;
    $name = basename($workspace);
    $type = dirname($workspace);

    echo COLOR_BLUE . "[$num/" . count($workspaces) . "] " . COLOR_RESET;
    echo "{$type}/{$name}: composer {$command}\n";
    echo str_repeat("-", 50) . "\n";

    // Change to workspace directory
    $previousDir = getcwd();
    chdir($workspace);

    // Execute composer command
    $fullCommand = "composer {$command}";
    passthru($fullCommand, $code);

    // Change back to backend directory
    chdir($previousDir);

    echo "\n";

    // Track results
    if ($code !== 0) {
        $exitCode = $code;
        $failCount++;
        echo COLOR_RED . "✗ Failed in {$type}/{$name} (exit code: {$code})" . COLOR_RESET . "\n";
    } else {
        $successCount++;
        echo COLOR_GREEN . "✓ Success in {$type}/{$name}" . COLOR_RESET . "\n";
    }

    echo "\n";
}

// Print summary
echo COLOR_BLUE . "====================================================" . COLOR_RESET . "\n";
echo COLOR_BLUE . "  Summary" . COLOR_RESET . "\n";
echo COLOR_BLUE . "====================================================" . COLOR_RESET . "\n";
echo "Total workspaces: " . count($workspaces) . "\n";
echo COLOR_GREEN . "Successful: {$successCount}" . COLOR_RESET . "\n";
echo COLOR_RED . "Failed: {$failCount}" . COLOR_RESET . "\n";

if ($failCount > 0) {
    echo "\n" . COLOR_RED . "Some workspaces failed. See output above for details." . COLOR_RESET . "\n";
}

exit($exitCode);
