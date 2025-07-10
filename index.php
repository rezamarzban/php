<!DOCTYPE html>
<html>
<head>
    <title>List .md Files in ZIP</title>
</head>
<body>
    <h2>Enter URL of ZIP File</h2>
    <form method="post">
        <label for="url">URL:</label>
        <input type="text" name="url" id="url" value="<?php echo isset($_POST['url']) ? htmlspecialchars($_POST['url']) : ''; ?>">
        <input type="submit" name="submit" value="List .md Files">
    </form>

    <?php
    if (isset($_POST['submit'])) {
        $url = $_POST['url'];
        if (!empty($url)) {
            // Create a temporary file to store the ZIP
            $tempfile = tempnam(sys_get_temp_dir(), 'zip');
            if (copy($url, $tempfile)) {
                $zip = new ZipArchive;
                if ($zip->open($tempfile) === TRUE) {
                    $md_files = [];
                    // Iterate through all files in the ZIP
                    for ($i = 0; $i < $zip->numFiles; $i++) {
                        $filename = $zip->getNameIndex($i);
                        // Check if the file has a .md extension
                        if (substr($filename, -3) === '.md') {
                            $md_files[] = $filename;
                        }
                    }
                    $zip->close();
                    // Clean up by deleting the temporary file
                    unlink($tempfile);

                    // Display the results
                    echo "<h2>.md Files in the ZIP:</h2>";
                    if (count($md_files) > 0) {
                        echo "<ul>";
                        foreach ($md_files as $file) {
                            echo "<li>" . htmlspecialchars($file) . "</li>";
                        }
                        echo "</ul>";
                    } else {
                        echo "<p>No .md files found in the ZIP.</p>";
                    }
                } else {
                    echo "<p>Failed to open ZIP file.</p>";
                    unlink($tempfile);
                }
            } else {
                echo "<p>Failed to download ZIP file from URL.</p>";
            }
        } else {
            echo "<p>Please enter a URL.</p>";
        }
    }
    ?>
</body>
</html>
