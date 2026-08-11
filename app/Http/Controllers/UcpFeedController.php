<?php

namespace App\Http\Controllers;

use App\Services\Exporter;
use Illuminate\Support\Facades\Response;

class UcpFeedController extends Controller
{
    public function generateFeed(Exporter $exporter)
    {
        $xml = $exporter->ucpFeed();

        return response($xml, 200, [
            'Content-Type' => 'text/xml',
        ]);
    }

}
