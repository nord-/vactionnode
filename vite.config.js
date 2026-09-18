import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
  // Appen får en egen portal med egen document root på Web Station,
  // så den ligger inte längre i en underkatalog som på GitHub Pages.
  base: '/',
  // public/ är PHP:ns document root i det här projektet, inte Vites mapp för
  // statiska filer. Utan false kopieras public/api/index.php och .htaccess
  // rakt in i dist/, och PHP-källkoden hamnar i bygget som deployas.
  publicDir: false,
  plugins: [vue()],
  server: {
    // Samma origin ur browserns perspektiv — ingen CORS behövs,
    // varken här eller i produktion.
    proxy: { '/api': 'http://localhost:8080' },
  },
  test: {
    environment: 'jsdom',
    globals: true,
  },
});
