// Salin library non-module (jQuery, DataTables) dari node_modules ke public/vendor
// supaya disajikan dari domain sendiri, bukan CDN pihak ketiga. Dijalankan
// otomatis oleh `npm run dev` / `npm run build`.
//
// Tidak lewat bundle Vite karena script di @push('scripts') memanggil `$(...)`
// langsung saat halaman di-parse, sedangkan bundle Vite (type="module") baru
// dieksekusi setelahnya.
import { copyFileSync, mkdirSync } from 'node:fs';
import { dirname, resolve } from 'node:path';

const files = {
    'node_modules/jquery/dist/jquery.min.js': 'public/vendor/jquery/jquery.min.js',
    'node_modules/datatables.net/js/jquery.dataTables.min.js': 'public/vendor/datatables/jquery.dataTables.min.js',
    'node_modules/datatables.net-dt/css/jquery.dataTables.min.css': 'public/vendor/datatables/jquery.dataTables.min.css',
};

for (const [from, to] of Object.entries(files)) {
    mkdirSync(dirname(resolve(to)), { recursive: true });
    copyFileSync(resolve(from), resolve(to));
}

console.log(`copy-vendor: ${Object.keys(files).length} file disalin ke public/vendor`);
