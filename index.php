<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>List and View .md Files with LaTeX</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/github-markdown-css/github-markdown.min.css">
    <style>
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
        if (!empty($url)) {
            // Create a temporary file to store the ZIP
            $tempfile = tempnam(sys_get_temp_dir(), 'zip');
            if (copy($url, $tempfile)) {
                $zip = new ZipArchive;
                if ($zip->open($tempfile) === TRUE) {
                    if (isset($_GET['file'])) {
                        // Display the content of the selected .md file
                        $file = $_GET['file'];
                        $content = $zip->getFromName($file);
                        if ($content !== false) {
                            echo "<h2>Content of " . htmlspecialchars($file) . "</h2>";
                            // Output raw Markdown in a hidden div
                            echo '<div id="raw-markdown" style="display:none;">' . htmlspecialchars($content) . '</div>';
                            // Div for rendered Markdown with GitHub styling
                            echo '<div class="markdown-body" id="rendered-markdown"></div>';
                            // Include marked.js for Markdown parsing
                            echo '<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>';
                            // Include MathJax for LaTeX rendering
                            echo '<script src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-mml-chtml.js"></script>';
                            // Render Markdown and ensure MathJax processes LaTeX
                            echo '<script>
                                document.getElementById("rendered-markdown").innerHTML = marked.parse(document.getElementById("raw-markdown").innerText);
                                // Trigger MathJax typesetting after Markdown rendering
                                MathJax.typesetPromise().catch(function(err) {
                                    console.error("MathJax typesetting failed: ", err);
                                });
                            </script>';
                            echo '<a href="?url=' . urlencode($url) . '">Back to list</a>';
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
                        if (count($md_files) > 0) {
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
                    // Clean up by deleting the temporary file
                    unlink($tempfile);
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
