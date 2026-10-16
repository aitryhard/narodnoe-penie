import { defineConfig } from "astro/config";

export default defineConfig({
  base: process.env.SITE_BASE || "/",
  trailingSlash: "ignore",
  devToolbar: {
    enabled: false,
  },
});
