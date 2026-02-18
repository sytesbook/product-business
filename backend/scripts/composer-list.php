#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * List all workspaces (services, packages, tools)
 *
 * This script discovers and displays all composer.json files in the monorepo
 * along with their key information (name, type, description, dependencies).
 *
 * Usage: php scripts/composer-list.php
 */

// ANSI color codes
const COLOR_RESET = "\033[0m";
const COLOR_BLUE = "\033[34m";
const COLOR_GREEN = "\033[32m";
const COLOR_YELLOW = "\033[33m";
const COLOR_CYAN = "\033[36m";
const COLOR_MAGENTA = "\033[35m";

$workspacePatterns = [
    'services/*',
    'packages/*',
    'tools/*',
];

$backendDir = dirname(__DIR__);
chdir($backendDir);

echo COLOR_BLUE . "====================================================" . COLOR_RESET . "\n";
echo COLOR_BLUE . "  Backend Workspaces" . COLOR_RESET . "\n";
echo COLOR_BLUE . "====================================================" . COLOR_RESET . "\n\n";

$workspacesByType = [
    'services' => [],
    'packages' => [],
    'tools' => [],
];

// Discover all workspaces
foreach ($workspacePatterns as $pattern) {
    $dirs = glob($pattern, GLOB_ONLYDIR);

    foreach ($dirs as $dir) {
        $composerJsonPath = $dir . '/composer.json';

        if (!file_exists($composerJsonPath)) {
            continue;
        }

        $composerJson = json_decode(file_get_contents($composerJsonPath), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            echo COLOR_YELLOW . "Warning: Invalid JSON in {$dir}/composer.json" . COLOR_RESET . "\n";
            continue;
        }

        $type = dirname($dir);
        $name = $composerJson['name'] ?? basename($dir);
        $description = $composerJson['description'] ?? 'No description';
        $packageType = $composerJson['type'] ?? 'library';

        // Count dependencies
        $dependencies = array_merge(
            array_keys($composerJson['require'] ?? []),
            array_keys($composerJson['require-dev'] ?? [])
        );

        // Filter to only local dependencies (sytesbook/business-*)
        $localDeps = array_filter($dependencies, fn($dep) => str_starts_with($dep, 'sytesbook/business-'));

        $workspacesByType[$type][] = [
            'path' => $dir,
            'name' => $name,
            'description' => $description,
            'packageType' => $packageType,
            'dependencies' => count($dependencies),
            'localDependencies' => $localDeps,
        ];
    }
}

// Display workspaces by type
foreach ($workspacesByType as $type => $workspaces) {
    if (empty($workspaces)) {
        continue;
    }

    $typeLabel = strtoupper($type);
    echo COLOR_CYAN . "┌─ {$typeLabel} " . str_repeat("─", 70 - strlen($typeLabel)) . "┐" . COLOR_RESET . "\n";

    foreach ($workspaces as $workspace) {
        echo COLOR_GREEN . "│ " . $workspace['name'] . COLOR_RESET . "\n";
        echo "│   " . COLOR_YELLOW . "Path:" . COLOR_RESET . " {$workspace['path']}\n";
        echo "│   " . COLOR_YELLOW . "Type:" . COLOR_RESET . " {$workspace['packageType']}\n";
        echo "│   " . COLOR_YELLOW . "Description:" . COLOR_RESET . " {$workspace['description']}\n";
        echo "│   " . COLOR_YELLOW . "Dependencies:" . COLOR_RESET . " {$workspace['dependencies']} total";

        if (!empty($workspace['localDependencies'])) {
            echo " (" . count($workspace['localDependencies']) . " local)";
            echo "\n│     " . COLOR_MAGENTA . "Local deps:" . COLOR_RESET . " " . implode(', ', $workspace['localDependencies']);
        }

        echo "\n│\n";
    }

    echo COLOR_CYAN . "└" . str_repeat("─", 78) . "┘" . COLOR_RESET . "\n\n";
}

// Print summary
$totalServices = count($workspacesByType['services']);
$totalPackages = count($workspacesByType['packages']);
$totalTools = count($workspacesByType['tools']);
$total = $totalServices + $totalPackages + $totalTools;

echo COLOR_BLUE . "====================================================" . COLOR_RESET . "\n";
echo COLOR_BLUE . "  Summary" . COLOR_RESET . "\n";
echo COLOR_BLUE . "====================================================" . COLOR_RESET . "\n";
echo "Total workspaces: {$total}\n";
echo "  " . COLOR_GREEN . "Services: {$totalServices}" . COLOR_RESET . "\n";
echo "  " . COLOR_GREEN . "Packages: {$totalPackages}" . COLOR_RESET . "\n";
echo "  " . COLOR_GREEN . "Tools: {$totalTools}" . COLOR_RESET . "\n";

exit(0);
