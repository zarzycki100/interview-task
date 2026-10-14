<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPat\Selector\Selector;
use PHPat\Test\Builder\Rule;
use PHPat\Test\PHPat;

final class ModuleBoundariesTest
{
    public function test_the_domain_depends_only_on_itself(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('Modules\Invoices\Domain'))
            ->canOnly()
            ->dependOn()
            ->classes(
                Selector::inNamespace('Modules\Invoices\Domain'),
                Selector::inNamespace('Ramsey\Uuid'),
            )
            ->because('the domain is plain PHP with no framework or infrastructure (ADR-001)');
    }

    public function test_the_application_layer_depends_only_on_the_domain(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('Modules\Invoices\Application'))
            ->canOnly()
            ->dependOn()
            ->classes(
                Selector::inNamespace('Modules\Invoices\Application'),
                Selector::inNamespace('Modules\Invoices\Domain'),
                Selector::inNamespace('Psr\Log'),
            )
            ->because('use cases talk to the outside world only through ports; PSR-3 logging is a standard abstraction (ADR-001)');
    }

    public function test_only_invoice_infrastructure_uses_the_notifications_api(): Rule
    {
        return PHPat::rule()
            ->classes(
                Selector::inNamespace('Modules\Invoices\Domain'),
                Selector::inNamespace('Modules\Invoices\Application'),
                Selector::inNamespace('Modules\Invoices\Presentation'),
            )
            ->shouldNotDependOn()
            ->classes(Selector::inNamespace('Modules\Notifications'))
            ->because('the notifications adapter in Infrastructure is the anti-corruption layer (ADR-001)');
    }

    public function test_invoices_use_only_the_public_api_of_notifications(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('Modules\Invoices'))
            ->shouldNotDependOn()
            ->classes(
                Selector::inNamespace('Modules\Notifications\Application'),
                Selector::inNamespace('Modules\Notifications\Infrastructure'),
                Selector::inNamespace('Modules\Notifications\Presentation'),
            )
            ->because('modules integrate only through their Api namespace (ADR-001)');
    }
}
