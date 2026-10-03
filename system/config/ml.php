<?php
return [
    'csv_path_output' => DOCROOT. 'temp/ml/' . CF::appCode() . '/csv/output/',
    'csv_path_input' => DOCROOT. 'temp/ml/' . CF::appCode() . '/csv/input/',
    'ai_model_path_output' => DOCROOT. 'temp/ml/' . CF::appCode() . '/model/',
    'RubixMainClass' => RubixService::class,
    // memory_limit selama training (CML_Adapter_RubixAdapter) - '-1' tanpa batas; pasang angka
    // di server sempit supaya PHP yang berhenti, bukan OOM killer yang memilih proses lain.
    'train_memory_limit' => '-1',
];
