import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";
import react from "@vitejs/plugin-react";
import vue from "@vitejs/plugin-vue";
import tailwindcss from "@tailwindcss/vite";
import collectModuleAssetsPaths from "./vite-module-loader";

// The admin bundle, and — separately — the Fast Auction app. Two independent entries: Rollup
// code-splits them, so nothing here changes app.js. Fast Auction has its own CSS entry on
// purpose; app.css scans every Blade file in the project and comes out at 384 KB, which is most
// of what the lean screens exist to avoid.
const paths = [
    "resources/css/app.css",
    "resources/js/app.js",
    "resources/css/fast-auction.css",
    "resources/js/fast-auction/main.js",
];

export default defineConfig(async () => {
    let allPaths = await collectModuleAssetsPaths(paths, "Modules");

    if (allPaths.length === 0) {
        allPaths = paths;
    }

    return {
        plugins: [
            laravel({
                input: allPaths,
                refresh: true,
            }),
            react(),
            vue(),
            tailwindcss(),
        ],
        esbuild: {
            jsx: "automatic",
        },
    };
});
