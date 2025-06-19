#!/usr/bin/env php
<?php

class VersionBumper
{
    private string $currentVersion;
    private array $filesToUpdate = [
        'composer.json', // Main composer file
        'wicket.php', // Wicket's Base plugin & Wicket's Memberships plugin
        'style.css', // Wicket's Theme
        'class-wicket-acc-main.php', // Wicket's Account Center plugin
        'class-wicket-wp-gf.php', // Wicket's GF plugin
    ];

    /**
     * Checks if the current directory is the root of the Wicket plugin.
     * If not, the script will exit with an error code.
     */
    public function __construct()
    {
        if (!$this->getCurrentVersion()) {
            exit(1);
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
            echo "Error: No version field found in composer.json\n";
            return false;
        }

        $this->currentVersion = $composerJson['version'];
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
                $pattern = '/Version:\s*' . preg_quote($this->currentVersion, '/') . '/i';
                $newContent = preg_replace($pattern, 'Version: ' . $newVersion, $content, -1, $count);
                $updated = $count > 0;
                break;
            case 'json':
                // For JSON files, look for "version": "X.X.X" pattern
                $pattern = '/"version":\s*"' . preg_quote($this->currentVersion, '/') . '"/';
                $newContent = preg_replace($pattern, '"version": "' . $newVersion . '"', $content, -1, $count);
                $updated = $count > 0;
                break;
            case 'php':
                $updated = false;
                $newContent = $content; // Default to original content
                // Generic pattern for the version number part (numbers, letters, dots, hyphens)
                $versionPatternPart = '[0-9a-zA-Z\\.-]+';

                // Pattern 1: For " * Version: X.X.X" in docblocks (typical WordPress plugin header)
                // Replaces X.X.X with $newVersion, preserving the prefix.
                $docblockPattern = '/(^\s*\*\s*Version:\s*)' . $versionPatternPart . '/m';
                $tempContent = preg_replace($docblockPattern, '${1}' . $newVersion, $content, -1, $count1);

                if ($count1 > 0) {
                    $newContent = $tempContent;
                    $updated = true;
                } else {
                    // Pattern 2: For "Version: X.X.X" (plain header, not necessarily in docblock comment line start)
                    // Replaces X.X.X with $newVersion, preserving the prefix.
                    $plainHeaderPattern = '/(Version:\s*)' . $versionPatternPart . '/i'; // Case insensitive
                    $tempContent = preg_replace($plainHeaderPattern, '${1}' . $newVersion, $content, -1, $count2);
                    if ($count2 > 0) {
                        $newContent = $tempContent;
                        $updated = true;
                    } else {
                        // Pattern 3: Fallback to direct replacement of $this->currentVersion (from composer.json)
                        // This handles cases where the version string is not in a standard "Version:" header
                        // but was expected to be in sync with composer.json.
                        $quotedCurrentVersion = preg_quote($this->currentVersion, '/');
                        $directPattern = '/' . $quotedCurrentVersion . '/';
                        $tempContent = preg_replace($directPattern, $newVersion, $content, -1, $count3);
                        if ($count3 > 0) {
                            $newContent = $tempContent;
                            $updated = true;
                        }
                    }
                }
                break;
            default:
                // For other files, do direct version replacement
                $pattern = '/' . preg_quote($this->currentVersion, '/') . '/';
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
            echo "{$successCount} out of " . count($this->filesToUpdate) . " files were updated\n";
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
