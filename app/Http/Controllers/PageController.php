<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

class PageController extends Controller
{
    public function about(): View { return $this->show('tentang'); }
    public function contact(): View { return $this->show('kontak'); }
    public function privacy(): View { return $this->show('privasi'); }
    public function terms(): View { return $this->show('syarat-layanan'); }
    public function classes(): View { return $this->show('kelas'); }
    public function packages(): View { return $this->show('paket'); }

    private function show(string $page): View
    {
        abort_unless(in_array($page, ['tentang', 'kontak', 'privasi', 'syarat-layanan', 'kelas', 'paket'], true), 404);
        return view('pages.static', compact('page'));
    }
}
