<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\File;

class FirmwareGuideController extends Controller
{
    public function show(): View
    {
        $path = base_path('firmware/Incubator/Incubator.ino');

        abort_unless(File::exists($path), 404, 'Firmware source not found.');

        return view('docs.firmware-guide', [
            'source' => File::get($path),
        ]);
    }
}
