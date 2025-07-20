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
    function displayTree($tree, $prefix = '', $base_params) {
        echo '<ul>';
        foreach ($tree as $key => $value) {
            $filename = $prefix . $key;
            if (is_array($value) && !empty($value)) {
                echo '<li>' . htmlspecialchars($key) . '/';
                displayTree($value, $filename . '/', $base_params);
                echo '</li>';
            } else {
                // Check if the file is a ZIP and if we're at the main ZIP level (no 'action' in params)
                if (strtolower(substr($filename, -4)) === '.zip' && !isset($base_params['action'])) {
                    $link_params = ['action' => 'ask_password'] + $base_params + ['nested_file' => $filename];
                } else {
                    $link_params = $base_params + ['file' => $filename];
                }
                echo '<li><a href="?' . http_build_query($link_params) . '">' . htmlspecialchars($key) . '</a></li>';
            }
        }
        echo '</ul>';
    }

    // Handle password prompt for nested ZIPs
    if (isset($_GET['action']) && $_GET['action'] === 'ask_password') {
        echo "<h2>Enter Password for Nested ZIP: " . htmlspecialchars($_GET['nested_file']) . "</h2>";
        echo '<form method="get">';
        echo '<input type="hidden" name="action" value="list_nested">';
        echo '<input type="hidden" name="url" value="' . htmlspecialchars($_GET['url']) . '">';
        echo '<input type="hidden" name="password" value="' . htmlspecialchars($_GET['password']) . '">';
        echo '<input type="hidden" name="nested_file" value="' . htmlspecialchars($_GET['nested_file']) . '">';
        echo '<label for="nested_password">Password (leave blank if none):</label>';
        echo '<input type="password" name="nested_password" id="nested_password">';
        echo '<input type="submit" value="List Files">';
        echo '</form>';
        exit;
    }

    // Handle listing or viewing contents of nested ZIPs
    if (isset($_GET['action']) && $_GET['action'] === 'list_nested') {
        $tempfile = tempnam(sys_get_temp_dir(), 'zip');
        if (copy($_GET['url'], $tempfile)) {
            $zip = new ZipArchive;
            if ($zip->open($tempfile) === TRUE) {
                if (!empty($_GET['password'])) {
                    $zip->setPassword($_GET['password']);
                }
                $nested_content = $zip->getFromName($_GET['nested_file']);
                $zip->close();
                if ($nested_content !== false) {
                    $nested_tempfile = tempnam(sys_get_temp_dir(), 'nested_zip');
                    file_put_contents($nested_tempfile, $nested_content);
                    
                    $nested_zip = new ZipArchive;
                    if ($nested_zip->open($nested_tempfile) === TRUE) {
                        if (!empty($_GET['nested_password'])) {
                            $nested_zip->setPassword($_GET['nested_password']);
                        }
                        if (isset($_GET['file'])) {
                            // View a specific file in the nested ZIP
                            $content = $nested_zip->getFromName($_GET['file']);
                            if ($content !== false) {
                                $extension = pathinfo($_GET['file'], PATHINFO_EXTENSION);
                                if (strtolower($extension) === 'md') {
                                    // Handle Markdown files with image embedding
                                    $md_dir = dirname($_GET['file']);
                                    preg_match_all('/!\[.*?\]\((.*?)\)/', $content, $matches);
                                    $image_paths = $matches[1];
                                    foreach ($image_paths as $image_path) {
                                        $full_image_path = $md_dir === '.' ? $image_path : $md_dir . '/' . $image_path;
                                        $image_content = $nested_zip->getFromName($full_image_path);
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
                            } else {
                                echo "<p>Failed to extract file. The password may be incorrect.</p>";
                            }
                        } else {
                            // List files in the nested ZIP
                            $files = [];
                            for ($i = 0; $i < $nested_zip->numFiles; $i++) {
                                $filename = $nested_zip->getNameIndex($i);
                                if (substr($filename, -1) !== '/') {
                                    $files[] = $filename;
                                }
                            }
                            $tree = buildTree($files);
                            $base_params = [
                                'action' => 'list_nested',
                                'url' => $_GET['url'],
                                'password' => $_GET['password'],
                                'nested_file' => $_GET['nested_file'],
                                'nested_password' => $_GET['nested_password']
                            ];
                            echo "<h2>Contents of " . htmlspecialchars($_GET['nested_file']) . "</h2>";
                            displayTree($tree, '', $base_params);
                        }
                        $nested_zip->close();
                        unlink($nested_tempfile);
                    } else {
                        echo "<p>Failed to open nested ZIP.</p>";
                    }
                } else {
                    echo "<p>Failed to extract nested ZIP from main ZIP.</p>";
                }
                unlink($tempfile);
            } else {
                echo "<p>Failed to open main ZIP.</p>";
            }
        } else {
            echo "<p>Failed to download ZIP file.</p>";
        }
        exit;
    }

    // Handle main ZIP processing
    if (isset($_GET['url'])) {
        $url = $_GET['url'];
        $password = isset($_GET['password']) ? $_GET['password'] : '';
        if (!empty($url)) {
            $tempfile = tempnam(sys_get_temp_dir(), 'zip');
            if (copy($url, $tempfile)) {
                $zip = new ZipArchive;
                if ($zip->open($tempfile) === TRUE) {
                    if (!empty($password)) {
                        $zip->setPassword($password);
                    }
                    if (isset($_GET['file'])) {
                        // Display content of a selected file
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
                        // List all files and directories in the ZIP
                        $files = [];
                        for ($i = 0; $i < $zip->numFiles; $i++) {
                            $filename = $zip->getNameIndex($i);
                            if (substr($filename, -1) !== '/') {
                                $files[] = $filename;
                            }
                        }
                        $tree = buildTree($files);
                        $base_params = ['url' => $url, 'password' => $password];
                        echo "<h2>Contents of the ZIP:</h2>";
                        displayTree($tree, '', $base_params);
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
