<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Markdown Viewer with LaTeX Support</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/github-markdown-css/github-markdown.min.css">
    <!-- Include libraries in head for better performance -->
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <script src="https://polyfill.io/v3/polyfill.min.js?features=es6"></script>
    <script id="MathJax-script" async src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-mml-chtml.js"></script>
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
        .error { color: #d93025; font-weight: bold; }
        .container { max-width: 980px; margin: 0 auto; padding: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <h2>View Markdown Files from ZIP Archive</h2>
        <form method="get">
            <label for="url">ZIP File URL (HTTP/HTTPS only):</label>
            <input type="url" name="url" id="url" 
                   placeholder="https://example.com/files.zip"
                   value="<?php echo isset($_GET['url']) ? htmlspecialchars($_GET['url']) : ''; ?>" 
                   required size="50">
            <input type="submit" value="Load ZIP">
        </form>

        <?php
        if (isset($_GET['url'])) {
            $url = $_GET['url'];
            
            // Validate URL format
            if (empty($url)) {
                echo '<p class="error">Please enter a URL</p>';
                exit;
            }
            
            // Validate URL scheme
            if (!preg_match('%^https?://%i', $url)) {
                echo '<p class="error">Only HTTP/HTTPS URLs are allowed</p>';
                exit;
            }
            
            // Create temporary file
            $tempfile = tempnam(sys_get_temp_dir(), 'zip_');
            $context = stream_context_create([
                'http' => ['timeout' => 30] // 30-second timeout
            ]);
            
            // Download file with error handling
            if (!@copy($url, $tempfile, $context)) {
                echo '<p class="error">Failed to download file. Check URL validity and network connection.</p>';
                @unlink($tempfile);
                exit;
            }
            
            $zip = new ZipArchive;
            if ($zip->open($tempfile) !== TRUE) {
                echo '<p class="error">Invalid ZIP file format</p>';
                @unlink($tempfile);
                exit;
            }
            
            // Handle file content request
            if (isset($_GET['file'])) {
                $requestedFile = $_GET['file'];
                
                // Security: Validate file path
                if (strpos($requestedFile, '..') !== false || !preg_match('/\.md$/i', $requestedFile)) {
                    echo '<p class="error">Invalid file requested</p>';
                } else {
                    $content = $zip->getFromName($requestedFile);
                    if ($content === false) {
                        echo '<p class="error">File not found in archive</p>';
                    } else {
                        echo '<h2>'.htmlspecialchars(basename($requestedFile)).'</h2>';
                        echo '<div class="markdown-body" id="rendered-markdown">';
                        echo htmlspecialchars($content);
                        echo '</div>';
                        echo '<a href="?url='.urlencode($url).'">&laquo; Back to file list</a>';
                        
                        // Render Markdown and LaTeX
                        echo '<script>
                            document.addEventListener("DOMContentLoaded", function() {
                                const mdContent = document.getElementById("rendered-markdown").textContent;
                                document.getElementById("rendered-markdown").innerHTML = marked.parse(mdContent);
                                
                                // Re-process MathJax after Markdown rendering
                                if (typeof MathJax !== "undefined") {
                                    MathJax.typesetPromise();
                                }
                            });
                        </script>';
                    }
                }
            } 
            // List Markdown files
            else {
                $mdFiles = [];
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $filename = $zip->getNameIndex($i);
                    if (preg_match('/\.md$/i', $filename)) {
                        $mdFiles[] = $filename;
                    }
                }
                
                if (count($mdFiles) > 0) {
                    echo '<h2>Markdown Files in Archive:</h2><ul>';
                    foreach ($mdFiles as $file) {
                        echo '<li><a href="?url='.urlencode($url).'&file='.urlencode($file).'">'
                            .htmlspecialchars(basename($file)).'</a></li>';
                    }
                    echo '</ul>';
                } else {
                    echo '<p>No Markdown files found in archive</p>';
                }
            }
            
            $zip->close();
            @unlink($tempfile);
        }
        ?>
    </div>
</body>
</html>
