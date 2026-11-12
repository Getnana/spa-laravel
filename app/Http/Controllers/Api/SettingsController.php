<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SettingsModel;

class SettingsController extends Controller
{
    protected $settingsModel;

    public function __construct()
    {
        $this->settingsModel = new SettingsModel();
    }

    public function index()
    {
        $settings = $this->settingsModel->findAll();
        return response()->json([
            'status' => true,
            'data'   => $settings,
        ]);
    }

    public function save()
    {
        $input = request()->input();
        $allowedKeys = ['recaptcha_enabled', 'recaptcha_site_key', 'recaptcha_secret_key', 'tax_enabled', 'tax_rate', 'tax_label', 'default_commission_rate'];

        $validationRules = [
            'recaptcha_enabled'       => 'in_list[0,1]',
            'recaptcha_site_key'      => 'permit_empty|max_length[500]',
            'recaptcha_secret_key'    => 'permit_empty|max_length[500]',
            'tax_enabled'             => 'in_list[0,1]',
            'tax_rate'                => 'permit_empty|decimal|greater_than_equal_to[0]|less_than_equal_to[100]',
            'tax_label'               => 'permit_empty|max_length[100]',
            'default_commission_rate' => 'permit_empty|decimal|greater_than_equal_to[0]|less_than_equal_to[100]',
        ];

        foreach ($input as $key => $value) {
            if (!in_array($key, $allowedKeys)) continue;

            if (isset($validationRules[$key])) {
                $validation = \App\Support\SpaValidation::make([$key => $value], [$key => $validationRules[$key]]);
                if ($validation->fails()) {
                    return response()->json([
                        'status'  => false,
                        'message' => "Nilai tidak valid untuk {$key}.",
                        'errors'  => $validation->errors()->toArray(),
                    ])->setStatusCode(422);
                }
            }

            $this->settingsModel->setValue($key, (string) $value);
        }

        return response()->json([
            'status'  => true,
            'message' => 'Pengaturan berhasil disimpan.',
        ]);
    }

    public function recaptchaPublicKey()
    {
        $enabled  = $this->settingsModel->getValue('recaptcha_enabled', '0');
        $siteKey  = $this->settingsModel->getValue('recaptcha_site_key', '');

        $taxEnabled = $this->settingsModel->getValue('tax_enabled', '0');
        $taxRate    = (float) $this->settingsModel->getValue('tax_rate', '0');
        $taxLabel   = $this->settingsModel->getValue('tax_label', 'Pajak');

        return response()->json([
            'status' => true,
            'data'   => [
                'recaptcha' => [
                    'enabled'  => $enabled === '1',
                    'site_key' => $siteKey,
                ],
                'tax' => [
                    'enabled' => $taxEnabled === '1',
                    'rate'    => $taxRate,
                    'label'   => $taxLabel,
                ],
            ],
        ]);
    }

    public function verifyRecaptcha(string $token): bool
    {
        $enabled   = $this->settingsModel->getValue('recaptcha_enabled', '0');
        $secretKey = $this->settingsModel->getValue('recaptcha_secret_key', '');

        if ($enabled !== '1' || empty($secretKey)) {
            return true;
        }

        $client = \Illuminate\Support\Facades\Http::asForm()->timeout(8);
        $response = $client->post('https://www.google.com/recaptcha/api/siteverify', [
            'secret'   => $secretKey,
            'response' => $token,
        ]);

        $result = $response->json();
        return isset($result['success']) && $result['success'] === true;
    }
}
