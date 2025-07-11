<?php
// Configuration
define('CACHE_DIR', sys_get_temp_dir() . '/zip_cache/');
define('CACHE_TTL', 3600); // Cache for 1 hour
define('MAX_ZIP_SIZE', 10 * 1024 * 1024); // 10 MB max ZIP size

// Ensure cache directory exists
if (!file_exists(CACHE_DIR)) {
    mkdir(CACHE_DIR, 0755, true);
}

/**
 * Validates a URL to ensure it's HTTP/HTTPS and well-formed
 * @param string $url
 * @return bool
 */
function isValidUrl($url) {
    return filter_var($url, FILTER_VALIDATE_URL) && preg_match('/^https?:\/\//i', $url);
}

/**
 * Converts HTML sub/sup to LaTeX (e.g., <sub>0</sub> to $_{0}$, <sup>2</sup> to $^{2}$)
 * @param string $content
 * @return string
 */
function convertHtmlToLatex($content) {
    // Convert <sub>...</sub> to LaTeX subscripts
    $content = preg_replace('/<sub>(.*?)<\/sub>/i', '$_{$1}$', $content);
    // Convert <sup>...</sup> to LaTeX superscripts
    $content = preg_replace('/<sup>(.*?)<\/sup>/i', '$^{$1}$', $content);
    return $content;
}

/**
 * Resolves relative paths within the ZIP, handling both / and \ separators
 * @param string $base Base path (file being processed)
 * @param string $path Relative path to resolve
 * @return string Resolved path
 */
function resolvePath($base, $path) {
    // Normalize separators
    $base = str_replace('\\', '/', $base);
    $path = str_replace('\\', '/', $path);
    
    if (strpos($path, '/') === 0) {
        return ltrim($path, '/'); // Absolute path
    }
    
    $baseParts = explode('/', dirname($base));
    $pathParts = explode('/', $path);
    $result = $baseParts;
    
    foreach ($pathParts as $part) {
        if ($part === '.' || empty($part)) continue;
        if ($part === '..') {
            array_pop($result);
        } else {
            $result[] = $part;
        }
    }
    
    $resolved = implode('/', $result);
    // Prevent path traversal outside ZIP
    return strpos($resolved, '..') === false ? $resolved : '';
}

/**
 * Downloads a ZIP file with size limit
 * @param string $url
 * @param string $destination
 * @return bool
 */
function downloadZip($url, $destination) {
    $fp = fopen($destination, 'w');
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_FILE => $fp,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXFILESIZE => MAX_ZIP_SIZE,
        CURLOPT_TIMEOUT => 30,
    ]);
    
    $success = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    fclose($fp);
    
    return $success && $httpCode === 200;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Markdown Viewer</title>
    <link rel="stylesheet" href="https://romantic-cerf-bi21kt1n6.storage.c2.liara.space/cdn/github-markdown.min.css">
    <style>
        .markdown-body {
            box-sizing: border-box;
            min-width: 200px;
            max-width: 980px;
            margin: 0 auto;
            padding: 45px;
        }
        .error, .loading { color: red; font-weight: bold; }
        .loading { color: blue; }
        .back-link { margin: 20px 0; display: block; }
        .debug { display: none; background: #f8f8f8; padding: 10px; border: 1px solid #ddd; }
        @media (max-width: 767px) {
            .markdown-body { padding: 15px; }
        }
    </style>
</head>
<body>
    <h2>Enter URL of ZIP File</h2>
    <form method="get">
        <label for="url">ZIP URL:</label>
        <input type="text" name="url" id="url" value="<?php echo isset($_GET['url']) ? htmlspecialchars($_GET['url']) : ''; ?>">
        <input type="submit" value="List .md Files">
    </form>

    <div id="loading" class="loading" style="display: none;">Loading...</div>
    <div id="debug" class="debug"></div>

    <?php
    if (isset($_GET['url'])) {
        $url = $_GET['url'];
        if (empty($url)) {
            echo '<p class="error">Please enter a URL.</p>';
            exit;
        }

        if (!isValidUrl($url)) {
            echo '<p class="error">Invalid URL. Please provide a valid HTTP or HTTPS URL.</p>';
            exit;
        }

        // Use cached ZIP if available and fresh
        $cacheKey = md5($url);
        $tempfile = CACHE_DIR . $cacheKey;
        $useCache = file_exists($tempfile) && (time() - filemtime($tempfile)) < CACHE_TTL;

        if (!$useCache && !downloadZip($url, $tempfile)) {
            echo '<p class="error">Failed to download ZIP file from URL.</p>';
            exit;
        }

        $zip = new ZipArchive;
        if ($zip->open($tempfile) !== TRUE) {
            echo '<p class="error">Failed to open ZIP file.</p>';
            if (!$useCache) unlink($tempfile);
            exit;
        }

        if (isset($_GET['file'])) {
            $file = filter_var($_GET['file'], FILTER_SANITIZE_STRING);
            $content = $zip->getFromName($file);
            if ($content === false) {
                echo '<p class="error">File not found in ZIP.</p>';
                echo '<a href="?url=' . urlencode($url) . '" class="back-link">Back to list</a>';
            } else {
                // Convert HTML sub/sup to LaTeX
                $content = convertHtmlToLatex($content);
                
                // Process images in Markdown
                $content = preg_replace_callback(
                    '/!\[(.*?)\]\((.*?)\)/',
                    function($matches) use ($zip, $file) {
                        $altText = $matches[1];
                        $imgPath = $matches[2];
                        
                        // Skip external URLs or data URIs
                        if (preg_match('/^(data:|https?:)/i', $imgPath)) {
                            return $matches[0];
                        }
                        
                        // Resolve path and validate
                        $absPath = resolvePath($file, $imgPath);
                        if (empty($absPath)) return $matches[0]; // Invalid path
                        
                        // Get image data
                        $imageData = $zip->getFromName($absPath);
                        if ($imageData === false) return $matches[0];
                        
                        // Check image size (limit to 1MB)
                        if (strlen($imageData) > 1024 * 1024) {
                            return "![$altText](data:image/gif;base64,R0lGODlhAQABAIAAAP///wAAACwAAAAAAQABAAACAkQBADs=)"; // Placeholder
                        }
                        
                        // Detect MIME type
                        $finfo = new finfo(FILEINFO_MIME_TYPE);
                        $mime = $finfo->buffer($imageData);
                        if (!in_array($mime, ['image/png', 'image/jpeg', 'image/gif', 'image/webp'])) {
                            return $matches[0]; // Not a supported image
                        }
                        
                        // Create data URI
                        $base64 = base64_encode($imageData);
                        return "![$altText](data:$mime;base64,$base64)";
                    },
                    $content
                );

                // Store raw Markdown in a hidden div
                echo '<div id="raw-markdown" style="display:none;">' . htmlspecialchars($content, ENT_QUOTES, 'UTF-8') . '</div>';
                echo '<a href="?url=' . urlencode($url) . '" class="back-link">Back to list</a>';
                echo '<h2>Content of ' . htmlspecialchars($file) . '</h2>';
                echo '<div class="markdown-body" id="rendered-markdown"></div>';
                ?>
                <script src="https://romantic-cerf-bi21kt1n6.storage.c2.liara.space/cdn/marked.min.js"></script>
                <script src="https://cdn.jsdelivr.net/npm/mathjax@3.2.2/es5/tex-mml-chtml.js"></script>
                <script>
                    // Configure MathJax with extensions for advanced LaTeX
                    window.MathJax = {
                        loader: {
                            load: ['[tex]/ams', '[tex]/amssymb'] // Load amsmath and amssymb for \text, \propto, \frac
                        },
                        tex: {
                            inlineMath: [['$', '$'], ['\\(', '\\)']],
                            displayMath: [['$$', '$$'], ['\\[', '\\]']],
                            packages: ['base', 'ams', 'amssymb'] // Ensure packages are available
                        },
                        options: {
                            skipHtmlTags: ['script', 'noscript', 'style', 'textarea', 'pre', 'code'],
                            ignoreHtmlClass: 'tex2jax_ignore'
                        }
                    };
                    
                    // Render Markdown
                    document.getElementById('loading').style.display = 'block';
                    const rawMarkdown = document.getElementById('raw-markdown').innerText;
                    document.getElementById('rendered-markdown').innerHTML = marked.parse(rawMarkdown);
                    MathJax.typesetPromise()
                        .then(() => {
                            document.getElementById('loading').style.display = 'none';
                        })
                        .catch(err => {
                            console.error('MathJax error:', err);
                            document.getElementById('loading').innerHTML = 'Error rendering LaTeX';
                            document.getElementById('debug').style.display = 'block';
                            document.getElementById('debug').innerHTML = 'MathJax Error: ' + err.message + '<br>Raw Markdown: <pre>' + rawMarkdown.replace(/</g, '&lt;') + '</pre>';
                        });
                </script>
                <?php
            }
        } else {
            // List .md files
            $md_files = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $filename = $zip->getNameIndex($i);
                if (substr(strtolower($filename), -3) === '.md') {
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
                echo '<p class="error">No .md files found in the ZIP.</p>';
            }
        }
        
        $zip->close();
        if (!$useCache && file_exists($tempfile)) {
            unlink($tempfile); // Only delete if not cached
        }
    }
    ?>
</body>
</html>
