<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:test-security-call')]
#[Description('Command description')]
class TestSecurityCall extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        \App\Jobs\MakeSecurityCheckCallJob::dispatch();
    }
}
