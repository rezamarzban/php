<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Markdown File Viewer</title>
    <link rel="stylesheet" href="https://romantic-cerf-bi21kt1n6.storage.c2.liara.space/cdn/github-markdown.min.css">
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
        .error { color: red; }
        .back-link { margin: 20px 0; }
    </style>
</head>
<body>
    <h2>Enter URL of ZIP File</h2>
    <form method="get">
        <label for="url">URL:</label>
        <input type="url" name="url" id="url" value="<?php echo isset($_GET['url']) ? htmlspecialchars($_GET['url'], ENT_QUOTES, 'UTF-8') : ''; ?>" required>
        <input type="submit" value="List .md Files">
    </form>

    <?php
    // Function to resolve relative paths
    function resolvePath($base, $path) {
        if (strpos($path, '/') === 0) {
            return ltrim($path, '/');
        }
        
        $baseParts = explode('/', dirname($base));
        $pathParts = explode('/', $path);
        $result = $baseParts;
        
        foreach ($pathParts as $part) {
            if ($part === '.' || $part === '') continue;
            if ($part === '..') {
                array_pop($result);
            } else {
                $result[] = $part;
            }
        }
        return implode('/', $result);
    }

    // Function to validate URL
    function isValidUrl($url) {
        return filter_var($url, FILTER_VALIDATE_URL) && 
               preg_match('/^https?:\/\/.*\.zip$/i', $url);
    }

    try {
        if (isset($_GET['url']) && !empty($_GET['url'])) {
            $url = filter_var($_GET['url'], FILTER_SANITIZE_URL);
            
            if (!isValidUrl($url)) {
                throw new Exception("Invalid ZIP file URL. Please provide a valid HTTPS URL ending in .zip");
            }

            $tempfile = tempnam(sys_get_temp_dir(), 'zip_');
            $http_options = [
                'http' => [
                    'timeout' => 30,
                    'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
                ]
            ];
            $context = stream_context_create($http_options);
            
            if (@copy($url, $tempfile, $context) === false) {
                throw new Exception("Failed to download ZIP file from URL.");
            }

            $zip = new ZipArchive;
            if ($zip->open($tempfile) !== TRUE) {
                throw new Exception("Failed to open ZIP file.");
            }

            if (isset($_GET['file']) && !empty($_GET['file'])) {
                $file = filter_var($_GET['file'], FILTER_SANITIZE_STRING);
                $content = $zip->getFromName($file);
                
                if ($content === false) {
                    throw new Exception("File not found in ZIP.");
                }

                // Process images
                $processedContent = preg_replace_callback(
                    '/!\[(.*?)\]\((.*?)\)/',
                    function($matches) use ($zip, $file) {
                        $altText = $matches[1];
                        $imgPath = $matches[2];
                        
                        // Skip external URLs or data URIs
                        if (preg_match('/^(data:|https?:)/i', $imgPath)) {
                            return $matches[0];
                        }
                        
                        $absPath = resolvePath($file, $imgPath);
                        $imageData = $zip->getFromName($absPath);
                        
                        if ($imageData === false) {
                            return $matches[0];
                        }
                        
                        $finfo = new finfo(FILEINFO_MIME_TYPE);
                        $mime = $finfo->buffer($imageData);
                        $base64 = base64_encode($imageData);
                        return "![$altText](data:$mime;base64,$base64)";
                    },
                    $content
                );

                echo "<h2>Content of " . htmlspecialchars($file, ENT_QUOTES, 'UTF-8') . "</h2>";
                echo '<a href="?url=' . urlencode($url) . '" class="back-link">Back to list</a>';
                ?>
                <div id="raw-markdown" style="display:none;"><?php echo htmlspecialchars($content, ENT_QUOTES, 'UTF-8'); ?></div>
                <div id="processed-markdown" style="display:none;"><?php echo htmlspecialchars($processedContent, ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="markdown-body" id="rendered-markdown"></div>
                <script src="https://romantic-cerf-bi21kt1n6.storage.c2.liara.space/cdn/marked.min.js"></script>
                <script src="https://romantic-cerf-bi21kt1n6.storage.c2.liara.space/cdn/tex-mml-chtml.js"></script>
                <script>
                    document.addEventListener('DOMContentLoaded', () => {
                        const rawContent = document.getElementById('raw-markdown').innerText;
                        const processedContent = document.getElementById('processed-markdown').innerText;
                        let combinedContent = rawContent;

                        // Replace image references
                        const imgRegex = /!\[(.*?)\]\((.*?)\)/g;
                        let match;
                        while ((match = imgRegex.exec(processedContent)) !== null) {
                            const altText = match[1];
                            const imgPath = match[2];
                            if (imgPath.startsWith('data:')) {
                                combinedContent = combinedContent.replace(
                                    `![${altText}]([^)]+)`,
                                    `![${altText}](${imgPath})`
                                );
                            }
                        }

                        // Render markdown
                        document.getElementById('rendered-markdown').innerHTML = marked.parse(combinedContent);

                        // Configure MathJax
                        MathJax = {
                            tex: {
                                inlineMath: [['$', '$'], ['\\(', '\\)']],
                                processEscapes: true
                            },
                            options: {
                                skipHtmlTags: ['script', 'noscript', 'style', 'textarea', 'pre', 'code'],
                                ignoreHtmlClass: 'tex2jax_ignore'
                            }
                        };

                        // Process math
                        MathJax.typesetPromise().catch(err => console.error('MathJax error:', err));
                    });
                </script>
                <?php
            } else {
                // List .md files
                $md_files = [];
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $filename = $zip->getNameIndex($i);
                    if (strtolower(substr($filename, -3)) === '.md') {
                        $md_files[] = $filename;
                    }
                }

                if (empty($md_files)) {
                    throw new Exception("No .md files found in the ZIP.");
                }

                echo "<h2>.md Files in the ZIP:</h2><ul>";
                foreach ($md_files as $file) {
                    echo '<li><a href="?url=' . urlencode($url) . '&file=' . urlencode($file) . '">' . htmlspecialchars($file, ENT_QUOTES, 'UTF-8') . '</a></li>';
                }
                echo "</ul>";
            }

            $zip->close();
            @unlink($tempfile);
        }
    } catch (Exception $e) {
        echo '<p class="error">' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>';
    }
    ?>
</body>
</html>
