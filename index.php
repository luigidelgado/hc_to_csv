<?php
// Move strict_types declaration to the top
declare(strict_types=1);

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Constants
define('BASE_DIR', realpath(__DIR__ . '/hc/es/'));
define('BACKUP_SUFFIX', '_backup_');

// Security headers
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("X-XSS-Protection: 1; mode=block");

/**
 * Display directory selection form.
 */
function displayDirectoryForm(array $directories): void {
    echo '<h1>Select Folder to Process</h1>';
    echo '<form method="POST">';
    echo '<label for="folder">Select Folder:</label>';
    echo '<select name="folder" id="folder">';
    foreach ($directories as $directory) {
        echo '<option value="' . htmlspecialchars($directory, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($directory, ENT_QUOTES, 'UTF-8') . '</option>';
    }
    echo '</select>';
    echo '<input type="hidden" name="step" value="1">';
    echo '<button type="submit">Start Process</button>';
    echo '</form>';
}

/**
 * List directories inside the base directory for selection.
 */
function listDirectories(string $baseDir): array {
    $dirs = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($baseDir), RecursiveIteratorIterator::SELF_FIRST);
    foreach ($iterator as $file) {
        if ($file->isDir() && strpos($file->getPathname(), BASE_DIR) === 0) {
            $dirs[] = $file->getPathname();
        }
    }
    return $dirs;
}

/**
 * Main processing steps.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $step = filter_input(INPUT_POST, 'step', FILTER_SANITIZE_NUMBER_INT);
    // Replace FILTER_SANITIZE_STRING with FILTER_SANITIZE_SPECIAL_CHARS
    $selectedDir = filter_input(INPUT_POST, 'folder', FILTER_SANITIZE_SPECIAL_CHARS);

    // Validate selected directory
    if (!isValidDirectory($selectedDir)) {
        echo "Invalid folder selection.";
        exit;
    }

    // Execute the appropriate action based on the step
    switch ($step) {
        case 1:
            executeStep($selectedDir, 'backupDirectory', 'Proceed to Renaming Files', 2);
            break;

        case 2:
            executeStep($selectedDir, 'renameFiles', 'Proceed to Replace Links', 3);
            break;

        case 3:
            executeStep($selectedDir, 'replaceAbsoluteLinks', 'Proceed to Generate CSV', 4);
            break;

        case 4:
            $csvFile = generateCsvForHtmlFiles($selectedDir);
            echo "CSV generation completed.<br>";
            echo '<a href="' . htmlspecialchars($csvFile, ENT_QUOTES, 'UTF-8') . '" download>Download CSV</a>';
            exit;

        default:
            echo "Invalid step.";
            exit;
    }
} else {
    // Display the directory selection form if no POST request is made
    $directories = listDirectories(BASE_DIR);
    displayDirectoryForm($directories);
}

/**
 * Validate if the directory is inside the base directory.
 */
function isValidDirectory(?string $selectedDir): bool {
    $selectedDirPath = realpath($selectedDir);
    return $selectedDirPath && strpos($selectedDirPath, BASE_DIR) === 0;
}

/**
 * Execute the specific step and prepare the next step's form.
 */
function executeStep(string $selectedDir, callable $action, string $nextLabel, int $nextStep): void {
    $action($selectedDir);
    echo ucfirst($action) . " completed.<br>";
    echo '<form method="POST">
            <input type="hidden" name="folder" value="' . htmlspecialchars($selectedDir, ENT_QUOTES, 'UTF-8') . '">
            <input type="hidden" name="step" value="' . $nextStep . '">
            <button type="submit">' . $nextLabel . '</button>';
    echo '</form>';
    exit;
}

/**
 * Backup a directory by copying its contents.
 */
function backupDirectory(string $src): void {
    $backupDir = $src . BACKUP_SUFFIX . date('Ymd_His');
    
    if (!is_dir($backupDir) && !mkdir($backupDir, 0777, true) && !is_dir($backupDir)) {
        error_log("Failed to create backup directory: $backupDir");
        echo "Error creating backup directory.";
        exit;
    }

    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        $destPath = $backupDir . DIRECTORY_SEPARATOR . $iterator->getSubPathName();
        
        // Ensure we get the real path of the file to copy
        if ($file->isDir()) {
            mkdir($destPath, 0777, true);
        } else {
            $sourcePath = $file->getRealPath(); // Get the file's real path
            if ($sourcePath && !copy($sourcePath, $destPath)) {
                error_log("Failed to copy file: $sourcePath");
                echo "Error during file backup.";
                exit;
            }
        }
    }
}

/**
 * Rename HTML files in a directory by keeping only numeric parts.
 */
function renameFiles(string $dir): void {
    $files = glob($dir . "/*.html");
    foreach ($files as $file) {
        $newName = preg_replace('/[^0-9]/', '', basename($file));
        if ($newName && !rename($file, $dir . '/' . $newName . '.html')) {
            error_log("Failed to rename file: $file");
            echo "Error renaming files.";
            exit;
        }
    }
}

/**
 * Replace absolute links and update href tags with numeric-only filenames.
 */
function replaceAbsoluteLinks(string $dir): void {
    $files = glob($dir . "/*.html");
    foreach ($files as $file) {
        $content = file_get_contents($file);
        if ($content === false) {
            error_log("Failed to read file: $file");
            echo "Error reading files.";
            exit;
        }

        // Replace absolute links with relative ones
        $updatedContent = str_replace('https://help.shopsettings.com/hc/', '/', $content);

        // Modify href tags for links pointing to numeric-only HTML files
        $updatedContent = updateHrefTagsForNumericHtmlFiles($updatedContent);

        // Write the updated content back to the file
        if (file_put_contents($file, $updatedContent) === false) {
            error_log("Failed to write file: $file");
            echo "Error writing files.";
            exit;
        }
    }
}

/**
 * Update href attributes for numeric HTML files in <a> tags.
 * Removes any trailing text after the numbers in the filename, keeping just the numbers followed by .html.
 */
function updateHrefTagsForNumericHtmlFiles(string $htmlContent): string {
    // Regular expression to match <a href="..."> where the file starts with numbers and ends with .html
    return preg_replace_callback(
        '/<a\s+href="[^"]*?(\d+)[^\/]*?\.html"/i',
        function ($matches) {
            // Extract the numeric part and return only the numbers followed by .html
            $numericPart = $matches[1];
            return '<a href="' . $numericPart . '.html"';
        },
        $htmlContent
    );
}

/**
 * Generate CSV file from HTML files in the directory.
 */
function generateCsvForHtmlFiles(string $dir): string {
    $timestamp = date("Ymd_His");
    $outputCsv = "ECWIDHC_{$timestamp}.csv";
    $csvFile = fopen($outputCsv, 'w');
    fwrite($csvFile, "\xEF\xBB\xBF"); // Add BOM for UTF-8
    fputcsv($csvFile, ['Translation_External_ID', 'H1', 'Category', 'Answer']); // Write CSV headers

    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($iterator as $filePath) {
        if (pathinfo($filePath->getRealPath(), PATHINFO_EXTENSION) === 'html') { // Get the file path as a string
            processSingleHtmlFile($filePath->getRealPath(), $csvFile); // Pass the file path string
        }
    }

    fclose($csvFile);
    return $outputCsv;
}

/**
 * Process a single HTML file and extract necessary data for the CSV.
 */
function processSingleHtmlFile(string $filePath, $csvFile): void {
    $filename = pathinfo($filePath, PATHINFO_FILENAME);
    $dom = new DOMDocument();
    libxml_use_internal_errors(true); // Suppress HTML parsing errors

    if (!$dom->loadHTMLFile($filePath)) {
        echo "<div class='result'>Error: Could not load the file <strong>" . htmlspecialchars($filename) . "</strong> (possibly malformed HTML).</div>";
        return;
    }

    $xpath = new DOMXPath($dom);
    $h1 = extractElement($xpath, '//h1', 'No <h1> found');
    $articleBodyHtml = extractInnerHtml($xpath, '//div[contains(@class, "article-body")]', 'No <div class="article-body"> found');
    $breadcrumb = extractElement($xpath, '//ol[@class="breadcrumbs"]/li[2]', 'No <ol class="breadcrumbs"> found');

    $csvRow = [
        cleanForCsv($filename), 
        cleanForCsv($h1), 
        cleanForCsv($breadcrumb), 
        cleanForCsv($articleBodyHtml)
    ];

    fputcsv($csvFile, $csvRow);
    echo "<div class='result'>Processed file: <strong>" . htmlspecialchars($filename) . "</strong></div>";
}

/**
 * Extract the content of a specific element from the HTML using XPath.
 */
function extractElement(DOMXPath $xpath, string $query, string $defaultValue): string {
    $node = $xpath->query($query);
    return ($node && $node->length > 0) ? decodeHtmlEntities($node->item(0)->nodeValue) : $defaultValue;
}

/**
 * Extract the inner HTML of a node from the DOM using XPath.
 */
function extractInnerHtml(DOMXPath $xpath, string $query, string $defaultValue): string {
    $node = $xpath->query($query)->item(0);
    return $node ? getInnerHtml($node) : $defaultValue;
}

/**
 * Get the inner HTML of a DOM node.
 */
function getInnerHtml(DOMNode $node): string {
    $innerHTML = '';
    foreach ($node->childNodes as $child) {
        $innerHTML .= $node->ownerDocument->saveHTML($child);
    }
    return $innerHTML;
}

/**
 * Clean and escape the given string for CSV output.
 */
function cleanForCsv(string $string): string {
    $string = trim($string);
    return preg_match('/[,"\n\r]/', $string) ? '"' . str_replace('"', '""', $string) . '"' : $string;
}

/**
 * Decode HTML entities into their corresponding characters.
 */
function decodeHtmlEntities(string $string): string {
    return html_entity_decode($string, ENT_QUOTES | ENT_XML1, 'UTF-8');
}