<?php

namespace App\Http\Controllers;

use Illuminate\Support\Str;

/**
 * Menampilkan dokumentasi (file Markdown di repo) di dalam panel: menu Panduan.
 * @2026-Alifiandra
 */
class DocsController extends Controller
{
    /** slug => [judul, path relatif dari root aplikasi] */
    public const DOCS = [
        'instalasi' => ['Cara pemasangan', 'docs/INSTALASI.md'],
        'fitur' => ['Fitur & cara pakai', 'README.md'],
        'api' => ['REST API', 'docs/api/README.md'],
        'api-autentikasi' => ['API — autentikasi', 'docs/api/authentication.md'],
        'api-error' => ['API — format error', 'docs/api/errors.md'],
        'api-batas' => ['API — rate limit & idempotency', 'docs/api/rate-limits.md'],
        'webhook' => ['Webhook', 'docs/api/webhooks.md'],
        'engine' => ['Engine Scan QR', 'engine/README.md'],
        'changelog' => ['Riwayat perubahan API', 'docs/api/changelog.md'],
    ];

    public function show(string $slug = 'instalasi')
    {
        abort_unless(isset(self::DOCS[$slug]), 404);

        [$title, $path] = self::DOCS[$slug];
        $file = base_path($path);
        abort_unless(is_file($file), 404, 'Dokumen tidak ditemukan.');

        $html = Str::markdown((string) file_get_contents($file), [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);

        return view('docs.show', [
            'slug' => $slug,
            'title' => $title,
            'html' => $this->postProcess($html, dirname($path)),
            'docs' => self::DOCS,
        ]);
    }

    /** Beri id pada judul (untuk daftar isi) dan arahkan tautan .md ke halaman panduan. */
    private function postProcess(string $html, string $baseDir): string
    {
        $html = preg_replace_callback('/<(h[1-4])>(.*?)<\/\1>/s', function ($m) {
            $text = html_entity_decode(strip_tags($m[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $id = preg_replace('/[^\p{L}\p{N} \-]/u', '', mb_strtolower($text));
            $id = str_replace(' ', '-', trim((string) $id));

            return '<'.$m[1].' id="'.e($id).'">'.$m[2].'</'.$m[1].'>';
        }, $html) ?? $html;

        $byPath = collect(self::DOCS)->mapWithKeys(fn ($d, $slug) => [$d[1] => $slug]);

        return preg_replace_callback('/href="([^"#]+\.md)(#[^"]*)?"/', function ($m) use ($baseDir, $byPath) {
            $target = $this->normalize(($baseDir === '.' ? '' : $baseDir.'/').$m[1]);
            $slug = $byPath[$target] ?? null;

            return $slug ? 'href="'.route('docs.show', $slug).($m[2] ?? '').'"' : $m[0];
        }, $html) ?? $html;
    }

    private function normalize(string $path): string
    {
        $parts = [];
        foreach (explode('/', $path) as $p) {
            if ($p === '' || $p === '.') {
                continue;
            }
            $p === '..' ? array_pop($parts) : $parts[] = $p;
        }

        return implode('/', $parts);
    }
}
