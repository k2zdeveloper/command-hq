<?php

namespace App\Controllers;

use App\Models\MessageModel;

class Home extends BaseController
{
    public function index()
    {
        if (! session('logged_in')) {
            return redirect()->to('/login');
        }

        // Ensure the messages table exists on first visit
        (new MessageModel())->ensureTable();

        $companies = config('Companies')->all();

        return view('chat', [
            'companies'    => $companies,
            'bossName'     => session('user') ?? 'Boss',
            'paperclipUrl' => rtrim((string) env('PAPERCLIP_BASE_URL', ''), '/'),
        ]);
    }

    public function login()
    {
        if (session('logged_in')) {
            return redirect()->to('/');
        }
        return view('login');
    }

    public function doLogin()
    {
        $pin = (string) $this->request->getPost('pin');

        if ($pin === '' || $pin !== env('BOSS_PIN', '')) {
            return redirect()->to('/login')->with('error', 'Invalid PIN.');
        }

        session()->set([
            'logged_in' => true,
            'user'      => 'Boss',
        ]);

        return redirect()->to('/');
    }

    public function logout()
    {
        session()->destroy();
        return redirect()->to('/login');
    }
}
