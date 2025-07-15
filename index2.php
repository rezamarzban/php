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

    if (isset($_GET['url'])) {
        $url = $_GET['url'];
        if (!empty($url)) {
            $tempfile = tempnam(sys_get_temp_dir(), 'zip');
            if (copy($url, $tempfile)) {
                $zip = new ZipArchive;
                if ($zip->open($tempfile) === TRUE) {
                    if (isset($_GET['file'])) {
                        // Display content of a selected file
                        $file = $_GET['file'];
                        $content = $zip->getFromName($file);
                        if ($content !== false) {
                            echo "<h2>Content of " . htmlspecialchars($file) . "</h2>";
                            $extension = pathinfo($file, PATHINFO_EXTENSION);
                            if (strtolower($extension) === 'md') {
                                // Render Markdown with LaTeX support
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
                                // Handle binary files
                                echo "<p>This is a binary file and cannot be displayed.</p>";
                            } elseif (array_key_exists(strtolower($extension), $code_extensions)) {
                                // Display code with syntax highlighting
                                $language = $code_extensions[strtolower($extension)];
                                echo '<pre><code class="language-' . $language . '">' . htmlspecialchars($content) . '</code></pre>';
                                echo '<script>hljs.highlightAll();</script>';
                            } else {
                                // Display as plain text for other text files
                                echo '<pre>' . htmlspecialchars($content) . '</pre>';
                            }
                            echo '<a href="?url=' . urlencode($url) . '">Back to list</a>';
                        } else {
                            echo "<p>File not found in ZIP.</p>";
                        }
                    } else {
                        // List all files and directories in the ZIP
                        echo "<h2>Contents of the ZIP:</h2><ul>";
                        for ($i = 0; $i < $zip->numFiles; $i++) {
                            $filename = $zip->getNameIndex($i);
                            if (substr($filename, -1) === '/') {
                                // Directory entry
                                echo '<li>' . htmlspecialchars($filename) . '</li>';
                            } else {
                                // File entry with clickable link
                                echo '<li><a href="?url=' . urlencode($url) . '&file=' . urlencode($filename) . '">' . htmlspecialchars($filename) . '</a></li>';
                            }
                        }
                        echo "</ul>";
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
