<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class CityDataController extends Controller
{
    public function updateMissingDistricts()
    {
        $jsonPath = base_path('resources/data/cities.json');
        $json = File::exists($jsonPath) ? json_decode(File::get($jsonPath), true) : [];

        $districtsInStores = DB::table('stores')
            ->select('district')
            ->whereNotNull('district')
            ->distinct()
            ->pluck('district')
            ->map(fn ($d) => trim($d))
            ->unique();

        $existingDistricts = collect($json)->pluck('district')->map(fn ($d) => strtolower(trim($d)));

        foreach ($districtsInStores as $district) {
            if ($existingDistricts->contains(strtolower($district))) {
                Log::info("District '{$district}' already exists in JSON.");
                continue;
            }

            Log::info("Looking up district: {$district}");

            sleep(1); // Respect Nominatim rate limits

            $response = Http::withHeaders([
                'User-Agent' => 'StoreFinder/1.0 (php.developer@wardwizard.in)'
            ])->get('https://nominatim.openstreetmap.org/search', [
                'q' => "{$district}, India",
                'format' => 'json',
                'limit' => 1,
            ]);

            if (!$response->successful() || empty($response->json())) {
                Log::warning("Geocoding failed for district: {$district}");
                continue;
            }

            $location = $response->json()[0];
            $lat = $location['lat'];
            $lon = $location['lon'];

            Log::info("Coordinates for {$district}: lat={$lat}, lon={$lon}");

            sleep(1); // Respect Nominatim rate limits

            $reverse = Http::withHeaders([
                 'User-Agent' => 'StoreFinder/1.0 (php.developer@wardwizard.in)'
            ])->get('https://nominatim.openstreetmap.org/reverse', [
                'lat' => $lat,
                'lon' => $lon,
                'format' => 'json',
            ]);

            $reverseData = $reverse->json();

            if (!$reverse->successful() || empty($reverseData['address']['state'])) {
                Log::warning("Reverse geocoding failed for {$district}");
                continue;
            }

            $state = $reverseData['address']['state'];
            Log::info("Found state for {$district}: {$state}");

            $newEntry = [
                'district' => $district,
                'state' => $state,
                'latitude' => (float) $lat,
                'longitude' => (float) $lon,
            ];

            $json[] = $newEntry;
            Log::info("Added new entry for {$district}: " . json_encode($newEntry));
        }

        File::put($jsonPath, json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return response()->json(['message' => 'Cities updated successfully.']);
    }
}
