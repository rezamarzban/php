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
 * Resolves relative paths within the ZIP, handling both / and \ separators
 * @param string $base Base path (file being processed)
 * @param string $path Relative path to resolve
 * @return string Resolved path
 */
function resolvePath($base, $path) {
    $base = str_replace('\\', '/', $base);
    $path = str_replace('\\', '/', $path);
    
    if (strpos($path, '/') === 0) {
        return ltrim($path, '/');
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

/**
 * Converts HTML <sub> and <sup> to LaTeX syntax
 * @param string $content Markdown content
 * @return string Content with HTML math converted to LaTeX
 */
function convertHtmlMathToLatex($content) {
    // Patterns to match <sub> and <sup> tags
    $patterns = [
        // Match <sub>...</sub> (e.g., I<sub>0</sub> -> $I_0$)
        '/(\w+)<sub>([\w\d]+(?:\{[\w\d\s]+\})?)<\/sub>/' => '$1_$2',
        // Match <sup>...</sup> (e.g., e<sup>jωt</sup> -> $e^{j\omega t}$)
        '/(\w+)<sup>([\w\d\s\(\)-]+(?:\{[\w\d\s]+\})?)<\/sup>/' => '$1^{$2}',
        // Match nested sub/sup (e.g., r<sub>pq</sub> -> $r_{pq}$)
        '/(\w+)<sub>([\w\d]+(?:\{[\w\d\s]+\})?)<\/sub>/' => '$1_{$2}',
        // Match complex expressions (e.g., e<sup>-jkr<sub>pq</sub></sup> -> $e^{-jkr_{pq}}$)
        '/(\w+)<sup>([\w\d\s\(\)-]*<sub>([\w\d]+(?:\{[\w\d\s]+\})?)<\/sub>[\w\d\s\(\)-]*)<\/sup>/' => '$1^{$2}',
    ];
    
    foreach ($patterns as $pattern => $replacement) {
        $content = preg_replace($pattern, '$$' . $replacement . '$$', $content);
    }
    
    return $content;
}

/**
 * Protects LaTeX content and sanitizes non-LaTeX parts
 * @param string $content Markdown content
 * @return string Processed content
 */
function protectLatex($content) {
    // First, convert HTML math to LaTeX
    $content = convertHtmlMathToLatex($content);
    
    // Match LaTeX expressions (inline $...$, \(...\), or display \[...\], $$...$$)
    $pattern = '/(\$\$[\s\S]*?\$\$|\$[\s\S]*?\$|\\\[\s\S]*?\\\]|\\\(.*?\\\))/';
    $parts = preg_split($pattern, $content, -1, PREG_SPLIT_DELIM_CAPTURE);
    
    $result = '';
    foreach ($parts as $part) {
        if (preg_match($pattern, $part)) {
            // LaTeX part: keep raw
            $result .= $part;
        } else {
            // Non-LaTeX part: sanitize
            $result .= htmlspecialchars($part, ENT_QUOTES, 'UTF-8');
        }
    }
    
    return $result;
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
                // Process images
                $content = preg_replace_callback(
                    '/!\[(.*?)\]\((.*?)\)/',
                    function($matches) use ($zip, $file) {
                        $altText = $matches[1];
                        $imgPath = $matches[2];
                        
                        if (preg_match('/^(data:|https?:)/i', $imgPath)) {
                            return $matches[0];
                        }
                        
                        $absPath = resolvePath($file, $imgPath);
                        if (empty($absPath)) return $matches[0];
                        
                        $imageData = $zip->getFromName($absPath);
                        if ($imageData === false) return $matches[0];
                        
                        if (strlen($imageData) > 1024 * 1024) {
                            return "![$altText](data:image/gif;base64,R0lGODlhAQABAIAAAP///wAAACwAAAAAAQABAAACAkQBADs=)";
                        }
                        
                        $finfo = new finfo(FILEINFO_MIME_TYPE);
                        $mime = $finfo->buffer($imageData);
                        if (!in_array($mime, ['image/png', 'image/jpeg', 'image/gif', 'image/webp'])) {
                            return $matches[0];
                        }
                        
                        $base64 = base64_encode($imageData);
                        return "![$altText](data:$mime;base64,$base64)";
                    },
                    $content
                );

                // Protect LaTeX and convert HTML math
                $content = protectLatex($content);
                
                echo '<a href="?url=' . urlencode($url) . '" class="back-link">Back to list</a>';
                echo '<h2>Content of ' . htmlspecialchars($file) . '</h2>';
                echo '<div class="markdown-body" id="rendered-markdown"></div>';
                ?>
                <script src="https://romantic-cerf-bi21kt1n6.storage.c2.liara.space/cdn/marked.min.js"></script>
                <script src="https://romantic-cerf-bi21kt1n6.storage.c2.liara.space/cdn/tex-mml-chtml.js"></script>
                <script>
                    MathJax = {
                        tex: {
                            inlineMath: [['$', '$'], ['\\(', '\\)']],
                            displayMath: [['$$', '$$'], ['\\[', '\\]']],
                            packages: ['base', 'ams'],
                            processEscapes: true
                        },
                        options: {
                            renderActions: {
                                addMenu: [0, '', '']
                            }
                        }
                    };
                    
                    const rawMarkdown = <?php echo json_encode($content); ?>;
                    document.getElementById('loading').style.display = 'block';
                    try {
                        document.getElementById('rendered-markdown').innerHTML = marked.parse(rawMarkdown);
                        MathJax.typesetPromise()
                            .then(() => document.getElementById('loading').style.display = 'none')
                            .catch(err => {
                                console.error('MathJax error:', err);
                                document.getElementById('loading').innerHTML = 'Error rendering LaTeX. Some equations may not display correctly.';
                            });
                    } catch (e) {
                        console.error('Marked.js error:', e);
                        document.getElementById('loading').innerHTML = 'Error rendering Markdown.';
                    }
                </script>
                <?php
            }
        } else {
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
            unlink($tempfile);
        }
    }
    ?>
</body>
</html>
