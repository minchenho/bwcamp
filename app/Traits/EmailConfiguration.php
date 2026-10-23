<?php

namespace App\Traits;

trait EmailConfiguration
{
    public static function setEmail($campTable, $variantTable = null)
    {
        if ($variantTable) {
            $campTable = $variantTable;
        }

        // 記下原本的 smtp host，不走 SES 的營隊要改回來（否則會沿用前一個營隊的 SES host）
        if (!config()->has('mail.default_smtp_host')) {
            config(['mail.default_smtp_host' => config('mail.mailers.smtp.host')]);
        }

        $config = \Config::get('mail.ses_' . $campTable);    //get mail.ses_$campTable version
        if (!$config || !$config['username']) {
            $config = \Config::get('mail.' . $campTable);    //if null, get mail.$campTable version
            config(['mail.mailers.smtp.host' => config('mail.default_smtp_host')]);
        } else {
            config(['mail.mailers.smtp.host' => $config['host']]);  //mail.ses_$campTable uses AWS SES
        }
        if ($config && $config['username']) {     //override
            config([
                'mail.mailers.smtp.username' => $config['username'],
                'mail.mailers.smtp.password' => $config['password'],
                'mail.from.address' => $config['address'],
                'mail.from.name' => $config['name'],
            ]);
        } elseif (app()->isLocal()) {
            config([
                'mail.mailers.smtp.username' => env('MAIL_USERNAME'),
                'mail.mailers.smtp.password' => env('MAIL_PASSWORD'),
                'mail.mailers.smtp.host' => env('MAIL_HOST'),
                'mail.from.address' => env('MAIL_FROM_ADDRESS'),
                'mail.from.name' => env('MAIL_FROM_NAME'),
            ]);
        }
        // 已建立的 mailer 不會因 config 改變而更新（佇列 worker 會連續處理不同營隊的信），清掉讓它依新設定重建
        if (app()->resolved('mail.manager')) {
            app('mail.manager')->purge('smtp');
            app()->forgetInstance('mailer');
        }
        app()->register('Illuminate\Mail\MailServiceProvider');
    }
}
