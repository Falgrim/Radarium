<?php

namespace App\Console\Commands;

use App\Enum\ApiAiSourceEnum;
use App\Enum\ApiChannelPostStatusEnum;
use App\Enum\ApiChannelSourceEnum;
use App\Enum\DictionaryEnum;
use App\Enum\ApiDataTypeEnum;
use App\Enum\ApiPostAiStatusEnum;
use App\Enum\PaymentStatusEnum;
use App\Infrastructures\Facades\Repositories;
use App\Models\ApiChannel;
use App\Models\ApiChannelPost;
use App\Models\ApiPostUser;
use App\Models\Builder;
use App\Models\BuilderSpeciality;
use App\Models\Payment;
use App\Models\Specialist;
use App\Models\SpecialistSpeciality;
use App\Services\ApiAIYandex;
use App\Services\Dictionary;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Robokassa\Robokassa;

class PaymentStatusCheck extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:payments:check';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Проверка статусов оплаты заказов';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $payments = Payment::where('status', PaymentStatusEnum::New)
            ->orderBy('created_at', 'asc')
            ->get();

        if (!count($payments)) {
            $this->warn('Нет новых платежей для проверки');
            return;
        }

        $robokassaConf = config('payment.robokassa');

        $conf = [
            'login' => $robokassaConf['login'],
            'password1' => $robokassaConf['pass1'],
            'password2' => $robokassaConf['pass1'],
            'hashType' => 'md5',
        ];

        if ($robokassaConf['is_test']) {
            $conf['is_test'] = $robokassaConf['is_test'];
            $conf['test_password1'] = $robokassaConf['pass1'];
            $conf['test_password2'] = $robokassaConf['pass2'];
        }

        $robokassa = new Robokassa($conf);

        foreach ($payments as $payment) {
            try {
                $status = $robokassa->opState($payment->id . '24234343fgdfggdf33');
                print_r($status);
            } catch (\Exception $e) {
                $payment->status = PaymentStatusEnum::Error;
                $payment->description = mb_substr($e->getMessage(), 0, 255);
                $payment->save();

                $this->error('Ошибка проверки платежа ID'.$payment->id.': '.$e->getMessage());
                continue;
            }

            if (is_array($status) AND isset($status['Result'])) {
                if ($status['Result']['Code'] AND $status['Result']['Code'] == 3) {
                    $payment->status = PaymentStatusEnum::Error;
                    $payment->description = $status['Result']['Description'] ?? 'Неизвестная ошибка';
                    $payment->save();

                    $this->error('Ошибка проверки платежа ID'.$payment->id.': '.$status['Result']['Description'] ?? 'Неизвестная ошибка');
                    continue;
                }
            }

            if (strtotime($payment->created_at) <= time()-Payment::PAYMENT_TTL) {
                $payment->status = PaymentStatusEnum::TTL;
                $payment->save();

                $this->warn('Платеж просрочен: ID '.$payment->id);
            }
        }
    }
}
