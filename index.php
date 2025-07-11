<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>List and View .md Files with LaTeX and Images</title>
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
            $tempfile = tempnam(sys_get_temp_dir(), 'zip');
            if (copy($url, $tempfile)) {
                $zip = new ZipArchive;
                if ($zip->open($tempfile) === TRUE) {
                    if (isset($_GET['file'])) {
                        $file = $_GET['file'];
                        $content = $zip->getFromName($file);
                        if ($content !== false) {
                            // Function to resolve relative paths
                            function resolvePath($base, $path) {
                                if (strpos($path, '/') === 0) return substr($path, 1); // Absolute path
                                
                                $baseParts = explode('/', dirname($base));
                                $pathParts = explode('/', $path);
                                foreach ($pathParts as $part) {
                                    if ($part === '.') continue;
                                    if ($part === '..') {
                                        if (!empty($baseParts)) array_pop($baseParts);
                                    } else {
                                        $baseParts[] = $part;
                                    }
                                }
                                return implode('/', $baseParts);
                            }

                            // Process images without modifying HTML tags
                            $processedContent = preg_replace_callback(
                                '/!\[(.*?)\]\((.*?)\)/',
                                function($matches) use ($zip, $file) {
                                    $altText = $matches[1];
                                    $imgPath = $matches[2];
                                    
                                    // Skip if already data URI or external URL
                                    if (preg_match('/^(data:|https?:)/i', $imgPath)) {
                                        return $matches[0];
                                    }
                                    
                                    // Resolve relative path
                                    $absPath = resolvePath($file, $imgPath);
                                    
                                    // Get image data from ZIP
                                    $imageData = $zip->getFromName($absPath);
                                    if ($imageData === false) return $matches[0]; // Return original if not found
                                    
                                    // Detect MIME type
                                    $finfo = new finfo(FILEINFO_MIME_TYPE);
                                    $mime = $finfo->buffer($imageData);
                                    
                                    // Create data URI
                                    $base64 = base64_encode($imageData);
                                    return "![$altText](data:$mime;base64,$base64)";
                                },
                                $content
                            );

                            echo "<h2>Content of " . htmlspecialchars($file) . "</h2>";
                            echo '<a href="?url=' . urlencode($url) . '">Back to list</a>';
                            // Output both original and processed content
                            echo '<div id="raw-markdown" style="display:none;">' . htmlspecialchars($content) . '</div>';
                            echo '<div id="processed-markdown" style="display:none;">' . htmlspecialchars($processedContent) . '</div>';
                            echo '<div class="markdown-body" id="rendered-markdown"></div>';
                            echo '<script src="https://romantic-cerf-bi21kt1n6.storage.c2.liara.space/cdn/marked.min.js"></script>';
                            echo '<script src="https://romantic-cerf-bi21kt1n6.storage.c2.liara.space/cdn/tex-mml-chtml.js"></script>';
                            echo '<script>
                                // Use original content for MathJax, processed content for images
                                const rawContent = document.getElementById("raw-markdown").innerText;
                                const processedContent = document.getElementById("processed-markdown").innerText;
                                
                                // Create a combined content where we keep original math expressions
                                let combinedContent = rawContent;
                                
                                // Replace image references with processed versions
                                const imgRegex = /!\[(.*?)\]\((.*?)\)/g;
                                let match;
                                while ((match = imgRegex.exec(processedContent)) !== null) {
                                    const altText = match[1];
                                    const imgPath = match[2];
                                    if (imgPath.startsWith("data:")) {
                                        combinedContent = combinedContent.replace(
                                            `![${altText}](${match[2].replace(imgPath, "PLACEHOLDER")})`,
                                            `![${altText}](${imgPath})`
                                        );
                                    }
                                }
                                
                                // Render the combined content
                                document.getElementById("rendered-markdown").innerHTML = marked.parse(combinedContent);
                                
                                // Configure MathJax to process math
                                MathJax = {
                                    tex: {
                                        inlineMath: [['$', '$'], ['\\(', '\\)']]
                                    },
                                    options: {
                                        skipHtmlTags: ['script', 'noscript', 'style', 'textarea', 'pre', 'code'],
                                        ignoreHtmlClass: 'tex2jax_ignore'
                                    }
                                };
                                
                                // Process math after rendering
                                MathJax.typesetPromise();
                            </script>';
                        } else {
                            echo "<p>File not found in ZIP.</p>";
                        }
                    } else {
                        // List .md files
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
