<?php

namespace Patrimeo\AddonFinaryBalance;

use Composer\InstalledVersions;
use Patrimeo\Contracts\AddonDescriptor;
use Patrimeo\Contracts\Enums\Capability;
use Patrimeo\AddonFinaryBalance\FinaryBalanceService;

final class FinaryBalanceDescriptor implements AddonDescriptor
{
    public function getKey(): string
    {
        return 'finary-balance';
    }

    public function getCapability(): Capability
    {
        return Capability::ASSET_BALANCE;
    }

    public function getLabel(): string
    {
        return 'Finary Balance';
    }

    public function getServiceClass(): string
    {
        return FinaryBalanceService::class;
    }

    public function getVersion(): string
    {
        return InstalledVersions::getPrettyVersion(
            'patrimeo/addon-finary-balance'
        ) ?? 'dev';
    }

    public function getDefaultSettings(): array
    {
        return ['finarySharingLink' => null, 'finarySecureCode' => null];
    }
}
