<?php

namespace Antareja\Console;

use Antareja\Support\SetupToken;
use Illuminate\Console\Command;

class SetupTokenCommand extends Command
{
    protected $signature = 'antareja:setup-token';

    protected $description = 'Tampilkan kode untuk halaman Setup Awal (/setup) selama admin pertama belum dibuat';

    public function handle(): int
    {
        if (! SetupToken::needed()) {
            SetupToken::clear();
            $this->info('Setup awal sudah selesai (sudah ada user). Tambah admin lain: ./antareja.sh admin');

            return 0;
        }

        $this->line(SetupToken::current());

        return 0;
    }
}
