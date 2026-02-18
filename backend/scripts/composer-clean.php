#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Clean vendor directories and caches from all workspaces
 *
 * This script removes vendor/ directories and cache files from the root
 * and all workspace directories (services/, packages/, tools/).
 *
 * Usage: php scripts/composer-clean.php
 */

// ANSI color codes
const COLOR_RESET = "\033[0m";
const COLOR_BLUE = "\033[34m";
const COLOR_GREEN = "\033[32m";
const COLOR_RED = "\033[31m";
const COLOR_YELLOW = "\033[33m";

$workspacePatterns = [
    'services/*',
    'packages/*',
    'tools/*',
];

$dirsToRemove = [
    'vendor',
    '.phpunit.cache',
    'coverage',
];

$filesToRemove = [
    '.phpunit.result.cache',
    '.php-cs-fixer.cache',
    '.php_cs.cache',
    '.phpstan.cache',
];

$backendDir = dirname(__DIR__);
chdir($backendDir);

echo COLOR_BLUE . "====================================================" . COLOR_RESET . "\n";
echo COLOR_BLUE . "  Cleaning Workspaces" . COLOR_RESET . "\n";
echo COLOR_BLUE . "====================================================" . COLOR_RESET . "\n\n";

$removedCount = 0;
$bytesFreed = 0;

/**
 * Calculate directory size recursively
 */
function getDirectorySize(string $path): int
{
    $size = 0;

    if (!is_dir($path)) {
        return 0;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $size += $file->getSize();
        }
    }

    return $size;
}

/**
 * Remove directory recursively
 */
function removeDirectory(string $path): bool
{
    if (!is_dir($path)) {
        return false;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($iterator as $file) {
        if ($file->isDir()) {
            rmdir($file->getPathname());
        } else {
            unlink($file->getPathname());
        }
    }

    return rmdir($path);
}

/**
 * Format bytes to human-readable string
 */
function formatBytes(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;

    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }

    return round($bytes, 2) . ' ' . $units[$i];
}

// Clean root directory
echo COLOR_YELLOW . "Cleaning root directory..." . COLOR_RESET . "\n";
foreach ($dirsToRemove as $dir) {
    if (is_dir($dir)) {
        $size = getDirectorySize($dir);
        echo "  Removing {$dir}/... ";
        if (removeDirectory($dir)) {
            $bytesFreed += $size;
            $removedCount++;
            echo COLOR_GREEN . "✓ " . formatBytes($size) . COLOR_RESET . "\n";
        } else {
            echo COLOR_RED . "✗ Failed" . COLOR_RESET . "\n";
        }
    }
}

foreach ($filesToRemove as $file) {
    if (file_exists($file)) {
        $size = filesize($file);
        echo "  Removing {$file}... ";
        if (unlink($file)) {
            $bytesFreed += $size;
            $removedCount++;
            echo COLOR_GREEN . "✓" . COLOR_RESET . "\n";
        } else {
            echo COLOR_RED . "✗ Failed" . COLOR_RESET . "\n";
        }
    }
}

echo "\n";

// Clean workspaces
foreach ($workspacePatterns as $pattern) {
    $dirs = glob($pattern, GLOB_ONLYDIR);

    foreach ($dirs as $workspace) {
        if (!file_exists($workspace . '/composer.json')) {
            continue;
        }

        $name = basename($workspace);
        $type = dirname($workspace);

        echo COLOR_YELLOW . "Cleaning {$type}/{$name}..." . COLOR_RESET . "\n";

        foreach ($dirsToRemove as $dir) {
            $path = $workspace . '/' . $dir;
            if (is_dir($path)) {
                $size = getDirectorySize($path);
                echo "  Removing {$dir}/... ";
                if (removeDirectory($path)) {
                    $bytesFreed += $size;
                    $removedCount++;
                    echo COLOR_GREEN . "✓ " . formatBytes($size) . COLOR_RESET . "\n";
                } else {
                    echo COLOR_RED . "✗ Failed" . COLOR_RESET . "\n";
                }
            }
        }

        foreach ($filesToRemove as $file) {
            $path = $workspace . '/' . $file;
            if (file_exists($path)) {
                $size = filesize($path);
                echo "  Removing {$file}... ";
                if (unlink($path)) {
                    $bytesFreed += $size;
                    $removedCount++;
                    echo COLOR_GREEN . "✓" . COLOR_RESET . "\n";
                } else {
                    echo COLOR_RED . "✗ Failed" . COLOR_RESET . "\n";
                }
            }
        }

        echo "\n";
    }
}

// Print summary
echo COLOR_BLUE . "====================================================" . COLOR_RESET . "\n";
echo COLOR_BLUE . "  Summary" . COLOR_RESET . "\n";
echo COLOR_BLUE . "====================================================" . COLOR_RESET . "\n";
echo COLOR_GREEN . "Removed: {$removedCount} items" . COLOR_RESET . "\n";
echo COLOR_GREEN . "Freed: " . formatBytes($bytesFreed) . COLOR_RESET . "\n";

exit(0);
