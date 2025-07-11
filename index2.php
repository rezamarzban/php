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
<?php
// Handle image serving
if (isset($_GET['action']) && $_GET['action'] === 'serve_image' && isset($_GET['url']) && isset($_GET['image']) && isset($_GET['dir'])) {
    $url = $_GET['url'];
    $image_path = $_GET['image'];
    $temp_dir_id = $_GET['dir'];

    // Validate URL
    if (!filter_var($url, FILTER_VALIDATE_URL) || !preg_match('/^https?:\/\//', $url)) {
        http_response_code(400);
        echo "Invalid URL.";
        exit;
    }

    // Validate image path (allow only certain extensions and no path traversal)
    $valid_extensions = ['png', 'jpg', 'jpeg', 'gif'];
    $ext = strtolower(pathinfo($image_path, PATHINFO_EXTENSION));
    if (!in_array($ext, $valid_extensions) || strpos($image_path, '..') !== false) {
        http_response_code(400);
        echo "Invalid image file.";
        exit;
    }

    // Validate temp directory
    $temp_dir = sys_get_temp_dir() . '/zip_images_' . basename($temp_dir_id);
    if (!is_dir($temp_dir)) {
        http_response_code(404);
        echo "Temporary directory not found.";
        exit;
    }

    // Construct full path to image
    $full_path = $temp_dir . '/' . $image_path;
    if (!file_exists($full_path)) {
        http_response_code(404);
        echo "Image not found.";
        exit;
    }

    // Serve the image with appropriate content type
    switch ($ext) {
        case 'png':
            header('Content-Type: image/png');
            break;
        case 'jpg':
        case 'jpeg':
            header('Content-Type: image/jpeg');
            break;
        case 'gif':
            header('Content-Type: image/gif');
            break;
    }
    header('Content-Length: ' . filesize($full_path));
    readfile($full_path);
    exit;
}

// Main page logic
?>
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

    function extract_images($zip, $temp_dir) {
        $image_extensions = ['png', 'jpg', 'jpeg', 'gif'];
        $image_files = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            if (in_array($ext, $image_extensions)) {
                $zip->extractTo($temp_dir, $filename);
                $image_files[] = $filename;
            }
        }
        return $image_files;
    }

    function display_md_file($zip, $file, $url, $temp_dir) {
        if (!preg_match('/^[a-zA-Z0-9_\-\.\/]+\.md$/', $file)) {
            echo "<p>Invalid file name.</p>";
            return;
        }
        $content = $zip->getFromName($file);
        if ($content !== false) {
            // Rewrite image paths in Markdown to point to self with action=serve_image
            $image_dir = basename($temp_dir);
            $content = preg_replace_callback(
                '/!\[([^\]]*)\]\(([^)]+)\)/',
                function ($matches) use ($url, $image_dir) {
                    $alt_text = $matches[1];
                    $image_path = $matches[2];
                    // Only rewrite relative paths (not URLs)
                    if (!preg_match('/^https?:\/\//', $image_path)) {
                        return "![$alt_text](?action=serve_image&url=" . urlencode($url) . "&image=" . urlencode($image_path) . "&dir=$image_dir)";
                    }
                    return $matches[0]; // Leave absolute URLs unchanged
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
        $temp_dir = sys_get_temp_dir() . '/zip_images_' . uniqid();
        mkdir($temp_dir, 0755, true);
        if (copy($url, $tempfile)) {
            $zip = new ZipArchive;
            if ($zip->open($tempfile) === TRUE) {
                // Extract images to temporary directory
                $image_files = extract_images($zip, $temp_dir);
                if (isset($_GET['file'])) {
                    display_md_file($zip, $_GET['file'], $url, $temp_dir);
                } else {
                    list_md_files($zip, $url);
                }
                $zip->close();
                // Clean up
                unlink($tempfile);
                // Delete extracted images and directory
                foreach (glob("$temp_dir/*") as $file) {
                    unlink($file);
                }
                rmdir($temp_dir);
            } else {
                echo "<p>Invalid or corrupted ZIP file.</p>";
                unlink($tempfile);
                rmdir($temp_dir);
            }
        } else {
            echo "<p>Failed to download ZIP file. Please check the URL or network connection.</p>";
            rmdir($temp_dir);
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
