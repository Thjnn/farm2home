<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Basket;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        return view('public.home', [
            'baskets' => Basket::with('items.product')->where('is_active', true)->orderBy('price')->get(),
        ]);
    }
}
