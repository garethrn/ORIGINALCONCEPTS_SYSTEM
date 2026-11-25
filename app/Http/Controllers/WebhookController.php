<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class WebhookController extends Controller
{
    public function __invoke(): Response
    {
        abort(404);
    }
}
