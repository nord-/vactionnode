export default {
  plugins: {
    // Tailwind 4 har ett eget PostCSS-paket och sköter vendor-prefix internt
    // via Lightning CSS, så autoprefixer behövs inte längre.
    '@tailwindcss/postcss': {},
  },
};
