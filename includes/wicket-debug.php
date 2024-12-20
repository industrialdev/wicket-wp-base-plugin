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
          --warp-dump-icon: url("data:image/svg+xml,%3Csvg%20fill%3D%27%23333%27%20version%3D%271.1%27%20xmlns%3D%27http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%27%20viewBox%3D%270%200%2032%2032%27%3E%3Cg%3E%3Cpath%20d%3D%27M8.2%2C9.6c0.2%2C0.2%2C0.5%2C0.3%2C0.7%2C0.3s0.5-0.1%2C0.7-0.3c0.4-0.4%2C0.4-1%2C0-1.4L7.5%2C6.1c-0.4-0.4-1-0.4-1.4%2C0s-0.4%2C1%2C0%2C1.4L8.2%2C9.6z%27%2F%3E%3Cpath%20d%3D%27M7%2C16c0-0.6-0.4-1-1-1H3c-0.6%2C0-1%2C0.4-1%2C1s0.4%2C1%2C1%2C1h3C6.6%2C17%2C7%2C16.6%2C7%2C16z%27%2F%3E%3Cpath%20d%3D%27M8.2%2C22.4l-2.1%2C2.1c-0.4%2C0.4-0.4%2C1%2C0%2C1.4c0.2%2C0.2%2C0.5%2C0.3%2C0.7%2C0.3s0.5-0.1%2C0.7-0.3l2.1-2.1c0.4-0.4%2C0.4-1%2C0-1.4S8.6%2C22%2C8.2%2C22.4z%27%2F%3E%3C%2Fg%3E%3Cpath%20d%3D%27M29.4%2C16.2c-0.4-0.2-0.9-0.1-1.2%2C0.3c-0.8%2C1-2%2C1.5-3.2%2C1.5c-2.3%2C0-4.3-1.9-4.3-4.3c0-1.6%2C0.9-3%2C2.3-3.8c0.4-0.2%2C0.6-0.7%2C0.5-1.1C23.4%2C8.4%2C23%2C8%2C22.5%2C8c-0.1%2C0-0.3%2C0-0.4%2C0c-1.9%2C0-3.7%2C0.7-5.1%2C1.8V3c0-0.6-0.4-1-1-1s-1%2C0.4-1%2C1v5.1c-3.9%2C0.5-7%2C3.9-7%2C7.9s3.1%2C7.4%2C7%2C7.9V29c0%2C0.6%2C0.4%2C1%2C1%2C1s1-0.4%2C1-1v-6.8c1.4%2C1.2%2C3.2%2C1.8%2C5.1%2C1.8c4%2C0%2C7.3-2.9%2C7.9-6.8C30.1%2C16.8%2C29.8%2C16.3%2C29.4%2C16.2z%20M17%2C20c0%2C0.6-0.4%2C1-1%2C1s-1-0.4-1-1v-8c0-0.6%2C0.4-1%2C1-1s1%2C0.4%2C1%2C1V20z%27%2F%3E%3C%2Fsvg%3E");
          --warp-dump-copy-icon: url("data:image/svg+xml,%3Csvg%20width%3D%27800px%27%20height%3D%27800px%27%20viewBox%3D%270%200%2024%2024%27%20fill%3D%27none%27%20xmlns%3D%27http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%27%3E%3Cpath%20d%3D%27M8%205.00005C7.01165%205.00082%206.49359%205.01338%206.09202%205.21799C5.71569%205.40973%205.40973%205.71569%205.21799%206.09202C5%206.51984%205%207.07989%205%208.2V17.8C5%2018.9201%205%2019.4802%205.21799%2019.908C5.40973%2020.2843%205.71569%2020.5903%206.09202%2020.782C6.51984%2021%207.07989%2021%208.2%2021H15.8C16.9201%2021%2017.4802%2021%2017.908%2020.782C18.2843%2020.5903%2018.5903%2020.2843%2018.782%2019.908C19%2019.4802%2019%2018.9201%2019%2017.8V8.2C19%207.07989%2019%206.51984%2018.782%206.09202C18.5903%205.71569%2018.2843%205.40973%2017.908%205.21799C17.5064%205.01338%2016.9884%205.00082%2016%205.00005M8%205.00005V7H16V5.00005M8%205.00005V4.70711C8%204.25435%208.17986%203.82014%208.5%203.5C8.82014%203.17986%209.25435%203%209.70711%203H14.2929C14.7456%203%2015.1799%203.17986%2015.5%203.5C15.8201%203.82014%2016%204.25435%2016%204.70711V5.00005M12%2011V17M12%2017L10%2015M12%2017L14%2015%27%20stroke%3D%27%23333%27%20stroke-width%3D%272%27%20stroke-linecap%3D%27round%27%20stroke-linejoin%3D%27round%27%2F%3E%3C%2Fsvg%3E");
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
          --warp-dump-icon: url("data:image/svg+xml,%3Csvg%20fill%3D%27%23ffffff%27%20version%3D%271.1%27%20xmlns%3D%27http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%27%20viewBox%3D%270%200%2032%2032%27%3E%3Cg%3E%3Cpath%20d%3D%27M8.2%2C9.6c0.2%2C0.2%2C0.5%2C0.3%2C0.7%2C0.3s0.5-0.1%2C0.7-0.3c0.4-0.4%2C0.4-1%2C0-1.4L7.5%2C6.1c-0.4-0.4-1-0.4-1.4%2C0s-0.4%2C1%2C0%2C1.4L8.2%2C9.6z%27%2F%3E%3Cpath%20d%3D%27M7%2C16c0-0.6-0.4-1-1-1H3c-0.6%2C0-1%2C0.4-1%2C1s0.4%2C1%2C1%2C1h3C6.6%2C17%2C7%2C16.6%2C7%2C16z%27%2F%3E%3Cpath%20d%3D%27M8.2%2C22.4l-2.1%2C2.1c-0.4%2C0.4-0.4%2C1%2C0%2C1.4c0.2%2C0.2%2C0.5%2C0.3%2C0.7%2C0.3s0.5-0.1%2C0.7-0.3l2.1-2.1c0.4-0.4%2C0.4-1%2C0-1.4S8.6%2C22%2C8.2%2C22.4z%27%2F%3E%3C%2Fg%3E%3Cpath%20d%3D%27M29.4%2C16.2c-0.4-0.2-0.9-0.1-1.2%2C0.3c-0.8%2C1-2%2C1.5-3.2%2C1.5c-2.3%2C0-4.3-1.9-4.3-4.3c0-1.6%2C0.9-3%2C2.3-3.8c0.4-0.2%2C0.6-0.7%2C0.5-1.1C23.4%2C8.4%2C23%2C8%2C22.5%2C8c-0.1%2C0-0.3%2C0-0.4%2C0c-1.9%2C0-3.7%2C0.7-5.1%2C1.8V3c0-0.6-0.4-1-1-1s-1%2C0.4-1%2C1v5.1c-3.9%2C0.5-7%2C3.9-7%2C7.9s3.1%2C7.4%2C7%2C7.9V29c0%2C0.6%2C0.4%2C1%2C1%2C1s1-0.4%2C1-1v-6.8c1.4%2C1.2%2C3.2%2C1.8%2C5.1%2C1.8c4%2C0%2C7.3-2.9%2C7.9-6.8C30.1%2C16.8%2C29.8%2C16.3%2C29.4%2C16.2z%20M17%2C20c0%2C0.6-0.4%2C1-1%2C1s-1-0.4-1-1v-8c0-0.6%2C0.4-1%2C1-1s1%2C0.4%2C1%2C1V20z%27%2F%3E%3C%2Fsvg%3E");
          --warp-dump-copy-icon: url("data:image/svg+xml,%3Csvg%20width%3D%27800px%27%20height%3D%27800px%27%20viewBox%3D%270%200%2024%2024%27%20fill%3D%27none%27%20xmlns%3D%27http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%27%3E%3Cpath%20d%3D%27M8%205.00005C7.01165%205.00082%206.49359%205.01338%206.09202%205.21799C5.71569%205.40973%205.40973%205.71569%205.21799%206.09202C5%206.51984%205%207.07989%205%208.2V17.8C5%2018.9201%205%2019.4802%205.21799%2019.908C5.40973%2020.2843%205.71569%2020.5903%206.09202%2020.782C6.51984%2021%207.07989%2021%208.2%2021H15.8C16.9201%2021%2017.4802%2021%2017.908%2020.782C18.2843%2020.5903%2018.5903%2020.2843%2018.782%2019.908C19%2019.4802%2019%2018.9201%2019%2017.8V8.2C19%207.07989%2019%206.51984%2018.782%206.09202C18.5903%205.71569%2018.2843%205.40973%2017.908%205.21799C17.5064%205.01338%2016.9884%205.00082%2016%205.00005M8%205.00005V7H16V5.00005M8%205.00005V4.70711C8%204.25435%208.17986%203.82014%208.5%203.5C8.82014%203.17986%209.25435%203%209.70711%203H14.2929C14.7456%203%2015.1799%203.17986%2015.5%203.5C15.8201%203.82014%2016%204.25435%2016%204.70711V5.00005M12%2011V17M12%2017L10%2015M12%2017L14%2015%27%20stroke%3D%27%23ffffff%27%20stroke-width%3D%272%27%20stroke-linecap%3D%27round%27%20stroke-linejoin%3D%27round%27%2F%3E%3C%2Fsvg%3E");
      }

      .warp-dump-theme-toggle {
          background: none;
          border: none;
          cursor: pointer;
          border-radius: 8px;
          display: flex;
          align-items: center;
          justify-content: center;
          min-width: 36px;
          min-height: 36px;
          background-image: var(--warp-dump-icon);
          background-size: contain;
          background-repeat: no-repeat;
          background-position: center;
      }

      .warp-dump-icon {
          width: 36px;
          height: 36px;
          background-size: contain;
          background-repeat: no-repeat;
          background-position: center;
          background-image: var(--warp-dump-icon);
      }

      .warp-dump-container {
          background-color: var(--warp-dump-bg);
          border: 1px solid var(--warp-dump-border);
          border-radius: 8px;
          margin: 20px 0;
          padding: 16px;
          position: relative;
      }

      .warp-dump-header {
          display: flex;
          align-items: center;
          gap: 8px;
          margin-bottom: 16px;
          justify-content: space-between;
      }

      .warp-dump-title {
          margin: 0;
          font-size: 1.1em;
          color: var(--warp-dump-text);
          flex-grow: 0;
      }

      .warp-actions {
          display: flex;
          align-items: center;
          gap: 8px;
      }

      .warp-copy-button {
          background: none;
          border: none;
          cursor: pointer;
          border-radius: 8px;
          display: flex;
          align-items: center;
          justify-content: center;
          min-width: 36px;
          min-height: 36px;
      }

      .warp-copy-icon {
          width: 36px;
          height: 36px;
          display: inline-block;
          background-size: contain;
          background-repeat: no-repeat;
          background-position: center;
          background-image: var(--warp-dump-copy-icon);
      }

      .dark .warp-copy-icon {
          filter: invert(1);
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
          position: absolute;
          top: 50%;
          left: 50%;
          transform: translate(-50%, -50%);
          background-color: var(--warp-dump-feedback-bg);
          color: var(--warp-dump-feedback-text);
          padding: 8px 16px;
          border-radius: 4px;
          box-shadow: 0 2px 5px var(--warp-dump-feedback-shadow);
          z-index: 9999;
          white-space: nowrap;
          animation: feedbackIn 0.2s ease-out forwards;
      }

      @keyframes feedbackIn {
          from {
              opacity: 0;
              transform: translate(-50%, -50%) scale(0.9);
          }
          to {
              opacity: 1;
              transform: translate(-50%, -50%) scale(1);
          }
      }

      @keyframes feedbackOut {
          from {
              opacity: 1;
              transform: translate(-50%, -50%) scale(1);
          }
          to {
              opacity: 0;
              transform: translate(-50%, -50%) scale(0.9);
          }
      }

      .warp-dump-search {
          margin-top: 16px;
          position: relative;
          width: 100%;
          display: flex;
          gap: 8px;
          align-items: center;
      }

      .warp-dump-search-wrapper {
          flex: 1;
          position: relative;
          display: flex;
          align-items: center;
      }

      .warp-dump-search input {
          width: 100%;
          padding: 8px 12px;
          border: 1px solid var(--warp-dump-border);
          border-radius: 4px;
          background-color: var(--warp-dump-content-bg);
          color: var(--warp-dump-text);
          font-size: 14px;
      }

      .warp-dump-clear-search {
          position: absolute;
          left: 8px;
          background: none;
          border: none;
          cursor: pointer;
          padding: 4px;
          border-radius: 50%;
          display: flex;
          align-items: center;
          justify-content: center;
          opacity: 0;
          width: 20px;
          height: 20px;
          background-size: contain;
          background-repeat: no-repeat;
          background-position: center;
          transition: opacity 0.2s ease-in-out, background-color 0.2s ease-in-out;
      }

      [data-theme="light"] .warp-dump-clear-search {
          background-image: url("data:image/svg+xml,%3Csvg width%3D%27800px%27 height%3D%27800px%27 viewBox%3D%270 0 76 76%27 xmlns%3D%27http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%27 version%3D%271.1%27 baseProfile%3D%27full%27%3E%3Cpath fill%3D%27%23333333%27 fill-opacity%3D%271%27 stroke-width%3D%270.2%27 stroke-linejoin%3D%27round%27 d%3D%27M 47.5282%2C42.9497L 42.5784%2C38L 47.5282%2C33.0502L 44.9497%2C30.4718L 40%2C35.4216L 35.0502%2C30.4718L 32.4718%2C33.0502L 37.4216%2C38L 32.4718%2C42.9497L 35.0502%2C45.5282L 40%2C40.5784L 44.9497%2C45.5282L 47.5282%2C42.9497 Z M 18.0147%2C41.5355L 26.9646%2C50.4854C 28.0683%2C51.589 29%2C52 31%2C52L 52%2C52C 54.7614%2C52 57%2C49.7614 57%2C47L 57%2C29C 57%2C26.2386 54.7614%2C24 52%2C24L 31%2C24C 29%2C24 28.0683%2C24.4113 26.9646%2C25.5149L 18.0147%2C34.4645C 16.0621%2C36.4171 16.0621%2C39.5829 18.0147%2C41.5355 Z M 31%2C49C 30%2C49 29.6048%2C48.8828 29.086%2C48.3641L 20.1361%2C39.4142C 19.355%2C38.6332 19.355%2C37.3669 20.1361%2C36.5858L 29.086%2C27.6362C 29.6048%2C27.1175 30%2C27 31%2C27.0001L 52%2C27.0001C 53.1046%2C27.0001 54%2C27.8955 54%2C29.0001L 54%2C47.0001C 54%2C48.1046 53.1046%2C49.0001 52%2C49.0001L 31%2C49 Z%27%2F%3E%3C%2Fsvg%3E");
      }

      [data-theme="dark"] .warp-dump-clear-search {
          background-image: url("data:image/svg+xml,%3Csvg width%3D%27800px%27 height%3D%27800px%27 viewBox%3D%270 0 76 76%27 xmlns%3D%27http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%27 version%3D%271.1%27 baseProfile%3D%27full%27%3E%3Cpath fill%3D%27%23ffffff%27 fill-opacity%3D%271%27 stroke-width%3D%270.2%27 stroke-linejoin%3D%27round%27 d%3D%27M 47.5282%2C42.9497L 42.5784%2C38L 47.5282%2C33.0502L 44.9497%2C30.4718L 40%2C35.4216L 35.0502%2C30.4718L 32.4718%2C33.0502L 37.4216%2C38L 32.4718%2C42.9497L 35.0502%2C45.5282L 40%2C40.5784L 44.9497%2C45.5282L 47.5282%2C42.9497 Z M 18.0147%2C41.5355L 26.9646%2C50.4854C 28.0683%2C51.589 29%2C52 31%2C52L 52%2C52C 54.7614%2C52 57%2C49.7614 57%2C47L 57%2C29C 57%2C26.2386 54.7614%2C24 52%2C24L 31%2C24C 29%2C24 28.0683%2C24.4113 26.9646%2C25.5149L 18.0147%2C34.4645C 16.0621%2C36.4171 16.0621%2C39.5829 18.0147%2C41.5355 Z M 31%2C49C 30%2C49 29.6048%2C48.8828 29.086%2C48.3641L 20.1361%2C39.4142C 19.355%2C38.6332 19.355%2C37.3669 20.1361%2C36.5858L 29.086%2C27.6362C 29.6048%2C27.1175 30%2C27 31%2C27.0001L 52%2C27.0001C 53.1046%2C27.0001 54%2C27.8955 54%2C29.0001L 54%2C47.0001C 54%2C48.1046 53.1046%2C49.0001 52%2C49.0001L 31%2C49 Z%27%2F%3E%3C%2Fsvg%3E");
      }

      .warp-dump-clear-search:hover {
          background-color: var(--warp-dump-border);
      }

      .warp-dump-search-wrapper.has-text .warp-dump-clear-search {
          opacity: 1;
      }

      .warp-dump-search-wrapper.has-text input {
          padding-left: 36px;
      }

      .warp-dump-search-nav {
          display: flex;
          gap: 4px;
          align-items: center;
      }

      .warp-dump-search-count {
          color: var(--warp-dump-text);
          font-size: 14px;
          opacity: 0.8;
          min-width: 80px;
          text-align: center;
      }

      .warp-dump-search-btn {
          background: none;
          border: 1px solid var(--warp-dump-border);
          border-radius: 4px;
          padding: 8px;
          cursor: pointer;
          color: var(--warp-dump-text);
          display: flex;
          align-items: center;
          justify-content: center;
      }

      .warp-dump-search-btn:hover {
          background-color: var(--warp-dump-border);
      }

      .warp-dump-search-btn:disabled {
          opacity: 0.5;
          cursor: not-allowed;
      }

      .highlight-match {
          background-color: var(--warp-dump-feedback-bg);
          color: var(--warp-dump-feedback-text);
          padding: 2px;
          border-radius: 2px;
      }

      .highlight-match.current {
          background-color: #FF9800;
          color: #000;
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
        feedback.style.animation = "feedbackIn 0.2s ease-out forwards";
        setTimeout(() => {
          feedback.style.animation = "feedbackOut 0.2s ease-out forwards";
          setTimeout(() => {
            feedback.style.display = "none";
          }, 200);
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
        echo '<div class="warp-dump-header">';
        echo '<h3 class="warp-dump-title">$' . esc_html($title) . '</h3>';
        echo '<span class="warp-actions">
          <button class="warp-copy-button" title="Copy to clipboard" onclick="warpdumpCopyDump(\'' . $dump_id . '\')">
            <span class="warp-copy-icon"></span>
          </button>
        </span>';
        echo '<button class="warp-dump-theme-toggle" title="Toggle theme" style="margin-left: auto;">
          <span class="warp-dump-icon"></span>
          </button>';
        echo '</div>';
        echo '<pre id="' . $dump_id . '" class="warp-dump-content">';
        var_dump($data);
        echo '</pre>';
        echo '<div class="warp-dump-search">
          <div class="warp-dump-search-wrapper">
            <button class="warp-dump-clear-search" title="Clear search (Esc)" aria-label="Clear search (Esc)"></button>
            <input type="text" placeholder="Search in $' . $title . '..." class="warp-dump-search-input">
          </div>
          <div class="warp-dump-search-nav">
            <span class="warp-dump-search-count"></span>
            <button class="warp-dump-search-btn prev" disabled title="Previous match (Shift + Enter)">↑</button>
            <button class="warp-dump-search-btn next" disabled title="Next match (Enter)">↓</button>
          </div>
        </div>';
        echo '<div id="' . $dump_id . '-feedback" class="warp-dump-feedback"></div>';
        echo '</div>';

        echo '<script>
        document.addEventListener("DOMContentLoaded", function() {
            const searchInputs = document.querySelectorAll(".warp-dump-search-input");

            searchInputs.forEach(searchInput => {
                const container = searchInput.closest(".warp-dump-container");
                const searchWrapper = searchInput.closest(".warp-dump-search-wrapper");
                const clearButton = searchWrapper.querySelector(".warp-dump-clear-search");
                const dumpPre = container.querySelector("pre");
                const originalContent = dumpPre.innerHTML;
                const countSpan = container.querySelector(".warp-dump-search-count");
                const prevBtn = container.querySelector(".warp-dump-search-btn.prev");
                const nextBtn = container.querySelector(".warp-dump-search-btn.next");
                let currentMatchIndex = 0;

                // Clear search functionality
                function clearSearch() {
                    searchInput.value = "";
                    searchWrapper.classList.remove("has-text");
                    dumpPre.innerHTML = originalContent;
                    updateMatchCount(null);
                    searchInput.focus(); // Keep focus on input after clearing
                }

                clearButton.addEventListener("click", clearSearch);

                function updateSearchState() {
                    if (searchInput.value.trim()) {
                        searchWrapper.classList.add("has-text");
                    } else {
                        searchWrapper.classList.remove("has-text");
                    }
                }

                function updateMatchCount(matches) {
                    const count = matches ? matches.length : 0;
                    countSpan.textContent = count ? `${currentMatchIndex + 1}/${count}` : "0/0";
                    prevBtn.disabled = count === 0;
                    nextBtn.disabled = count === 0;
                }

                function scrollToMatch(index) {
                    const matches = container.querySelectorAll(".highlight-match");
                    if (matches.length === 0) return;

                    // Remove current highlight
                    matches.forEach(m => m.classList.remove("current"));

                    // Add highlight to current match
                    matches[index].classList.add("current");

                    // Scroll the match into view
                    matches[index].scrollIntoView({
                        behavior: "smooth",
                        block: "center"
                    });
                }

                function goToNextMatch() {
                    const matches = container.querySelectorAll(".highlight-match");
                    if (matches.length === 0) return;

                    currentMatchIndex = (currentMatchIndex + 1) % matches.length;
                    scrollToMatch(currentMatchIndex);
                    updateMatchCount(matches);
                }

                function goToPrevMatch() {
                    const matches = container.querySelectorAll(".highlight-match");
                    if (matches.length === 0) return;

                    currentMatchIndex = (currentMatchIndex - 1 + matches.length) % matches.length;
                    scrollToMatch(currentMatchIndex);
                    updateMatchCount(matches);
                }

                prevBtn.addEventListener("click", goToPrevMatch);
                nextBtn.addEventListener("click", goToNextMatch);

                // Add keyboard navigation
                searchInput.addEventListener("keydown", function(e) {
                    if (e.key === "Enter") {
                        e.preventDefault(); // Prevent form submission
                        if (e.shiftKey) {
                            goToPrevMatch();
                        } else {
                            goToNextMatch();
                        }
                    } else if (e.key === "Escape") {
                        e.preventDefault();
                        clearSearch();
                    }
                });

                searchInput.addEventListener("input", function(e) {
                    const searchTerm = e.target.value.trim();
                    currentMatchIndex = 0;

                    updateSearchState();

                    if (!searchTerm) {
                        dumpPre.innerHTML = originalContent;
                        updateMatchCount(null);
                        return;
                    }

                    try {
                        const regex = new RegExp(searchTerm, "gi");
                        let newContent = originalContent;

                        // Remove existing highlights
                        newContent = newContent.replace(/<span class="highlight-match( current)?">([^<]+)<\/span>/g, "$2");

                        // Add new highlights
                        if (searchTerm) {
                            newContent = newContent.replace(regex, match =>
                                `<span class="highlight-match">${match}</span>`
                            );
                        }

                        dumpPre.innerHTML = newContent;

                        const matches = container.querySelectorAll(".highlight-match");
                        updateMatchCount(matches);

                        // Scroll to first match if any exists
                        if (matches.length > 0) {
                            scrollToMatch(0);
                        }
                    } catch (error) {
                        // Handle invalid regex
                        dumpPre.innerHTML = originalContent;
                        updateMatchCount(null);
                    }
                });
            });
        });
        </script>';
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
