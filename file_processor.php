<?php

declare(strict_types=1);

// Constants for link replacement
const OLD_LINK = 'https://help.shopsettings.com/hc/en-us/articles/';
const NEW_LINK = 'http://ventasclicksoporte.s3-website-us-east-1.amazonaws.com/hc/es/articles/';
const BASE_DIRECTORY = 'hc/es';  // Adjust this to the base directory where HTML files are located
const LOG_FILE = 'html_processing.log';  // Path to the log file
const DEBUG_MODE = true;  // Set to false to disable logging

/**
 * Logs messages to the browser/console and optionally to a log file if debug mode is enabled.
 * 
 * @param string $message Message to log.
 */
function logMessage(string $message): void {
    $timestamp = date('Y-m-d H:i:s');
    $formattedMessage = "[$timestamp] $message";

    // Output to browser or CLI
    if (php_sapi_name() === 'cli') {
        echo $formattedMessage . "\n";
    } else {
        echo "<p>$formattedMessage</p>";
    }

    // Log to file if debug mode is enabled
    if (DEBUG_MODE) {
        error_log($formattedMessage . "\n", 3, LOG_FILE);
    }
}

/**
 * Reads content from a file.
 * 
 * @param string $filePath Path to the file.
 * @return string|null File content or null if the read fails.
 */
function readFileContent(string $filePath): ?string {
    $content = file_get_contents($filePath);
    if ($content === false) {
        logMessage("Failed to read $filePath");
        return null;
    }
    return $content;
}

/**
 * Writes content to a file.
 * 
 * @param string $filePath Path to the file.
 * @param string $content Content to write.
 * @return bool True if successful, false otherwise.
 */
function writeFileContent(string $filePath, string $content): bool {
    if (file_put_contents($filePath, $content) === false) {
        logMessage("Failed to write to $filePath");
        return false;
    }
    return true;
}

/**
 * Backs up all HTML files in the directory.
 * 
 * @param string $outputDir Directory containing HTML files.
 */
function backupFiles(string $outputDir): void {
    $backupDir = $outputDir . '_backup';

    if (!is_dir($backupDir) && !mkdir($backupDir) && !is_dir($backupDir)) {
        logMessage("Failed to create backup directory: $backupDir");
        throw new Exception("Failed to create backup directory: $backupDir");
    }

    foreach (glob("$outputDir/*.html") as $file) {
        if (!copy($file, "$backupDir/" . basename($file))) {
            logMessage("Failed to copy $file to backup directory");
        } else {
            logMessage("Backed up $file");
        }
    }
}

/**
 * Validates HTML structure by checking for opening and closing HTML tags.
 * 
 * @param string $outputDir Directory containing HTML files.
 */
function validateHtmlFiles(string $outputDir): void {
    foreach (glob("$outputDir/*.html") as $file) {
        $content = readFileContent($file);
        if ($content === null) {
            continue;
        }

        if (strpos($content, '<html>') !== false && strpos($content, '</html>') !== false) {
            logMessage("$file is valid HTML.");
        } else {
            logMessage("$file is not valid HTML.");
        }
    }
}

/**
 * Processes HTML files by modifying links but keeping the text before the numeric part intact.
 * 
 * @param string $outputDir Directory containing HTML files.
 */
function processHtmlFiles(string $outputDir): void {
    // Regex to match the text before the numbers, the numeric part (9 to 14 digits), and any text after the numbers.
    $htmlLinkPattern = '/(.*?)([0-9]{9,14})-(.*?)\.html/';
    $nonHtmlLinkPattern = '/(.*?)([0-9]{9,14})-(\S+)/';

    foreach (glob("$outputDir/*.html") as $file) {
        $content = readFileContent($file);
        if ($content === null) {
            continue;
        }

        // Process .html links: keep text before the numbers and truncate the text after the numbers
        $content = preg_replace_callback($htmlLinkPattern, function ($matches) {
            $originalLink = $matches[0];
            $preservedText = $matches[1];  // Text before the numbers
            $numbers = $matches[2];        // The numeric part
            $cleanedLink = $preservedText . $numbers . '.html';  // Only keep .html extension, truncate after numbers
            logMessage("Updated .html link from: $originalLink to: $cleanedLink");
            return $cleanedLink;
        }, $content);

        // Process non .html links: keep text before the numbers and truncate the text after the numbers
        $content = preg_replace_callback($nonHtmlLinkPattern, function ($matches) {
            $originalLink = $matches[0];
            $preservedText = $matches[1];  // Text before the numbers
            $numbers = $matches[2];        // The numeric part
            $cleanedLink = $preservedText . $numbers;  // Only keep the numeric part, truncate after numbers
            logMessage("Updated non .html link from: $originalLink to: $cleanedLink");
            return $cleanedLink;
        }, $content);

        if (!writeFileContent($file, $content)) {
            continue;
        }

        logMessage("Processed links in $file");
    }
}

/**
 * Replaces specific links in HTML files.
 * 
 * @param string $outputDir Directory containing HTML files.
 */
function replaceLinksInFiles(string $outputDir): void {
    foreach (glob("$outputDir/*.html") as $file) {
        $content = readFileContent($file);
        if ($content === null) {
            continue;
        }

        if (strpos($content, OLD_LINK) !== false) {
            $updatedContent = str_replace(OLD_LINK, NEW_LINK, $content);
            if (!writeFileContent($file, $updatedContent)) {
                continue;
            }
            logMessage("Replaced link in $file");
        } else {
            logMessage("No matching link found in $file");
        }
    }
}

/**
 * Main script execution for browser.
 */
function main(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $directory = isset($_POST['directory']) ? $_POST['directory'] : '';

        if (!empty($directory)) {
            if (is_dir($directory)) {
                try {
                    backupFiles($directory);
                    processHtmlFiles($directory);
                    replaceLinksInFiles($directory);
                    validateHtmlFiles($directory);
                    logMessage("Finished processing directory: $directory");
                } catch (Exception $e) {
                    logMessage("Error: " . $e->getMessage());
                }
            } else {
                logMessage("Invalid directory: $directory");
            }
        } else {
            logMessage("No directory selected.");
        }
    } else {
        renderForm();
    }
}

/**
 * Renders the HTML form for selecting a directory.
 */
function renderForm(): void {
    echo '<form method="POST" action="">';
    echo '<label for="directory">Enter Directory Path:</label>';
    echo '<input type="text" id="directory" name="directory" required>';
    echo '<input type="submit" value="Process">';
    echo '</form>';
}

// Run the script in a browser
if (php_sapi_name() !== 'cli') {
    main();
}