import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";
import { bunny } from "laravel-vite-plugin/fonts";
import tailwindcss from "@tailwindcss/vite";
import vue from "@vitejs/plugin-vue";
import inertia from "@inertiajs/vite";

export default defineConfig({
    plugins: [
        laravel({
            input: ["resources/css/app.css", "resources/js/app.ts"],
            refresh: true,
            fonts: [
                bunny("Instrument Sans", {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        inertia(),
        vue(),
        tailwindcss(),
    ],
    server: {
        host: "0.0.0.0",
        port: 5173,
        strictPort: true,
        hmr: {
            host: "localhost",
            port: 5173,
        },
        cors: {
            origin: "http://localhost:8000",
        },
        watch: {
            ignored: ["**/storage/framework/views/**"],
        },
    },
});
