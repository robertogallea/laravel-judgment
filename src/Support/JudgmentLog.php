<?php

namespace RobertoGallea\Judgment\Support;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Log\LogManager;
use Psr\Log\LoggerInterface;
use RobertoGallea\Judgment\Assessment;
use RobertoGallea\Judgment\Contracts\Decision;
use RobertoGallea\Judgment\Contracts\Outcome;
use RobertoGallea\Judgment\Exceptions\EngineFailed;
use RobertoGallea\Judgment\Judgment;
use RobertoGallea\Judgment\Provenance;

/**
 * Writes the package's log entries, each carrying the Judgment, model and
 * engine request id, to the channel named by judgment.log_channel, unless
 * judgment.log is off.
 *
 * @internal
 */
final class JudgmentLog
{
    public function __construct(
        private readonly LogManager $log,
        private readonly Config $config,
    ) {}

    public function assessed(Assessment $assessment): void
    {
        $this->channel()?->info('Judgment assessed.', $assessment->logContext());
    }

    /** @param  Provenance|null  $provenance  known when the Engine responded but the response was unusable */
    public function unassessed(Judgment $judgment, EngineFailed $exception, ?Provenance $provenance): void
    {
        $this->channel()?->warning('Judgment unassessed.', [
            'judgment' => $judgment::class,
            ...$provenance?->logContext() ?? [],
            'exception' => $exception,
        ]);
    }

    public function decided(Assessment $assessment, Decision $decision, Outcome $outcome): void
    {
        $this->channel()?->info('Judgment decided.', [
            ...$assessment->logContext(),
            'decision' => $decision::class,
            'outcome' => $outcome->value,
        ]);
    }

    /** Warned whatever judgment.log says: it is about configuration, not one Judgment. */
    public function unpinnedModel(string $connection, string $model): void
    {
        $this->logger()->warning('Judgment Engine model is an alias, not an exact version.', ['connection' => $connection, 'model' => $model]);
    }

    /** Null when judgment.log is off. */
    private function channel(): ?LoggerInterface
    {
        if (! $this->config->get('judgment.log')) {
            return null;
        }

        return $this->logger();
    }

    private function logger(): LoggerInterface
    {
        $channel = $this->config->get('judgment.log_channel');

        return is_string($channel) ? $this->log->channel($channel) : $this->log;
    }
}
