<?php

/**
 * Periksa konfigurasi email jalur lama app saat ini tanpa mengubah apa pun.
 */
class CConsole_Command_Email_CheckCommand extends CConsole_Command_AppCommand {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'email:check {--json : Keluarkan hasil sebagai JSON}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Periksa konfigurasi email jalur lama (app.smtp_*, MAIL_MAILER) yang basi atau saling bertentangan';

    /**
     * @return int
     */
    public function handle() {
        $settings = [
            'smtp_host' => CF::config('app.email.host', CF::config('app.smtp_host')),
            'smtp_username' => CF::config('app.email.username', CF::config('app.smtp_username')),
            'smtp_password' => CF::config('app.email.password', CF::config('app.smtp_password')),
            'smtp_from' => CEmail_Config::resolveFrom([]),
            'mail_mailer' => c::env('MAIL_MAILER'),
            'domain' => CF::domain(),
            'sender_call_files' => $this->countSenderCallFiles(),
        ];

        $findings = (new CEmail_ConfigAuditor())->audit($settings);
        $hasWarning = count(array_filter($findings, function ($finding) {
            return $finding['level'] === CEmail_ConfigAuditor::LEVEL_WARNING;
        })) > 0;

        if ($this->option('json')) {
            $this->line(json_encode(['settings' => carr::except($settings, ['smtp_password']), 'findings' => $findings], JSON_PRETTY_PRINT));

            return $hasWarning ? 1 : 0;
        }

        $this->info('Email app: ' . CF::appCode() . ' | smtp_host: ' . ($settings['smtp_host'] ?: '-') . ' | MAIL_MAILER: ' . ($settings['mail_mailer'] ?: '-')
            . ' | legacy_sender_via_mailer: ' . (CEmail_Sender::viaMailer() ? 'true' : 'false'));

        if (count($findings) == 0) {
            $this->info('Tidak ada temuan.');

            return 0;
        }

        $this->table(['Level', 'Item', 'Keterangan'], array_map(function ($finding) {
            return [$finding['level'], $finding['key'], $finding['message']];
        }, $findings));

        return $hasWarning ? 1 : 0;
    }

    /**
     * Jumlah berkas PHP app yang memanggil CEmail::sender().
     *
     * @return int
     */
    protected function countSenderCallFiles() {
        $directory = c::appRoot('default');
        if (!CFile::isDirectory($directory)) {
            return 0;
        }

        $count = 0;
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            $path = $file->getPathname();
            if ($file->getExtension() !== 'php' || $file->getSize() > 1048576 || preg_match('#/(vendor|node_modules|js|media)/#', $path)) {
                continue;
            }
            if (strpos((string) file_get_contents($path), 'CEmail::sender(') !== false) {
                $count++;
            }
        }

        return $count;
    }
}
