<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;

class PwaController extends Controller
{
    public function manifest(): Response
    {
        $manifest = [
            'name' => config('support.pwa.name'),
            'short_name' => config('support.pwa.short_name'),
            'start_url' => '/login',
            'scope' => '/',
            'display' => 'standalone',
            'background_color' => config('support.pwa.background_color'),
            'theme_color' => config('support.pwa.theme_color'),
            'icons' => [
                ['src' => '/icons/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/icons/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/icons/icon-192-maskable.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'maskable'],
                ['src' => '/icons/icon-512-maskable.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
        ];

        return response(json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), 200, [
            'Content-Type' => 'application/manifest+json',
            'Cache-Control' => 'no-cache',
        ]);
    }

    public function serviceWorker(): Response
    {
        $assets = ['/offline', '/manifest.webmanifest', '/icons/icon-192.png', '/icons/icon-512.png', '/icons/icon-192-maskable.png', '/icons/icon-512-maskable.png'];
        $manifestPath = public_path('build/manifest.json');
        $versionSource = 'dev';
        if (File::exists($manifestPath)) {
            $versionSource = File::get($manifestPath);
            $manifest = json_decode($versionSource, true) ?: [];
            foreach ($manifest as $entry) {
                if (! is_array($entry)) {
                    continue;
                }
                if (isset($entry['file'])) {
                    $assets[] = '/build/'.$entry['file'];
                }
                foreach ($entry['css'] ?? [] as $css) {
                    $assets[] = '/build/'.$css;
                }
            }
        }
        foreach (['icon-192.png', 'icon-512.png'] as $icon) {
            $path = public_path('icons/'.$icon);
            if (File::exists($path)) {
                $versionSource .= hash_file('sha256', $path);
            }
        }
        $version = substr(hash('sha256', $versionSource), 0, 16);

        return response()
            ->view('pwa.sw', ['version' => $version, 'assets' => array_values(array_unique($assets))])
            ->header('Content-Type', 'application/javascript')
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
            ->header('Service-Worker-Allowed', '/');
    }

    public function offline(): Response
    {
        return response()
            ->view('pwa.offline')
            ->header('Cache-Control', 'public, max-age=300');
    }
}
