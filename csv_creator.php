<?php

// Specify the root directory from which the user can select directories
$rootDir = __DIR__ . '/hc/es'; // Change this to your desired directory

/**
 * Recursively lists all directories under the given root directory.
 *
 * @param string $dir The directory to list
 * @return array List of directories
 */
function listDirectories($dir) {
    $result = [];
    $items = scandir($dir);

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $fullPath = $dir . '/' . $item;
        if (is_dir($fullPath)) {
            $result[] = $fullPath;
            $result = array_merge($result, listDirectories($fullPath));
        }
    }

    return $result;
}

// Get all directories in the root directory
$directories = listDirectories($rootDir);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select Directory for HTML to CSV Conversion</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
        }
        form {
            margin-bottom: 20px;
        }
        .result {
            margin-top: 20px;
            padding: 10px;
            border: 1px solid #ddd;
            background-color: #f9f9f9;
        }
    </style>
</head>
<body>

<h1>HTML to CSV Converter</h1>

<form action="process.php" method="post">
    <label for="directory">Select a directory with HTML files:</label>
    <select id="directory" name="directory">
        <?php foreach ($directories as $directory): ?>
            <option value="<?php echo htmlspecialchars($directory); ?>">
                <?php echo htmlspecialchars(str_replace($rootDir, '', $directory)); ?>
            </option>
        <?php endforeach; ?>
    </select>
    <br><br>
    <input type="submit" name="submit" value="Convert to CSV">
</form>

</body>
</html>