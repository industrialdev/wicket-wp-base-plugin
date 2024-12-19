<?php

/**
 * Warp Dump
 * A nicer way to dump data on screen, for those who have not yet been introduced to Xdebug
 *
 * @author Esteban Cuevas
 * @version 1.0.0
 * @link https://github.com/EstebanForge/warp-dump
 * @license MIT https://mit-license.org
 */

/**
 * Dumps variables to the screen
 *
 * @param mixed ...$vars The variables to dump
 *
 * @return void
 */
function warp_dump(...$vars)
{
    static $dump_counter = 0;
    static $has_printed_styles = false;

    // Add styles and scripts only once
    if (!$has_printed_styles) {
        echo '<style>
      @keyframes warpdumpFlash {
          0%, 100% { background: var(--warp-dump-bg); }
          50% { background: var(--warp-dump-flash); }
      }

      :root {
          --warp-dump-bg: #f5f5f5;
          --warp-dump-border: #ddd;
          --warp-dump-content-bg: #fff;
          --warp-dump-content-border: #eee;
          --warp-dump-flash: #e0e0e0;
          --warp-dump-text: #333;
          --warp-dump-feedback-bg: #4CAF50;
          --warp-dump-feedback-text: #fff;
          --warp-dump-feedback-shadow: rgba(0, 0, 0, 0.2);
          --warp-dump-sun-icon: url("data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 24 24\'%3E%3Cpath fill=\'%23333\' d=\'M12 2.25a.75.75 0 01.75.75v2.25a.75.75 0 01-1.5 0V3a.75.75 0 01.75-.75zM7.5 12a4.5 4.5 0 119 0 4.5 4.5 0 01-9 0zM12 21.75a.75.75 0 01-.75-.75v-2.25a.75.75 0 011.5 0V21a.75.75 0 01-.75.75zM3 12a.75.75 0 01.75-.75h2.25a.75.75 0 010 1.5H3.75A.75.75 0 013 12zM21 12a.75.75 0 01-.75.75h-2.25a.75.75 0 010-1.5h2.25A.75.75 0 0121 12zM5.47 5.47a.75.75 0 011.06 0l1.59 1.59a.75.75 0 11-1.06 1.06L5.47 6.53a.75.75 0 010-1.06zM18.53 5.47a.75.75 0 010 1.06l-1.59 1.59a.75.75 0 01-1.06-1.06l1.59-1.59a.75.75 0 011.06 0zM5.47 18.53a.75.75 0 010-1.06l1.59-1.59a.75.75 0 111.06 1.06l-1.59 1.59a.75.75 0 01-1.06 0zM18.53 18.53a.75.75 0 01-1.06 0l-1.59-1.59a.75.75 0 111.06-1.06l1.59 1.59a.75.75 0 010 1.06z\'/%3E%3C/svg%3E");
          --warp-dump-moon-icon: url("data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 24 24\'%3E%3Cpath fill=\'%23333\' d=\'M9.528 1.718a.75.75 0 01.162.819A8.97 8.97 0 009 6a9 9 0 009 9 8.97 8.97 0 003.463-.69.75.75 0 01.981.98 10.503 10.503 0 01-9.694 6.46c-5.799 0-10.5-4.701-10.5-10.5 0-4.368 2.667-8.112 6.46-9.694a.75.75 0 01.818.162z\'/%3E%3C/svg%3E");
      }

      :root[data-theme="dark"] {
          --warp-dump-bg: #2d2d2d;
          --warp-dump-border: #404040;
          --warp-dump-content-bg: #1a1a1a;
          --warp-dump-content-border: #333;
          --warp-dump-flash: #404040;
          --warp-dump-text: #e0e0e0;
          --warp-dump-feedback-bg: #43A047;
          --warp-dump-feedback-text: #fff;
          --warp-dump-feedback-shadow: rgba(0, 0, 0, 0.4);
          --warp-dump-sun-icon: url("data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 24 24\'%3E%3Cpath fill=\'%23e0e0e0\' d=\'M12 2.25a.75.75 0 01.75.75v2.25a.75.75 0 01-1.5 0V3a.75.75 0 01.75-.75zM7.5 12a4.5 4.5 0 119 0 4.5 4.5 0 01-9 0zM12 21.75a.75.75 0 01-.75-.75v-2.25a.75.75 0 011.5 0V21a.75.75 0 01-.75.75zM3 12a.75.75 0 01.75-.75h2.25a.75.75 0 010 1.5H3.75A.75.75 0 013 12zM21 12a.75.75 0 01-.75.75h-2.25a.75.75 0 010-1.5h2.25A.75.75 0 0121 12zM5.47 5.47a.75.75 0 011.06 0l1.59 1.59a.75.75 0 11-1.06 1.06L5.47 6.53a.75.75 0 010-1.06zM18.53 5.47a.75.75 0 010 1.06l-1.59 1.59a.75.75 0 01-1.06-1.06l1.59-1.59a.75.75 0 011.06 0zM5.47 18.53a.75.75 0 010-1.06l1.59-1.59a.75.75 0 111.06 1.06l-1.59 1.59a.75.75 0 01-1.06 0zM18.53 18.53a.75.75 0 01-1.06 0l-1.59-1.59a.75.75 0 111.06-1.06l1.59 1.59a.75.75 0 010 1.06z\'/%3E%3C/svg%3E");
          --warp-dump-moon-icon: url("data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 24 24\'%3E%3Cpath fill=\'%23e0e0e0\' d=\'M9.528 1.718a.75.75 0 01.162.819A8.97 8.97 0 009 6a9 9 0 009 9 8.97 8.97 0 003.463-.69.75.75 0 01.981.98 10.503 10.503 0 01-9.694 6.46c-5.799 0-10.5-4.701-10.5-10.5 0-4.368 2.667-8.112 6.46-9.694a.75.75 0 01.818.162z\'/%3E%3C/svg%3E");
      }

      .warp-dump-theme-toggle {
          position: absolute;
          top: 10px;
          right: 10px;
          background: transparent;
          border: none;
          cursor: pointer;
          padding: 5px;
          width: 24px;
          height: 24px;
          display: flex;
          align-items: center;
          justify-content: center;
      }

      .warp-dump-sun-icon,
      .warp-dump-moon-icon {
          width: 24px;
          height: 24px;
          background-size: contain;
          background-repeat: no-repeat;
          background-position: center;
      }

      .warp-dump-sun-icon {
          background-image: var(--warp-dump-sun-icon);
      }

      .warp-dump-moon-icon {
          background-image: var(--warp-dump-moon-icon);
      }

      [data-theme="dark"] .warp-dump-sun-icon {
          display: none;
      }

      [data-theme="light"] .warp-dump-moon-icon {
          display: none;
      }

      .warp-dump-container {
          background: var(--warp-dump-bg);
          padding: 15px;
          margin: 10px 0;
          border: 1px solid var(--warp-dump-border);
          border-radius: 4px;
          color: var(--warp-dump-text);
          position: relative;
      }

      .warp-dump-title {
          margin-top: 0;
          margin-bottom: 10px;
          padding-right: 40px;
          cursor: pointer;
      }

      .warp-dump-content {
          background: var(--warp-dump-content-bg);
          padding: 10px;
          border: 1px solid var(--warp-dump-content-border);
          border-radius: 3px;
          height: 20vh;
          min-height: 100px;
          max-height: 500px;
          color: var(--warp-dump-text);
          overflow: auto;
          white-space: pre;
          word-wrap: normal;
          cursor: pointer;
          font-size: 0.85rem;
      }

      .warp-dump-feedback {
          display: none;
          position: fixed;
          top: 5vh;
          left: 50%;
          transform: translateX(-50%);
          background: var(--warp-dump-feedback-bg);
          color: var(--warp-dump-feedback-text);
          padding: 8px 16px;
          border-radius: 4px;
          box-shadow: 0 2px 5px var(--warp-dump-feedback-shadow);
          z-index: 9999;
      }
      </style>';

        echo '<script>
      document.addEventListener("DOMContentLoaded", function() {
        let mouseDownTime = 0;
        let isDragging = false;

        // Add event listeners to theme toggle buttons
        document.querySelectorAll(".warp-dump-theme-toggle").forEach(button => {
          button.addEventListener("click", function(e) {
            e.preventDefault();
            e.stopPropagation();
            toggleWarpDumpTheme(e);
          });
        });

        // Add event listeners to titles
        document.querySelectorAll(".warp-dump-title").forEach(title => {
          title.addEventListener("click", function(e) {
            const dumpId = this.nextElementSibling.id;
            warpdumpCopyDump(dumpId);
          });
        });

        // Add mousedown and mouseup listeners to all dump content
        document.querySelectorAll(".warp-dump-content").forEach(content => {
          content.addEventListener("mousedown", function(e) {
            mouseDownTime = new Date().getTime();
            isDragging = false;
          });

          content.addEventListener("mousemove", function(e) {
            if (mouseDownTime > 0) {
              isDragging = true;
            }
          });

          content.addEventListener("mouseup", function(e) {
            const mouseUpTime = new Date().getTime();
            const timeDiff = mouseUpTime - mouseDownTime;
            const selection = window.getSelection();
            const hasSelection = selection && selection.toString().trim().length > 0;

            // Reset flags
            mouseDownTime = 0;

            // If there\'s a selection, copy it
            if (hasSelection) {
              e.preventDefault();
              e.stopPropagation();
              navigator.clipboard.writeText(selection.toString()).then(() => {
                warpdumpShowFeedback(this.id, "Selection copied to clipboard");
              });
              isDragging = false;
              return;
            }

            // If it was a quick click without selection, copy everything
            if (!isDragging && timeDiff <= 200) {
              warpdumpCopyDump(this.id);
            }

            isDragging = false;
          });

          content.addEventListener("click", function(e) {
            const selection = window.getSelection();
            if (selection && selection.toString().trim()) {
              e.preventDefault();
              e.stopPropagation();
            }
          });
        });
      });

      function warpdumpShowFeedback(dumpId, message) {
        const feedback = document.getElementById(dumpId + "-feedback");
        if (!feedback) return;

        // Hide all other feedback messages first
        document.querySelectorAll(".warp-dump-feedback").forEach(el => {
          el.style.display = "none";
        });

        feedback.textContent = message;
        feedback.style.display = "block";
        setTimeout(() => {
          feedback.style.display = "none";
        }, 2000);
      }

      window.warpdumpCopyDump = function(dumpId) {
        const dump = document.getElementById(dumpId);
        if (!dump) return;

        navigator.clipboard.writeText(dump.textContent).then(() => {
          const title = dump.parentElement.querySelector(".warp-dump-title").textContent.split(" (")[0];
          warpdumpShowFeedback(dumpId, title + " copied to clipboard");
        });
      }

      window.toggleWarpDumpTheme = function(event) {
        event.stopPropagation(); // Prevent container click
        const currentTheme = document.documentElement.getAttribute("data-theme") || "light";
        const newTheme = currentTheme === "light" ? "dark" : "light";
        document.documentElement.setAttribute("data-theme", newTheme);
        localStorage.setItem("warp-dump-theme", newTheme);
      }

      function initWarpDumpTheme() {
        const theme = localStorage.getItem("warp-dump-theme") ||
          (window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light");
        document.documentElement.setAttribute("data-theme", theme);
      }

      initWarpDumpTheme();
      </script>';
        $has_printed_styles = true;
    }

    // Get the line of code that called warp_dump
    $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1);
    $code_line = isset($backtrace[0]['file'], $backtrace[0]['line'])
      ? file($backtrace[0]['file'])[$backtrace[0]['line'] - 1]
      : '';

    // Extract variable names from the function call
    preg_match_all('/warp_dump\s*\((.*)\)/i', $code_line, $matches);
    $var_names = [];
    if (!empty($matches[1][0])) {
        $var_names = array_map('trim', explode(',', $matches[1][0]));
    }

    // Output each variable in its own container
    foreach ($vars as $index => $data) {
        $dump_counter++;
        $dump_id = 'warp-dump-' . $dump_counter;

        // Try to get the variable name, fallback to "Dump #N"
        $title = isset($var_names[$index]) ? $var_names[$index] : 'Dump #' . $dump_counter;
        $title = trim($title, '$'); // Remove $ if present

        echo '<div id="' . $dump_id . '-container" class="warp-dump-container">';
        echo '<button class="warp-dump-theme-toggle" title="Toggle theme">
          <span class="warp-dump-sun-icon"></span>
          <span class="warp-dump-moon-icon"></span>
          </button>';
        echo '<h3 class="warp-dump-title">$' . esc_html($title) . ' (Click to Copy):</h3>';
        echo '<pre id="' . $dump_id . '" class="warp-dump-content">';
        var_dump($data);
        echo '</pre>';
        echo '<div id="' . $dump_id . '-feedback" class="warp-dump-feedback"></div>';
        echo '</div>';
    }
}

/**
 * Dump one or more variables and die.
 *
 * This function combines the functionality of warp_dump() with PHP's die() function.
 * It will output the variables in a formatted way and then terminate script execution.
 *
 * @param mixed ...$vars One or more variables to dump
 * @return void
 */
function warp_dd(...$vars)
{
    warp_dump(...$vars);
    die();
}
