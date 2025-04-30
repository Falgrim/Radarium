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
            'password2' => $robokassaConf['pass2'],
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
                $status = $robokassa->opState($payment->id);
                Log::channel('payments')->info('Платеж ID ' . $payment->id.': '.(is_array($status) ? json_encode($status) : $status));

                if (!is_array($status)) {
                    throw new \Exception('Не получен ответ в формате массива');
                }

                if (!isset($status['Result'])) {
                    throw new \Exception('В теле ответа нет параметра Result');
                }

                if (!isset($status['Result']['Code'])) {
                    throw new \Exception('В теле ответа нет параметра Result.Code');
                }

                // Все что больше 0 - ошибка
                if ($status['Result']['Code']) {
                    $payment->status = PaymentStatusEnum::Error;
                    $payment->description = $status['Result']['Description'] ?? 'Неизвестная ошибка';
                    $payment->save();

                    $this->error(
                        'Ошибка проверки платежа ID' . $payment->id . ': ' . $status['Result']['Description'] ?? 'Неизвестная ошибка'
                    );
                    continue;
                } elseif (isset($status['State']) AND $status['State']['Code'] == 100) {
                    $payment->status = PaymentStatusEnum::Success;
                    $payment->save();

                    $this->info('Платеж подтвержден ID' . $payment->id);
                    continue;
                }
            } catch (\Exception $e) {
                $payment->status = PaymentStatusEnum::Error;
                $payment->description = mb_substr($e->getMessage(), 0, 255);
                $payment->save();

                Log::channel('payments')->error('Платеж ID ' . $payment->id.': '.$e->getMessage());

                $this->error('Ошибка проверки платежа ID'.$payment->id.': '.$e->getMessage());
                continue;
            }

            if (strtotime($payment->created_at) <= time()-Payment::PAYMENT_TTL) {
                $payment->status = PaymentStatusEnum::TTL;
                $payment->save();

                $this->warn('Платеж просрочен: ID '.$payment->id);
                Log::channel('payments')->info('Платеж ID ' . $payment->id.': просрочен');
            }
        }
    }
}
