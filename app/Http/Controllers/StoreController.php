<?php

namespace App\Http\Controllers;

use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;


class StoreController extends Controller
{
    public function index()
    {
        return response()->json(Store::all());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'address' => 'nullable|string',
            'city' => 'nullable|string',
            'pincode' => 'nullable|string',
            'district' => 'nullable|string',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);

        $store = Store::create($validated);

        return response()->json($store, 201);
    }

    public function search(Request $request)
    {
        $query = Store::query();

        if ($request->filled('name')) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }

        if ($request->filled('address')) {
            $query->orWhere('address', 'like', '%' . $request->address . '%');
        }

        return response()->json($query->get());
    }

    public function findNearbyStores(Request $request)
    {
        $validated = $request->validate([
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
        ]);

        $latitude = $validated['latitude'];
        $longitude = $validated['longitude'];
        $radius = 50; // in kilometers

        $stores = Store::selectRaw("*, 
            (6371 * acos(
                cos(radians(?)) *
                cos(radians(latitude)) *
                cos(radians(longitude) - radians(?)) +
                sin(radians(?)) *
                sin(radians(latitude))
            )) AS distance", [$latitude, $longitude, $latitude])
            ->having("distance", "<", $radius)
            ->orderBy("distance")
            ->get();

        return response()->json($stores);
    }

    public function updateDistrictsFromPincode()
    {
        $stores = Store::where(function ($query) {
            $query->whereNull('district')
                  ->orWhere('district', '');
        })
        ->whereNotNull('pincode')
        ->get();

        foreach ($stores as $store) {
            try {
                $response = Http::get("https://api.postalpincode.in/pincode/{$store->pincode}");
                $pinData = $response->json();

                if (
                    isset($pinData[0]['Status']) && $pinData[0]['Status'] === 'Success' &&
                    isset($pinData[0]['PostOffice'][0]['District'])
                ) {
                    $store->district = $pinData[0]['PostOffice'][0]['District'];
                    $store->save();
                }
            } catch (\Exception $e) {
                \Log::warning("Failed to fetch district for pincode {$store->pincode}: " . $e->getMessage());
            }
        }

        return response()->json([
            'message' => 'Districts update finished',
            'updated' => $stores->pluck('id') // or add more detailed debug info
        ]);
    }

}
