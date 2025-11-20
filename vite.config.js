/**
 * We need to complete a few things in here as we change things depending on how the plugin is configured, and what themes we are using.
 * 
 * Gulp currently generates several files
 * 
 * - When Wicket Theme is NOT active, and base Styles are disabled:
 * wicket-tailwind-wrapped.css - Tailwind styles wrapped in .wicket-base-plugin
 * wicket-wrapped.css - Base styles wrapped in .wicket-base-plugin
 * 
 * - When Wicket Theme IS active, and base Styles are enabled:
 * wicket-tailwind.css - Tailwind styles unwrapped
 * wicket.css - Base styles unwrapped
 * 
 * - Always on public
 * wicket-alpine.js - Alpine JS for components
 * 
 * - On Admin Pages when in Wicket Admin Page
 * wicket_admin.js
 * wicket_admin.css
 * select2.css
 * select2.js
 * 
 * - Admin page with certain configrations
 * wicket_wc_org.js
 * 
 * 
 * The goal will be to compile these down, and import them via JS and vite instead of Gulp.
 * 
 * 
 */

import { resolve } from "path";

export default {
  root: __dirname, // project root
  build: {
    outDir: "dist", // output directory for built CSS
    sourcemap: false, // This would generate JS sourcemaps for production builds
    emptyOutDir: true,
    manifest: true, // we can parse this manifest to enqueue files in WordPress
    cssMinify: true,
    rollupOptions: {
      input: {

        //TODO: while we are loading both of these files in, for some reason we only get a single .css file output, which means we can't wrap just one of them (in postcss). Need to investigate further.

        // Default, standard wicket files
        wicket: resolve(__dirname, "assets/js/wicket.js"),

        // When we arent't using the Wicket Theme, we need the wrapped CSS versions. We wrap this in postcss config.
        wicket_wrapped: resolve(__dirname, "assets/js/wicket_wrapped.js"),

        //admin: resolve(__dirname, "assets/js/admin.js"), // TODO: Setup and organize the admin.js entry point
        //wicket_wc_org: resolve(__dirname, "assets/js/wicket_wc_org.js"), // TODO: Setup and organize the wicket_wc_org entry point
      },
    },
  },
  css: {
    devSourcemap: true,
    preprocessorOptions: {},
    postcss: "./postcss.config.js",
  },
  server: {
    // Vite dev server
    host: "127.0.0.1",
    port: 5173,
    strictPort: true,
    hmr: {
      host: "127.0.0.1",
    },
  },
};
