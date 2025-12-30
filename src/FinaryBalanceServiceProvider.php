<?php

namespace Patrimeo\AddonFinaryBalance;

use Patrimeo\Contracts\AddonRegistrar;
use Illuminate\Support\ServiceProvider;
use Patrimeo\AddonFinaryBalance\FinaryBalanceDescriptor;

final class FinaryBalanceServiceProvider extends ServiceProvider
{

    public function boot(AddonRegistrar $addonRegistrar)
    {
        $addonRegistrar->register(new FinaryBalanceDescriptor());
    }
}
