<?php

namespace Eavio\ProjectBoard\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class UnsplashController
{
    protected ?string $accessKey = null;
    protected string $baseUrl = 'https://api.unsplash.com';

    public function __construct()
    {
        $this->accessKey = config('project-board.unsplash.access_key')
            ?? config('services.unsplash.access_key')
            ?? env('UNSPLASH_ACCESS_KEY');
    }

    /**
     * Search photos from Unsplash
     */
    public function search(Request $request)
    {
        if (!$this->accessKey) {
            return response()->json(['error' => 'Unsplash is not configured'], 501);
        }
        $query = $request->get('query', 'nature');
        $page = $request->get('page', 1);
        $perPage = $request->get('per_page', 20);

        $response = Http::withHeaders([
            'Authorization' => 'Client-ID ' . $this->accessKey,
        ])->get("{$this->baseUrl}/search/photos", [
            'query' => $query,
            'page' => $page,
            'per_page' => $perPage,
            'orientation' => 'landscape',
        ]);

        if ($response->failed()) {
            return response()->json(['error' => 'Failed to fetch photos'], 500);
        }

        $data = $response->json();

        // Transform response to only include what we need
        $photos = collect($data['results'] ?? [])->map(function ($photo) {
            return [
                'id' => $photo['id'],
                'thumb' => $photo['urls']['thumb'],
                'small' => $photo['urls']['small'],
                'regular' => $photo['urls']['regular'],
                'full' => $photo['urls']['full'],
                'color' => $photo['color'],
                'alt' => $photo['alt_description'] ?? $photo['description'] ?? '',
                'user' => [
                    'name' => $photo['user']['name'],
                    'link' => $photo['user']['links']['html'],
                ],
                'download_location' => $photo['links']['download_location'],
            ];
        });

        return response()->json([
            'photos' => $photos,
            'total' => $data['total'] ?? 0,
            'total_pages' => $data['total_pages'] ?? 0,
        ]);
    }

    /**
     * Get curated/featured photos for default selection
     */
    public function featured(Request $request)
    {
        if (!$this->accessKey) {
            return response()->json(['error' => 'Unsplash is not configured'], 501);
        }
        $page = $request->get('page', 1);
        $perPage = $request->get('per_page', 20);

        $response = Http::withHeaders([
            'Authorization' => 'Client-ID ' . $this->accessKey,
        ])->get("{$this->baseUrl}/photos", [
            'page' => $page,
            'per_page' => $perPage,
            'order_by' => 'popular',
        ]);

        if ($response->failed()) {
            return response()->json(['error' => 'Failed to fetch photos'], 500);
        }

        $photos = collect($response->json())->map(function ($photo) {
            return [
                'id' => $photo['id'],
                'thumb' => $photo['urls']['thumb'],
                'small' => $photo['urls']['small'],
                'regular' => $photo['urls']['regular'],
                'full' => $photo['urls']['full'],
                'color' => $photo['color'],
                'alt' => $photo['alt_description'] ?? $photo['description'] ?? '',
                'user' => [
                    'name' => $photo['user']['name'],
                    'link' => $photo['user']['links']['html'],
                ],
                'download_location' => $photo['links']['download_location'],
            ];
        });

        return response()->json([
            'photos' => $photos,
        ]);
    }

    /**
     * Track download (required by Unsplash API guidelines)
     */
    public function trackDownload(Request $request)
    {
        if (!$this->accessKey) {
            return response()->json(['error' => 'Unsplash is not configured'], 501);
        }
        $downloadLocation = $request->get('download_location');

        if (!$downloadLocation) {
            return response()->json(['error' => 'Missing download_location'], 400);
        }

        Http::withHeaders([
            'Authorization' => 'Client-ID ' . $this->accessKey,
        ])->get($downloadLocation);

        return response()->json(['success' => true]);
    }
}
