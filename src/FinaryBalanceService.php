<?php

namespace Patrimeo\AddonFinaryBalance;


use Illuminate\Support\Facades\Http;
use Patrimeo\Contracts\AssetBalance;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Patrimeo\Contracts\Errors\AddonError;

class FinaryBalanceService implements AssetBalance
{

    protected ?string $objectId;
    protected ?string $assetType;
    protected ?string $finarySharingLink;
    protected ?string $finarySecureCode;
    protected ?string $parsedSharingLink;

    public function __construct(array $attributes)
    {
        $this->objectId = $attributes['object_id'] ?? null;
        $this->assetType = $attributes['asset_type'] ?? null;
        $this->finarySharingLink = $attributes['finarySharingLink'] ?? null;
        $this->finarySecureCode = $attributes['finarySecureCode'] ?? null;
        if (empty($this->objectId) || empty($this->assetType)) {
            throw new AddonError(__('Object ID and asset type are required'), null);
        }
        if (empty($this->finarySharingLink) || empty($this->finarySecureCode)) {
            throw new AddonError(__('Finary sharing link and secure code are required'), null);
        }
        // Check if sharing_link is a URL and extract the ID
        if (filter_var($this->finarySharingLink, FILTER_VALIDATE_URL)) {
            $path = parse_url($this->finarySharingLink, PHP_URL_PATH);
            if ($path && preg_match('/\/v2\/share\/([a-zA-Z0-9]+)$/', $path, $matches)) {
                $this->parsedSharingLink = $matches[1];
            }
        }
    }

    public static function getFields(): array
    {
        return [
            'object_id' => TextInput::make('object_id')
                ->label(__('Object ID'))
                ->helperText(__('The Finary object ID to retrieve the quantity from'))
                ->required(),

            'asset_type' => Select::make('asset_type')
                ->label(__('Finary asset type'))
                ->options([
                    'checking_accounts' => __('Checking Accounts'),
                    'investments' => __('Investments'),
                    'fonds_euro' => __('Fonds Euro'),
                    'commodities' => __('Commodities'),
                    'real_estates' => __('Real Estates'),
                    'cryptos' => __('Cryptocurrencies'),
                ])
                ->helperText(__('The type of asset to sync from Finary'))
                ->required(),
        ];
    }

    public static function getSettingFields(): ?Section
    {
        return Section::make(__('Finary Balance Integration'))
            ->schema([
                TextInput::make('finarySharingLink')
                    ->label(__('Finary Sharing Link'))
                    ->helperText('Your Finary sharing link for portfolio import'),
                TextInput::make('finarySecureCode')
                    ->label(__('Finary Secure Code'))
                    ->helperText('Your Finary secure code for authentication'),
            ]);
    }



    public function getBalance(): float
    {
        $url = $this->getFinaryUrl();
        if (!$url) {
            throw new AddonError(__('Invalid asset type'), null);
        }
        $response = Http::get($url . '&sharing_link_id=' . $this->parsedSharingLink . '&access_code=' . $this->finarySecureCode);
        if ($response->status() !== 200) {
            throw new AddonError(__('Failed to retrieve data from Finary API'), $response->status(), $response->json());
        }
        $data = $response->json();
        if (!isset($data['result'])) {
            throw new AddonError(__('Invalid response format from Finary API'), null, $data);
        }
        $result = $data['result'];

        if ($this->assetType === 'checking_accounts') {
            // Special case: checking_accounts has a different structure
            foreach ($result as $account) {
                if ($account['id'] === $this->objectId) {
                    return (float) $account['balance'];
                }
            }
        } else {
            // Other asset types have accounts structure
            foreach ($result['accounts'] as $account) {
                $quantity = $this->findQuantityInAccount($account);
                if ($quantity !== null) {
                    return $quantity;
                }
            }
        }

        throw new AddonError(__('Could not retrieve quantity from Finary for the specified object'), null, $data);
    }

    protected function findQuantityInAccount(array $account): ?float
    {
        // Check fiats (cash)
        foreach ($account['fiats'] as $fiat) {
            if ($fiat['id'] === $this->objectId) {
                return (float) $fiat['current_value'];
            }
        }

        // Check securities
        foreach ($account['securities'] as $security) {
            if ($security['id'] === $this->objectId) {
                return (float) $security['quantity'];
            }
        }

        // Check cryptos
        foreach ($account['cryptos'] as $crypto) {
            if ($crypto['id'] === $this->objectId) {
                return (float) $crypto['quantity'];
            }
        }

        // Check fonds euro
        foreach ($account['fonds_euro'] as $fond) {
            if ($fond['id'] === $this->objectId) {
                return (float) $fond['current_value'];
            }
        }

        // Check precious metals
        foreach ($account['precious_metals'] as $metal) {
            if ($metal['id'] === $this->objectId) {
                return (float) $metal['quantity'];
            }
        }

        // Check SCPIs
        foreach ($account['scpis'] as $scpi) {
            if ($scpi['id'] === $this->objectId) {
                return (float) $scpi['shares'];
            }
        }

        return null;
    }

    protected function getFinaryUrl(): ?string
    {
        return match ($this->assetType) {
            'checking_accounts' => 'https://api.finary.com/users/me/portfolio/checking_accounts/accounts?period=all',
            'investments' => 'https://api.finary.com/users/me/portfolio/investments?period=all',
            'fonds_euro' => 'https://api.finary.com/users/me/portfolio/fonds_euro?period=all',
            'commodities' => 'https://api.finary.com/users/me/portfolio/commodities?period=all',
            'real_estates' => 'https://api.finary.com/users/me/portfolio/real_estates?period=all',
            'cryptos' => 'https://api.finary.com/users/me/portfolio/cryptos?period=all',
            default => null,
        };
    }
}
