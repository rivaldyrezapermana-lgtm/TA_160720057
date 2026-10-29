<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Production;
use App\Models\ProductionDesignSketch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductionDesignSketchController extends Controller
{
    public function store(Request $request, Production $production)
    {
        if ($production->designSketchesLocked()) {
            return back()->with('error', 'Tahap Desain sudah selesai. Sketsa tidak bisa ditambah lagi.');
        }

        $request->validate([
            'sketches' => ['required', 'array', 'min:1'],
            'sketches.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ], [
            'sketches.required' => 'Pilih minimal satu gambar sketsa.',
            'sketches.*.image' => 'File sketsa harus berupa gambar.',
            'sketches.*.mimes' => 'Sketsa harus berformat JPG, PNG, atau WebP.',
            'sketches.*.max' => 'Ukuran tiap sketsa maksimal 2 MB.',
        ]);

        foreach ($request->file('sketches') as $file) {
            $production->designSketches()->create([
                'path' => $file->store('production-designs', 'public'),
                'original_name' => $file->getClientOriginalName(),
                'user_id' => auth()->id(),
            ]);
        }

        return back()->with('success', 'Sketsa desain diunggah.');
    }

    public function destroy(Production $production, ProductionDesignSketch $sketch)
    {
        abort_unless($sketch->production_id === $production->id, 404);

        if ($production->designSketchesLocked()) {
            return back()->with('error', 'Tahap Desain sudah selesai. Sketsa tidak bisa dihapus lagi.');
        }

        Storage::disk('public')->delete($sketch->path);
        $sketch->delete();

        return back()->with('success', 'Sketsa dihapus.');
    }
}
