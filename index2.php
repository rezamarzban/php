<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>List and View .md Files with LaTeX and Images</title>
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
            .markdown-body { padding: 15px; }
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
    <div id="loading" style="display:none;">Loading...</div>

    <?php
    function list_md_files($zip, $url) {
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

    function get_image_data_uri($zip, $image_path) {
        $valid_extensions = ['png', 'jpg', 'jpeg', 'gif'];
        $ext = strtolower(pathinfo($image_path, PATHINFO_EXTENSION));
        if (!in_array($ext, $valid_extensions) || strpos($image_path, '..') !== false) {
            return null; // Invalid image or potential path traversal
        }

        $image_data = $zip->getFromName($image_path);
        if ($image_data === false) {
            return null; // Image not found in ZIP
        }

        // Get MIME type
        $mime_type = match ($ext) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            default => null
        };

        if (!$mime_type) {
            return null;
        }

        // Encode image as base64 and create data URI
        $base64 = base64_encode($image_data);
        return "data:$mime_type;base64,$base64";
    }

    function display_md_file($zip, $file, $url) {
        if (!preg_match('/^[a-zA-Z0-9_\-\.\/]+\.md$/', $file)) {
            echo "<p>Invalid file name.</p>";
            return;
        }
        $content = $zip->getFromName($file);
        if ($content !== false) {
            // Rewrite image paths in Markdown to data URIs
            $content = preg_replace_callback(
                '/!\[([^\]]*)\]\(([^)]+)\)/',
                function ($matches) use ($zip) {
                    $alt_text = $matches[1];
                    $image_path = $matches[2];
                    // Only rewrite relative paths (not URLs)
                    if (!preg_match('/^https?:\/\//', $image_path)) {
                        $data_uri = get premeditated
                            return "![$alt_text]($data_uri)";
                        }
                        return $matches[0]; // Leave absolute URLs unchanged
                    }
                    return $matches[0];
                },
                $content
            );

            echo "<h2>Content of " . htmlspecialchars($file) . "</h2>";
            echo '<div id="raw-markdown" style="display:none;">' . htmlspecialchars($content) . '</div>';
            echo '<div class="markdown-body" id="rendered-markdown"></div>';
            echo '<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>';
            echo '<script src="https://cdn.jsdelivr.net/npm/dompurify@2.4.1/dist/purify.min.js"></script>';
            echo '<script src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-mml-chtml.js"></script>';
            echo '<script>
                const rawMarkdown = document.getElementById("raw-markdown").innerText;
                const parsedMarkdown = marked.parse(rawMarkdown);
                document.getElementById("rendered-markdown").innerHTML = DOMPurify.sanitize(parsedMarkdown);
                MathJax.typesetPromise().then(() => {
                    console.log("MathJax typesetting completed.");
                }).catch(err => {
                    console.error("MathJax typesetting failed: ", err);
                    document.getElementById("rendered-markdown").innerHTML += "<p>Error rendering LaTeX equations.</p>";
                });
                document.getElementById("loading").style.display = "none";
            </script>';
            echo '<a href="?url=' . urlencode($url) . '">Back to list</a>';
        } else {
            echo "<p>File not found in ZIP.</p>";
        }
    }

    if (isset($_GET['url']) && !empty($_GET['url'])) {
        $url = $_GET['url'];
        if (!filter_var($url, FILTER_VALIDATE_URL) || !preg_match('/^https?:\/\//', $url)) {
            echo "<p>Invalid or unsupported URL scheme.</p>";
            exit;
        }
        $tempfile = tempnam(sys_get_temp_dir(), 'zip');
        if (copy($url, $tempfile)) {
            $zip = new ZipArchive;
            if ($zip->open($tempfile) === TRUE) {
                if (isset($_GET['file'])) {
                    display_md_file($zip, $_GET['file'], $url);
                } else {
                    list_md_files($zip, $url);
                }
                $zip->close();
                unlink($tempfile);
            } else {
                echo "<p>Invalid or corrupted ZIP file.</p>";
                unlink($tempfile);
            }
        } else {
            echo "<p>Failed to download ZIP file. Please check the URL or network connection.</p>";
            unlink($tempfile);
        }
    } else {
        echo "<p>Please enter a URL.</p>";
    }
    ?>
    <script>
        document.querySelector('form').addEventListener('submit', () => {
            document.getElementById('loading').style.display = 'block';
        });
    </script>
</body>
</html>
