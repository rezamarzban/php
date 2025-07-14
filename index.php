<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>List and View .md Files with LaTeX</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/github-markdown-css/github-markdown.min.css ">
    <style>
        body {
            font-family: sans-serif;
        }
        .markdown-body {
            box-sizing: border-box;
            min-width: 200px;
            max-width: 980px;
            margin: 0 auto;
            padding: 45px;
        }
        @media (max-width: 767px) {
            .markdown-body {
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <h2>Enter URL of ZIP File</h2>
    <form method="get">
        <label for="url">URL:</label>
        <input type="text" name="url" id="url" value="<?php echo isset($_GET['url']) ? htmlspecialchars($_GET['url']) : ''; ?>">
        <input type="submit" value="List .md Files">
    </form>

    <?php
    if (isset($_GET['url'])) {
        $url = $_GET['url'];

        // Basic URL validation
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            echo "<p>Invalid URL format.</p>";
            exit;
        }

        // Create a temporary file to store the ZIP
        $tempfile = tempnam(sys_get_temp_dir(), 'zip');
        if (!$tempfile || !copy($url, $tempfile)) {
            echo "<p>Failed to download ZIP file from URL.</p>";
            exit;
        }

        $zip = new ZipArchive;
        if ($zip->open($tempfile) !== TRUE) {
            echo "<p>Failed to open ZIP file.</p>";
            unlink($tempfile);
            exit;
        }

        if (isset($_GET['file'])) {
            // Display the content of the selected .md file
            $file = $_GET['file'];
            $content = $zip->getFromName($file);
            if ($content !== false) {
                echo "<h2>Content of " . htmlspecialchars($file) . "</h2>";
                echo '<div class="markdown-body" id="rendered-markdown"></div>';

                // Pass raw Markdown content safely to JavaScript
                echo '<script type="text/javascript">';
                echo 'var rawMarkdownContent = ' . json_encode($content) . ';';
                echo '</script>';

                // Load libraries
                echo '<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js "></script>';
                echo '<script src="https://cdn.jsdelivr.net/npm/mathjax @3/es5/tex-mml-chtml.js"></script>';

                // Render Markdown and process LaTeX
                echo '<script>
                    document.addEventListener("DOMContentLoaded", function() {
                        document.getElementById("rendered-markdown").innerHTML = marked.parse(rawMarkdownContent);
                        MathJax.typesetPromise().catch(function(err) {
                            console.error("MathJax typesetting failed: ", err);
                        });
                    });
                </script>';

                echo '<p><a href="?url=' . urlencode($url) . '">Back to list</a></p>';
            } else {
                echo "<p>File not found in ZIP.</p>";
            }
        } else {
            // List all .md files in the ZIP
            $md_files = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $filename = $zip->getNameIndex($i);
                if (substr($filename, -3) === '.md') {
                    $md_files[] = $filename;
                }
            }
            if (!empty($md_files)) {
                echo "<h2>.md Files in the ZIP:</h2><ul>";
                foreach ($md_files as $file) {
                    echo '<li><a href="?url=' . urlencode($url) . '&file=' . urlencode($file) . '">' . htmlspecialchars($file) . '</a></li>';
                }
                echo "</ul>";
            } else {
                echo "<p>No .md files found in the ZIP.</p>";
            }
        }

        $zip->close();
        unlink($tempfile); // Clean up
    }
    ?>
</body>
</html>
