/** @type {import('tailwindcss').Config} */
export default {
  content: ["./src/**/*.{astro,html,js,jsx,ts,tsx}"],
  theme: {
    extend: {
      colors: {
        primary: {
          DEFAULT: "rgb(var(--c-primary) / <alpha-value>)",
          hover: "rgb(var(--c-primary-hover) / <alpha-value>)",
          deep: "rgb(var(--c-primary-deep) / <alpha-value>)",
        },
        rose: {
          50: "rgb(var(--c-rose-50) / <alpha-value>)",
          100: "rgb(var(--c-rose-100) / <alpha-value>)",
          200: "rgb(var(--c-rose-200) / <alpha-value>)",
          300: "rgb(var(--c-rose-300) / <alpha-value>)",
          accent: "rgb(var(--c-rose-accent) / <alpha-value>)",
        },
        sand: {
          50: "rgb(var(--c-sand-50) / <alpha-value>)",
          100: "rgb(var(--c-sand-100) / <alpha-value>)",
          200: "rgb(var(--c-sand-200) / <alpha-value>)",
        },
        mustard: {
          DEFAULT: "rgb(var(--c-mustard) / <alpha-value>)",
          hover: "rgb(var(--c-mustard-hover) / <alpha-value>)",
          deep: "rgb(var(--c-mustard-deep) / <alpha-value>)",
        },
        ink: {
          DEFAULT: "rgb(var(--c-ink) / <alpha-value>)",
          soft: "rgb(var(--c-ink-soft) / <alpha-value>)",
          mute: "rgb(var(--c-ink-mute) / <alpha-value>)",
        },
        danger: "rgb(var(--c-danger) / <alpha-value>)",
        surface: "rgb(var(--c-surface) / <alpha-value>)",
      },
      fontFamily: {
        display: ['"Playfair Display"', "Georgia", "serif"],
        sans: [
          "-apple-system",
          "BlinkMacSystemFont",
          '"Segoe UI"',
          "Roboto",
          '"Helvetica Neue"',
          "Arial",
          "sans-serif",
        ],
      },
      borderRadius: {
        xl: "1.25rem",
        "2xl": "1.75rem",
        "3xl": "2.25rem",
      },
      boxShadow: {
        soft: "0 18px 40px -24px rgba(45, 40, 35, 0.28)",
        card: "0 2px 0 rgba(45, 40, 35, 0.03), 0 18px 44px -30px rgba(45, 40, 35, 0.35)",
        lift: "0 28px 60px -30px rgba(45, 40, 35, 0.35)",
      },
      maxWidth: {
        content: "72rem",
      },
    },
  },
  plugins: [],
};