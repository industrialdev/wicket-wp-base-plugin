#!/usr/bin/env php
<?php

class VersionBumper
{
    private string $currentVersion;
    private array $filesToUpdate = [
        'composer.json',
        'wicket.php', // Wicket's Account Center plugin
    ];

    /**
     * Checks if the current directory is the root of the Wicket plugin.
     * If not, the script will exit with an error code.
     */
    public function __construct()
    {
        if (!$this->getCurrentVersion()) {
            if (!$this->getCurrentVersionFromPHP()) {
                echo "Error: Could not get version from either composer.json or plugin file.\n";
                exit(1);
            }
        }
    }

    /**
     * Tries to read the current version from composer.json.
     * If the file does not exist, or the version field is not present,
     * or the JSON is invalid, it will print an error message and return false.
     * Otherwise, the current version is stored in the $currentVersion property and the method returns true.
     *
     * @return bool
     */
    private function getCurrentVersion(): bool
    {
        if (!file_exists('composer.json')) {
            echo "Error: composer.json not found in current directory.\n";
            return false;
        }

        $composerJson = json_decode(file_get_contents('composer.json'), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            echo "Error: Unable to parse composer.json: " . json_last_error_msg() . "\n";
            return false;
        }

        if (!isset($composerJson['version'])) {
            echo "Note: No version field found in composer.json\n";
            return false;
        }

        $this->currentVersion = $composerJson['version'];
        return true;
    }

    /**
     * Tries to read the current version from the Wicket plugin.
     * If the file does not exist, or the version string is invalid, an error message is printed and the method returns false.
     *
     * @return bool
     */
    public function getCurrentVersionFromPHP(): bool
    {
        $pluginFile = '';

        // Scan $filesToUpdate for a .php file
        foreach ($this->filesToUpdate as $file) {
            if (strpos($file, '.php') !== false) {
                // Use this file
                $pluginFile = $file;
                break;
            }
        }

        if ($pluginFile === '') {
            echo "Error: No .php file found to read version from.\n";
            return false;
        }

        // Get the version from the plugin header
        $pluginContent = file_get_contents($pluginFile);
        if ($pluginContent === false) {
            echo "Error: Could not read plugin file.\n";
            return false;
        }

        // Use regex to extract version
        if (!preg_match('/\* Version:\s*([0-9.]+)/', $pluginContent, $matches)) {
            echo "Error: Could not find version in plugin file.\n";
            return false;
        }

        $this->currentVersion = $matches[1];
        return true;
    }

    /**
     * Validate a given version string against the semantic versioning regex pattern.
     * If the version string is invalid, an error message is printed and the method returns false.
     * Otherwise, the method returns true.
     *
     * @param string $newVersion
     * @return bool
     */
    private function validateNewVersion(string $newVersion): bool
    {
        // Semantic versioning regex pattern
        $semverPattern = '/^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)(?:-((?:0|[1-9]\d*|\d*[a-zA-Z-][0-9a-zA-Z-]*)(?:\.(?:0|[1-9]\d*|\d*[a-zA-Z-][0-9a-zA-Z-]*))*))?(?:\+([0-9a-zA-Z-]+(?:\.[0-9a-zA-Z-]+)*))?$/';

        if (!preg_match($semverPattern, $newVersion)) {
            echo "Error: Invalid version format. Please use semantic versioning (e.g., 1.2.3)\n";
            return false;
        }

        return true;
    }

    /**
     * Update the version string in a file by replacing the current version with a new one.
     *
     * @param string $filePath The path to the file to update.
     * @param string $newVersion The new version string to use.
     *
     * @return bool True if the file was updated successfully, false otherwise.
     */
    private function updateVersionInFile(string $filePath, string $newVersion): bool
    {
        if (!file_exists($filePath)) {
            echo "Warning: File not found: {$filePath}\n";
            return false;
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            echo "Error: Unable to read file: {$filePath}\n";
            return false;
        }

        // Handle different file types
        $extension = pathinfo($filePath, PATHINFO_EXTENSION);
        $pattern = preg_quote($this->currentVersion, '/');
        $updated = false;

        switch ($extension) {
            case 'css':
                // For CSS files, look for Version: X.X.X pattern
                $pattern = '/Version:\s*' . $pattern . '/i';
                $newContent = preg_replace($pattern, 'Version: ' . $newVersion, $content, -1, $count);
                $updated = $count > 0;
                break;
            case 'json':
                // For JSON files, look for "version": "X.X.X" pattern
                $pattern = '/"version":\s*"' . $pattern . '"/';
                $newContent = preg_replace($pattern, '"version": "' . $newVersion . '"', $content, -1, $count);
                $updated = $count > 0;
                break;
            case 'php':
                // Try both patterns for PHP files
                // Pattern 1: Version: X.X.X with possible multiple spaces
                $pattern1 = '/(Version:)\s+' . $pattern . '/';
                $newContent = preg_replace($pattern1, '$1 ' . $newVersion, $content, -1, $count1);

                if ($count1 === 0) {
                    // Pattern 2: Direct version number (fallback)
                    $pattern2 = '/' . $pattern . '/';
                    $newContent = preg_replace($pattern2, $newVersion, $content, -1, $count2);
                    $updated = $count2 > 0;
                } else {
                    $updated = true;
                }
                break;
            default:
                // For other files, do direct version replacement
                $pattern = '/' . $pattern . '/';
                $newContent = preg_replace($pattern, $newVersion, $content, -1, $count);
                $updated = $count > 0;
        }

        if ($newContent === null) {
            echo "Error: Pattern replacement failed in {$filePath}\n";
            return false;
        }

        if (!$updated) {
            echo "Warning: No version string found in {$filePath}\n";
            return false;
        }

        if (file_put_contents($filePath, $newContent) === false) {
            echo "Error: Unable to write to file: {$filePath}\n";
            return false;
        }

        return true;
    }

    /**
     * Runs the version bump process.
     *
     * Prompts the user to enter a new version string, validates it using the semantic versioning regex pattern,
     * and updates the version string in all files listed in the $filesToUpdate property.
     *
     * If any of the files cannot be updated, an error message is printed and the script exits with a status code of 1.
     * If no files are updated, an error message is printed and the script exits with a status code of 1.
     * If not all files are updated, a warning message is printed.
     *
     * @return void
     */
    public function run(): void
    {
        echo "Current version: {$this->currentVersion}\n";
        echo "Enter new version (semver): ";
        $newVersion = trim(fgets(STDIN));

        if (!$this->validateNewVersion($newVersion)) {
            exit(1);
        }

        $successCount = 0;
        foreach ($this->filesToUpdate as $file) {
            // Skip files that don't exist
            if (!file_exists($file)) {
                echo "Warning: File {$file} does not exist\n";
                continue;
            }

            if ($this->updateVersionInFile($file, $newVersion)) {
                echo "Updated version in {$file}\n";
                $successCount++;
            }
        }

        if ($successCount === 0) {
            echo "Error: No files were updated\n";
            exit(1);
        }

        if ($successCount !== count($this->filesToUpdate)) {
            echo "Warning: Only {$successCount} out of " . count($this->filesToUpdate) . " files were updated\n";
        }

        echo "Version bump completed: {$this->currentVersion} → {$newVersion}\n";
    }

    /**
     * Create a backup of the files to be updated.
     *
     * @return bool True if backup was successful, false otherwise.
     */
    public function backup(): bool
    {
        $backupDir = '.version-bump-backup-' . date('Y-m-d-H-i-s');
        if (!mkdir($backupDir)) {
            echo "Error: Unable to create backup directory\n";
            return false;
        }

        foreach ($this->filesToUpdate as $file) {
            if (file_exists($file)) {
                if (!copy($file, $backupDir . DIRECTORY_SEPARATOR . $file)) {
                    echo "Error: Failed to backup {$file}\n";
                    return false;
                }
            }
        }

        return true;
    }
}

// Execute the script
$bumper = new VersionBumper();

// Create backup before proceeding
/*if (!$bumper->backup()) {
    echo "Error: Backup failed, aborting version bump\n";
    exit(1);
}*/

$bumper->run();
