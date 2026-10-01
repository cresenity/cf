<?php

class Controller_Demo_Email_Template extends \Cresenity\Demo\Controller {
    public function index() {
        $app = c::app();
        $app->setTitle('Email Template');

        $template = CEmail::template(['app_name' => 'CF Demo'])
            ->title('Atur ulang sandi')
            ->preview('Klik tombol untuk melanjutkan');

        $column = $template->bodySection()->addColumn();
        $column->addText()->setFontSize('18px')->add('Atur ulang sandi Anda');
        $column->addText()->add('Kami menerima permintaan atur ulang sandi untuk akun Anda. Abaikan email ini bila bukan Anda.');
        $column->addButton()->setHref('https://cresenity.com')->setBackgroundColor('#1a347b')->add('Atur Ulang Sandi');

        $card = $app->addDiv()->addClass('border-1 p-3 mb-3');
        $card->addH5()->add('Bawaan dari config email.template');
        $card->add('<iframe style="width:100%;height:560px;border:1px solid #ddd;" srcdoc="' . c::e($template->render()) . '"></iframe>');

        $branded = CEmail::template([
            'app_name' => 'Toko Contoh',
            'primary_color' => '#0b7a4b',
            'background_color' => '#eef5f1',
            'width' => '560px',
            'footer_text' => '&copy; {year} {app_name} &middot; Jakarta',
        ]);
        $branded->bodySection()->addColumn()->addText()->add('Contoh dengan warna, lebar, dan footer berbeda lewat opsi.');

        $card = $app->addDiv()->addClass('border-1 p-3 mb-3');
        $card->addH5()->add('Dengan opsi per pemakaian');
        $card->add('<iframe style="width:100%;height:420px;border:1px solid #ddd;" srcdoc="' . c::e($branded->render()) . '"></iframe>');

        return $app;
    }
}
