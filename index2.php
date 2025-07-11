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

    function display_md_file($zip, $file, $url) {
        if (!preg_match('/^[a-zA-Z0-9_\-\.\/]+\.md$/', $file)) {
            echo "<p>Invalid file name.</p>";
            return;
        }

        // Get Markdown content
        $content = $zip->getFromName($file);
        if ($content === false) {
            echo "<p>File not found in ZIP.</p>";
            return;
        }

        // Extract and encode images as base64
        $allowed_extensions = ['png', 'jpg', 'jpeg', 'gif'];
        $image_map = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            if (in_array($ext, $allowed_extensions)) {
                $image_content = $zip->getFromName($filename);
                if ($image_content !== false) {
                    // Determine MIME type
                    $mime_types = [
                        'png' => 'image/png',
                        'jpg' => 'image/jpeg',
                        'jpeg' => 'image/jpeg',
                        'gif' => 'image/gif'
                    ];
                    $mime = $mime_types[$ext] ?? 'application/octet-stream';
                    // Encode image as base64
                    $base64 = 'data:' . $mime . ';base64,' . base64_encode($image_content);
                    $image_map[$filename] = $base64;
                }
            }
        }

        // Rewrite image paths in Markdown to use base64 data URLs
        foreach ($image_map as $original_path => $base64_data) {
            $content = str_replace($original_path, $base64_data, $content);
        }

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
