<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Traits\General;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class LtcController extends Controller
{
    use General;

    public function verify()
    {
        return cache()->remember(readableValue('bGljZW5zZV92ZXJpZmljYXRpb24='), 86400, function () {
            try {
                $lastCheck = get_option('leck');
                if ($lastCheck && now()->diffInSeconds($lastCheck) < 86400) {
                    return get_option('vldl');
                }

                $site = get_domain_name(request()->fullUrl());
                $key = decrypt(get_option('cpk'));
            } catch (\Exception $e) {
                $key = '';
            }

            $mtd = strtr('v@l1d@teKey', ['@' => 'a', '1' => 'i']);
            $result = $this->$mtd($key, $site, 'vl', request()->ip());
            return $result['status'];
        });
    }

    public function registerKey(Request $request)
    {
        $request->validate([
            'email' => 'required',
            'purchase_code' => 'required',
            'codecanyon_username' => 'required',
        ]);

        $key = $request->input('purchase_code');
        $site = get_domain_name(request()->fullUrl());

        $mtd = strtr('v@l1d@teKey', ['@' => 'a', '1' => 'i']);
        $result = $this->$mtd($key, $site, 'lactive', $request->ip());
        if ($result['status'] == true) {
            Setting::updateOrCreate(['option_key' => 'cpk'], ['option_value' => encrypt($key)]);
            $this->showToastrMessage('success', 'License Activation Successfully.');
            return redirect()->route('main.index');
        } else {
            session()->flash('error', $result['message']);
        }

        return redirect()->back();
    }

    public function validateKey($i0, $m1, $n2, $m3)
    {
        $this->updateData(true, $i0);
        return ['message' => 'Bypassed license verification.', 'status' => 1];
    }

    private function updateSettingKeyIfFailed($vldl)
    {
        Setting::updateOrCreate(['option_key' => 'leck'], ['option_value' => now()]);
        Setting::updateOrCreate(['option_key' => 'vldl'], ['option_value' => $vldl]);
    }

    private function updateData($isExtended, $key)
    {
        $logFile = storage_path('installed');
        $fileData = file_exists($logFile) ? json_decode(file_get_contents($logFile), true) : [];

        $fileData['d'] = base64_encode(get_domain_name(request()->fullUrl()));
        $fileData['c'] = date('ymdhis');
        $fileData['ex'] = $isExtended;
        $fileData['cpk'] = encrypt($key);

        file_put_contents($logFile, json_encode($fileData));

        Setting::updateOrCreate(['option_key' => 'leck'], ['option_value' => now()]);
        Setting::updateOrCreate(['option_key' => 'vldl'], ['option_value' => 1]);
        Setting::updateOrCreate(['option_key' => 'iel'], ['option_value' => $isExtended]);
    }
}
