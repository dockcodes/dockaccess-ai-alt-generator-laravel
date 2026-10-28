<?php

declare(strict_types=1);

namespace Dock\AltGenerator\Laravel\Console;

use Dock\AltGenerator\Connection\Verification;
use Dock\AltGenerator\Exception\AltGeneratorException;
use Dock\AltGenerator\Exception\ConnectionException;
use Dock\AltGenerator\Laravel\AltText;
use Dock\AltGenerator\Laravel\Console\Concerns\ExplainsConnectionErrors;
use Dock\AltGenerator\Result\Usage;
use Illuminate\Console\Command;

/**
 * Whether the connection is on, and how much of the plan is left.
 */
final class StatusCommand extends Command
{
    use ExplainsConnectionErrors;

    protected $signature = 'dockaccess-alt:status';

    protected $description = 'Show the connection to dockaccess.org and the alt generator usage';

    public function handle(AltText $altText): int
    {
        $guard = $altText->guard();

        if (! $guard->isConfigured() || blank(config('dockaccess-alt.account_key'))) {
            $this->components->error('This application is not connected. Run "php artisan dockaccess-alt:connect".');

            return self::FAILURE;
        }

        try {
            $verification = $guard->verify(true);
        } catch (ConnectionException $e) {
            $this->components->error($this->explain($e));

            return self::FAILURE;
        } catch (AltGeneratorException $e) {
            $this->components->error('dockaccess.org could not be asked: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->components->twoColumnDetail('Connection', match ($verification->state()) {
            Verification::ACTIVE => '<fg=green>active</>',
            Verification::PAUSED => '<fg=yellow>paused by the plan</>',
            default => '<fg=yellow>generator switched off for the site</>',
        });

        if ($verification->websitesLimit() !== null) {
            $this->components->twoColumnDetail('Sites with the generator', $verification->websitesUsed().' / '.$verification->websitesLimit());
        }

        try {
            $usage = $altText->usage();
        } catch (AltGeneratorException $e) {
            $usage = $verification->usage();

            if ($usage === null) {
                $this->components->warn('The usage could not be read: '.$e->getMessage());
            }
        }

        if ($usage !== null) {
            $this->components->twoColumnDetail(
                $usage->isMonthly() ? 'Alts this month' : 'Alts today',
                $usage->used().' / '.$usage->limit().' ('.$usage->remaining().' left)'
            );
            $this->components->twoColumnDetail('Limit renews', self::renewal($usage));
        }

        return $verification->isActive() ? self::SUCCESS : self::FAILURE;
    }

    private static function renewal(Usage $usage): string
    {
        $resetsAt = $usage->resetsAt();

        if ($resetsAt !== null) {
            return $resetsAt->format('Y-m-d H:i').' UTC';
        }

        return $usage->isMonthly() ? 'on the 1st of next month (UTC)' : 'at midnight (UTC)';
    }
}
