import { defineConfig } from "astro/config";
import tailwind from "@astrojs/tailwind";

export default defineConfig({
  base: process.env.SITE_BASE || "/",
  trailingSlash: "ignore",
  integrations: [tailwind()],
  devToolbar: {
    enabled: false,
  },
});
