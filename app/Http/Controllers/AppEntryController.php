<?php

namespace App\Http\Controllers;

use App\Enums\FarmerStatus;
use App\Models\Farmer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

class AppEntryController extends Controller
{
    /** Cookie ghi nhớ link nông hộ trên máy đã mở link đó (nông hộ không đăng nhập). */
    public const FARMER_COOKIE = 'f2h_farmer';

    /**
     * Trang mở đầu của app đã cài (PWA): đã đăng nhập → quản trị, máy của nông hộ → trang nông hộ, còn lại → trang khách.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        if ($request->user()) {
            return redirect()->route('admin.dashboard');
        }

        $token = $request->cookie(self::FARMER_COOKIE);
        if (is_string($token) && $token !== '') {
            $farmer = Farmer::where('access_token', $token)->where('status', '!=', FarmerStatus::Inactive)->first();
            if ($farmer) {
                return redirect()->route('farmer.show', $farmer);
            }

            // Link đã được cấp mới hoặc nông hộ ngưng hợp tác: quên link cũ.
            Cookie::queue(Cookie::forget(self::FARMER_COOKIE));
        }

        return redirect()->route('home');
    }
}
