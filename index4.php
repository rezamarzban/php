<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>List and View Files in ZIP</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/github-markdown-css/github-markdown.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.5.1/styles/default.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.5.1/highlight.min.js"></script>
    <style>
        .markdown-body {
            box-sizing: border-box;
            min-width: 200px;
            max-width: 980px;
            margin: 0 auto;
            padding: 45px;
        }
        ul {
            list-style-type: none;
            padding-left: 20px;
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
        <label for="password">Password (if protected):</label>
        <input type="password" name="password" id="password">
        <input type="submit" value="List Files">
    </form>

    <?php
    // Define code file extensions and their corresponding Highlight.js languages
    $code_extensions = [
        'php' => 'php',
        'js' => 'javascript',
        'css' => 'css',
        'html' => 'html',
        'py' => 'python',
        'java' => 'java',
        'c' => 'c',
        'cpp' => 'cpp',
        'cs' => 'csharp',
        'rb' => 'ruby',
        'go' => 'go',
        'swift' => 'swift',
        'kt' => 'kotlin',
        'rs' => 'rust',
        'ts' => 'typescript',
        'sh' => 'bash',
        'sql' => 'sql',
        'json' => 'json',
        'xml' => 'xml',
    ];

    // Define common binary file extensions
    $binary_extensions = [
        'jpg', 'jpeg', 'png', 'gif', 'bmp', 'pdf', 'zip', 'rar', 'exe', 'dll',
        'bin', 'mp3', 'mp4', 'avi', 'mov', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'
    ];

    // Function to build a tree structure from file paths
    function buildTree($files) {
        $tree = [];
        foreach ($files as $file) {
            $parts = explode('/', trim($file, '/'));
            $current = &$tree;
            foreach ($parts as $part) {
                if (!isset($current[$part])) {
                    $current[$part] = [];
                }
                $current = &$current[$part];
            }
        }
        return $tree;
    }

    // Function to display the tree structure as a nested list
    function displayTree($tree, $prefix = '', $url, $password) {
        echo '<ul>';
        foreach ($tree as $key => $value) {
            echo '<li>';
            if (empty($value)) {
                // File (leaf node)
                echo '<a href="?url=' . urlencode($url) . '&password=' . urlencode($password) . '&file=' . urlencode($prefix . $key) . '">' . htmlspecialchars($key) . '</a>';
            } else {
                // Directory (non-leaf node)
                echo htmlspecialchars($key) . '/';
                displayTree($value, $prefix . $key . '/', $url, $password);
            }
            echo '</li>';
        }
        echo '</ul>';
    }

    // Process the ZIP file when a URL is provided
    if (isset($_GET['url'])) {
        $url = $_GET['url'];
        $password = isset($_GET['password']) ? $_GET['password'] : '';
        if (!empty($url)) {
            $tempfile = tempnam(sys_get_temp_dir(), 'zip');
            if (copy($url, $tempfile)) {
                $zip = new ZipArchive;
                if ($zip->open($tempfile) === TRUE) {
                    // Set the password if provided
                    if (!empty($password)) {
                        $zip->setPassword($password);
                    }

                    if (isset($_GET['file'])) {
                        // Display the content of a selected file
                        $file = $_GET['file'];
                        $content = $zip->getFromName($file);
                        if ($content !== false) {
                            echo "<h2>Content of " . htmlspecialchars($file) . "</h2>";
                            $extension = pathinfo($file, PATHINFO_EXTENSION);
                            if (strtolower($extension) === 'md') {
                                // Handle Markdown files with image embedding
                                $md_dir = dirname($file);
                                preg_match_all('/!\[.*?\]\((.*?)\)/', $content, $matches);
                                $image_paths = $matches[1];
                                foreach ($image_paths as $image_path) {
                                    $full_image_path = $md_dir === '.' ? $image_path : $md_dir . '/' . $image_path;
                                    $image_content = $zip->getFromName($full_image_path);
                                    if ($image_content !== false) {
                                        $image_extension = pathinfo($full_image_path, PATHINFO_EXTENSION);
                                        $mime_type = 'image/' . strtolower($image_extension);
                                        $data_uri = 'data:' . $mime_type . ';base64,' . base64_encode($image_content);
                                        $content = str_replace($image_path, $data_uri, $content);
                                    }
                                }
                                echo '<div id="raw-markdown" style="display:none;" aria-hidden="true">' . htmlspecialchars($content) . '</div>';
                                echo '<div class="markdown-body" id="rendered-markdown"></div>';
                                echo '<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>';
                                echo '<script>
                                    MathJax = {
                                        tex: {
                                            inlineMath: [["$", "$"], ["\\\\(", "\\\\)"]],
                                            displayMath: [["$$", "$$"], ["\\\\[", "\\\\]"]]
                                        }
                                    };
                                </script>';
                                echo '<script src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-mml-chtml.js"></script>';
                                echo '<script>
                                    document.getElementById("rendered-markdown").innerHTML = marked.parse(document.getElementById("raw-markdown").innerText);
                                    MathJax.typesetPromise().catch(function(err) {
                                        console.error("MathJax typesetting failed: ", err);
                                    });
                                </script>';
                            } elseif (in_array(strtolower($extension), $binary_extensions)) {
                                echo "<p>This is a binary file and cannot be displayed.</p>";
                            } elseif (array_key_exists(strtolower($extension), $code_extensions)) {
                                $language = $code_extensions[strtolower($extension)];
                                echo '<pre><code class="language-' . $language . '">' . htmlspecialchars($content) . '</code></pre>';
                                echo '<script>hljs.highlightAll();</script>';
                            } else {
                                echo '<pre>' . htmlspecialchars($content) . '</pre>';
                            }
                            echo '<a href="?url=' . urlencode($url) . '&password=' . urlencode($password) . '">Back to list</a>';
                        } else {
                            echo "<p>Failed to extract file. The ZIP might be password-protected with an incorrect password.</p>";
                        }
                    } else {
                        // Build and display the tree view
                        $files = [];
                        for ($i = 0; $i < $zip->numFiles; $i++) {
                            $filename = $zip->getNameIndex($i);
                            if (substr($filename, -1) !== '/') { // Exclude directory entries
                                $files[] = $filename;
                            }
                        }
                        $tree = buildTree($files);
                        echo "<h2>Contents of the ZIP:</h2>";
                        displayTree($tree, '', $url, $password);
                    }
                    $zip->close();
                    unlink($tempfile);
                } else {
                    echo "<p>Failed to open ZIP file. It might be password-protected or corrupted.</p>";
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
