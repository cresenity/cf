<?php

class Controller_Demo_Email_Builder extends \Cresenity\Demo\Controller {
    public function index() {
        $app = c::app();
        $app->setTitle('Email Builder');

        $builder = CEmail::builder()->createRuntimeBuilder();

        $head = $builder->addHead();
        $head->addTitle('Selamat datang');
        $head->addPreview('Ringkasan singkat yang tampil di daftar inbox');
        $defaults = $head->addHeadAttributes();
        $defaults->addAll()->setFontFamily('Arial, Helvetica, sans-serif')->setColor('#333333');
        $defaults->addClass('lead')->setFontSize('18px')->setColor('#1568b0');

        $body = $builder->addBody()->setBackgroundColor('#f2f4f7')->setWidth('600px');
        $wrapper = $body->addWrapper()->setBackgroundColor('#ffffff')->setPadding('24px 0');
        $section = $wrapper->addSection();
        $column = $section->addColumn();
        $column->addText()->useClass('lead')->add('Halo, selamat datang!');
        $column->addText()->add('Email ini disusun dengan <b>CEmail::builder()</b> tanpa menulis tabel HTML sendiri.');
        $column->addSpacer()->setHeight('16px');
        $column->addButton()->setHref('https://cresenity.com')->setBackgroundColor('#1568b0')->add('Buka Dashboard');
        $column->addDivider()->setBorderWidth('1px')->setBorderColor('#dddddd');

        $columns = $wrapper->addSection();
        $columns->addColumn()->addText()->setAlign('center')->add('Kolom kiri');
        $columns->addColumn()->addText()->setAlign('center')->add('Kolom kanan');

        $html = $builder->render();

        $card = $app->addDiv()->addClass('border-1 p-3 mb-3');
        $card->addH5()->add('Hasil render (iframe)');
        $card->add('<iframe style="width:100%;height:560px;border:1px solid #ddd;" srcdoc="' . c::e($html) . '"></iframe>');

        return $app;
    }
}
