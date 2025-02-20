<?php

namespace App\Services\Company\Adminland\Expense;

use Carbon\Carbon;
use ErrorException;
use Illuminate\Support\Str;
use App\Services\BaseService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Client\HttpClientException;
use App\Exceptions\WrongCurrencyLayerApiKeyException;

class ConvertAmountFromOneCurrencyToCompanyCurrency extends BaseService
{
    private int $amount;
    private string $amountCurrency;
    private string $companyCurrency;
    private float $convertedAmount;
    private Carbon $amountDate;
    private float $rate;
    private string $query;

    /**
     * Converts an amount from one currency to another, at the rate
     * that was active on the given date.
     * When converting, we will keep track of the exchange rate on that day.
     * The exchange rate for this day is cached for 1 hour.
     * This allows us to reduce the number of queries if multiple conversions
     * have to be made in a short period of time.
     */
    public function execute(int $amount, string $amountCurrency, string $companyCurrency, Carbon $amountDate): ?array
    {
        $this->amount = $amount;
        $this->amountCurrency = $amountCurrency;
        $this->companyCurrency = $companyCurrency;
        $this->amountDate = $amountDate;

        if ($this->companyCurrency == $this->amountCurrency) {
            return null;
        }

        $this->buildQuery();
        $this->getConversionRate();
        $this->convert();

        return [
            'exchange_rate' => $this->rate,
            'converted_amount' => $this->convertedAmount,
            'converted_to_currency' => $this->companyCurrency,
            'converted_at' => Carbon::now(),
        ];
    }

    private function buildQuery(): void
    {
        if (is_null(config('officelife.currency_layer_api_key'))) {
            throw new WrongCurrencyLayerApiKeyException();
        }

        if (config('officelife.currency_layer_plan') === 'free') {
            $uri = config('officelife.currency_layer_url_free_plan');
        } else {
            $uri = config('officelife.currency_layer_url_paid_plan');
        }

        $query = http_build_query([
            'access_key' => config('officelife.currency_layer_api_key'),
            'source' => $this->companyCurrency,
            'currencies' => $this->amountCurrency,
            'date' => $this->amountDate->format('Y-m-d'),
        ]);

        $this->query = Str::finish($uri, '?') . $query;
    }

    private function getConversionRate(): void
    {
        if (Cache::has($this->cachedKey())) {
            $this->rate = Cache::get($this->cachedKey());
            return;
        }

        try {
            $response = Http::get($this->query);

            $currencies = $this->companyCurrency.$this->amountCurrency;
            $this->rate = $response->json("quotes.{$currencies}");
        } catch (HttpClientException $e) {
            Log::error('Error calling currencylayer: '.$e);
        } catch (ErrorException $e) {
            Log::error('Error getting exchange rate: '.$e);
        }

        Cache::put($this->cachedKey(), $this->rate, Carbon::now()->addMinutes(60));
    }

    private function convert(): void
    {
        if ($this->rate !== 0.0) {
            $this->convertedAmount = $this->amount / $this->rate;
        }
    }

    /**
     * Returns the name of the key that can be in the cache for the given
     * exchange rate.
     * Format is `exchange-rate-eur-usd-1990-01-01`.
     *
     * @return string
     */
    private function cachedKey(): string
    {
        return Str::lower('exchange-rate-'.$this->companyCurrency.'-'.$this->amountCurrency.'-'.$this->amountDate->format('Y-m-d'));
    }
}
